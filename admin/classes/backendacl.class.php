<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Pure decision policy for granular JEM backend event and venue permissions.
 */
final class JemBackendAclPolicy
{
    private const RESOURCE_ACTIONS = array(
        'event' => array(
            'access'       => 'jem.events.access',
            'create'       => 'jem.events.create',
            'delete'       => 'jem.events.delete',
            'edit'         => 'jem.events.edit',
            'edit.state'   => 'jem.events.edit.state',
            'edit.own'     => 'jem.events.edit.own',
            'edit.created' => 'jem.events.edit.created',
        ),
        'venue' => array(
            'access'       => 'jem.venues.access',
            'create'       => 'jem.venues.create',
            'delete'       => 'jem.venues.delete',
            'edit'         => 'jem.venues.edit',
            'edit.state'   => 'jem.venues.edit.state',
            'edit.own'     => 'jem.venues.edit.own',
            'edit.created' => 'jem.venues.edit.created',
        ),
        'type' => array(
            'access'     => 'jem.types.access',
            'create'     => 'jem.types.create',
            'delete'     => 'jem.types.delete',
            'edit'       => 'jem.types.edit',
            'edit.state' => 'jem.types.edit.state',
        ),
    );

    public static function getAction($type, $operation)
    {
        return self::RESOURCE_ACTIONS[$type][$operation] ?? null;
    }

    /**
     * Evaluate a backend resource decision using a Joomla authorisation callback.
     *
     * @param   string    $type          event or venue.
     * @param   string    $operation     access, create, delete, edit or edit.state.
     * @param   int|null  $recordOwner   Owner read from the stored record.
     * @param   int       $userId        Current Joomla user id.
     * @param   callable  $authorise     fn(string $action): bool.
     */
    public static function allows($type, $operation, $recordOwner, $userId, callable $authorise)
    {
        $action = self::getAction($type, $operation);

        if ($action === null) {
            return false;
        }

        if ($authorise('core.admin')) {
            return true;
        }

        if ($operation !== 'access' && !$authorise(self::getAction($type, 'access'))) {
            return false;
        }

        if ($authorise($action)) {
            return true;
        }

        return $operation === 'edit'
            && $recordOwner !== null
            && (int) $recordOwner > 0
            && (int) $recordOwner === (int) $userId
            && $authorise(self::getAction($type, 'edit.own'));
    }

    /**
     * Evaluate a backend Event action against every stored or selected category.
     *
     * @param   string    $operation    create, delete, edit or edit.state.
     * @param   array     $categoryIds  JEM category ids.
     * @param   int|null  $recordOwner  Owner read from the stored Event.
     * @param   int       $userId       Current Joomla user id.
     * @param   callable  $authorise    fn(string $action, string $asset): bool.
     */
    public static function allowsEventCategories($operation, array $categoryIds, $recordOwner, $userId, callable $authorise)
    {
        $action = self::getAction('event', $operation);

        if ($action === null || $operation === 'access' || $operation === 'edit.created') {
            return false;
        }

        if ($authorise('core.admin', 'com_jem')) {
            return true;
        }

        if (!$authorise(self::getAction('event', 'access'), 'com_jem')) {
            return false;
        }

        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        $assets = $categoryIds
            ? array_map(static function ($categoryId) { return 'com_jem.category.' . $categoryId; }, $categoryIds)
            : array('com_jem');

        $allowsEvery = static function ($permission) use ($assets, $authorise) {
            foreach ($assets as $asset) {
                if (!$authorise($permission, $asset)) {
                    return false;
                }
            }

            return true;
        };

        if ($allowsEvery($action)) {
            return true;
        }

        return $operation === 'edit'
            && $recordOwner !== null
            && (int) $recordOwner > 0
            && (int) $recordOwner === (int) $userId
            && $allowsEvery(self::getAction('event', 'edit.own'));
    }
}
