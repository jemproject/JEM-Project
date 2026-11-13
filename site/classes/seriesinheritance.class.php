<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\Registry\Registry;

/** Content inheritance is independent of recurrence membership and occurrence identity. */
final class JemSeriesInheritance
{
    public const GROUPS = [
        'title' => ['title'],
        'description' => ['introtext', 'fulltext', 'article_id'],
        'images' => ['datimage', 'fullimage', 'image_path', 'image_metadata', 'fullimage_layout'],
        'categories' => ['cats'],
        'venue' => ['locid', 'venue_allocation_mode', 'venue_profile_id', 'venue_profile_revision', 'venue_snapshot', 'capacity_mode'],
        'times' => ['times', 'endtimes'],
        'online' => ['online_meeting_url', 'online_meeting_label'],
    ];

    /**
     * Read a stored policy, preserving pre-existing custom content when first adopting it.
     *
     * @param array $child Stored occurrence, including category IDs when available.
     * @param array $root Parent before its edit; empty when already migrated.
     * @return array Inheritance flag and explicitly independent field groups.
     */
    public static function policy(array $child, array $root = []): array
    {
        $attribs = new Registry($child['attribs'] ?? []);
        $stored = $attribs->get('series_inheritance');
        if ($stored !== null) {
            $stored = (array) $stored;
            return [
                'inherit' => array_key_exists('inherit', $child) ? (bool) $child['inherit'] : (bool) ($stored['inherit'] ?? true),
                'overrides' => array_values(array_intersect(array_keys(self::GROUPS), (array) ($stored['overrides'] ?? []))),
            ];
        }
        $overrides = [];
        foreach (self::GROUPS as $group => $fields) {
            foreach ($fields as $field) {
                if (array_key_exists($field, $root) && array_key_exists($field, $child)
                    && self::value($field, $child[$field]) !== self::value($field, $root[$field])) {
                    $overrides[] = $group;
                    break;
                }
            }
        }
        return ['inherit' => array_key_exists('inherit', $child) ? (bool) $child['inherit'] : true, 'overrides' => $overrides];
    }

    /**
     * Normalize values for comparisons, without rewriting the stored field types.
     *
     * @param string $field Event field name.
     * @param mixed $value Submitted or stored value.
     * @return mixed Comparable value.
     */
    private static function value(string $field, $value)
    {
        if ($field === 'cats') {
            $ids = array_unique(array_map('intval', is_array($value) ? $value : explode(',', (string) $value)));
            sort($ids);
            return $ids;
        }
        if (in_array($field, ['times', 'endtimes'], true)) {
            return substr((string) $value, 0, 5);
        }
        if (in_array($field, ['image_metadata', 'venue_snapshot'], true)) {
            return json_encode((new Registry($value ?? []))->toArray(), JSON_THROW_ON_ERROR);
        }
        return is_array($value) || is_object($value)
            ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;
    }

    /**
     * Apply parent content to a complete occurrence, never its identity or dates.
     *
     * @param array $child Complete stored occurrence.
     * @param array $root Stored parent before the edit.
     * @param array $changes Whitelisted content changes from the parent.
     * @param bool $usePolicy Respect the 5.1 inheritance controls.
     * @return array Complete occurrence ready for normal validation and save.
     */
    public static function propagate(array $child, array $root, array $changes, bool $usePolicy = true): array
    {
        $policy = self::policy($child, $root);
        if ($usePolicy && !$policy['inherit']) {
            return $child;
        }
        $protected = ['id', 'alias', 'dates', 'enddates', 'times', 'endtimes', 'created', 'created_by',
            'author_ip', 'version', 'modified', 'modified_by', 'checked_out', 'checked_out_time',
            'recurrence_first_id', 'recurrence_type', 'recurrence_counter', 'series_id', 'series_order',
            'parent_event_id', 'event_tree_order', 'inherit', 'attribs'];
        if ($usePolicy) {
            foreach ($policy['overrides'] as $group) {
                $protected = array_merge($protected, self::GROUPS[$group]);
            }
        }
        foreach ($changes as $field => $value) {
            if (!in_array($field, $protected, true) && array_key_exists($field, $child)) {
                $child[$field] = $value;
            }
        }
        if ($usePolicy) {
            $attribs = new Registry($child['attribs'] ?? []);
            $attribs->set('series_inheritance', ['overrides' => $policy['overrides']]);
            // Source article and its read/write policy form one description setting.
            if (!in_array('description', $policy['overrides'], true) && isset($changes['article_id'])) {
                $source = new Registry($changes['attribs'] ?? $root['attribs'] ?? []);
                $attribs->set('article_usage', $source->get('article_usage', 'information'));
                $attribs->set('article_sync_mode', 'none');
            }
            $child['attribs'] = $attribs->toArray();
        }
        return $child;
    }

    /**
     * Save checkbox choices and detect edited groups, retaining a child's membership.
     * Unchecking an existing override explicitly restores that group from the parent.
     *
     * @param array $submitted Event form values, normalized before validation.
     * @param array $stored Stored child with category IDs.
     * @param array $root Current parent with category IDs.
     * @return array Event form with protected policy metadata and restored groups.
     */
    public static function submission(array $submitted, array $stored, array $root): array
    {
        $previous = self::policy($stored, $root);
        $policy = $previous;
        $explicit = !empty($submitted['series_inheritance_present']);
        if ($explicit) {
            $policy['inherit'] = !empty($submitted['series_inherit']);
            $policy['overrides'] = array_values(array_diff(array_keys(self::GROUPS), (array) ($submitted['series_inherit_fields'] ?? [])));
        }
        foreach (self::GROUPS as $group => $fields) {
            $restore = $explicit && $policy['inherit'] && !in_array($group, $policy['overrides'], true)
                && (!$previous['inherit'] || in_array($group, $previous['overrides'], true));
            foreach ($fields as $field) {
                if ($restore && array_key_exists($field, $root)) {
                    $submitted[$field] = $root[$field];
                } elseif (array_key_exists($field, $submitted) && array_key_exists($field, $stored)
                    && self::value($field, $submitted[$field]) !== self::value($field, $stored[$field])) {
                    $policy['overrides'][] = $group;
                }
            }
            if ($restore && $group === 'description') {
                $submitted['articletext'] = ($root['introtext'] ?? '')
                    . (!empty($root['fulltext']) ? '<hr id="system-readmore" />' . $root['fulltext'] : '');
                $source = new Registry($root['attribs'] ?? []);
                $attributes = new Registry($submitted['attribs'] ?? []);
                $attributes->set('article_usage', $source->get('article_usage', 'information'));
                $attributes->set('article_sync_mode', 'none');
                $submitted['attribs'] = $attributes->toArray();
            }
        }
        $policy['overrides'] = array_values(array_unique($policy['overrides']));
        $attribs = new Registry($submitted['attribs'] ?? []);
        $attribs->set('series_inheritance', ['overrides' => $policy['overrides']]);
        $submitted['attribs'] = $attribs->toArray();
        $submitted['inherit'] = (int) $policy['inherit'];
        unset($submitted['series_inheritance_present'], $submitted['series_inherit'], $submitted['series_inherit_fields']);
        return $submitted;
    }
}
