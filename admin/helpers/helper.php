<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

require_once(JPATH_SITE.'/components/com_jem/factory.php');
require_once(JPATH_ADMINISTRATOR.'/components/com_jem/classes/backendacl.class.php');


// class JemSidebarHelper extends HTMLHelperSidebar
class JemSidebarHelper extends Sidebar

{
    public static function render()
    {
        /* Do nothing */
    }

    public static function getEntries()
    {
        return array();
    }
}


/**
 * Helper: Backend
 */
class JemHelperBackend
{

    public static $extension = 'com_jem';

    /**
     * Check a dedicated event or venue backend permission.
     *
     * These permissions deliberately exclude the frontend User Control and JEM
     * Group grant paths evaluated by JemUser::can(). Backend mutations must be
     * determined only by Joomla component ACL and the stored record owner.
     *
     * core.admin remains the Joomla-standard full-control permission. Edit-own
     * is granted only when the owner loaded from storage matches the current
     * identity; callers must never build $record from submitted form data.
     *
     * @param   string       $type       event or venue.
     * @param   string       $operation  access, create, edit, edit.state, edit.own, edit.created or delete.
     * @param   object|null  $record     Stored record for edit-own evaluation.
     *
     * @return boolean
     */
    public static function can($type, $operation, $record = null)
    {
        $user = JemFactory::getUser();
        $owner = is_object($record) && isset($record->created_by) ? (int) $record->created_by : null;

        if ($type === 'event'
            && is_object($record)
            && in_array($operation, array('create', 'delete', 'edit', 'edit.state'), true)) {
            return self::canEventCategories($operation, self::getEventCategoryIds($record), $record);
        }

        return JemBackendAclPolicy::allows(
            $type,
            $operation,
            $owner,
            (int) $user->id,
            static function ($action) use ($user) {
                return $user->authorise($action, self::$extension);
            }
        );
    }

    /**
     * Check an Event operation in every applicable category.
     */
    public static function canEventCategories($operation, array $categoryIds, $record = null)
    {
        $user = JemFactory::getUser();
        $owner = is_object($record) && isset($record->created_by) ? (int) $record->created_by : null;

        return JemBackendAclPolicy::allowsEventCategories(
            $operation,
            $categoryIds,
            $owner,
            (int) $user->id,
            static function ($action, $asset) use ($user) {
                return $user->authorise($action, $asset);
            }
        );
    }

    /**
     * Whether the current user can create an Event in at least one category.
     */
    public static function canCreateEvent(array $categoryIds = array())
    {
        if ($categoryIds) {
            return self::canEventCategories('create', $categoryIds);
        }

        if (self::can('event', 'create')) {
            return true;
        }

        return self::can('event', 'access')
            && count(self::getAuthorisedJemCategoryIds('jem.events.create', true)) > 0;
    }

    /**
     * Whether an Event action is available at component or category level.
     *
     * This method is intended for list toolbars only. Record mutations must
     * still call can() with the stored Event so every category is evaluated.
     */
    public static function canManageAnyEvent($operation)
    {
        if (self::can('event', $operation)) {
            return true;
        }

        if (!self::can('event', 'access')) {
            return false;
        }

        $action = self::getResourceAction('event', $operation);

        if ($action === null) {
            return false;
        }

        if (count(self::getAuthorisedJemCategoryIds($action)) > 0) {
            return true;
        }

        return $operation === 'edit'
            && count(self::getAuthorisedJemCategoryIds('jem.events.edit.own')) > 0;
    }

    /**
     * Return JEM category ids on which the current user may perform an action.
     *
     * Joomla's User::getAuthorisedCategories() reads #__categories and cannot
     * be used because JEM stores its hierarchy in #__jem_categories.
     */
    public static function getAuthorisedJemCategoryIds($action, $publishedOnly = false)
    {
        $allowedActions = array(
            'core.create',
            'core.delete',
            'core.edit',
            'core.edit.state',
            'core.edit.own',
            'jem.events.create',
            'jem.events.delete',
            'jem.events.edit',
            'jem.events.edit.state',
            'jem.events.edit.own',
        );

        if (!in_array($action, $allowedActions, true)) {
            return array();
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('id') . ' > 1');

        if ($publishedOnly) {
            $query->where($db->quoteName('published') . ' = 1');
        }

        $db->setQuery($query);
        $user = JemFactory::getUser();
        $allowed = array();

        foreach ((array) $db->loadColumn() as $categoryId) {
            $categoryId = (int) $categoryId;

            if ($user->authorise($action, self::$extension . '.category.' . $categoryId)) {
                $allowed[] = $categoryId;
            }
        }

        return $allowed;
    }

