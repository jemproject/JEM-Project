<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$canAccessEvents = JemHelperBackend::can('event', 'access');
$canCreateEvents = JemHelperBackend::can('event', 'create');
$canAccessVenues = JemHelperBackend::can('venue', 'access');
$canCreateVenues = JemHelperBackend::can('venue', 'create');
$canManageTools = JemHelperBackend::canManage('jem.tools.manage');
$canViewRegistrationHistory = JemHelperBackend::canManage('jem.registrations.history');
$canManageNotificationTemplates = JemHelperBackend::canManage('jem.notifications.templates');
$canViewNotificationHistory = JemHelperBackend::canManage('jem.notifications.history');
$canConfigure = JemHelperBackend::canManage('core.options');
$featurePolicy = $this->featurePolicy ?? JemFeaturePolicy::current();
$profileLabel = Text::_('COM_JEM_OPERATING_PROFILE_' . strtoupper($featurePolicy->getProfile()));
?>
<style>
    .jem-wei-menus .card { min-height: 126px; }
    .jem-wei-menus .card-body div:first-child { float: none !important; }
    .jem-wei-menus .icon { text-align: center; }
    .jem-wei-menus .icon a {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .jem-wei-menus .icon a img { width: 65px; }
</style>
<?php // Group titles, tile groups and the "Add" badge are defined in media/com_jem/css/backend.css. ?>
<form action="<?php echo Route::_('index.php?option=com_jem'); ?>" id="application-form" method="post" name="adminForm" class="form-validate">
    <div id="j-main-container" class="j-main-container">
        <?php if ($canConfigure) : ?>
            <section class="jem-operating-profile-summary<?php echo empty($this->operatingProfileConfigured) ? ' border-warning' : ''; ?>" aria-label="<?php echo Text::_('COM_JEM_OPERATING_PROFILE'); ?>">
                <div>
                    <strong><?php echo Text::sprintf('COM_JEM_OPERATING_PROFILE_CURRENT', $profileLabel); ?></strong>
                    <?php if (empty($this->operatingProfileConfigured)) : ?>
                        <div class="form-text"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_INTRO'); ?></div>
                    <?php endif; ?>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="index.php?option=com_jem&amp;view=settings"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_CONFIGURE'); ?></a>
            </section>
        <?php endif; ?>
        <div class="cpanel jem-wei-menus">
            <h3 class="jem-wei-group-title"><?php echo Text::_('COM_JEM_MAIN_GROUP_CONTENT'); ?></h3>
            <div class="jem-wei-group">
            <?php
                if ($canAccessEvents) {
                    $link = 'index.php?option=com_jem&amp;view=events';
                    $addLink = $canCreateEvents ? 'index.php?option=com_jem&amp;task=event.add' : null;
                    $this->quickiconButton($link, 'icon-48-events.svg', Text::_('COM_JEM_EVENTS'), 0, $addLink, Text::_('COM_JEM_ADD_EVENT'));
                }

                if ($canAccessVenues) {
                    $link = 'index.php?option=com_jem&amp;view=venues';
                    $addLink = $canCreateVenues ? 'index.php?option=com_jem&amp;task=venue.add' : null;
                    $this->quickiconButton($link, 'icon-48-venues.svg', Text::_('COM_JEM_VENUES'), 0, $addLink, Text::_('COM_JEM_ADD_VENUE'));
                }

                $this->quickiconButton('index.php?option=com_jem&amp;view=categories', 'icon-48-categories.svg', Text::_('COM_JEM_CATEGORIES'), 0, 'index.php?option=com_jem&amp;task=category.add', Text::_('COM_JEM_ADD_CATEGORY'));
                $this->quickiconButton('index.php?option=com_jem&amp;view=groups', 'icon-48-groups.svg', Text::_('COM_JEM_GROUPS'), 0, 'index.php?option=com_jem&amp;task=group.add', Text::_('COM_JEM_GROUP_ADD'));
                $this->quickiconButton('index.php?option=com_jem&amp;view=types', 'icon-48-types.svg', Text::_('COM_JEM_TYPES'), 0, 'index.php?option=com_jem&amp;task=type.add', Text::_('COM_JEM_ADD_TYPE'));
                $this->quickiconButton('index.php?option=com_jem&amp;view=specialdays', 'icon-48-specialdays.svg', Text::_('COM_JEM_SPECIAL_DAYS'), 0, 'index.php?option=com_jem&amp;task=specialday.add', Text::_('COM_JEM_ADD_SPECIAL_DAYS'));
                $this->quickiconButton('index.php?option=com_jem&amp;view=attachments', 'icon-48-attachments.svg', Text::_('COM_JEM_ATTACHMENTS'));
            ?>
            </div>

            <h3 class="jem-wei-group-title"><?php echo Text::_('COM_JEM_MAIN_GROUP_SYSTEM'); ?></h3>
            <div class="jem-wei-group">
            <?php
                if ($canConfigure) {
                    $this->quickiconButton('index.php?option=com_jem&amp;view=settings', 'icon-48-settings.svg', Text::_('COM_JEM_MENU_SETTINGS'));
                }

                if ($featurePolicy->allows(JemFeaturePolicy::FEATURE_NOTIFICATION_AUTOMATION)
                    && ($canManageNotificationTemplates || $canViewNotificationHistory)) {
                    $link = $canManageNotificationTemplates
                        ? 'index.php?option=com_jem&amp;view=notifications'
                        : 'index.php?option=com_jem&amp;view=notificationhistory';
                    $this->quickiconButton($link, 'icon-48-notifications.svg', Text::_('COM_JEM_NOTIFICATIONS'));
                }

                if ($canManageTools) {
                    $this->quickiconButton('index.php?option=com_jem&amp;view=cssmanager', 'icon-48-cssmanager.svg', Text::_('COM_JEM_CSSMANAGER_TITLE'));
                    $this->quickiconButton('index.php?option=com_jem&amp;task=plugins.plugins', 'icon-48-plugins.svg', Text::_('COM_JEM_MANAGE_PLUGINS'));
                }

                if ($canConfigure && $featurePolicy->allows(JemFeaturePolicy::FEATURE_PRICING)) {
                    $this->quickiconButton('index.php?option=com_jem&amp;view=taxrates', 'icon-48-taxrates.svg', Text::_('COM_JEM_TAX_RATES'), 0, 'index.php?option=com_jem&amp;task=taxrate.add', Text::_('COM_JEM_TAX_RATE_ADD'));
                }
            ?>
            </div>

            <h3 class="jem-wei-group-title"><?php echo Text::_('COM_JEM_MAIN_GROUP_DATA'); ?></h3>
            <div class="jem-wei-group">
            <?php
                $this->quickiconButton('index.php?option=com_jem&amp;view=statistics', 'icon-48-statistics.svg', Text::_('COM_JEM_STATISTICS'));

                if ($canViewRegistrationHistory) {
                    $this->quickiconButton('index.php?option=com_jem&amp;view=registrationhistory', 'icon-48-registration.svg', Text::_('COM_JEM_REGISTRATION_BUTTON'));
                }

                if ($canManageTools) {
                    $this->quickiconButton('index.php?option=com_jem&amp;view=import', 'icon-48-tableimport.svg', Text::_('COM_JEM_IMPORT_DATA'));
                    $this->quickiconButton('index.php?option=com_jem&amp;view=export', 'icon-48-tableexport.svg', Text::_('COM_JEM_EXPORT_DATA'));
                    $this->quickiconPostButton('sampledata.load', 'icon-48-sampledata.svg', Text::_('COM_JEM_MAIN_LOAD_SAMPLE_DATA'));
                }
            ?>
            </div>

            <?php if ($canManageTools) : ?>
                <h3 class="jem-wei-group-title"><?php echo Text::_('COM_JEM_MAIN_GROUP_MISC'); ?></h3>
                <div class="jem-wei-group">
                <?php
                    $this->quickiconButton('index.php?option=com_jem&amp;view=housekeeping', 'icon-48-housekeeping.svg', Text::_('COM_JEM_HOUSEKEEPING'));
                    $this->quickiconPostButton('frontendmenu.create', 'icon-48-frontendmenu.svg', Text::_('COM_JEM_MAIN_CREATE_FRONTEND_MENU'));

                    $icon = 'icon-48-update.svg';
                    if (!empty($this->updatedata) && isset($this->updatedata->current) && (int) $this->updatedata->current === -1) {
                        $icon = 'icon-48-update-y.svg';
                    }
                    $this->quickiconButton('index.php?option=com_jem&amp;view=updatecheck', $icon, Text::_('COM_JEM_UPDATECHECK_TITLE'));
                    $this->quickiconButton('index.php?option=com_jem&amp;view=help', 'icon-48-help.svg', Text::_('COM_JEM_HELP'));
                ?>
                </div>
            <?php else : ?>
                <div class="jem-wei-group">
                    <?php $this->quickiconButton('index.php?option=com_jem&amp;view=help', 'icon-48-help.svg', Text::_('COM_JEM_HELP')); ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="card mt-4 mw-100" style="max-width: 42rem;">
            <div class="card-body">
                <h3 class="h5 card-title"><?php echo Text::_('COM_JEM_MAIN_DONATE'); ?></h3>
                <p class="card-text"><?php echo Text::_('COM_JEM_MAIN_DONATE_TEXT'); ?></p>
                <a href="https://www.joomlaeventmanager.net/project/donate" target="_blank" rel="noopener noreferrer">
                    <?php echo HTMLHelper::_('image', 'com_jem/PayPal_DonateButton.webp', Text::_('COM_JEM_MAIN_DONATE'), null, true); ?>
                </a>
            </div>
        </aside>
    </div>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
