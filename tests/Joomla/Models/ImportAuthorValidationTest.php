<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real import model and backend helper, with isolated identities and ACL grants. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ImportAuthorValidationTest extends TestCase
{
    private JemModelImport $model;
    private array $users = [];

    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to read Joomla libraries. No site or database changes.');
        }

        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        define('JPATH_ADMINISTRATOR', JPATH_SITE . '/administrator');
        define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        define('JPATH_ROOT', $root);
        define('JPATH_BASE', $root);
        require_once JEM_TEST_ROOT . '/admin/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/admin/models/import.php';

        $this->users[10] = $this->user(10, ['core.admin']);
        $this->users[20] = $this->user(20, []);
        $this->users[21] = $this->user(21, ['jem.tools.manage']);
        $this->users[22] = $this->user(22, ['core.admin']);
        $this->users[23] = $this->user(23, ['core.manage']);
        $this->users[24] = $this->user(24, ['jem.tools.manage'], true);
        $this->users[25] = $this->user(25, ['core.create']);

        $factory = $this->createMock(UserFactoryInterface::class);
        $factory->method('loadUserById')->willReturnCallback(function (int $id): User {
            if ($id === 98) {
                throw new RuntimeException('User lookup failed');
            }
            return $this->users[$id] ?? new User();
        });
        $container = new Joomla\DI\Container();
        $container->set(UserFactoryInterface::class, $factory);
        // Date::toSql() only needs the driver's date format; no connection/query.
        $container->set(Joomla\Database\DatabaseInterface::class, new Joomla\Database\Mysqli\MysqliDriver([]));
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        (new ReflectionProperty(User::class, 'instances'))->setValue(null, []);
        (new ReflectionProperty(JemUserAbstract::class, 'instances_jemuser'))->setValue(null, $this->users);

        Factory::$application = new class ($this->users[10]) {
            public function __construct(public User $identity)
            {
            }

            public function getIdentity(): User
            {
                return $this->identity;
            }

            public function getSession(): object
            {
                return new class ($this->identity) {
                    public function __construct(private User $identity)
                    {
                    }

                    public function get($key)
                    {
                        return $key === 'user' ? $this->identity : null;
                    }
                };
            }
        };
        Factory::$language = (new ReflectionClass(Joomla\CMS\Language\Language::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Factory::$language, 'strings'))->setValue(Factory::$language, []);
        (new ReflectionProperty(Factory::$language, 'metadata'))->setValue(Factory::$language, ['tag' => 'en-GB']);
        $this->model = (new ReflectionClass(JemModelImport::class))->newInstanceWithoutConstructor();
    }

    private function user(int $id, array $grants, bool $guest = false): User
    {
        $user = $this->getMockBuilder(JemUser::class)->onlyMethods(['authorise'])->getMock();
        $user->id = $id;
        $user->guest = (int) $guest;
        $user->method('authorise')->willReturnCallback(function ($action, $asset) use ($grants): bool {
            self::assertSame('com_jem', $asset);
            return in_array($action, $grants, true);
        });
        return $user;
    }

    public function testCreatorPermissionDoesNotRequireImportPermission(): void
    {
        $this->model->setImportAuthor('types', 25);
        self::assertSame(25, (new ReflectionProperty($this->model, 'importAuthor'))->getValue($this->model));
    }

    public static function rejectedAuthors(): iterable
    {
        foreach ([0, -1, 20, 21, 23, 24, 98, 99] as $id) {
            yield 'user ' . $id => [$id];
        }
    }

    #[DataProvider('rejectedAuthors')]
    public function testInvalidOrUnauthorisedSelectionIsRejected(int $id): void
    {
        $this->expectException(RuntimeException::class);
        $this->model->setImportAuthor('types', $id);
    }

    public function testSourceCreatorIsNeverUsedEvenIfItIsAnotherAdministrator(): void
    {
        $this->model->setImportAuthor('types', 25);
        $table = new class {
            public function getProperties(): array { return []; }
        };
        $values = (new ReflectionMethod($this->model, 'applyImportAuthor'))->invoke(
            $this->model, ['created_by' => 22, 'modified_by' => 22], 'types', $table, false
        );
        self::assertSame(25, $values['created_by']);
        self::assertSame(0, $values['modified_by']);
        self::assertNull($values['modified']);
    }

    public function testUiErrorLimitDoesNotDropRowsFromTheJournal(): void
    {
        $result = ['error_details' => []];
        $method = new ReflectionMethod($this->model, 'appendImportErrorDetail');
        for ($line = 2; $line <= 9; $line++) {
            $this->model->getImportJournal()->begin('events', $line, 0);
            $method->invokeArgs($this->model, [&$result, $line, 'Bad row']);
        }
        self::assertCount(3, $result['error_details']);
        $entries = $this->model->getImportJournal()->drain();
        self::assertCount(8, $entries);
        self::assertSame(9, $entries[7]['line']);
    }
}