    /**
     * Category-management decision using Joomla's category assets.
     */
    public static function canCategory($operation, $record = null, $parentId = 1)
    {
        $user = JemFactory::getUser();

        if ($user->authorise('core.admin', self::$extension)) {
            return true;
        }

        if (!$user->authorise('jem.categories.access', self::$extension)) {
            return false;
        }

        $categoryId = is_object($record) ? (int) ($record->id ?? 0) : 0;
        $owner = is_object($record) ? (int) ($record->created_user_id ?? 0) : 0;
        $parentId = is_object($record) && !empty($record->parent_id)
            ? (int) $record->parent_id
            : (int) $parentId;

        if ($operation === 'create') {
            $asset = $parentId > 1 ? self::$extension . '.category.' . $parentId : self::$extension;

            return $user->authorise('core.create', $asset);
        }

        if ($categoryId < 1) {
            return false;
        }

        $asset = self::$extension . '.category.' . $categoryId;

        if ($operation === 'edit') {
            return $user->authorise('core.edit', $asset)
                || ($owner > 0 && $owner === (int) $user->id && $user->authorise('core.edit.own', $asset));
        }

        $actions = array(
            'delete'     => 'core.delete',
            'edit.state' => 'core.edit.state',
        );

        return isset($actions[$operation]) && $user->authorise($actions[$operation], $asset);
    }

    /**
     * Load stored Event category ids, preferring an already-loaded property.
     */
    private static function getEventCategoryIds($record)
    {
        if (isset($record->cats)) {
            return array_values(array_unique(array_filter(array_map('intval', (array) $record->cats))));
        }

        if (isset($record->categories)) {
            return array_values(array_unique(array_filter(array_map(static function ($category) {
                return is_object($category) ? (int) ($category->id ?? 0) : (int) $category;
            }, (array) $record->categories))));
        }

        $eventId = (int) ($record->id ?? 0);

        if ($eventId < 1) {
            return array();
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__jem_cats_event_relations'))
            ->where($db->quoteName('itemid') . ' = ' . $eventId);
        $db->setQuery($query);

        return array_values(array_unique(array_filter(array_map('intval', (array) $db->loadColumn()))));
    }

    /**
     * Return the ACL action name used for a backend resource operation.
     *
     * @return string|null
     */
    public static function getResourceAction($type, $operation)
    {
        return JemBackendAclPolicy::getAction($type, $operation);
    }

    /**
     * Check a non-resource backend administration action.
     */
    public static function canManage($action)
    {
        $allowedActions = array(
            'jem.attendees.manage',
            'jem.registrations.history',
            'jem.notifications.templates',
            'jem.notifications.history',
            'jem.notifications.resend',
            'jem.tools.manage',
            'jem.categories.access',
            'core.options',
        );

        if (!in_array($action, $allowedActions, true)) {
            return false;
        }

        $user = JemFactory::getUser();

        if ($action === 'jem.attendees.manage' && !self::can('event', 'access')) {
            return false;
        }

        return $user->authorise('core.admin', self::$extension)
            || $user->authorise($action, self::$extension);
    }

    /**
     * Check access to an attachment through the ACL of its linked resource.
     *
     * Viewing and downloading require resource access. Changing attachment
     * metadata or deleting a file requires edit permission on the stored event
     * or venue, including edit-own when its stored creator matches the user.
     */
    public static function canAccessAttachment($object, $operation = 'access')
    {
        if (!preg_match('/^(event|venue)([0-9]+)$/i', (string) $object, $matches)) {
            $user = JemFactory::getUser();

            if (preg_match('/^category[0-9]+$/i', (string) $object)) {
                $action = $operation === 'access' ? 'core.manage' : 'core.edit';

                return $user->authorise('core.admin', self::$extension)
                    || $user->authorise($action, self::$extension);
            }

            return self::canManage('jem.tools.manage');
        }

        $type = strtolower($matches[1]);

        if ($operation === 'access') {
            return self::can($type, 'access');
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'created_by')))
            ->from($db->quoteName($type === 'event' ? '#__jem_events' : '#__jem_venues'))
            ->where($db->quoteName('id') . ' = ' . (int) $matches[2]);
        $db->setQuery($query);
        $record = $db->loadObject();

