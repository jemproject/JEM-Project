<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

require_once __DIR__ . '/seriesinheritance.class.php';

/** Explicit decisions for replacing an arithmetic recurrence, shared by both editors. */
final class JemRecurrenceChange
{
    /**
     * Compare effective scheduling settings, ignoring counters and unrelated content.
     *
     * @param array $event Stored or normalised submitted event.
     * @return array Canonical rule used by preview and save.
     */
    public static function rule(array $event): array
    {
        $type = (int) ($event['recurrence_type'] ?? 0);
        $rule = ['type' => $type];
        if ($type < 1 || $type > 6) {
            return $rule;
        }
        foreach (['dates', 'enddates', 'recurrence_limit_date'] as $key) {
            $value = substr((string) ($event[$key] ?? ''), 0, 10);
            $rule[$key] = $value === '0000-00-00' ? '' : $value;
        }
        foreach (['times', 'endtimes'] as $key) {
            $value = (string) ($event[$key] ?? '');
            $rule[$key] = strlen($value) === 5 ? $value . ':00' : $value;
        }
        $rule['number'] = (int) ($event['recurrence_number'] ?? 0);
        if (in_array($type, [2, 4], true)) {
            $days = array_filter(explode(',', (string) ($event['recurrence_byday'] ?? '')), 'strlen');
            $days = array_unique(array_map('trim', $days));
            sort($days);
            $rule['days'] = $days;
        }
        if ($type === 6) {
            $rule['lastday'] = (string) ($event['recurrence_bylastday'] ?? '');
        }
        $attribs = new Registry($event['attribs'] ?? []);
        $exclusions = (array) $attribs->get('recurrence_exclusions', []);
        sort($exclusions);
        $rule['exclusions'] = $exclusions;
        $rule['timezone_mode'] = (string) ($event['timezone_mode'] ?? '');
        $rule['timezone'] = (string) ($event['timezone'] ?? '');

        return $rule;
    }

    /**
     * Read the exact affected set; a child never owns its siblings.
     *
     * @param object $db Joomla database driver.
     * @param int $id Edited event ID.
     * @param bool $lock Lock rows in the caller's transaction during save.
     * @return array Root, children and registration rows for stale-preview detection.
     */
    public static function snapshot($db, int $id, bool $lock = false): array
    {
        $suffix = $lock ? ' FOR UPDATE' : '';
        $db->setQuery('SELECT * FROM #__jem_events WHERE id = ' . $id . $suffix);
        $root = $db->loadAssoc();
        if (!$root) {
            throw new RuntimeException(Text::_('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'));
        }
        if (!empty($root['series_id'])) {
            $seriesRoot = (int) $db->setQuery('SELECT root_event_id FROM #__jem_event_series WHERE id = '
                . (int) $root['series_id'])->loadResult();
            $root['series_parent_id'] = $seriesRoot !== $id ? $seriesRoot : 0;
            $root['recurrence_type'] = 7; // Custom dates are stored as type 0 but edited as mode 7.
        }
        $children = [];
        if (!(int) $root['recurrence_first_id']) {
            $db->setQuery('SELECT * FROM #__jem_events WHERE recurrence_first_id = ' . $id . ' ORDER BY id' . $suffix);
            $children = $db->loadAssocList();
        }
        $registrations = [];
        if ($children) {
            $ids = implode(',', array_map('intval', array_column($children, 'id')));
            $db->setQuery('SELECT * FROM #__jem_register WHERE event IN (' . $ids . ') ORDER BY id' . $suffix);
            $registrations = $db->loadAssocList();
        }

        return ['root' => $root, 'children' => $children, 'registrations' => $registrations];
    }

