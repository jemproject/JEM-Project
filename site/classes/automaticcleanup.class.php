<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;

/** Committed automatic event changes and their Smart Search notifications. */
final class JemAutomaticCleanup
{
    /**
     * Atomically claim the daily job without rewriting cached administrator settings.
     * @param DatabaseInterface $db Active database, outside a caller-owned transaction.
     * @param object $settings Settings snapshot used by the daily eligibility check.
     * @param int $now UTC claim timestamp.
     * @param int $delay Retry/lease interval in seconds.
     * @return bool Whether this request owns the job.
     */
    public static function claim(DatabaseInterface $db, object $settings, int $now, int $delay): bool
    {
        if (self::inTransaction($db)) {
            return false;
        }
        $db->transactionStart();
        try {
            // Lock the lease first, matching complete(), without changing existing values.
            foreach (['runningupdate', 'lastupdate'] as $key) {
                $db->setQuery('INSERT INTO #__jem_config (keyname, value) VALUES ('
                    . $db->quote($key) . ', ' . $db->quote('0')
                    . ') ON DUPLICATE KEY UPDATE keyname = keyname')->execute();
            }
            $rows = $db->setQuery('SELECT keyname, value FROM #__jem_config WHERE keyname IN ('
                . $db->quote('lastupdate') . ', ' . $db->quote('runningupdate')
                . ') ORDER BY keyname DESC FOR UPDATE')->loadAssocList();
            $state = [];
            foreach ($rows as $row) {
                $state[$row['keyname']] = self::timestamp($row['value']);
            }
            // Recheck the snapshot under lock before repairing or claiming any state.
            if (
                $state['lastupdate'] !== self::timestamp($settings->lastupdate ?? null)
                || $state['runningupdate'] !== self::timestamp($settings->runningupdate ?? null)
                || $state['runningupdate'] >= $now - $delay
            ) {
                $db->transactionRollback();
                return false;
            }
            $state['runningupdate'] = $now;
            foreach ($state as $key => $value) {
                $db->setQuery('UPDATE #__jem_config SET value = ' . $db->quote((string) $value)
                    . ' WHERE keyname = ' . $db->quote($key))->execute();
            }
            $db->transactionCommit();
        } catch (Throwable $error) {
            $db->transactionRollback();
            throw $error;
        }
        self::cache($settings, $state);
        return true;
    }

