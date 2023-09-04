<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

$app = Factory::getApplication();
$document = $app->getDocument();
$wa = $document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');
//->useScript('behavior.calendar');

// Create shortcut to parameters.
$params		= $this->params;
// $settings	= json_decode($this->item->attribs);
?>

<script type="text/javascript">
    jQuery(document).ready(function($){

        function checkmaxplaces(){
            var maxplaces = $('jform_maxplaces');

            if (maxplaces != null){
                $('#jform_maxplaces').on('change', function(){
                    if ($('#event-available')) {
                        var val = parseInt($('#jform_maxplaces').val());
                        var booked = parseInt($('#event-booked').val());
                        $('event-available').val() = (val-booked);
                    }
                });

                $('#jform_maxplaces').on('keyup', function(){
                    if ($('event-available')) {
                        var val = parseInt($('jform_maxplaces').val());
                        var booked = parseInt($('event-booked').val());
                        $('event-available').val() = (val-booked);
                    }
                });
            }
        }
        checkmaxplaces();
    });
</script>

<script type="text/javascript">
    $(document).ready(function () {
        var $registraCheckbox = $('input[name="jform[registra]"]');
        var $restOfContent = $(".jem-dl-rest").children("dd, dt");

        $registraCheckbox.on("change", function () {
            if ($(this).is(":checked")) {
                $restOfContent.show();
            } else {
                $restOfContent.hide();
            }
        });

        var $minBookedUserInput = $("#jform_minbookeduser");
        var $maxBookedUserInput = $("#jform_maxbookeduser");
        var $maxPlacesInput = $("#jform_maxplaces");
        var $reservedPlacesInput = $("#jform_reservedplaces");

        $minBookedUserInput
            .add($maxBookedUserInput)
            .add($maxPlacesInput)
            .add($reservedPlacesInput)
            .on("change", function () {
                var minBookedUserValue = parseInt($minBookedUserInput.val());
                var maxBookedUserValue = parseInt($maxBookedUserInput.val());
                var maxPlacesValue = parseInt($maxPlacesInput.val());
                var reservedPlacesValue = parseInt($reservedPlacesInput.val());
                if (minBookedUserValue > maxPlacesValue && maxPlacesValue != 0) {
                    $minBookedUserInput.val(maxPlacesValue);
                }
                if (maxBookedUserValue > maxPlacesValue && maxPlacesValue != 0) {
                    $maxBookedUserInput.val(maxPlacesValue);
                }
                if (minBookedUserValue > maxBookedUserValue) {
                    $minBookedUserInput.val(maxBookedUserValue);
                }
                if (reservedPlacesValue > maxPlacesValue && maxPlacesValue != 0) {
                    $reservedPlacesInput.val(maxPlacesValue);
                }
            });

        // Trigger the change event on page load to initialize the state
        $registraCheckbox.change();
        $minBookedUserInput.change();
    });
</script>;

<script type="text/javascript">
    Joomla.submitbutton = function(task) {
        if (task == 'event.cancel' || document.formvalidator.isValid(document.getElementById('adminForm'))) {
            <?php //echo $this->form->getField('articletext')->save(); ?>
            Joomla.submitform(task);
        } else {
            alert('<?php echo $this->escape(Text::_('JGLOBAL_VALIDATION_FORM_FAILED'));?>');
        }
    }
</script>
<script type="text/javascript">
    // window.addEvent('domready', function(){
    jQuery(document).ready(function($){

        var showUnregistraUntil = function(){
            var unregistra = $("#jform_unregistra");

            var unregistramode = unregistra.val();

            if (unregistramode == 2) {
                document.getElementById('jform_unregistra_until').style.display = '';
                document.getElementById('jform_unregistra_until2').style.display = '';
            } else {
                document.getElementById('jform_unregistra_until').style.display = 'none';
                document.getElementById('jform_unregistra_until2').style.display = 'none';
            }
        }
        $("#jform_unregistra").on('change', showUnregistraUntil);
        showUnregistraUntil();
    });
</script>

