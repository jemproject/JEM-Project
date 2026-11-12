<?php
/** Isolated Joomla libraries and in-memory SQL; never boots or changes an installed site. */
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\Database\Sqlite\SqliteDriver;
use Joomla\DI\Container;

$joomlaRoot = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
$loader = require $joomlaRoot . '/libraries/vendor/autoload.php';
$loader->addPsr4('Joomla\\CMS\\', $joomlaRoot . '/libraries/src');
define('JPATH_BASE', JEM_TEST_ROOT);
define('JPATH_ROOT', $joomlaRoot);
define('JPATH_LIBRARIES', $joomlaRoot . '/libraries');
define('JPATH_SITE', 'jemsource://site');
define('JPATH_ADMINISTRATOR', 'jemsource://admin');

/** Resolve source includes without copying files into an installed Joomla site. */
final class InvitationSourceStream
{
    public $context;
    private $handle;

    /** @param string $path Virtual path. @return string Read-only checkout file. */
    private function path(string $path): string
    {
        return str_replace(['jemsource://site/components/com_jem', 'jemsource://admin/components/com_jem',
            'jemsource://site', 'jemsource://admin'],
            [JEM_TEST_ROOT . '/site', JEM_TEST_ROOT . '/admin', JPATH_ROOT, JPATH_ROOT . '/administrator'], $path);
    }
    /** @return bool Open a source file for PHP includes. */
    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        $openedPath = realpath($this->path($path));
        if ($openedPath === false) {
            return false;
        }
        $this->handle = fopen($openedPath, 'rb');
        return $this->handle !== false;
    }
    /** @return string Read source bytes. */
    public function stream_read($count): string { return fread($this->handle, $count); }
    /** @return bool End of source. */
    public function stream_eof(): bool { return feof($this->handle); }
    /** @return array File metadata. */
    public function stream_stat(): array { return fstat($this->handle); }
    /** @return array|false Metadata for require_once resolution. */
    public function url_stat($path, $flags) { return @stat($this->path($path)); }
    /** @return bool PHP stream buffering is unused. */
    public function stream_set_option($option, $arg1, $arg2): bool { return false; }
    /** @return void Close the source handle. */
    public function stream_close(): void { fclose($this->handle); }
}
stream_wrapper_register('jemsource', InvitationSourceStream::class);
Factory::$language = new class ('en-GB', false) extends Language {
    /** @return string Stable fixture locale without installed language metadata. */
    public function getTag() { return 'en-GB'; }
};
/** Single-process SQL fixtures; SQLite omits MySQL row-lock syntax, not any application checks. */
final class InvitationSqliteDriver extends SqliteDriver
{
    /** @return static Execute the same queries without unsupported FOR UPDATE syntax. */
    public function setQuery($query, $offset = 0, $limit = 0)
    {
        return parent::setQuery(is_string($query) ? preg_replace('/ FOR UPDATE$/', '', $query) : $query, $offset, $limit);
    }
}
$db = new InvitationSqliteDriver(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'qa2514_']);
$container = new Container();
$container->set('DatabaseDriver', $db);
$container->set(Joomla\Database\DatabaseInterface::class, $db);
(new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);

$db->setQuery('CREATE TABLE #__jem_events (
    id INTEGER PRIMARY KEY, title TEXT, published INTEGER, publish_up TEXT, publish_down TEXT,
    event_status TEXT, minbookeduser INTEGER, maxbookeduser INTEGER, registra INTEGER,
    dates TEXT, times TEXT, recurrence_first_id INTEGER, series_id INTEGER, access INTEGER
)')->execute();
$db->setQuery('CREATE TABLE #__jem_register (
    id INTEGER PRIMARY KEY, event INTEGER, uid INTEGER, status INTEGER, waiting INTEGER, places INTEGER,
    revision INTEGER, invitation_revoked INTEGER DEFAULT 0, invitation_expires TEXT,
    invitation_key TEXT, invitation_token_hash TEXT, invitation_scope TEXT
)')->execute();
$db->setQuery('CREATE TABLE #__jem_notifications (
    id INTEGER PRIMARY KEY, registration_id INTEGER, registration_revision INTEGER, notification_type TEXT,
    payload_json TEXT, state TEXT, cancelled_at TEXT, next_attempt_at TEXT,
    attempt_count INTEGER, max_attempts INTEGER, processing_started_at TEXT, modified TEXT
)')->execute();
$db->setQuery('CREATE TABLE #__jem_notifications_attempts (
    id INTEGER PRIMARY KEY, notification_id INTEGER, attempt_number INTEGER, transport TEXT,
    source TEXT, requested_by_user_id INTEGER, started_at TEXT, finished_at TEXT, result TEXT,
    transport_message_id TEXT, error_code TEXT, error_message TEXT
)')->execute();
$db->setQuery('CREATE TABLE #__users (id INTEGER PRIMARY KEY,name TEXT,email TEXT,block INTEGER,activation TEXT)')->execute();
$db->setQuery("INSERT INTO #__users VALUES (42,'Test participant','participant@example.test',0,'')")->execute();

/** Only the registration clock is replaced; policy, SQL and mutation methods are production code. */
final class JemHelper
{
    /** @param object $event Fixture date. @return string Registration availability. */
    public static function getEventRegistrationWindowState($event): string
    {
        return (int) ($event->registra ?? 1) === 1 ? 'open' : 'closed';
    }
}
require_once JEM_TEST_ROOT . '/site/classes/registrationaccesspolicy.class.php';
require_once JEM_TEST_ROOT . '/site/classes/registrationservice.class.php';
require_once JEM_TEST_ROOT . '/admin/models/event.php';