    /**
     * Read a nonnegative timestamp without accepting malformed or overflowing state.
     * @param mixed $value Stored bookkeeping value, including legacy empty or null values.
     * @return int Timestamp within the supported calendar range, or zero for invalid state.
     */
    public static function timestamp($value): int
    {
        if (!is_int($value) && !is_string($value)) {
            return 0;
        }
        $digits = ltrim((string) $value, '0');
        $maximum = (string) min(PHP_INT_MAX, 253402300799); // End of year 9999 in UTC.
        if (
            $digits === '' || !ctype_digit($digits) || strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)
        ) {
            return 0;
        }
        return (int) $digits;
    }

    /**
     * Finish only our own successful claim; failures retain the retry delay.
     * @param DatabaseInterface $db Active database.
     * @param object $settings Request-local settings snapshot.
     * @param int $claim Timestamp used for this request's successful claim.
     * @return void
     */
    public static function complete(DatabaseInterface $db, object $settings, int $claim): void
    {
        $db->transactionStart();
        try {
            $owner = $db->setQuery('SELECT value FROM #__jem_config WHERE keyname = '
                . $db->quote('runningupdate') . ' FOR UPDATE')->loadResult();
            if (self::timestamp($owner) !== $claim) {
                $db->transactionRollback();
                return;
            }
            foreach (['lastupdate' => $claim, 'runningupdate' => 0] as $key => $value) {
                $db->setQuery('UPDATE #__jem_config SET value = ' . $db->quote((string) $value)
                    . ' WHERE keyname = ' . $db->quote($key))->execute();
            }
            $db->transactionCommit();
        } catch (Throwable $error) {
            $db->transactionRollback();
            throw $error;
        }
        self::cache($settings, ['lastupdate' => $claim, 'runningupdate' => 0]);
    }

    /**
     * Delete or change expired events, notifying Finder only after each commit.
     * @param DatabaseInterface $db Active database with no surrounding transaction.
     * @param int $mode Existing old-event setting: 1 delete, 2 archive, 3 trash, 4 unpublish.
     * @param string $cutoff Last eligible end date, in Joomla's timezone (Y-m-d).
     * @param bool $hierarchy Whether this JEM version supports parent_event_id.
     * @return bool False when at least one database operation failed and needs retrying.
     */
    public static function run(DatabaseInterface $db, int $mode, string $cutoff, bool $hierarchy): bool
    {
        if (!in_array($mode, [1, 2, 3, 4], true)) {
            return true;
        }
        if (self::inTransaction($db)) {
            throw new LogicException('Automatic cleanup must own its transactions.');
        }
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_jem/tables');
        $where = 'dates > 0 AND ' . $db->quote($cutoff)
            . ' >= (IF (enddates IS NOT NULL, enddates, dates))';
        if ($mode !== 1) {
            $where .= ' AND published = 1';
        }
        $pending = array_map('intval', $db->setQuery('SELECT id FROM #__jem_events WHERE '
            . $where . ' ORDER BY id ASC')->loadColumn());
        $success = true;
        do {
            $blocked = [];
            $progress = false;
            foreach ($pending as $id) {
                $active = false;
                try {
                    $db->transactionStart();
                    $active = true;
                    // Recheck dates/state under lock: a selected event may have been rescheduled.
                    $row = $db->setQuery('SELECT * FROM #__jem_events WHERE id = ' . $id
                        . ' AND ' . $where . ' FOR UPDATE')->loadObject();
                    if (!$row) {
                        $db->transactionRollback();
                        continue;
                    }
                    if ($mode === 1 && $hierarchy && $db->setQuery(
                        'SELECT id FROM #__jem_events WHERE parent_event_id = ' . $id . ' FOR UPDATE'
                    )->loadColumn()) {
                        $blocked[] = $id;
                        $db->transactionRollback();
                        continue;
                    }
                    if ($mode === 1) {
                        $table = Table::getInstance('Event', 'JemTable', ['dbo' => $db]);
                        if (!$table || !$table->load($id)) {
                            throw new RuntimeException('Could not load the expired event.');
                        }
                        $deleted = $hierarchy ? $table->delete($id, [
                            'actorId' => 0, 'source' => 'system.cleanup', 'reasonCode' => 'outdated_event_deleted',
                        ]) : $table->delete($id);
                        if (!$deleted) {
                            throw new RuntimeException('Event table deletion failed.');
                        }
                    } else {
                        $state = [2 => 2, 3 => -2, 4 => 0][$mode];
                        $db->setQuery('UPDATE #__jem_events SET published = ' . $state . ' WHERE id = ' . $id)->execute();
                    }
                    $db->transactionCommit();
                    $active = false;
                    $progress = true;
                } catch (Throwable $error) {
                    if ($active) {
                        $db->transactionRollback();
                    }
                    $success = false;
                    self::warning('Automatic cleanup failed for event ID ' . $id . '; ' . get_class($error));
                    continue;
                }
                // These effects cannot roll back a committed event change.
                if ($mode === 1) {
                    JemDeletedObjectCleanup::flush($db);
                    self::notify('onFinderAfterDelete', ['com_jem.event', $row], $id);
                } else {
                    self::notify('onFinderChangeState', ['com_jem.event', [$id], $state], $id);
                }
            }
            // Children can have any ID; revisit parents after deleting their last expired child.
            $pending = $blocked;
        } while ($pending && $progress);
        return $success;
    }

    /**
     * Deliver the normal Finder lifecycle event without replaying other content plugins.
     * @param string $event Finder event name.
     * @param array $arguments Context and committed record/state.
     * @param int $id Event identity for diagnostic logging.
     * @return void
     */
    private static function notify(string $event, array $arguments, int $id): void
    {
        try {
            if (PluginHelper::importPlugin('finder', 'jem')) {
                $results = Factory::getApplication()->triggerEvent($event, $arguments);
                if (in_array(false, (array) $results, true)) {
                    throw new RuntimeException('Finder rejected the notification.');
                }
            }
        } catch (Throwable $error) {
            self::warning('Smart Search update failed after ' . $event . ' for event ID ' . $id
                . '; rebuild the Smart Search index; ' . get_class($error));
        }
    }

    /**
     * Refresh only bookkeeping values in both configuration caches; never store all settings.
     * @param object $settings Helper snapshot.
     * @param array $values Committed bookkeeping values.
     * @return void
     */
    private static function cache(object $settings, array $values): void
    {
        JemConfig::getInstance()->bind($values);
        foreach ($values as $key => $value) {
            $settings->$key = $value;
        }
    }

    /**
     * Detect a transaction owned by a Joomla caller, including savepoints.
     * @param DatabaseInterface $db Joomla database driver.
     * @return bool Whether automatic cleanup must wait for another request.
     */
    private static function inTransaction(DatabaseInterface $db): bool
    {
        return (int) (new ReflectionProperty($db, 'transactionDepth'))->getValue($db) > 0;
    }

    /**
     * Record cleanup failures without breaking the frontend if logging is unavailable.
     * @param string $message Safe operation and event identifiers, without record contents.
     * @return void
     */
    public static function warning(string $message): void
    {
        try {
            JemHelper::addLogEntry($message, __CLASS__, Log::WARNING);
        } catch (Throwable $ignored) {
            // A logger failure cannot undo a committed cleanup.
        }
    }
}
