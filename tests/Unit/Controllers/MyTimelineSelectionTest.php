<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\PersonalTimeline;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Isolated app/user state for the actual personal-model filtering methods. */
final class TimelineServices
{
    public static object $context;

    /** @return object Synthetic application context. */
    public static function getApplication(): object
    {
        return self::$context;
    }

    /** @return object Synthetic current user. */
    public static function getUser(): object
    {
        return self::$context;
    }
}

/** Confirm the existing personal model's selection contract without touching Joomla data. */
final class MyTimelineSelectionTest extends TestCase
{
    /** @return void Load production query filtering and guest guard, not copies of their logic. */
    public static function setUpBeforeClass(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/mytimeline.php');
        $methods = '';
        foreach (['_buildWhere', 'getItems'] as $name) {
            self::assertSame(1, preg_match(
                '/(?:public|protected) function ' . $name . '\([^\n]*\)\s*\{.*?^    \}/ms',
                $source,
                $match
            ));
            $methods .= $match[0] . "\n";
        }
        eval('namespace ' . __NAMESPACE__ . ';'
            . 'use ' . TimelineServices::class . ' as Factory;'
            . 'use ' . TimelineServices::class . ' as JemFactory;'
            . 'final class PersonalModel { protected $_items = null;' . $methods . '}');
    }

    /**
     * Set current user, registration inclusion and archive task; disable date-purpose filtering.
     *
     * @param int $user Current user ID.
     * @param bool $registered Include registered events.
     * @param string $task Normal or archive request.
     * @return void
     */
    private function configure(int $user, bool $registered, string $task): void
    {
        TimelineServices::$context = new class ($user, $registered, $task) {
            public object $input;

            /** @param int $user User ID. @param bool $registered Registration setting. @param string $task Request task. */
            public function __construct(private int $user, private bool $registered, string $task)
            {
                $this->input = new class ($task) {
                    /** @param string $task Request task. */
                    public function __construct(private string $task)
                    {
                    }

                    /** @param string $key Input name. @param string $default Fallback. @return string Requested task. */
                    public function getCmd(string $key, string $default): string
                    {
                        return $key === 'task' ? $this->task : $default;
                    }
                };
            }

            /** @return self Menu parameters exposed through get(). */
            public function getParams(): self
            {
                return $this;
            }

            /** @param string $key User/parameter key. @param mixed $default Fallback. @return mixed */
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'id' => $this->user,
                    'mytimeline_include_registered' => $this->registered,
                    'filtermytimeline' => 0,
                    default => $default,
                };
            }

            /** @return int[] Accessible view levels in this fixture. */
            public function getAuthorisedViewLevels(): array
            {
                return [1];
            }
        };
    }

    /** @return iterable<string, array{int, bool, string, array<int>}> Personal-selection cases. */
    public static function selections(): iterable
    {
        yield 'A owns events' => [1, false, '', [101, 104]];
        yield 'B owns events' => [2, false, '', [102]];
        yield 'A includes registrations' => [1, true, '', [101, 102, 104]];
        yield 'B includes registrations' => [2, true, '', [101, 102]];
        yield 'A own archive' => [1, false, 'archive', [105]];
        yield 'B own archive' => [2, false, 'archive', []];
        yield 'A archive with registrations' => [1, true, 'archive', [105]];
        yield 'B registered archive' => [2, true, 'archive', [105]];
    }

    /**
     * Run the production WHERE expression against synthetic SQL rows.
     *
     * @param int $user User ID.
     * @param bool $registered Include registrations.
     * @param string $task Normal or archive request.
     * @param int[] $expected Expected event IDs.
     * @return void
     */
    #[DataProvider('selections')]
    public function testPersonalSelection(int $user, bool $registered, string $task, array $expected): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite for isolated SQL fixtures.');
        }
        $this->configure($user, $registered, $task);
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE events (id INTEGER, created_by INTEGER, published INTEGER, access INTEGER, catid INTEGER)');
        $db->exec('INSERT INTO events VALUES (101,1,1,1,1),(102,2,1,1,1),(103,1,1,2,1),'
            . '(104,1,0,1,1),(105,1,2,1,1),(106,1,-2,1,1),(107,1,1,1,2),(108,1,1,1,3)');
        $db->exec('CREATE TABLE categories (id INTEGER, published INTEGER, access INTEGER)');
        $db->exec('INSERT INTO categories VALUES (1,1,1),(2,0,1),(3,1,2)');
        $db->exec('CREATE TABLE registrations (event INTEGER, uid INTEGER)');
        $db->exec('INSERT INTO registrations VALUES (102,1),(101,2),(105,2)');
        $where = (new ReflectionMethod(PersonalModel::class, '_buildWhere'))->invoke(new PersonalModel());
        $sql = 'SELECT a.id FROM events a JOIN categories c ON c.id = a.catid'
            . ' LEFT JOIN registrations r ON r.event = a.id AND r.uid = ' . $user
            . $where . ' ORDER BY a.id';
        $actual = array_map('intval', $db->query($sql)->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame($expected, $actual);
        self::assertSame([], array_intersect([103, 106, 107, 108], $actual), 'Access/category/trash exclusions stay intact.');
    }

    /** @return void Guests receive no personal items without reaching a database dependency. */
    public function testGuestCannotLoadPersonalItems(): void
    {
        $this->configure(0, true, '');
        $model = new PersonalModel();
        self::assertSame([], $model->getItems());
        self::assertSame([], $model->getItems());
    }
}
