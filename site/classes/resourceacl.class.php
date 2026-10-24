<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Pure three-state Joomla ACL policy for frontend Event and Venue operations.
 */
final class JemResourceAclPolicy
{
    private const ACTIONS = array(
        'event' => array(
            'add'      => 'jem.events.create',
            'edit'     => 'jem.events.edit',
            'edit.own' => 'jem.events.edit.own',
            'publish'  => 'jem.events.edit.state',
            'delete'   => 'jem.events.delete',
        ),
        'venue' => array(
            'add'      => 'jem.venues.create',
            'edit'     => 'jem.venues.edit',
            'edit.own' => 'jem.venues.edit.own',
            'publish'  => 'jem.venues.edit.state',
            'delete'   => 'jem.venues.delete',
        ),
    );

    public static function getAction($type, $operation)
    {
        return self::ACTIONS[$type][$operation] ?? null;
    }

    /**
     * Return true (Allow), false (explicit/inherited Deny), or null (Not Set).
     */
    public static function decide($type, $operation, array $categoryIds, callable $ruleDecision)
    {
        $action = self::getAction($type, $operation);

        if ($action === null) {
            return false;
        }

        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        $assets = $type === 'event' && $categoryIds
            ? array_map(static function ($categoryId) { return 'com_jem.category.' . $categoryId; }, $categoryIds)
            : array('com_jem');
        $hasUnset = false;

        foreach ($assets as $asset) {
            $decision = $ruleDecision($action, $asset);

            if ($decision === false) {
                return false;
            }

            if ($decision !== true) {
                $hasUnset = true;
            }
        }

        return $hasUnset ? null : true;
    }

    /**
     * Apply a legacy JEM Group grant only where category Event ACL is unused.
     * Every category must allow the same requested operation.
     */
    public static function allowsLegacyEventCategories(
        $operation,
        array $categories,
        array $groupIds,
        array $viewLevels,
        callable $resourceDecision,
        callable $hasCategoryRules
    ) {
        if (!$categories || !$groupIds) {
            return false;
        }

        $groupIds = array_map('intval', $groupIds);
        $viewLevels = array_map('intval', $viewLevels);

        foreach ($categories as $category) {
            $categoryId = (int) ($category->id ?? 0);

            if ($categoryId < 1 || (int) ($category->published ?? 0) !== 1
                || !in_array((int) ($category->access ?? 0), $viewLevels, true)) {
                return false;
            }

            $decision = $resourceDecision($operation, $categoryId);

            if ($decision === true) {
                continue;
            }

            if ($decision === false || $hasCategoryRules($categoryId)) {
                return false;
            }

            $groupId = (int) ($category->groupid ?? 0);

            if ($groupId > 0 && !in_array($groupId, $groupIds, true)) {
                return false;
            }

            if ($groupId === 0 && $operation === 'publish') {
                return false;
            }
        }

        return true;
    }
}
