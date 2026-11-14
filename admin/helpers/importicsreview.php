<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** Review external calendar changes without guessing identities or overwriting local edits. */
final class JemImportIcsReview
{
    public const FIELDS = ['title', 'dates', 'enddates', 'times', 'endtimes', 'introtext', 'fulltext', 'event_status'];

    /**
     * Attach source identity, revision and a baseline to a concrete import row.
     *
     * @param array $record Normalised numeric event row.
     * @param array $event Expanded VEVENT.
     * @param string $source Administrator's stable calendar reference, independent of upload filenames.
     * @return array Row with provenance stored in its existing metadata field.
     */
    public static function track(array $record, array $event, string $source): array
    {
        $uid = JemImportSecurityHelper::sanitiseValue('uid', trim((string) ($event['UID'][0]['value'] ?? '')), 'events', (int) ($record['_jem_source_line'] ?? 0));
        if ($uid === '') {
            return $record;
        }
        $metadata = self::metadata($record[7] ?? '');
        $metadata['jem_ics_import'] = [
            'source' => $source === '' ? '' : hash('sha256', $source),
            'family' => hash('sha256', $uid . "\n"),
            'slot' => (string) ($event['_jem_ics_slot'] ?? $event['_jem_ics_identity'] ?? ''),
            'rule' => (string) ($event['_jem_ics_rule'] ?? ''),
            'detached' => isset($event['RECURRENCE-ID']) && empty($event['_jem_ics_rule']),
            'sequence' => (int) ($event['SEQUENCE'][0]['value'] ?? 0),
            'stamp' => (string) ($event['DTSTAMP'][0]['value'] ?? ''),
            'baseline' => self::baseline(self::incoming($record)),
        ];
        $record[7] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strtoupper((string) ($event['STATUS'][0]['value'] ?? '')) === 'CANCELLED') {
            $record['_jem_ics_cancelled'] = true;
            $record['_jem_ics_whole_calendar_event'] = !isset($event['RECURRENCE-ID']);
        }
        return $record;
    }

    /** @param mixed $json Existing event metadata. @return array Decoded metadata, never an object. */
    public static function metadata($json): array
    {
        $data = is_array($json) ? $json : json_decode((string) $json, true);
        return is_array($data) ? $data : [];
    }

    /** @param array $record Numeric import row. @return array Only fields managed by the external calendar. */
    public static function incoming(array $record): array
    {
        return array_combine(self::FIELDS, array_merge(array_slice($record, 0, 7), ['scheduled']));
    }

    /** @param array $event Event data. @return array Canonical managed values for exact comparisons. */
    public static function values(array $event): array
    {
        $result = [];
        foreach (self::FIELDS as $field) {
            $result[$field] = (string) ($event[$field] ?? ($field === 'event_status' ? 'scheduled' : ''));
        }
        return $result;
    }

    /** @param array $event Stored event. @return string Optimistic review fingerprint, rechecked under a row lock. */
    public static function fingerprint(array $event): string
    {
        return hash('sha256', json_encode([self::values($event), (string) ($event['metadata'] ?? ''),
            (string) ($event['modified'] ?? ''), (int) ($event['version'] ?? 0)], JSON_THROW_ON_ERROR));
    }

    /** @param array $event Managed field values. @return string Compact baseline, without duplicating descriptions in metadata. */
    public static function baseline(array $event): string
    {
        return hash('sha256', json_encode(self::values($event), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Index existing imported events, retaining duplicate identities as conflicts.
     *
     * @param array $events Stored rows.
     * @return array Lookup by hash, family and original recurrence slot.
     */
    public static function index(array $events): array
    {
        $index = ['hash' => [], 'family' => [], 'slot' => []];
        foreach ($events as $event) {
            $meta = self::metadata($event['metadata'] ?? '');
            $hash = (string) ($meta['jem_ics_uid_hash'] ?? '');
            if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                continue;
            }
            $index['hash'][$hash][] = $event;
            $tracking = (array) ($meta['jem_ics_import'] ?? []);
            if (!empty($tracking['family'])) {
                $index['family'][$tracking['family']][] = $event;
                $index['slot'][$tracking['family'] . ':' . ($tracking['slot'] ?? '')][] = $event;
            }
        }
        return $index;
    }

    /**
     * Match exact original slots; a master cancellation proposes each known occurrence separately.
     *
     * @param array $record Incoming row, including cancellation markers.
     * @param array $index Existing identity index.
     * @return array Candidate stored rows; empty means no known target.
     */
    public static function targets(array $record, array $index): array
    {
        $meta = self::metadata($record[7] ?? '');
        $tracking = (array) ($meta['jem_ics_import'] ?? []);
        $family = (string) ($tracking['family'] ?? '');
        if (!empty($record['_jem_ics_whole_calendar_event']) && !empty($index['family'][$family])) {
            return $index['family'][$family];
        }
        $hash = (string) ($meta['jem_ics_uid_hash'] ?? '');
        return $index['hash'][$hash] ?? $index['slot'][$family . ':' . ($tracking['slot'] ?? '')] ?? [];
    }

    /**
     * Classify a row for preview and recheck the same policy at commit.
     *
     * @param array $record Incoming row.
     * @param array|null $stored Exact stored target, if any.
     * @param array $family Known occurrences for this UID.
     * @return array Action, explanation key, differences and review fingerprint.
     */
    public static function review(array $record, ?array $stored, array $family = []): array
    {
        $meta = self::metadata($record[7] ?? '');
        $next = (array) ($meta['jem_ics_import'] ?? []);
        $cancelled = !empty($record['_jem_ics_cancelled']);
        $result = ['action' => 'new', 'reason' => 'NEW', 'id' => (int) ($stored['id'] ?? 0), 'changes' => [], 'eligible' => false];
        if ($stored === null && $cancelled) {
            return array_replace($result, ['action' => 'ignored', 'reason' => 'UNKNOWN_CANCELLATION']);
        }
        // A changed recurrence rule cannot be paired by row number or by its new dates.
        if (!$cancelled && empty($next['detached'])) {
            foreach ($family as $relative) {
                $old = (array) (self::metadata($relative['metadata'] ?? '')['jem_ics_import'] ?? []);
                if ($stored === null && !empty($old['source']) && ($next['source'] ?? '') !== $old['source']) {
                    return array_replace($result, ['action' => 'conflict', 'reason' => 'SOURCE']);
                }
                if (($next['rule'] ?? '') !== ($old['rule'] ?? '') && (!empty($next['rule']) || !empty($old['rule']))) {
                    return array_replace($result, ['action' => 'conflict', 'reason' => 'SERIES']);
                }
            }
        }
        if ($stored === null) {
            return $result;
        }
        $oldMeta = self::metadata($stored['metadata'] ?? '');
        $previous = (array) ($oldMeta['jem_ics_import'] ?? []);
        $current = self::values($stored);
        $incoming = $cancelled ? array_replace($current, ['event_status' => 'cancelled']) : self::values(self::incoming($record));
        foreach ($incoming as $field => $value) {
            if ($value !== $current[$field]) {
                $result['changes'][$field] = [$current[$field], $value];
            }
        }
        $result['fingerprint'] = self::fingerprint($stored);
        if (!$result['changes']) {
            return array_replace($result, ['action' => 'unchanged', 'reason' => 'UNCHANGED']);
        }
        $result['action'] = $cancelled ? 'cancelled' : 'changed';
        $result['reason'] = $cancelled ? 'CANCELLATION' : 'CHANGED';
        if (empty($previous['source']) || empty($next['source']) || !isset($previous['baseline'])) {
            $result['reason'] = 'LEGACY';
        } elseif (!hash_equals($previous['source'], $next['source'])) {
            $result['reason'] = 'SOURCE';
        } elseif (!hash_equals((string) $previous['baseline'], self::baseline($current))) {
            $result['reason'] = 'LOCAL';
        } elseif ([(int) ($next['sequence'] ?? 0), (string) ($next['stamp'] ?? '')]
            < [(int) ($previous['sequence'] ?? 0), (string) ($previous['stamp'] ?? '')]) {
            $result['reason'] = 'STALE';
        } elseif (!empty($stored['recurrence_type']) || !empty($stored['recurrence_first_id']) || !empty($stored['series_id'])
            || !empty($stored['article_id']) || !empty(self::metadata($stored['attribs'] ?? '')['event_schedule'])
            || !in_array((string) ($stored['pricing_mode'] ?? 'classic'), ['', 'classic'], true)
            || !in_array((string) ($stored['capacity_mode'] ?? 'classic'), ['', 'classic'], true)
            || !in_array((string) ($stored['venue_allocation_mode'] ?? 'none'), ['', 'none'], true)
            || !empty($stored['venue_space_id']) || !empty($stored['venue_profile_id'])
            || ($stored['timezone_mode'] ?? 'site') === 'custom') {
            $result['reason'] = 'LOCAL_MANAGEMENT';
        } else {
            $result['eligible'] = true;
        }
        return $result;
    }

    /**
     * Enrich preview rows and records with a bounded, private review payload.
     *
     * @param array $preview Parsed preview.
     * @param array $index Current imported events.
     * @param bool $allowUpdates Whether this version offers reviewed updates.
     * @param callable|null $canReview Optional actor check; denied targets expose no stored content.
     * @return array Preview with changes visible even when no new events can be added.
     */
    public static function preview(array $preview, array $index, bool $allowUpdates = false, ?callable $canReview = null): array
    {
        $rows = [];
        $records = [];
        $new = 0;
        foreach ($preview['records'] ?? [] as $record) {
            $line = (int) ($record['_jem_source_line'] ?? 0);
            $row = $preview['rows'][$record['_jem_preview_row'] ?? ($line - 1)] ?? ['notes' => []];
            $meta = self::metadata($record[7] ?? '');
            $family = (string) ($meta['jem_ics_import']['family'] ?? '');
            $targets = self::targets($record, $index);
            $identities = array_map(static fn (array $target): string => (string) (self::metadata($target['metadata'] ?? '')['jem_ics_uid_hash'] ?? ''), $targets);
            $ambiguous = count($targets) > 1 && (empty($record['_jem_ics_whole_calendar_event']) || count(array_unique($identities)) !== count($targets));
            if ($ambiguous) {
                $targets = [null];
            }
            foreach ($targets ?: [null] as $stored) {
                if ($stored && !empty($record['_jem_ics_whole_calendar_event']) && !empty($preview['ics_from'])
                    && ($stored['dates'] < $preview['ics_from'] || $stored['dates'] > $preview['ics_to'])) {
                    continue;
                }
                $review = $ambiguous
                    ? ['action' => 'conflict', 'reason' => 'DUPLICATE', 'id' => 0, 'changes' => [], 'eligible' => false]
                    : self::review($record, $stored, $index['family'][$family] ?? $index['hash'][$family] ?? []);
                $visible = !$stored || $canReview === null || $canReview($stored, $review);
                if (!$visible) {
                    $review = array_replace($review, ['action' => 'conflict', 'reason' => 'PERMISSION', 'changes' => [], 'eligible' => false]);
                }
                $review['eligible'] = $allowUpdates && $review['eligible'];
                $copy = $record;
                $copy['_jem_ics_review'] = $review;
                $copy['_jem_ics_selection'] = count($records);
                $display = $row;
                $display['status'] = Text::_('COM_JEM_IMPORT_ICS_REVIEW_' . strtoupper($review['action']));
                $display['notes'][] = Text::_('COM_JEM_IMPORT_ICS_REVIEW_' . $review['reason'] . '_DESC');
                if ($stored && $visible) {
                    $display['notes'][] = 'Event ID ' . (int) $stored['id'];
                    $display['title'] = $stored['title'];
                }
                foreach ($review['changes'] as $field => [$before, $after]) {
                    $display['notes'][] = $field . ': ' . $before . ' → ' . $after;
                }
                $display['ics_review'] = $review;
                $display['ics_selection'] = $copy['_jem_ics_selection'];
                $display['import_data'] = array_combine(array_slice($preview['record_fields'], 0, 14), array_slice($record, 0, 14));
                if ($stored && $visible && !empty($record['_jem_ics_cancelled'])) {
                    $display['import_data'] = array_replace($display['import_data'], self::values($stored));
                }
                $rows[] = $display;
                $records[] = $copy;
                $new += $review['action'] === 'new' ? 1 : 0;
                JemImportBudgetHelper::assertRecordCount(count($records));
            }
        }
        // Validation failures still need to be visible, but must never become update candidates.
        foreach ($preview['rows'] ?? [] as $row) {
            if (!empty($row['_jem_ics_invalid'])) {
                $rows[] = $row;
            }
        }
        $preview['rows'] = $rows;
        $preview['records'] = $records;
        $preview['valid_count'] = $new;
        $preview['ics_review'] = true;
        $preview['ics_updates'] = $allowUpdates;
        $preview['summary'] = Text::sprintf('COM_JEM_IMPORT_EXTERNAL_PREVIEW_SUMMARY', $new, $preview['error_count'])
            . ' ' . Text::_('COM_JEM_IMPORT_ICS_REVIEW_POLICY');
        return $preview;
    }
}
