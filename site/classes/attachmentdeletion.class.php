<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;

/**
 * Confirm record deletion before performing irreversible attachment cleanup.
 */
final class JemAttachmentDeletion
{
    /**
     * Delete pre-authorized records, then clean up only their unreferenced files.
     * Callbacks retain their existing ACL and plugin checks. This is a top-level
     * operation: an outer transaction cannot safely promise permanent file removal.
     *
     * @param DatabaseInterface $db            Database used by the record writer.
     * @param array             $attachments  ID-keyed object/file/path snapshots.
     * @param callable          $deleteRows   Existing deletion workflow, returning bool.
     * @return object Confirmed IDs, removed-file count, retained/pending IDs and error key.
     */
    public static function execute(DatabaseInterface $db, array $attachments, callable $deleteRows): object
    {
        $result = (object) [
            'success' => false, 'deleted' => [], 'files' => 0,
            'retained' => [], 'pending' => [], 'error' => 'COM_JEM_ATTACHMENT_DELETE_FAILED',
        ];
        if (!$attachments) {
            return $result;
        }

        $ids = array_map('intval', array_keys($attachments));
        $started = false;
        $phase = 'transaction-start';
        try {
            if (self::transactionDepth($db) !== 0) {
                $result->error = 'COM_JEM_ATTACHMENT_DELETE_TRANSACTION_ACTIVE';
                return $result;
            }

            $db->transactionStart();
            $started = true;
            if (self::transactionDepth($db) !== 1) {
                throw new RuntimeException('Attachment deletion requires its own transaction.');
            }

            $phase = 'records';
            if (!$deleteRows()) {
                throw new RuntimeException('Attachment record deletion was rejected.');
            }

            // AdminModel may return early when a record disappears during a batch.
            $remaining = $db->getQuery(true)->select('COUNT(*)')
                ->from($db->quoteName('#__jem_attachments'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
            $count = $db->setQuery($remaining)->loadResult();
            if (!in_array($count, [0, '0'], true) || self::transactionDepth($db) !== 1) {
                throw new RuntimeException('Attachment deletion was not completed.');
            }

            $phase = 'commit';
            $db->transactionCommit();
            if (self::transactionDepth($db) !== 0) {
                throw new RuntimeException('Attachment deletion commit was not confirmed.');
            }
            $started = false;
            $result->success = true;
            $result->deleted = $ids;
            $result->error = '';
        } catch (Throwable $e) {
            if ($started) {
                try {
                    $db->transactionRollback();
                } catch (Throwable $rollbackError) {
                    self::logFailure($ids, 'rollback', $rollbackError);
                }
            }
            self::logFailure($ids, $phase, $e);
            return $result;
        }

        // Filesystem cleanup is not part of the database transaction. Any failure
        // here must keep the remaining bytes and report a partial result.
        foreach ($attachments as $id => $attachment) {
            try {
                $references = $db->getQuery(true)->select('COUNT(*)')
                    ->from($db->quoteName('#__jem_attachments'))
                    ->where('(' . $db->quoteName('object') . ' = ' . $db->quote($attachment->object)
                        . ' AND ' . $db->quoteName('file') . ' = ' . $db->quote($attachment->file) . ')'
                        . ' OR ' . $db->quoteName('id') . ' = ' . (int) $id);
                $count = $db->setQuery($references)->loadResult();
                if (!ctype_digit((string) $count) || self::transactionDepth($db) !== 0) {
                    throw new RuntimeException('Attachment references could not be confirmed.');
                }
                if ((int) $count > 0) {
                    $result->retained[] = (int) $id;
                    continue;
                }
                if (!$attachment->path) {
                    throw new RuntimeException('Attachment path is not safe.');
                }
                if (is_file($attachment->path)) {
                    if (!File::delete($attachment->path)) {
                        throw new RuntimeException('Attachment file cleanup failed.');
                    }
                    $result->files++;
                }
            } catch (Throwable $e) {
                $result->pending[] = (int) $id;
                self::logFailure([(int) $id], 'file-cleanup', $e);
            }
        }

        return $result;
    }

    /**
     * Queue partial-cleanup notices without changing a successful record deletion.
     *
     * @param object $result  Confirmed deletion result.
     * @param bool   $persist Preserve messages for the next page after a raw AJAX response.
     * @return void
     */
    public static function notifyCleanup(object $result, bool $persist = false): void
    {
        foreach (['pending' => 'COM_JEM_ATTACHMENT_CLEANUP_PENDING', 'retained' => 'COM_JEM_ATTACHMENT_FILES_IN_USE'] as $field => $key) {
            if (!$result->$field) {
                continue;
            }
            try {
                $app = Factory::getApplication();
                $app->enqueueMessage(
                    Text::sprintf($key, implode(', ', array_map('intval', $result->$field))),
                    'warning'
                );
                if ($persist) {
                    // Raw AJAX callers exit without Joomla's usual redirect queue transfer.
                    $app->getSession()->set('application.queue', $app->getMessageQueue());
                }
            } catch (Throwable $e) {
                self::logFailure($result->$field, 'cleanup-notice', $e);
            }
        }
    }

    /**
     * Log IDs and failure stage without exposing file paths, SQL or credentials.
     *
     * @param array     $ids   Attachment IDs involved.
     * @param string    $phase Internal failure stage.
     * @param Throwable $error Error whose type is recorded, not its sensitive message.
     * @return void
     */
    public static function logFailure(array $ids, string $phase, Throwable $error): void
    {
        try {
            JemHelper::addLogEntry(
                'Attachment deletion; ids=' . implode(',', array_map('intval', $ids))
                    . '; stage=' . $phase . '; error=' . get_class($error),
                __METHOD__,
                Log::WARNING
            );
        } catch (Throwable $loggingError) {
            // Optional logging cannot change an already committed deletion.
        }
    }

    /**
     * Read Joomla's transaction nesting without starting or committing a caller's work.
     * Joomla Database 2/3 has no public depth accessor. Fail closed if its protected
     * depth contract changes; do not guess that a savepoint release is a commit.
     *
     * @param DatabaseInterface $db Active Joomla driver.
     * @return int Current Joomla transaction nesting level.
     */
    private static function transactionDepth(DatabaseInterface $db): int
    {
        $property = new ReflectionProperty($db, 'transactionDepth');
        $depth = (int) $property->getValue($db);
        $connection = $db->getConnection();
        if ($connection instanceof PDO && $connection->inTransaction() && $depth === 0) {
            return 1;
        }

        return $depth;
    }
}