    /**
     * Build a confirmation tied to the stored series and the requested rule.
     *
     * @param array $snapshot Current database snapshot.
     * @param array $candidate Normalised proposed event.
     * @param callable $can Callable accepting an operation and stored child row.
     * @param string $today Current Joomla calendar date.
     * @return array Preview; an unchanged rule needs no confirmation.
     */
    public static function plan(array $snapshot, array $candidate, callable $can, string $today): array
    {
        $root = $snapshot['root'];
        $oldType = (int) $root['recurrence_type'];
        $child = (int) $root['recurrence_first_id'] > 0 || !empty($root['series_parent_id']);
        $oldRule = self::rule($root);
        $newRule = self::rule($candidate);
        if ($child) {
            // Individual session hours are a content override, not a replacement series.
            unset($oldRule['times'], $oldRule['endtimes'], $newRule['times'], $newRule['endtimes']);
        }
        $required = ($oldType >= 1 && $oldType <= 6 || $child || $snapshot['children']) && $oldRule !== $newRule;
        if ($required && $child && JemSeriesInheritance::policy($candidate)['inherit']) {
            throw new RuntimeException(Text::_('COM_JEM_SERIES_INHERIT_RULE_LOCKED'));
        }
        if (!$required) {
            return ['required' => false];
        }
        $past = 0;
        $keepAllowed = true;
        $deleteAllowed = true;
        foreach ($snapshot['children'] as $child) {
            $past += !empty($child['dates']) && ($child['enddates'] ?: $child['dates']) < $today ? 1 : 0;
            $keepAllowed = $can('edit', $child) && $keepAllowed;
            $deleteAllowed = $can('delete', $child) && $deleteAllowed;
        }
        $active = count(array_filter($snapshot['registrations'], static function (array $row): bool {
            // Only an explicitly cancelled registration is safe to remove.
            // Includes confirmed, waiting, invited/unanswered and unknown states.
            return (string) ($row['status'] ?? '') !== '-1';
        }));
        $blocked = [];
        if ($active) {
            $blocked[] = Text::_('COM_JEM_RECURRENCE_CHANGE_ACTIVE');
        }
        if (!$keepAllowed && !$deleteAllowed) {
            $blocked[] = Text::_('COM_JEM_RECURRENCE_CHANGE_PERMISSION');
        } elseif (!$deleteAllowed) {
            $blocked[] = Text::_('COM_JEM_RECURRENCE_CHANGE_KEEP_ONLY');
        } elseif (!$keepAllowed) {
            $blocked[] = Text::_('COM_JEM_RECURRENCE_CHANGE_KEEP_PERMISSION');
        }
        $count = count($snapshot['children']);
        return [
            'required' => true,
            'fingerprint' => hash('sha256', json_encode([$snapshot, self::rule($candidate)], JSON_THROW_ON_ERROR)),
            'child' => $child,
            'keepLabel' => Text::_($child ? 'COM_JEM_RECURRENCE_CHANGE_CHILD_KEEP' : 'COM_JEM_RECURRENCE_CHANGE_KEEP'),
            'count' => $count,
            'past' => $past,
            'future' => $count - $past,
            'active' => $active,
            'keepAllowed' => $keepAllowed,
            'deleteAllowed' => $count > 0 && $deleteAllowed && !$active,
            'message' => $child ? Text::_('COM_JEM_RECURRENCE_CHANGE_CHILD')
                : Text::sprintf('COM_JEM_RECURRENCE_CHANGE_ROOT', $count, $past, $count - $past),
            'blocked' => implode(' ', $blocked),
        ];
    }

    /**
     * Reject missing, stale or unauthorised decisions before any event mutation.
     *
     * @param array $plan Fresh plan computed under row locks.
     * @param string $choice Explicit keep/delete decision.
     * @param string $fingerprint Preview fingerprint returned to this editor.
     * @return void
     * @throws RuntimeException When the decision cannot be applied safely.
     */
    public static function assertDecision(array $plan, string $choice, string $fingerprint): void
    {
        if (!$plan['required']) {
            return;
        }
        if (!hash_equals($plan['fingerprint'], $fingerprint) || !in_array($choice, ['keep', 'delete'], true)) {
            throw new RuntimeException(Text::_('COM_JEM_RECURRENCE_CHANGE_REVIEW'));
        }
        if ($choice === 'delete' && !$plan['deleteAllowed']) {
            throw new RuntimeException($plan['blocked'] ?: Text::_('COM_JEM_RECURRENCE_CHANGE_PERMISSION'));
        }
        if ($choice === 'keep' && !$plan['keepAllowed']) {
            throw new RuntimeException(Text::_('COM_JEM_RECURRENCE_CHANGE_PERMISSION'));
        }
    }
}
