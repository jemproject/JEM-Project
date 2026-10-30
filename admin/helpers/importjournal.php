<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Buffer row outcomes until the caller knows whether its transaction committed. */
final class JemImportJournal
{
    private array $entries = array();
    private array $current = array();

    /**
     * Remember the row being processed, including failures before persistence.
     *
     * @param string $entity Content kind.
     * @param int|null $line Source row/record number when available.
     * @param int $sourceId Source ID, distinct from a newly allocated local ID.
     * @return void
     */
    public function begin(string $entity, ?int $line, int $sourceId): void
    {
        $this->current = array('entity' => $entity, 'line' => $line, 'source_id' => $sourceId);
    }

    /**
     * Buffer a row outcome without claiming that its transaction has committed.
     *
     * @param string $outcome Added, updated, ignored, warning or error.
     * @param int $localId Stored or attempted local record ID.
     * @param string $reason Diagnostic explanation without the complete input row.
     * @return void
     */
    public function record(string $outcome, int $localId = 0, string $reason = ''): void
    {
        $this->entries[] = $this->current + array('outcome' => $outcome, 'id' => $localId, 'reason' => $reason);
        if ($outcome !== 'warning') {
            $this->current = array();
        }
    }

    /**
     * Return and clear buffered outcomes, including an interrupted current row.
     *
     * @param Throwable|null $failure Optional exception interrupting the operation.
     * @return array<array> Row outcomes for the caller's committed/rollback context.
     */
    public function drain(?Throwable $failure = null): array
    {
        if ($failure) {
            $this->record('error', 0, $failure->getMessage());
        }
        $entries = $this->entries;
        $this->entries = $this->current = array();

        return $entries;
    }

    /**
     * Format an outcome with local/source identity and the final rollback state.
     *
     * @param array $entry Buffered row outcome.
     * @param bool $rolledBack Whether the transaction was reverted.
     * @return array{string, string} Message and info/warning/error severity.
     */
    public static function format(array $entry, bool $rolledBack): array
    {
        $outcome = (string) $entry['outcome'];
        $success = in_array($outcome, array('added', 'updated'), true);
        $status = $rolledBack && $success ? 'ROLLED_BACK_' . strtoupper($outcome) : strtoupper($outcome);
        $level = $outcome === 'error' ? 'error' : ($rolledBack || !$success ? 'warning' : 'info');
        $message = 'entity=' . self::clean((string) ($entry['entity'] ?? 'import'))
            . ' row=' . ((int) ($entry['line'] ?? 0) ?: '-')
            . ' source_id=' . ((int) ($entry['source_id'] ?? 0) ?: '-')
            . ' id=' . ((int) ($entry['id'] ?? 0) ?: '-')
            . ' outcome=' . $status;
        if (!empty($entry['reason'])) {
            $message .= ' reason=' . self::clean((string) $entry['reason']);
        }

        return array($message, $level);
    }

    /**
     * Bound log messages and remove control characters and common credential values.
     *
     * @param string $message Raw diagnostic message, never a complete source record.
     * @return string Single-line UTF-8 text suitable for the import log.
     */
    public static function clean(string $message): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', strip_tags($message));
        $message = preg_replace('/\b(password|passwd|token|secret|api[_-]?key)\s*[=:]\s*[^\s;&]+/i', '$1=[redacted]', $message);

        return mb_strcut(trim($message), 0, 2000, 'UTF-8');
    }
}
