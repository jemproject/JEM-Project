<?php
/** Native Joomla/MariaDB fixtures in a disposable database, never the installed site. */
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\ComponentRecord;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

$joomla = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
$loader = require $joomla . '/libraries/vendor/autoload.php';
$loader->addPsr4('Joomla\\CMS\\', $joomla . '/libraries/src');
require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
define('JPATH_SITE', 'jemrecurrence://site');
define('JPATH_ADMINISTRATOR', JPATH_SITE . '/administrator');
define('JPATH_ROOT', $joomla);
define('JPATH_BASE', $joomla);
define('JPATH_CACHE', sys_get_temp_dir());

/** External plugins/SMTP are outside this SQL test; accidental imports remain visible. */
final class SeriesInvitationPlugins
{
    public static int $imports = 0;
    public static array $logs = [];
    /** @return bool No external plugins are loaded in a disposable fixture. */
    public static function importPlugin(...$arguments): bool { ++self::$imports; return false; }
}
class_alias(SeriesInvitationPlugins::class, 'Joomla\\CMS\\Plugin\\PluginHelper');
require $joomla . '/configuration.php';
$config = new JConfig();
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_qa2512_' . bin2hex(random_bytes(7));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
// PHPUnit teardown also drops it; this covers setup/assertion failures.
register_shutdown_function(static function () use ($server, $database): void {
    $server->query('DROP DATABASE IF EXISTS ' . $database);
});
$db = new Joomla\Database\Mysqli\MysqliDriver([
    'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
    'database' => $database, 'prefix' => 'qa_',
]);
preg_match_all('/CREATE TABLE IF NOT EXISTS `#__jem_[^`]+`.*?ENGINE=InnoDB;/s',
    file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql'), $schemas);
foreach ($schemas[0] as $schema) { $db->setQuery($schema)->execute(); }
$db->setQuery('CREATE TABLE #__users (id INT PRIMARY KEY,name VARCHAR(100),email VARCHAR(254),block INT DEFAULT 0,activation VARCHAR(100) DEFAULT NULL)')->execute();
foreach (range(42, 52) as $id) {
    $db->setQuery("INSERT INTO #__users VALUES ($id,'Participant $id','qa$id@example.invalid',0,NULL)")->execute();
}
$container = new Joomla\DI\Container();
$container->set('DatabaseDriver', $db, true);
$container->set(Joomla\Database\DatabaseInterface::class, $db, true);
$container->set(Joomla\CMS\User\UserFactoryInterface::class, new class ($db) {
    /** @param object $db Disposable fixture connection. */
    public function __construct(private object $db) {}
    /** @param int $id Recipient. @return object Fixture account with explicit ACL levels. */
    public function loadUserById($id): object
    {
        $row = $this->db->setQuery('SELECT * FROM #__users WHERE id=' . (int) $id)->loadObject();
        return new class ($row) {
            public int $id; public int $block; public string $activation;
            /** @param object|null $row Account fixture, absent after deletion. */
            public function __construct($row) {
                $this->id = (int) ($row->id ?? 0); $this->block = (int) ($row->block ?? 0);
                $this->activation = (string) ($row->activation ?? '');
            }
            /** @return array Visible levels; recipient 52 cannot access Registered. */
            public function getAuthorisedViewLevels(): array { return $this->id === 52 ? [1] : [1, 2]; }
        };
    }
});
(new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
Factory::$config = new Registry(['offset' => 'UTC', 'debug' => false]);
Factory::$language = (new ReflectionClass(Joomla\CMS\Language\Language::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Factory::$language, 'metadata'))->setValue(Factory::$language, ['tag' => 'en-GB']);
Factory::$application = new class {
    public object $input;
    /** @return void Empty request boundary for error logging. */
    public function __construct() { $this->input = new Joomla\Input\Input([]); }
    /** @return bool This SQL fixture has no browser request. */
    public function isClient($client): bool { return $client === 'cli'; }
    /** @return mixed Fixture configuration, without booting Joomla. */
    public function get($key, $default = null) { return $key === 'access' ? 1 : $default; }
    /** @return Registry Joomla's clock configuration for recurrence generation. */
    public function getConfig(): Registry { return Factory::$config; }
    /** @return object Current test operator. */
    public function getIdentity(): object { return (object) ['id' => 42]; }
    /** @return object Isolated language service. */
    public function getLanguage(): object { return Factory::$language; }
    /** @return void No UI is rendered by this integration fixture. */
    public function enqueueMessage(...$arguments): void {}
};
(new ReflectionProperty(ComponentHelper::class, 'components'))->setValue(null, [
    'com_jem' => new ComponentRecord(['enabled' => 1, 'params' => new Registry()]),
]);
require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
require_once JEM_TEST_ROOT . '/admin/models/event.php';
$jemConfig = (new ReflectionClass(JemConfig::class))->newInstanceWithoutConstructor();
(new ReflectionProperty($jemConfig, '_data'))->setValue($jemConfig, new Registry([
    'weekdaystart' => 1, 'lastupdate' => 0, 'runningupdate' => 0, 'oldevent' => 0, 'minus' => 1,
    'recurrence_anticipation_week' => 1, 'access_level_locked_venues' => '["1"]', 'globalattribs' => ['loglevel' => 0],
]));
(new ReflectionProperty(JemConfig::class, 'instance'))->setValue(null, $jemConfig);
Joomla\CMS\Log\Log::addLogger(['logger' => 'callback', 'callback' => static function ($entry): void {
    SeriesInvitationPlugins::$logs[] = $entry->message;
}], Joomla\CMS\Log\Log::ALL, ['JEM']);

/** Isolate event ACL/asset storage, retaining Joomla's real SQL table CRUD and transactions. */
class JemTableEvent extends Table
{
    /** @param object $db Native database. */
    public function __construct($db)
    {
        parent::__construct('#__jem_events', 'id', $db, new Joomla\Event\Dispatcher());
        $this->_trackAssets = false;
    }
}

/** Real series algorithms; only already-validated editing permissions and category storage are fixtures. */
class SeriesInvitationModel extends JemModelEvent
{
    /** @return JemTableEvent Native SQL table without unrelated asset/plugin lifecycle. */
    public function getTable($type = 'Event', $prefix = 'JemTable', $config = [])
    {
        return new JemTableEvent(Factory::getContainer()->get('DatabaseDriver'));
    }
    /** @return bool The test operator can edit every fixture occurrence. */
    protected function canEditCustomSeriesOccurrence($event, $backend) { return true; }
    /** @return bool Fixture category membership used by the real recipient ACL policy. */
    protected function _storeCategoriesSelected($eventId, $categories, $frontend, $new)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery('DELETE FROM #__jem_cats_event_relations WHERE itemid=' . (int) $eventId)->execute();
        foreach ((array) $categories as $catId) {
            $db->setQuery('INSERT INTO #__jem_cats_event_relations (itemid,catid) VALUES (' . (int) $eventId . ',' . (int) $catId . ')')->execute();
        }
        return true;
    }
}
return [$db, $server, $database];
