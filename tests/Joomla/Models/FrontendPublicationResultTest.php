<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** #2391: exercise both controller/model paths without changing an installed site. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FrontendPublicationResultTest extends TestCase
{
    private PublicationDatabase $db;
    private PublicationApplication $app;

    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT and pdo_sqlite; no installed site is changed.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Joomla\\CMS\\';
            if (str_starts_with($class, $prefix)) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        foreach (['myevents', 'myvenues'] as $view) {
            require_once JEM_TEST_ROOT . '/site/models/' . $view . '.php';
            require_once JEM_TEST_ROOT . '/site/controllers/' . $view . '.php';
        }
        require_once JEM_TEST_ROOT . '/tests/Support/frontend_publication_environment.php';
        $this->app = new PublicationApplication();
        Factory::$application = $this->app;
        Factory::$language = new class {
            private array $strings;

            public function __construct()
            {
                $this->strings = parse_ini_file(JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini');
            }

            public function _($key, ...$options)
            {
                return $this->strings[$key] ?? $key;
            }
        };
        $this->db = new PublicationDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
    }

    private function prepare(string $entity, string $task): object
    {
        $target = ['publish' => 1, 'unpublish' => 0, 'trash' => -2][$task];
        $this->db->setQuery('CREATE TABLE t_jem_' . $entity
            . ' (id INTEGER PRIMARY KEY, created_by INTEGER, published INTEGER, checked_out INTEGER)')->execute();
        foreach ([1 => 0, 2 => 0, 3 => 99, 4 => 0, 5 => 7, 6 => null, 7 => 0] as $id => $checkout) {
            $row = (object) ['id' => $id, 'created_by' => $id === 7 ? 99 : 7,
                'published' => $id === 4 ? $target : ($target === 1 ? 0 : 1), 'checked_out' => $checkout];
            $this->db->insertObject('#__jem_' . $entity, $row);
        }
        // Bypass unrelated list pagination setup; retain the real model state and DB methods.
        $model = (new ReflectionClass('JemModelMy' . $entity))->newInstanceWithoutConstructor();
        $model->setDatabase($this->db);
        return $model;
    }

    private function runAction(string $entity, string $task, object $model, array $ids, bool $token = true): array
    {
        $this->app->request($ids, $token);
        $controller = $entity === 'events' ? new PublicationEventsController($model) : new PublicationVenuesController($model);
        $controller->$task();
        return $controller->result();
    }

    public static function actions(): iterable
    {
        yield 'event publish' => ['events', 'publish'];
        yield 'event unpublish' => ['events', 'unpublish'];
        yield 'event trash' => ['events', 'trash'];
        yield 'venue publish' => ['venues', 'publish'];
        yield 'venue unpublish' => ['venues', 'unpublish'];
    }

    public static function selections(): iterable
    {
        foreach (self::actions() as $name => [$entity, $task]) {
            foreach ([
                'single' => [[1], 1, 0], 'mixed permissions' => [[1, 2], 1, 1],
                'checkout' => [[3], 0, 1], 'unchanged' => [[4], 0, 1],
                'combined' => [[1, 2, 3, 4, 5, 6, 7, 999], 3, 5],
                'duplicates' => [[1, 1], 1, 0], 'missing record' => [[1, 999], 1, 1],
            ] as $scenario => [$ids, $updated, $skipped]) {
                yield "$name $scenario" => [$entity, $task, $ids, $updated, $skipped];
            }
        }
    }

    #[DataProvider('selections')]
    public function testCountsMatchStoredChangesAndRedirects(string $entity, string $task, array $ids, int $updated, int $skipped): void
    {
        $model = $this->prepare($entity, $task);
        $before = $this->states($entity);
        [$message, $type, $url] = $this->runAction($entity, $task, $model, $ids);
        self::assertCount($updated, array_diff_assoc($this->states($entity), $before));
        if ($updated) {
            self::assertStringStartsWith($updated . ' ', $message);
        } else {
            self::assertStringContainsString('No items changed.', $message);
            self::assertStringNotContainsString('successfully', $message);
        }
        self::assertSame($updated, $model->getState('publish.updated'));
        self::assertSame($skipped, $model->getState('publish.skipped'));
        self::assertSame($skipped ? 'warning' : 'message', $type);
        self::assertSame('index.php?option=com_jem&view=my' . $entity, $url);
        if ($skipped) {
            self::assertStringContainsString('Not changed: ' . $skipped . '.', $message);
        }
    }

    #[DataProvider('actions')]
    public function testZeroAffectedRowsAreNotReportedAsSuccess(string $entity, string $task): void
    {
        $model = $this->prepare($entity, $task);
        $this->db->setQuery('CREATE TRIGGER skip_update BEFORE UPDATE ON t_jem_' . $entity
            . ' BEGIN SELECT RAISE(IGNORE); END;')->execute();
        [$message, $type] = $this->runAction($entity, $task, $model, [1]);
        self::assertSame(0, $model->getState('publish.updated'));
        self::assertSame(1, $model->getState('publish.skipped'));
        self::assertSame('warning', $type);
        self::assertStringContainsString('No items changed.', $message);
    }

    #[DataProvider('actions')]
    public function testSqlFailureNeverReportsSuccess(string $entity, string $task): void
    {
        $model = $this->prepare($entity, $task);
        $before = $this->states($entity);
        $this->db->setQuery('CREATE TRIGGER fail_update BEFORE UPDATE ON t_jem_' . $entity
            . " BEGIN SELECT RAISE(ABORT, 'synthetic SQL failure'); END;")->execute();
        [$message, $type] = $this->runAction($entity, $task, $model, [1]);
        self::assertSame($before, $this->states($entity));
        self::assertSame('error', $type);
        self::assertSame(0, $model->getState('publish.updated'));
        self::assertStringContainsString('could not be updated', $message);
        self::assertStringNotContainsString('synthetic SQL', $message);
    }

    #[DataProvider('actions')]
    public function testDeniedAndMissingSelectionsDoNotReusePreviousCounts(string $entity, string $task): void
    {
        $model = $this->prepare($entity, $task);
        $this->runAction($entity, $task, $model, [1]);
        foreach ([[2], [7], [999]] as $ids) {
            $before = $this->states($entity);
            [$message, $type] = $this->runAction($entity, $task, $model, $ids);
            self::assertSame($before, $this->states($entity));
            self::assertSame('error', $type);
            self::assertSame('JERROR_ALERTNOAUTHOR', $message);
            self::assertSame(0, $model->getState('publish.updated'));
        }
    }

    #[DataProvider('actions')]
    public function testInvalidTokenPreventsAnyChange(string $entity, string $task): void
    {
        $model = $this->prepare($entity, $task);
        $before = $this->states($entity);
        try {
            $this->runAction($entity, $task, $model, [1], false);
            self::fail('Invalid token accepted');
        } catch (RuntimeException $error) {
            self::assertSame('Invalid Token', $error->getMessage());
        }
        self::assertSame($before, $this->states($entity));
    }

    private function states(string $entity): array
    {
        $this->db->setQuery('SELECT id, published FROM t_jem_' . $entity . ' ORDER BY id');
        return $this->db->loadAssocList('id', 'published');
    }
}