        // Orphaned attachments require the unrestricted edit permission.
        return $record ? self::can($type, 'edit', $record) : self::can($type, 'edit');
    }

    /**
     * Configure the Linkbar.
     *
     * @param    string    The name of the active view.
     *
     * @return    void
     *
     */
    public static function addSubmenu($vName)
    {
        JemSidebarHelper::addEntry(
            Text::_('COM_JEM_SUBMENU_MAIN'),
            'index.php?option=com_jem&view=main',
            $vName == 'main'
        );

        if (self::can('event', 'access')) {
            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_EVENTS'),
                'index.php?option=com_jem&view=events',
                $vName == 'events'
            );
        }

        if (self::can('venue', 'access')) {
            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_VENUES'),
                'index.php?option=com_jem&view=venues',
                $vName == 'venues'
            );
        }

        if (self::canManage('jem.categories.access')) {
            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_CATEGORIES'),
                'index.php?option=com_jem&view=categories',
                $vName == 'categories'
            );
        }

        JemSidebarHelper::addEntry(
            Text::_('COM_JEM_GROUPS'),
            'index.php?option=com_jem&view=groups',
            $vName == 'groups'
        );

        JemSidebarHelper::addEntry(
            Text::_('COM_JEM_ATTACHMENTS'),
            'index.php?option=com_jem&view=attachments',
            $vName == 'attachments'
        );

        if (self::can('type', 'access')) {
            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_TYPES'),
                'index.php?option=com_jem&view=types',
                $vName == 'types'
            );
        }

        JemSidebarHelper::addEntry(
            Text::_('COM_JEM_SPECIAL_DAYS'),
            'index.php?option=com_jem&view=specialdays',
            $vName == 'specialdays'
        );

        if (self::canManage('core.options')) {
            if (JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_PRICING)) {
                JemSidebarHelper::addEntry(
                    Text::_('COM_JEM_TAX_RATES'),
                    'index.php?option=com_jem&view=taxrates',
                    in_array($vName, array('taxrates', 'taxrate'), true)
                );
            }

            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_SETTINGS_TITLE'),
                'index.php?option=com_jem&view=settings',
                $vName == 'settings'
            );
        }

        if (Factory::getApplication()->getIdentity()->authorise('core.admin')) {
            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_LANGUAGES'),
                'index.php?option=com_jem&view=languages',
                $vName == 'languages'
            );
        }

        if (self::canManage('jem.tools.manage')) {
            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_HOUSEKEEPING'),
                'index.php?option=com_jem&amp;view=housekeeping',
                $vName == 'housekeeping'
            );

            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_UPDATECHECK_TITLE'),
                'index.php?option=com_jem&amp;view=updatecheck',
                $vName == 'updatecheck'
            );

            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_IMPORT_DATA'),
                'index.php?option=com_jem&amp;view=import',
                $vName == 'import'
            );

            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_IMPORT_PROFILES'),
                'index.php?option=com_jem&amp;view=importprofiles',
                $vName == 'importprofiles'
            );

            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_EXPORT_DATA'),
                'index.php?option=com_jem&amp;view=export',
                $vName == 'export'
            );

            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_CSSMANAGER_TITLE'),
                'index.php?option=com_jem&amp;view=cssmanager',
                $vName == 'cssmanager'
            );
        }

        if (self::canManage('jem.registrations.history')) {
            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_REGISTRATION_HISTORY'),
                'index.php?option=com_jem&amp;view=registrationhistory',
                in_array($vName, array('registrationhistory', 'registrationhistoryentry'), true)
            );
        }

        $canManageNotificationTemplates = self::canManage('jem.notifications.templates');
        $canViewNotificationHistory = self::canManage('jem.notifications.history');
        if (JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_NOTIFICATION_AUTOMATION)
            && ($canManageNotificationTemplates || $canViewNotificationHistory)) {
            $notificationView = $canManageNotificationTemplates ? 'notifications' : 'notificationhistory';
            JemSidebarHelper::addEntry(
                Text::_('COM_JEM_NOTIFICATIONS'),
                'index.php?option=com_jem&amp;view=' . $notificationView,
                in_array($vName, array('notifications', 'notificationtemplates', 'notificationtemplate', 'notificationcontent', 'notificationhistory'), true)
            );
        }

        JemSidebarHelper::addEntry(
            Text::_('COM_JEM_HELP'),
            'index.php?option=com_jem&view=help',
            $vName == 'help'
        );
    }

    /**
     * Gets a list of the actions that can be performed.
     *
     * @param    int        The category ID.
     *
     * @return    Registry
     */
    public static function getActions($categoryId = 0)
    {
        $user    = JemFactory::getUser();
        $result  = new Registry();

        if (empty($categoryId)) {
            $assetName = 'com_jem';
            $level = 'component';
        } else {
            $assetName = 'com_jem.category.'.(int) $categoryId;
            $level = 'category';
        }

        // $actions = Access::getActions('com_jem', $level);
        $actions = Access::getActionsFromFile(JPATH_ADMINISTRATOR.'/components/com_jem/access.xml',"/access/section[@name='".$level."']/");

        foreach ($actions as $action) {
            $result->set($action->name, $user->authorise($action->name, $assetName));
        }

        return $result;
    }

    public static function getCountryOptions()
    {
        $options = array();
        $options = array_merge(JEMHelperCountries::getCountryOptions(),$options);

        array_unshift($options, HTMLHelper::_('select.option', '0', Text::_('COM_JEM_SELECT_COUNTRY')));

        return $options;
    }

}
