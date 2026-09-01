<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * * @todo: move js to a file
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
        $wa
            ->useScript('keepalive')
            ->useScript('inlinehelp')
            ->useScript('form.validate');
?>

<script>
jQuery(document).ready(function($) {
    function activateSettingsHashTab() {
        var requestedTab = (window.location.hash || '').replace('#', '').replace(/_/g, '-');

        if (!requestedTab) {
            return;
        }

        var tabTrigger = document.querySelector(
            'button[data-bs-target="#' + requestedTab + '"], ' +
            'a[href="#' + requestedTab + '"], ' +
            '[aria-controls="' + requestedTab + '"], ' +
            'button[data-bs-target="#settings-pane-' + requestedTab + '"], ' +
            'a[href="#settings-pane-' + requestedTab + '"], ' +
            '[aria-controls="settings-pane-' + requestedTab + '"]'
        );

        if (!tabTrigger) {
            return;
        }

        if (window.bootstrap && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
        } else {
            $(tabTrigger).trigger('click');
        }
    }

    activateSettingsHashTab();
    $(window).on('hashchange', activateSettingsHashTab);
});
</script>

<script>
    Joomla.submitbutton = function(task)
    {
        if (task == 'settings.cancel' || document.formvalidator.isValid(document.getElementById('settings-form'))) {
            Joomla.submitform(task, document.getElementById('settings-form'));
        }
    }
</script>



<form action="<?php echo Route::_('index.php?option=com_jem&view=settings'); ?>" method="post" id="settings-form" name="adminForm" class="form-validate jem-settings-form">

    <div id="j-main-container" class="j-main-container">

            <div class="row">
                <div class="col-md-12">
                    <?php echo HTMLHelper::_('uitab.startTabSet', 'settings-pane', ['active' => 'parameters', 'recall' => false, 'breakpoint' => 768]); ?>

                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'parameters', Text::_('COM_JEM_GLOBAL_PARAMETERS')); ?>
                        <fieldset class="adminform">
                            <?php echo $this->loadTemplate('parameters'); ?>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>


                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'settings-basic', Text::_('COM_JEM_BASIC_SETTINGS')); ?>
                        <fieldset class="adminform">
                            <div class="row g-0 align-items-start">
                                <div class="col-12 col-xl-6">
                                    <?php echo $this->loadTemplate('basicdisplay'); ?>
                                    <?php echo $this->loadTemplate('basiclayout'); ?>
                                </div>
                                <div class="col-12 col-xl-6">
                                    <?php echo $this->loadTemplate('basiceventhandling'); ?>
                                    <?php echo $this->loadTemplate('basicmetahandling'); ?>
                                </div>
                            </div>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>


                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'image-settings', Text::_('COM_JEM_IMAGE_SETTINGS')); ?>
                        <fieldset class="adminform">
                            <?php echo $this->loadTemplate('basicimagehandling'); ?>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>


                    <?php echo HTMLHelper::_('uitab.addTab','settings-pane', 'event-settings', Text::_('COM_JEM_EVENT_PAGE')); ?>
                        <fieldset class="adminform">
                            <div class="width-50 fltlft">
                                <?php echo $this->loadTemplate('evevents'); ?>
                            </div>
                            <div class="width-50 fltrt">
                                <?php echo $this->loadTemplate('evvenues'); ?>
                                <?php echo $this->loadTemplate('evregistration'); ?>
                                <?php echo $this->loadTemplate('evlinks'); ?>
                            </div>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>


                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'venue-settings', Text::_('COM_JEM_VENUE_PAGE')); ?>
                    <fieldset class="adminform">
                        <?php echo $this->loadTemplate('venues'); ?>
                    </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>


                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'layout', Text::_('COM_JEM_LAYOUT')); ?>
                        <fieldset class="adminform">
                           <?php echo $this->loadTemplate('layout'); ?>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>

                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'usercontrol', Text::_('COM_JEM_USER_CONTROL')); ?>
                        <fieldset class="adminform">
                           <?php echo $this->loadTemplate('usercontrol'); ?>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>


                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'custom-fields', Text::_('COM_JEM_CUSTOM_FIELDS_SETTINGS')); ?>
                        <fieldset class="adminform">
                            <?php echo $this->loadTemplate('customfields'); ?>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>


                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'countries-settings', Text::_('COM_JEM_COUNTRIES')); ?>
                    <fieldset class="adminform">
                        <?php echo $this->loadTemplate('countries'); ?>
                    </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>


                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'pdf-settings', Text::_('COM_JEM_PDF_SETTINGS')); ?>
                        <fieldset class="adminform">
                            <?php echo $this->loadTemplate('pdf'); ?>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>


                    <?php if ($this->featurePolicy->allows(JemFeaturePolicy::FEATURE_NOTIFICATION_AUTOMATION)) : ?>
                        <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'notification-settings', Text::_('COM_JEM_NOTIFICATION_SETTINGS')); ?>
                            <fieldset class="adminform">
                                <?php echo $this->loadTemplate('notifications'); ?>
                            </fieldset>
                        <?php echo HTMLHelper::_('uitab.endTab'); ?>
                        <div class="clr"></div>
                    <?php endif; ?>


                    <?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'configinfo', Text::_('COM_JEM_SETTINGS_TAB_CONFIGINFO')); ?>
                        <fieldset class="adminform">
                           <?php echo $this->loadTemplate('configinfo'); ?>
                        </fieldset>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>
                    <div class="clr"></div>

                    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

                </div>
            </div>

    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="id" value="1">
    <input type="hidden" name="lastupdate" value="<?php $this->jemsettings->lastupdate; ?>">
    <input type="hidden" name="option" value="com_jem">
    <input type="hidden" name="controller" value="settings">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