<div id="jem" class="jem_editevent<?php echo $this->pageclass_sfx; ?>">
    <div class="edit item-page">
        <?php if ($params->get('show_page_heading')) : ?>
            <h1>
                <?php echo $this->escape($params->get('page_heading')); ?>
            </h1>
        <?php endif; ?>

        <form enctype="multipart/form-data" action="<?php echo Route::_('index.php?option=com_jem&a_id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">

            <button type="submit" class="btn btn-primary" onclick="Joomla.submitbutton('event.save')"><?php echo Text::_('JSAVE') ?></button>
            <button type="cancel" class="btn btn-secondary" onclick="Joomla.submitbutton('event.cancel')"><?php echo Text::_('JCANCEL') ?></button>

            <br>
            <?php if ($this->item->recurrence_type > 0) : ?>
                <div class="description warningrecurrence" style="clear: both;">
                    <div style="float:left;">
                        <?php echo JemOutput::recurrenceicon($this->item, false, false); ?>
                    </div>
                    <div class="floattext" style="margin-left:36px;">
                        <strong><?php echo Text::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TITLE'); ?></strong>
                        <br>
                        <?php
                        if (!empty($this->item->recurrence_type) && empty($this->item->recurrence_first_id)) {
                            echo nl2br(Text::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_FIRST_TEXT'));
                        } else {
                            echo nl2br(Text::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TEXT'));
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($this->params->get('showintrotext')) : ?>
                <div class="description no_space floattext">
                    <?php echo $this->params->get('introtext'); ?>
                </div>
            <?php endif; ?>

            <?php //echo HTMLHelper::_('tabs.start', 'det-pane'); ?>

            <!-- DETAILS TAB -->
            <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_EDITEVENT_INFO_TAB'), 'editevent-infotab'); ?>
            <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'editevent-infotab', 'recall' => true, 'breakpoint' => 768]); ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'editevent-infotab', Text::_('COM_JEM_EDITEVENT_INFO_TAB')); ?>

            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_JEM_EDITEVENT_DETAILS_LEGEND'); ?></legend>
                <dl class="jem-dl">
                    <dt><?php echo $this->form->getLabel('title'); ?></dt>
                    <dd><?php echo $this->form->getInput('title'); ?></dd>
                    <?php if (is_null($this->item->id)) : ?>
                        <dt><?php echo $this->form->getLabel('alias'); ?></dt>
                        <dd><?php echo $this->form->getInput('alias'); ?></dd>
                    <?php endif; ?>
                    <dt><?php echo $this->form->getLabel('dates'); ?></dt>
                    <dd><?php echo $this->form->getInput('dates'); ?></dd>
                    <dt><?php echo $this->form->getLabel('enddates'); ?></dt>
                    <dd><?php echo $this->form->getInput('enddates'); ?></dd>
                    <dt><?php echo $this->form->getLabel('times'); ?></dt>
                    <dd><?php echo $this->form->getInput('times'); ?></dd>
                    <dt><?php echo $this->form->getLabel('endtimes'); ?></dt>
                    <dd><?php echo $this->form->getInput('endtimes'); ?></dd>
                    <dt><?php echo $this->form->getLabel('cats'); ?></dt>
                    <dd><?php echo $this->form->getInput('cats'); ?></dd>
                    <dt><?php echo $this->form->getLabel('locid'); ?></dt>
                    <dd><?php echo $this->form->getInput('locid'); ?></dd>

                </dl>
            </fieldset>
            <!-- EVENTDESCRIPTION -->
            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_JEM_EDITEVENT_DESCRIPTION_LEGEND'); ?></legend>
                <div>
                    <?php echo $this->form->getLabel('articletext'); ?>
                    <?php echo $this->form->getInput('articletext'); ?>
                </div>
            </fieldset>

            <!-- IMAGE -->
            <?php if ($this->item->datimage || $this->jemsettings->imageenabled != 0) : ?>
                <fieldset class="jem_fldst_image">
                    <legend><?php echo Text::_('COM_JEM_IMAGE'); ?></legend>
                    <?php if ($this->jemsettings->imageenabled != 0) : ?>
                        <dl class="adminformlist jem-dl">
                            <dt><?php echo $this->form->getLabel('userfile'); ?></dt>
                            <?php if ($this->item->datimage) : ?>
                                <dd>
                                    <?php echo JEMOutput::flyer($this->item, $this->dimage, 'event', 'datimage'); ?>
                                    <input type="hidden" name="datimage" id="datimage" value="<?php echo $this->item->datimage; ?>" />
                                </dd>
                                <dt> </dt>
                            <?php endif; ?>
                            <dd><?php echo $this->form->getInput('userfile'); ?></dd>
                            <dt> </dt>
                            <dd><button type="button" class="button3 btn btn-secondary" onclick="document.getElementById('jform_userfile').value = ''"><?php echo Text::_('JSEARCH_FILTER_CLEAR') ?></button></dd>
                            <?php if ($this->item->datimage) : ?>
                                <dt><?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?></dt>
                                <dd><?php
                                    echo HTMLHelper::image('media/com_jem/images/publish_r.png', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'events', 'title' => Text::_('COM_JEM_REMOVE_IMAGE'), 'class' => 'btn')); ?>
                                </dd>
                            <?php endif; ?>
                        </dl>
                        <input type="hidden" name="removeimage" id="removeimage" value="0" />
                    <?php endif; ?>
                </fieldset>
            <?php endif; ?>

            <!-- EXTENDED TAB -->
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'editevent-extendedtab', Text::_('COM_JEM_EDITEVENT_EXTENDED_TAB')); ?>
            <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_EDITEVENT_EXTENDED_TAB'), 'editevent-extendedtab'); ?>
            <?php echo $this->loadTemplate('extended'); ?>

            <!-- PUBLISH TAB -->
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'editevent-publishtab', Text::_('COM_JEM_EDITEVENT_PUBLISH_TAB')); ?>
            <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_EDITEVENT_PUBLISH_TAB'), 'editevent-publishtab'); ?>
            <?php echo $this->loadTemplate('publish'); ?>

            <!-- ATTACHMENTS TAB -->
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php if (!empty($this->item->attachments) || ($this->jemsettings->attachmentenabled != 0)) : ?>
                <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'event-attachments', Text::_('COM_JEM_EVENT_ATTACHMENTS_TAB')); ?>
                <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'event-attachments'); ?>
                <?php echo $this->loadTemplate('attachments'); ?>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php endif; ?>

            <!-- OTHER TAB -->
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'event-other', Text::_('COM_JEM_EVENT_OTHER_TAB')); ?>
            <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_EVENT_OTHER_TAB'), 'event-other'); ?>
            <?php echo $this->loadTemplate('other'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php //echo HTMLHelper::_('tabs.end'); ?>

            <input type="hidden" name="task" value="" />
            <input type="hidden" name="return" value="<?php echo $this->return_page; ?>" />
            <input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
            <?php if ($this->params->get('enable_category', 0) == 1) : ?>
                <input type="hidden" name="jform[catid]" value="<?php echo $this->params->get('catid', 1); ?>" />
            <?php endif; ?>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </div>

    <div class="copyright">
        <?php echo JemOutput::footer(); ?>
    </div>
</div>
