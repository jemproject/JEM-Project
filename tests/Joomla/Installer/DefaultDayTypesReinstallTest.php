<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\Mysqli\MysqliDriver;
use JemProject\Tests\TypeLanguage as TypeSupport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real installer SQL and repair over a private MariaDB schema, never installed JEM tables. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DefaultDayTypesReinstallTest extends TestCase
{
    private ?mysqli $server = null;
    private ?MysqliDriver $db = null;
    private string $database = '';

    /** @return void Load native Joomla services and create the two real tables in a disposable schema. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (getenv('JEM_TEST_DAY_TYPES_DATABASE') !== '1' || !is_file($root . '/configuration.php')) {
            self::markTestSkipped('Set JEM_TEST_DAY_TYPES_DATABASE=1 and JEM_TEST_JOOMLA_ROOT.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once $root . '/configuration.php';
        $config = new JConfig();
        $this->server = new mysqli($config->host, $config->user, $config->password);
        $this->database = 'jem_daytypes_test_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
        $this->db = new class ([
            'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
            'database' => $this->database, 'prefix' => 't_',
        ]) extends MysqliDriver {
            public bool $failDelete = false;

            /** @return bool Execute real SQL, optionally failing after the reference UPDATE. */
            public function execute()
            {
                if ($this->failDelete && preg_match('/^\s*DELETE\s+FROM\s+`#__jem_types`/i', (string) $this->getQuery())) {
                    throw new RuntimeException('Injected duplicate deletion failure');
                }
                return parent::execute();
            }
        };
        $container = new Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->set(Joomla\Database\DatabaseInterface::class, $this->db, true);
        $container->set(Joomla\CMS\HTML\Registry::class, new Joomla\CMS\HTML\Registry());
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$config = new Joomla\Registry\Registry(['access' => 1, 'unicodeslugs' => 1]);
        Factory::$language = new class {
            /** @param string $key Language key. @param mixed ...$args Flags. @return string English fixture text. */
            public function _($key, ...$args): string
            {
                $strings = parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.sys.ini', false, INI_SCANNER_RAW);
                return $strings[$key] ?? $key;
            }

            /** @return string Fixture language. */
            public function getTag(): string
            {
                return 'en-GB';
            }

            /** @param string $text ASCII fixture. @return string Input unchanged. */
            public function transliterate(string $text): string
            {
                return $text;
            }
        };
        Factory::$application = new class {
            public array $messages = [];

            /** @param string $message Message. @param string $type Severity. @return void */
            public function enqueueMessage($message, $type = 'message'): void
            {
                $this->messages[] = [$type, $message];
            }

            /** @return object Fixture language. */
            public function getLanguage(): object
            {
                return Factory::$language;
            }

            /** @return object Authenticated editor with public view access. */
            public function getIdentity(): object
            {
                return new TypeSupport\User();
            }

            /** @return Joomla\Event\Dispatcher No installed plugins are executed. */
            public function getDispatcher(): Joomla\Event\Dispatcher
            {
                return new Joomla\Event\Dispatcher();
            }

            /** @param string $key Setting. @param mixed $default Fallback. @return mixed Fixture setting. */
            public function get($key, $default = null)
            {
                return $key === 'access' ? 1 : $default;
            }
        };
        require_once JEM_TEST_ROOT . '/tests/Support/type_language_environment.php';
        require_once JEM_TEST_ROOT . '/script.php';
        foreach (['types', 'special_days'] as $name) {
            $source = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
            self::assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_' . $name . '`.*?;/s', $source, $match));
            $this->db->setQuery($match[0])->execute();
        }
    }

    /** @return void Drop only the uniquely named database created by this test. */
    protected function tearDown(): void
    {
        if ($this->server && preg_match('/^jem_daytypes_test_[a-f0-9]{16}$/D', $this->database)) {
            $this->db?->disconnect();
            $this->server->query('DROP DATABASE ' . $this->database);
            $this->server->close();
        }
    }

    /** @param string $file SQL source. @return void Run only its real default Day type insertions. */
    private function seed(string $file = 'admin/sql/install.mysql.utf8.sql'): void
    {
        $count = 0;
        foreach (DatabaseDriver::splitSql(file_get_contents(JEM_TEST_ROOT . '/' . $file)) as $sql) {
            if (preg_match('/INSERT(?: IGNORE)? INTO `#__jem_types`/', $sql)) {
                $this->db->setQuery($sql)->execute();
                $count++;
            }
        }
        self::assertGreaterThan(0, $count);
    }

    /** @return void Execute the actual postflight repair method against fixture tables. */
    private function repair(): void
    {
        (new ReflectionMethod(com_jemInstallerScript::class, 'repairDefaultDayTypes'))
            ->invoke(new com_jemInstallerScript());
    }

    /** @param int $source Original ID. @param array $changes Overrides. @return int New duplicate ID. */
    private function duplicate(int $source = 1, array $changes = []): int
    {
        $row = $this->db->setQuery('SELECT * FROM #__jem_types WHERE id = ' . $source)->loadAssoc();
        unset($row['id']);
        $row['created'] = '2026-11-14 00:00:00';
        $row = (object) array_replace($row, $changes);
        $this->db->insertObject('#__jem_types', $row, 'id');
        return (int) $row->id;
    }

    /** @param int $id Special Day ID. @param int $type Type reference. @return void Insert a valid day. */
    private function day(int $id, int $type): void
    {
        $row = (object) [
            'id' => $id, 'title' => 'Weekend rule ' . $id, 'alias' => 'weekend-' . $id,
            'day_type_id' => $type,
            'day_type' => $this->db->setQuery('SELECT name FROM #__jem_types WHERE id=' . $type)->loadResult(),
            'start_date' => '1900-01-01',
            'end_date' => '2100-12-31', 'weekdays' => '0,6',
        ];
        $this->db->insertObject('#__jem_special_days', $row);
    }

    /** @return array Full persisted data for comparison and rollback assertions. */
    private function snapshot(): array
    {
        return [
            $this->db->setQuery('SELECT * FROM #__jem_types ORDER BY id')->loadAssocList(),
            $this->db->setQuery('SELECT * FROM #__jem_special_days ORDER BY id')->loadAssocList(),
        ];
    }

    /** @return void Reinstallation and historical update seeds never duplicate or change configured defaults. */
    public function testAllSeedsAreRepeatableAndPreserveExistingTypes(): void
    {
        $this->seed();
        $this->db->setQuery("UPDATE #__jem_types SET color='#112233', published=0, access=2 WHERE id=1")->execute();
        $this->day(1, 1);
        $expected = $this->snapshot();
        for ($pass = 0; $pass < 3; $pass++) {
            foreach (['admin/sql/install.mysql.utf8.sql', 'admin/sql/updates/mysql/4.5.0.sql', 'admin/sql/updates/mysql/5.0.0.sql'] as $file) {
                $this->seed($file);
                $this->repair();
                self::assertSame($expected, $this->snapshot());
            }
        }
        self::assertSame([], Factory::$application->messages);
    }

    /** @return void Other entities do not suppress Day defaults; a missing default is restored once. */
    public function testEntityScopeAndMissingDefault(): void
    {
        $row = (object) ['name' => 'Weekend', 'alias' => 'weekend', 'entity' => 1];
        $this->db->insertObject('#__jem_types', $row);
        $this->seed();
        self::assertCount(3, $this->snapshot()[0]);
        $this->db->setQuery("DELETE FROM #__jem_types WHERE entity=4 AND alias='public-holiday'")->execute();
        $this->seed();
        $expected = $this->snapshot();
        $this->seed();
        $this->repair();
        self::assertSame($expected, $this->snapshot());
        self::assertCount(3, $expected[0]);
    }

    /** @return void Every reference is redirected before duplicates disappear; repeated repair is stable. */
    public function testMergePreservesOriginalRowsAndSpecialDays(): void
    {
        $this->seed();
        $copy = $this->duplicate();
        $copy2 = $this->duplicate(1, ['attribs' => '{"show_dates_default":1,"block_events":0}', 'created_by' => 99]);
        $holiday = $this->duplicate(2);
        foreach ([1, $copy, $copy2, $holiday] as $index => $type) {
            $this->day($index + 1, $type);
        }
        $this->db->setQuery('UPDATE #__jem_special_days SET published=0, access=2 WHERE id=2')->execute();
        $this->db->setQuery('UPDATE #__jem_special_days SET published=2 WHERE id=3')->execute();
        $before = $this->snapshot();
        $this->repair();
        $after = $this->snapshot();
        self::assertSame(array_slice($before[0], 0, 2), $after[0]);
        foreach ($before[1] as $index => $day) {
            $day['day_type_id'] = $index === 3 ? 2 : 1;
            self::assertEquals($day, $after[1][$index]);
        }
        self::assertSame([], Factory::$application->messages);
        $this->repair();
        self::assertSame($after, $this->snapshot());
    }

    /** @return iterable<string, array{array}> Settings that must never be silently overwritten. */
    public static function conflictingSettings(): iterable
    {
        foreach ([
            'color' => ['color' => '#123456'], 'publication' => ['published' => 0],
            'access' => ['access' => 2], 'ordering' => ['ordering' => 9],
            'name' => ['name' => 'Custom weekend'], 'description' => ['description' => 'Custom text'],
            'translations' => ['translations' => '{"es-ES":{"name":"Fin de semana"}}'],
            'block events' => ['attribs' => '{"block_events":1}'],
            'unknown attribute' => ['attribs' => '{"block_events":0,"custom":1}'],
            'checked out' => ['checked_out' => 42],
        ] as $name => $changes) {
            yield $name => [$changes];
        }
    }

    /** @param array $changes Conflicting fields. @return void Keep the entire group and report its IDs. */
    #[DataProvider('conflictingSettings')]
    public function testConflictsKeepRecordsAndReferences(array $changes): void
    {
        $this->seed();
        $id = $this->duplicate(1, $changes);
        $this->day(1, $id);
        $before = $this->snapshot();
        $this->repair();
        self::assertSame($before, $this->snapshot());
        self::assertCount(1, Factory::$application->messages);
        self::assertSame('warning', Factory::$application->messages[0][0]);
        self::assertStringContainsString('IDs 1, ' . $id, Factory::$application->messages[0][1]);
    }

    /** @return void A day being edited must not lose the type ID still present in its open form. */
    public function testCheckedOutSpecialDayPreventsMerge(): void
    {
        $this->seed();
        $id = $this->duplicate();
        $this->day(1, $id);
        $this->db->setQuery('UPDATE #__jem_special_days SET checked_out=42 WHERE id=1')->execute();
        $before = $this->snapshot();
        $this->repair();
        self::assertSame($before, $this->snapshot());
        self::assertCount(1, Factory::$application->messages);
    }

    /** @return void A conflicting alias stays intact while another equivalent group is repaired. */
    public function testConflictDoesNotPreventIndependentGroupRepair(): void
    {
        $this->seed();
        $conflict = $this->duplicate(1, ['color' => '#123456']);
        $holiday = $this->duplicate(2);
        $this->day(1, $conflict);
        $this->day(2, $holiday);
        $this->repair();
        self::assertSame([1, 2, $conflict], array_map('intval', array_column($this->snapshot()[0], 'id')));
        self::assertSame([$conflict, 2], array_map('intval', array_column($this->snapshot()[1], 'day_type_id')));
        self::assertCount(1, Factory::$application->messages);
    }

    /** @return void A failed DELETE rolls back the preceding reference UPDATE. */
    public function testDeleteFailureRollsBackReferences(): void
    {
        $this->seed();
        $id = $this->duplicate();
        $this->day(1, $id);
        $before = $this->snapshot();
        $this->db->failDelete = true;
        $this->repair();
        self::assertSame($before, $this->snapshot());
        self::assertCount(1, Factory::$application->messages);
        self::assertStringContainsString('could not be merged safely', Factory::$application->messages[0][1]);
    }

    /** @return void Do not claim transactional repair on nontransactional legacy tables. */
    public function testNontransactionalTablesAreKept(): void
    {
        $this->seed();
        $id = $this->duplicate();
        $this->day(1, $id);
        $this->db->setQuery('ALTER TABLE #__jem_types ENGINE=MyISAM')->execute();
        $before = $this->snapshot();
        $this->repair();
        self::assertSame($before, $this->snapshot());
        self::assertCount(1, Factory::$application->messages);
    }

    /** @return void Repaired IDs remain offered by the actual field and can be stored unchanged. */
    public function testRepairedSpecialDayCanKeepItsType(): void
    {
        $this->seed();
        $copy = $this->duplicate();
        $this->day(1, $copy);
        $this->repair();
        TypeSupport\loadMethods();
        eval('namespace JemProject\\Tests\\DayTypeReinstall; use Joomla\\CMS\\HTML\\HTMLHelper;'
            . ' use Joomla\\CMS\\Language\\Text; use JemProject\\Tests\\TypeLanguage\\JemHelper;'
            . ' class TypeField extends \\Joomla\\CMS\\Form\\Field\\ListField {'
            . TypeSupport\methods('admin/models/fields/specialdaytype.php', ['getOptions']) . '}');
        $field = new JemProject\Tests\DayTypeReinstall\TypeField();
        self::assertTrue($field->setup(new SimpleXMLElement('<field name="day_type_id" type="list" required="true" />'), '1'));
        self::assertSame(['', 1, 2], array_column($field->options, 'value'));
        self::assertSame(1, TypeSupport\JemHelper::resolveCalendarSpecialDayType(1)['id']);
        self::assertNull(TypeSupport\JemHelper::resolveCalendarSpecialDayType($copy));

        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        require_once JEM_TEST_ROOT . '/admin/tables/jem_special_days.php';
        $table = new jem_special_days($this->db);
        self::assertTrue($table->load(1));
        self::assertTrue($table->check(), (string) $table->getError());
        self::assertTrue($table->store(), (string) $table->getError());
        self::assertSame(1, (int) $this->db->setQuery('SELECT day_type_id FROM #__jem_special_days WHERE id=1')->loadResult());
    }

    /** @return void Repair runs after schema/language normalization for install, update and discovery. */
    public function testInstallerWiring(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/script.php');
        self::assertSame(1, substr_count($source, '$this->repairDefaultDayTypes();'));
        self::assertLessThan(strpos($source, '$this->repairDefaultDayTypes();'), strpos($source, '$this->normaliseTypeLanguages();'));
        self::assertLessThan(strpos($source, '$this->normaliseTypeLanguages();'), strpos($source, '$this->repair502SchemaParity();'));
    }
}
