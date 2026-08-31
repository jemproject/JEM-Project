<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Authoritative Joomla ACL policy for the frontend JEM category editor.
 */
abstract class JemFrontendCategoryAccess
{
    /**
     * Check whether a user may create a category below the selected parent.
     */
    public static function canCreate($user, $parentId = 0)
    {
        if (empty($user->id) || $user->get('guest', 0)) {
            return false;
        }

        if ($user->authorise('core.admin', 'com_jem') || $user->authorise('core.create', 'com_jem')) {
            return true;
        }

        $parentId = (int) $parentId;

        return $parentId > 1 && $user->authorise('core.create', 'com_jem.category.' . $parentId);
    }

    /**
     * Check whether a user may edit one stored category.
     */
    public static function canEdit($user, $category)
    {
        if (empty($user->id) || !is_object($category) || empty($category->id)) {
            return false;
        }

        $categoryId = (int) $category->id;
        $asset = 'com_jem.category.' . $categoryId;

        if ($user->authorise('core.admin', 'com_jem')
            || $user->authorise('core.edit', $asset)
            || $user->authorise('core.edit', 'com_jem')) {
            return self::canView($user, $category);
        }

        $isOwner = (int) ($category->created_user_id ?? 0) === (int) $user->id;
        $canEditOwn = $user->authorise('core.edit.own', $asset)
            || $user->authorise('core.edit.own', 'com_jem');

        return $isOwner && $canEditOwn && self::canView($user, $category);
    }

    /**
     * Check whether a user may change category publication state.
     */
    public static function canEditState($user, $category = null, $parentId = 0)
    {
        if (empty($user->id)) {
            return false;
        }

        if ($user->authorise('core.admin', 'com_jem')
            || $user->authorise('core.edit.state', 'com_jem')) {
            return true;
        }

        if (is_object($category) && !empty($category->id)) {
            return $user->authorise('core.edit.state', 'com_jem.category.' . (int) $category->id);
        }

        return (int) $parentId > 1
            && $user->authorise('core.edit.state', 'com_jem.category.' . (int) $parentId);
    }

    /**
     * Check the category view level without trusting request data.
     */
    public static function canView($user, $category)
    {
        $access = (int) ($category->access ?? 0);
        $levels = array_map('intval', (array) $user->getAuthorisedViewLevels());

        return in_array($access, $levels, true);
    }

    /**
     * Check whether a submitted access level can be assigned by this user.
     */
    public static function canAssignAccess($user, $access)
    {
        return in_array(
            (int) $access,
            array_map('intval', (array) $user->getAuthorisedViewLevels()),
            true
        );
    }
}
