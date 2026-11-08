<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

/** Retryable cleanup of resources whose event or venue has been permanently deleted. */
final class JemDeletedObjectCleanup
{
    public const PAGE_SIZE = 50;
    public const BATCH_LIMIT = 10;

    /** @var array<int, array<string, bool>> Deferred objects, grouped by database connection. */
    private static array $pending = [];

    /**
     * Queue cleanup after a table deletion; a savepoint must never authorize file removal.
     * @param DatabaseInterface $db Database used by the parent deletion.
     * @param string $object Canonical event/venue identity.
     * @return void
     */
    public static function afterDelete(DatabaseInterface $db, string $object): void
    {
        self::$pending[spl_object_id($db)][$object] = true;
        self::flush($db);
    }

    /**
     * Resolve pending deletions after the owning transaction ends, including rollback.
     * @param DatabaseInterface $db Database whose committed state must be inspected.
     * @return void
     */
    public static function flush(DatabaseInterface $db): void
    {
        $key = spl_object_id($db);
        if (empty(self::$pending[$key])) {
            return;
        }
        try {
            if (self::inTransaction($db)) {
                return;
            }
            $objects = array_keys(self::$pending[$key] ?? []);
            unset(self::$pending[$key]);
            $failed = [];
            foreach ($objects as $object) {
                $status = self::purge($db, $object);
                if (!in_array($status, ['done', 'parent'], true)) {
                    $failed[] = $object;
                    self::log($object . ' (' . $status . ')', new RuntimeException('Cleanup pending.'));
                }
            }
            if ($failed) {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('COM_JEM_DELETED_RESOURCES_PENDING', implode(', ', $failed)),
                    'warning'
                );
            }
        } catch (Throwable $error) {
            self::log('queue', $error);
        }
    }

    /**
     * Find orphan objects before pagination, including events with links but no attachments.
     * @param DatabaseInterface $db Read connection.
     * @param int $page One-based page.
     * @return array Preview records and a flag indicating a following page.
     */
    public static function preview(DatabaseInterface $db, int $page = 1): array
    {
        $sql = "SELECT a.object FROM #__jem_attachments a"
            . " LEFT JOIN #__jem_events e ON a.object = CONCAT('event', e.id)"
            . " LEFT JOIN #__jem_venues v ON a.object = CONCAT('venue', v.id)"
            . " WHERE a.object REGEXP '^(event|venue)[1-9][0-9]*$' AND e.id IS NULL AND v.id IS NULL"
            . " UNION SELECT CONCAT('event', l.event_id) AS object FROM #__jem_links l"
            . " LEFT JOIN #__jem_events e ON e.id = l.event_id WHERE e.id IS NULL AND l.event_id > 0"
            . ' ORDER BY object';
        $objects = $db->setQuery($sql, (max(1, $page) - 1) * self::PAGE_SIZE, self::PAGE_SIZE + 1)->loadColumn();
        $more = count($objects) > self::PAGE_SIZE;
        $entries = [];
        foreach (array_slice($objects, 0, self::PAGE_SIZE) as $object) {
            $entries[$object] = self::inspect($db, $object);
        }
        return ['entries' => $entries, 'more' => $more];
    }

    /**
     * Inspect ownership and files without deleting anything; used by preview and cleanup.
     * @param DatabaseInterface $db Active database.
     * @param string $object Canonical event/venue identity.
     * @param bool $lock Lock parent gap and attachment records during cleanup.
     * @return array Status, snapshot, owned rows and safe paths.
     */
    public static function inspect(DatabaseInterface $db, string $object, bool $lock = false): array
    {
        $result = ['object' => $object, 'status' => 'ready', 'rows' => [], 'links' => [], 'paths' => [], 'folder' => '', 'snapshot' => ''];
        if (!preg_match('/^(event|venue)([1-9][0-9]*)$/D', $object, $matches)
            || (string) (int) $matches[2] !== $matches[2]) {
            $result['status'] = 'unsafe';
            return $result;
        }
        $table = $matches[1] === 'event' ? '#__jem_events' : '#__jem_venues';
        $suffix = $lock ? ' FOR UPDATE' : '';
        $parent = $db->setQuery('SELECT id FROM ' . $db->quoteName($table) . ' WHERE id = ' . (int) $matches[2] . $suffix)->loadColumn();
        if ($parent) {
            $result['status'] = 'parent';
            return $result;
        }
        $result['rows'] = $db->setQuery('SELECT id, object, file FROM #__jem_attachments WHERE object = '
            . $db->quote($object) . ' ORDER BY id' . $suffix)->loadAssocList();
        if ($matches[1] === 'event') {
            $result['links'] = $db->setQuery('SELECT id FROM #__jem_links WHERE event_id = ' . (int) $matches[2]
                . ' ORDER BY id' . $suffix)->loadColumn();
        }
        if ($result['rows']) {
            try {
                $base = self::attachmentBase();
                $folder = $base . DIRECTORY_SEPARATOR . $object;
                self::assertPath($folder, true);
                $result['folder'] = $folder;
                foreach ($result['rows'] as $row) {
                    $file = (string) $row['file'];
                    if ($row['object'] !== $object || $file === '' || $file === '.' || $file === '..'
                        || strpbrk($file, "\\/\0:") !== false || rtrim($file, '. ') !== $file) {
                        throw new RuntimeException('unsafe');
                    }
                    $path = $folder . DIRECTORY_SEPARATOR . $file;
                    self::assertPath($path, false);
                    $result['paths'][self::pathKey($path)] = $path;
                }
                // Legacy/imported rows can contain relative paths across object folders.
                $foreign = $db->setQuery('SELECT object, file FROM #__jem_attachments WHERE object <> '
                    . $db->quote($object) . ' AND (LOCATE(' . $db->quote('/') . ', file) > 0'
                    . ' OR LOCATE(' . $db->quote('\\') . ', file) > 0)' . $suffix)->loadAssocList();
                foreach ($foreign as $row) {
                    $path = Path::clean($base . '/' . $row['object'] . '/' . $row['file']);
                    $resolved = realpath($path);
                    if (isset($result['paths'][self::pathKey($resolved === false ? $path : $resolved)])) {
                        throw new RuntimeException('referenced');
                    }
                }
                if (is_dir($folder)) {
                    foreach (new DirectoryIterator($folder) as $entry) {
                        if (!$entry->isDot() && !isset($result['paths'][self::pathKey($entry->getPathname())])) {
                            throw new RuntimeException('unknown');
                        }
                    }
                }
            } catch (RuntimeException $error) {
                $result['status'] = in_array($error->getMessage(), ['referenced', 'unknown'], true) ? $error->getMessage() : 'unsafe';
            }
        }
        $result['snapshot'] = hash('sha256', serialize([$result['rows'], $result['links'], $result['status']]));
        return $result;
    }

    /**
     * Remove only orphan-owned files, then their exact records; retain rows on any failure.
     * The parent is already committed deleted, so rows serve as the durable retry manifest.
     * @param DatabaseInterface $db Active database.
     * @param string $object Canonical parent identity.
     * @param string|null $snapshot Optional server-side preview fingerprint.
     * @return string Completion or reason that resources were preserved.
     */
    public static function purge(DatabaseInterface $db, string $object, ?string $snapshot = null): string
    {
        $started = false;
        try {
            if (self::inTransaction($db)) {
                return 'transaction';
            }
            $db->transactionStart();
            $started = true;
            $record = self::inspect($db, $object, true);
            if ($record['status'] !== 'ready') {
                return $record['status'];
            }
            if ($snapshot !== null && !hash_equals($snapshot, $record['snapshot'])) {
                return 'stale';
            }
            foreach ($record['paths'] as $path) {
                self::assertPath(dirname($path), true);
                self::assertPath($path, false);
                if (is_file($path) && !File::delete($path)) {
                    throw new RuntimeException('File cleanup failed.');
                }
            }
            if ($record['folder'] !== '' && is_dir($record['folder']) && !@rmdir($record['folder'])) {
                throw new RuntimeException('Folder cleanup failed.');
            }
            foreach (['#__jem_attachments' => array_column($record['rows'], 'id'), '#__jem_links' => $record['links']] as $table => $ids) {
                if (!$ids) {
                    continue;
                }
                $where = 'id IN (' . implode(',', array_map('intval', $ids)) . ')';
                if (!$db->setQuery('DELETE FROM ' . $db->quoteName($table) . ' WHERE ' . $where)->execute()) {
                    throw new RuntimeException('Record cleanup failed.');
                }
                $count = $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName($table) . ' WHERE ' . $where)->loadResult();
                if (!ctype_digit((string) $count) || (int) $count !== 0) {
                    throw new RuntimeException('Record cleanup could not be verified.');
                }
            }
            $db->transactionCommit();
            if (self::inTransaction($db)) {
                throw new RuntimeException('Cleanup commit not confirmed.');
            }
            $started = false;
            return 'done';
        } catch (Throwable $error) {
            self::log($object, $error);
            return 'failed';
        } finally {
            if ($started) {
                try {
                    $db->transactionRollback();
                } catch (Throwable $error) {
                    self::log($object, $error);
                }
            }
        }
    }

    /**
     * Recover only a bounded selection from an unexpired, server-owned preview.
     * @param DatabaseInterface $db Active database.
     * @param array $selected Submitted object identities.
     * @param array $preview Previously inspected entries stored by the controller/view.
     * @return array Object-to-result map.
     */
    public static function recover(DatabaseInterface $db, array $selected, array $preview): array
    {
        if (!$selected || count($selected) > self::BATCH_LIMIT
            || count(array_filter($selected, 'is_string')) !== count($selected)
            || count(array_unique($selected)) !== count($selected)) {
            throw new InvalidArgumentException('Invalid cleanup selection.');
        }
        foreach ($selected as $object) {
            if (!isset($preview[$object]) || $preview[$object]['status'] !== 'ready') {
                throw new InvalidArgumentException('Object was not approved in the preview.');
            }
        }
        $result = [];
        foreach ($selected as $object) {
            $result[$object] = self::purge($db, $object, $preview[$object]['snapshot']);
        }
        return $result;
    }

    /**
     * Resolve the configured attachment root, rejecting symlinks and paths outside Joomla.
     * @return string Absolute configured base; it need not exist when all files are missing.
     */
    private static function attachmentBase(): string
    {
        $relative = str_replace('\\', '/', trim((string) JemHelper::config()->attachments_path));
        if ($relative === '' || $relative[0] === '/' || strpbrk($relative, "\0:") !== false) {
            throw new RuntimeException('unsafe');
        }
        $root = realpath(JPATH_SITE);
        if ($root === false) {
            throw new RuntimeException('unsafe');
        }
        $cursor = $root;
        foreach (explode('/', trim($relative, '/')) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || rtrim($part, '. ') !== $part) {
                throw new RuntimeException('unsafe');
            }
            $cursor .= DIRECTORY_SEPARATOR . $part;
            self::assertPath($cursor, true);
        }
        return $cursor;
    }

    /**
     * Reject links, unexpected types and path aliases before touching owned files.
     * @param string $path Expected absolute path.
     * @param bool $directory Whether this path must be a directory if present.
     * @return void
     */
    private static function assertPath(string $path, bool $directory): void
    {
        clearstatcache(true, $path);
        if (is_link($path) || (file_exists($path)
            && (($directory ? !is_dir($path) : !is_file($path))
                || self::pathKey((string) realpath($path)) !== self::pathKey($path)))) {
            throw new RuntimeException('unsafe');
        }
    }

    /**
     * Compare normalized physical paths using the host filesystem's case convention.
     * @param string $path Path to compare.
     * @return string Normalized comparison key.
     */
    private static function pathKey(string $path): string
    {
        $path = Path::clean($path);
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }

    /**
     * Detect active Joomla transactions, including savepoints and native driver transactions.
     * @param DatabaseInterface $db Active database.
     * @return bool Whether file cleanup must wait.
     */
    private static function inTransaction(DatabaseInterface $db): bool
    {
        if ((int) (new ReflectionProperty($db, 'transactionDepth'))->getValue($db) > 0) {
            return true;
        }
        $connection = $db->getConnection();
        if ($connection instanceof PDO) {
            return $connection->inTransaction();
        }
        $active = $db->setQuery('SELECT @@in_transaction')->loadResult();
        if (!in_array((string) $active, ['0', '1'], true)) {
            throw new RuntimeException('Transaction state could not be verified.');
        }
        return (int) $active !== 0;
    }

    /**
     * Record an incomplete cleanup without leaking paths or changing a committed save result.
     * @param string $object Affected parent identity.
     * @param Throwable $error Failure whose class is safe to log.
     * @return void
     */
    private static function log(string $object, Throwable $error): void
    {
        try {
            JemHelper::addLogEntry('Deleted-object cleanup pending: ' . $object . '; ' . get_class($error), __METHOD__, Log::WARNING);
        } catch (Throwable $ignored) {
            // Optional logging cannot undo the committed parent deletion.
        }
    }
}
