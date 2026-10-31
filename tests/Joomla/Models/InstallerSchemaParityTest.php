<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise actual installer SQL and fallback against a disposable MySQL database. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class InstallerSchemaParityTest extends TestCase
{
    private ?mysqli $server = null;
    private string $database = '';
    private $db;

    /** Initialise isolated tables; never use the installed Joomla database. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (getenv('JEM_TEST_SCHEMA_PARITY') !== '1' || !is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Opt in with JEM_TEST_SCHEMA_PARITY=1 and JEM_TEST_JOOMLA_ROOT.');
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
        $options = ['host' => '127.0.0.1', 'user' => getenv('JEM_TEST_MYSQL_USER') ?: 'root',
            'password' => getenv('JEM_TEST_MYSQL_PASSWORD') ?: '', 'prefix' => 't_',
            'port' => (int) (getenv('JEM_TEST_MYSQL_PORT') ?: 3306)];
        $this->server = new mysqli($options['host'], $options['user'], $options['password'], '', $options['port']);
        $this->database = 'jem_schemaparity_' . bin2hex(random_bytes(8));
        $this->server->query('CREATE DATABASE ' . $this->database . ' CHARACTER SET utf8mb4');
        $this->db = new Joomla\Database\Mysqli\MysqliDriver($options + ['database' => $this->database]);
        $container = new Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        $this->db->setQuery("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'")->execute();
        foreach (Joomla\Database\DatabaseDriver::splitSql(file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql')) as $sql) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS .#__jem_(categories|types|special_days|attachments|config)./', $sql)) {
                $this->db->setQuery($sql)->execute();
            }
        }
        require_once JEM_TEST_ROOT . '/script.php';
    }

    /** Drop only the uniquely named database owned by this test. */
    protected function tearDown(): void
    {
        if ($this->server && preg_match('/^jem_schemaparity_[a-f0-9]{16}$/D', $this->database)) {
            $this->db?->disconnect();
            $this->server->query('DROP DATABASE ' . $this->database);
            $this->server->close();
        }
    }

    /** Return sorted column and index definitions, ignoring physical column order. */
    private function schema(): array
    {
        $result = [];
        foreach (['categories', 'types', 'special_days'] as $table) {
            $columns = $this->db->getTableColumns('#__jem_' . $table, false);
            ksort($columns);
            $indexes = [];
            foreach ($this->db->getTableKeys('#__jem_' . $table) as $key) {
                $indexes[$key->Key_name . ':' . $key->Seq_in_index] = (array) $key;
                unset($indexes[$key->Key_name . ':' . $key->Seq_in_index]['Cardinality']);
            }
            ksort($indexes);
            $result[$table] = [$columns, $indexes];
        }

        return $result;
    }

    /** Apply the current version's SQL, tolerating only explicitly optional duplicate definitions. */
    private function updateSql(): void
    {
        foreach (Joomla\CMS\Installer\Installer::splitSql(file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.2.sql')) as $sql) {
            try {
                $this->db->setQuery($sql)->execute();
            } catch (Throwable $error) {
                if (!str_contains($sql, 'CAN FAIL') || !in_array((int) $error->getCode(), [1060, 1061], true)) {
                    throw $error;
                }
            }
        }
    }

    /** Return old release and same-schema reinstall scenarios. */
    public static function variants(): array
    {
        return [
            '442 SQL and fallback' => ['442', true],
            '442 beta fallback' => ['442', false],
            '450 SQL and fallback' => ['450', true],
            '450 beta fallback' => ['450', false],
            '501 SQL and fallback' => ['501', true],
            'current reinstall' => ['current', false],
        ];
    }

    /** Restore the clean schema without changing historical values; repeated repair is stable. */
    #[DataProvider('variants')]
    public function testLegacyParityAndIdempotence(string $variant, bool $runSql): void
    {
        $expected = $this->schema();
        if ($variant === '442') {
            $this->db->setQuery("ALTER TABLE #__jem_special_days MODIFY weekdays VARCHAR(30) NOT NULL DEFAULT '',"
                . " MODIFY created DATETIME NULL DEFAULT NULL,"
                . " DROP INDEX idx_day_type, DROP INDEX idx_published_dates, DROP INDEX idx_checkout,"
                . " ADD INDEX idx_type(day_type), ADD INDEX idx_dates(start_date,end_date), ADD INDEX idx_published(published)")->execute();
            $this->db->setQuery('ALTER TABLE #__jem_types ALTER COLUMN name DROP DEFAULT')->execute();
        }
        if ($variant === '450') {
            $this->db->setQuery('ALTER TABLE #__jem_categories DROP COLUMN image_as_default')->execute();
        }
        if ($variant === '501') {
            $this->db->setQuery('ALTER TABLE #__jem_special_days DROP INDEX idx_day_type,'
                . ' DROP INDEX idx_published_dates, DROP INDEX idx_checkout')->execute();
        }
        $this->db->setQuery("INSERT INTO #__jem_special_days(title,created) VALUES ('Unknown history',NULL),('Known history','2012-03-04 05:06:07')")->execute();
        if ($runSql) {
            $this->updateSql();
        }
        $installer = new com_jemInstallerScript();
        $repair = new ReflectionMethod($installer, 'repair502SchemaParity');
        $repair->invoke($installer);
        self::assertEquals($expected, $this->schema());
        self::assertSame([null, '2012-03-04 05:06:07'], $this->db->setQuery('SELECT created FROM #__jem_special_days ORDER BY id')->loadColumn());
        foreach (Joomla\Database\DatabaseDriver::splitSql(file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.2.sql')) as $sql) {
            $check = new Joomla\CMS\Schema\ChangeItem\MysqlChangeItem($this->db, '5.0.2.sql', $sql);
            self::assertContains($check->check(), [1, -1], $sql);
        }
        $repair->invoke($installer);
        self::assertEquals($expected, $this->schema());
        $this->db->setQuery("INSERT INTO #__jem_special_days(title) VALUES ('New history')")->execute();
        self::assertNotNull($this->db->setQuery("SELECT created FROM #__jem_special_days WHERE title='New history'")->loadResult());
    }

    /** Do not drop an administrator's differently defined index with a legacy name. */
    public function testCustomIndexIsPreserved(): void
    {
        $this->db->setQuery('ALTER TABLE #__jem_special_days ADD INDEX idx_type(title), ADD INDEX local_custom(city)')->execute();
        $expected = $this->schema();
        (new ReflectionMethod(com_jemInstallerScript::class, 'repair502SchemaParity'))->invoke(new com_jemInstallerScript());
        self::assertEquals($expected, $this->schema());
    }
}
