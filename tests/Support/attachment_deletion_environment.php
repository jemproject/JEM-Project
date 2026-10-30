<?php

declare(strict_types=1);

use Joomla\CMS\Factory;

/** Failure injection around real database statements and transaction boundaries. */
trait AttachmentIntegrityFaults
{
    public string $fault = '';
    public bool $committed = false;

    /** @return bool Real SQL outcome, or a simulated driver false return. */
    public function execute()
    {
        if ($this->fault === 'delete-false' && preg_match('/^\s*DELETE\b/i', (string) $this->sql)) {
            return false;
        }
        return parent::execute();
    }

    /** @return mixed Real count, or an uncertain/error result at the selected stage. */
    public function loadResult()
    {
        if (($this->fault === 'verify-null' && !$this->committed)
            || ($this->fault === 'reference-null' && $this->committed)) {
            return null;
        }
        if ($this->fault === 'reference-error' && $this->committed) {
            throw new RuntimeException('Synthetic reference query failure.');
        }
        return parent::loadResult();
    }

    /** @return void Commit normally or simulate a failure before/after acknowledgement. */
    public function transactionCommit($toSavepoint = false)
    {
        if ($this->fault === 'commit-before') {
            throw new RuntimeException('Synthetic commit failure.');
        }
        if ($this->fault === 'commit-silent') {
            return;
        }
        parent::transactionCommit($toSavepoint);
        $this->committed = true;
        if ($this->fault === 'commit-after') {
            throw new RuntimeException('Synthetic lost commit acknowledgement.');
        }
    }
}

/** Real in-memory SQLite adapter, also used with PDO-only PHP builds. */
final class AttachmentIntegritySqlite extends Joomla\Database\Sqlite\SqliteDriver
{
    use AttachmentIntegrityFaults;

    /** @return bool Whether PDO SQLite is available. */
    public static function isSupported()
    {
        return extension_loaded('pdo_sqlite');
    }

    /** @return string Escaped literal body for SQLite. */
    public function escape($text, $extra = false)
    {
        $this->connect();
        return substr($this->connection->quote((string) $text), 1, -1);
    }
}

/** Optional disposable MariaDB/MySQL driver; never bound to an installed site. */
final class AttachmentIntegrityMysqli extends Joomla\Database\Mysqli\MysqliDriver
{
    use AttachmentIntegrityFaults;
}

/** Identity/configuration and optional logging are isolated test doubles. */
final class JemFactory
{
    /** @return self Fixture identity. */
    public static function getUser()
    {
        return new self();
    }
    /** @return int Synthetic user ID. */
    public function get($key)
    {
        return 7;
    }
    /** @return array<int> Public fixture view levels. */
    public function getAuthorisedViewLevels()
    {
        return [1];
    }
    /** @return bool Current parent-record edit permission. */
    public function can(...$args)
    {
        return JemHelperBackend::$allowed;
    }
    /** @return object Test dispatcher. */
    public static function getDispatcher()
    {
        return Factory::getApplication()->getDispatcher();
    }
}

final class JemHelperBackend
{
    public static bool $allowed = true;
    /** @return bool Fixture edit permission, not a real ACL implementation. */
    public static function canAccessAttachment(...$args)
    {
        return self::$allowed;
    }
}

final class JemHelper
{
    public static array $logs = [];
    /** @return object Synthetic site configuration. */
    public static function config()
    {
        return (object) ['attachments_path' => 'files'];
    }
    /** @return void Capture logs without writing to an installed site. */
    public static function addLogEntry($message, ...$args)
    {
        self::$logs[] = $message;
    }
}

/** Real Joomla events plus a legacy action-log event recorder. */
final class AttachmentIntegrityDispatcher extends Joomla\Event\Dispatcher
{
    public array $actionLogs = [];
    /** @return array Empty optional plugin results. */
    public function triggerEvent($name, $args = [])
    {
        $this->actionLogs[] = $name;
        return [];
    }
}

/** Real JEM model/table/deletion logic; only bootstrap and cache are substituted. */
final class AttachmentIntegrityModel extends JemModelAttachment
{
    /**
     * @param object $db Fixture driver.
     * @param object $dispatcher Isolated dispatcher.
     */
    public function __construct($db, $dispatcher)
    {
        $this->setDatabase($db);
        $this->setDispatcher($dispatcher);
        $this->name = 'attachment';
        $this->option = 'com_jem';
        $this->events_map = ['delete' => 'content'];
        $this->event_before_delete = 'onContentBeforeDelete';
        $this->event_after_delete = 'onContentAfterDelete';
    }

    /** @return jem_attachments Actual JEM table using only fixture data. */
    public function getTable($name = 'jem_attachments', $prefix = '', $options = [])
    {
        $db = $this->getDatabase();
        return new jem_attachments($db);
    }

    /** @return void No installed cache is touched. */
    protected function cleanCache($group = null)
    {
    }
}

/** Filesystem adapter that makes the real Joomla File::delete throw safely. */
final class AttachmentIntegrityFailingStream
{
    public $context;
    public static string $file = '';

    /** @return array|false Stat of the synthetic file whose deletion will fail. */
    public function url_stat($path, $flags)
    {
        return stat(self::$file);
    }
    /** @return bool Ignore Joomla's pre-delete chmod in this adapter. */
    public function stream_metadata($path, $option, $value)
    {
        return true;
    }
    /** @return bool Never deletes fixture bytes; simulates an OS deletion error. */
    public function unlink($path)
    {
        throw new RuntimeException('Synthetic filesystem refusal.');
    }
}
