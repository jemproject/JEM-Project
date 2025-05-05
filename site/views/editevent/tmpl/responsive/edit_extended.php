<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Date\Date;
?>

<!-- RECURRENCE START -->

<fieldset class="panelform" style="margin:0">
    <legend><?php echo Text::_('COM_JEM_RECURRENCE'); ?></legend>
    <dl class="adminformlist jem-dl">
        <dt><?php echo $this->form->getLabel('recurrence_type'); ?></dt>
        <dd><?php echo $this->form->getInput('recurrence_type', null, $this->item->recurrence_type); ?></dd>
        <dt> </dt>
        <dd id="recurrence_output">
        </dd>
        <dt> </dt>
        <dd>
            <div id="counter_row" style="display: none;">
                <?php echo $this->form->getLabel('recurrence_limit_date'); ?>
                <?php echo $this->form->getInput('recurrence_limit_date', null, $this->item->recurrence_limit_date); ?>
                <br>
                <div class="recurrence_notice"><small>
                        <?php
                        switch ($this->item->recurrence_type) {
                            case 1:
                                $anticipation	= $this->jemsettings->recurrence_anticipation_day;
                                break;
                            case 2:
                                $anticipation	= $this->jemsettings->recurrence_anticipation_week;
                                break;
                            case 3:
                                $anticipation	= $this->jemsettings->recurrence_anticipation_month;
                                break;
                            case 4:
                                $anticipation	= $this->jemsettings->recurrence_anticipation_week;
                                break;
                            case 5:
                                $anticipation	= $this->jemsettings->recurrence_anticipation_year;
                                break;
                            case 6:
                                $anticipation	= $this->jemsettings->recurrence_anticipation_lastday;
                                break;
                            default:
                                $anticipation	= $this->jemsettings->recurrence_anticipation_day;
                                break;

                        }

                        $limitdate = new Date('now +' . $anticipation . 'month');
                        $limitdate = JemOutput::formatLongDateTime($limitdate->format('Y-m-d'), '');
                        echo Text::sprintf(Text::_('COM_JEM_EDITEVENT_NOTICE_GENSHIELD'), $limitdate);
                        ?></small></div>
            </div>
        </dd>
    </dl>
    <input type="hidden" name="recurrence_number" id="recurrence_number" value="<?php echo $this->item->recurrence_number; ?>" />
    <input type="hidden" name="recurrence_number_saved" id="recurrence_number_saved" value="<?php echo $this->item->recurrence_number;?>">
    <input type="hidden" name="recurrence_byday" id="recurrence_byday" value="<?php echo $this->item->recurrence_byday; ?>" />
    <input type="hidden" name="recurrence_bylastday" id="recurrence_bylastday" value="<?php echo $this->item->recurrence_bylastday;?>" />

    <script>

        <!--
        var $select_output = new Array();
        $select_output[1] = "<?php
            echo Text::_('COM_JEM_OUTPUT_DAY');
            ?>";
        $select_output[2] = "<?php
            echo Text::_('COM_JEM_OUTPUT_WEEK');
            ?>";
        $select_output[3] = "<?php
            echo Text::_('COM_JEM_OUTPUT_MONTH');
            ?>";
        $select_output[4] = "<?php
            echo Text::_('COM_JEM_OUTPUT_WEEKDAY');
            ?>";
        $select_output[5] = "<?php
            echo Text::_('COM_JEM_OUTPUT_YEAR');
            ?>";
        $select_output[6] = "<?php
            echo Text::_('COM_JEM_OUTPUT_LASTDAY');
            ?>";

        var $weekday = new Array();
        $weekday[0] = new Array("MO", "<?php echo Text::_('COM_JEM_MONDAY'); ?>");
        $weekday[1] = new Array("TU", "<?php echo Text::_('COM_JEM_TUESDAY'); ?>");
        $weekday[2] = new Array("WE", "<?php echo Text::_('COM_JEM_WEDNESDAY'); ?>");
        $weekday[3] = new Array("TH", "<?php echo Text::_('COM_JEM_THURSDAY'); ?>");
        $weekday[4] = new Array("FR", "<?php echo Text::_('COM_JEM_FRIDAY'); ?>");
        $weekday[5] = new Array("SA", "<?php echo Text::_('COM_JEM_SATURDAY'); ?>");
        $weekday[6] = new Array("SU", "<?php echo Text::_('COM_JEM_SUNDAY'); ?>");

        var $lastday = new Array();
        $lastday[0]  = new Array("L1", "<?php echo Text::_ ('COM_JEM_LAST_DAY'); ?>");
        $lastday[1]  = new Array("L2", "<?php echo Text::_ ('COM_JEM_LAST_DAY_SECOND'); ?>");
        $lastday[2]  = new Array("L3", "<?php echo Text::_ ('COM_JEM_LAST_DAY_THIRD'); ?>");
        $lastday[3]  = new Array("L4", "<?php echo Text::_ ('COM_JEM_LAST_DAY_FOURTH'); ?>");
        $lastday[4]  = new Array("L5", "<?php echo Text::_ ('COM_JEM_LAST_DAY_FIFTH'); ?>");
        $lastday[5]  = new Array("L6", "<?php echo Text::_ ('COM_JEM_LAST_DAY_SIXTH'); ?>");
        $lastday[6]  = new Array("L7", "<?php echo Text::_ ('COM_JEM_LAST_DAY_SEVEN'); ?>");

        var $before_last = "<?php
            echo Text::_('COM_JEM_BEFORE_LAST');
            ?>";
        var $last = "<?php
            echo Text::_('COM_JEM_LAST');
            ?>";
        start_recurrencescript("jform_recurrence_type");
        -->
    </script>

    <?php /* show "old" recurrence settings for information */
    if (!empty($this->item->recurr_bak->recurrence_type)) {
        $recurr_type = '';
        $rlDate = $this->item->recurr_bak->recurrence_limit_date;
        $recurrence_first_id = $this->item->recurr_bak->recurrence_first_id;
        if (!empty($rlDate)) {
            $recurr_limit_date = JemOutput::formatdate($rlDate);
        } else {
            $recurr_limit_date = Text::_('COM_JEM_UNLIMITED');
        }

        switch ($this->item->recurr_bak->recurrence_type) {
            case 1:
                $recurr_type = Text::_('COM_JEM_DAILY');
                $recurr_info = str_ireplace('[placeholder]',
                    $this->item->recurr_bak->recurrence_number,
                    Text::_('COM_JEM_OUTPUT_DAY'));
                break;
            case 2:
                $recurr_type = Text::_('COM_JEM_WEEKLY');
                $recurr_info = str_ireplace('[placeholder]',
                    $this->item->recurr_bak->recurrence_number,
                    Text::_('COM_JEM_OUTPUT_WEEK'));
                break;
            case 3:
                $recurr_type = Text::_('COM_JEM_MONTHLY');
                $recurr_info = str_ireplace('[placeholder]',
                    $this->item->recurr_bak->recurrence_number,
                    Text::_('COM_JEM_OUTPUT_MONTH'));
                break;
            case 4:
                $recurr_type = Text::_('COM_JEM_WEEKDAY');
                $recurr_byday = preg_replace('/(,)([^ ,]+)/', '$1 $2', $this->item->recurr_bak->recurrence_byday);
                $recurr_days = str_ireplace(array('MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SO'),
                    array(Text::_('COM_JEM_MONDAY'), Text::_('COM_JEM_TUESDAY'),
                        Text::_('COM_JEM_WEDNESDAY'), Text::_('COM_JEM_THURSDAY'),
                        Text::_('COM_JEM_FRIDAY'), Text::_('COM_JEM_SATURDAY'),
                        Text::_('COM_JEM_SUNDAY')),
                    $recurr_byday);
                $recurr_num  = str_ireplace(array('6', '7'),
                    array(Text::_('COM_JEM_LAST'), Text::_('COM_JEM_BEFORE_LAST')),
                    $this->item->recurr_bak->recurrence_number);
                $recurr_info = str_ireplace(array('[placeholder]', '[placeholder_weekday]'),
                    array($recurr_num, $recurr_days),
                    Text::_('COM_JEM_OUTPUT_WEEKDAY'));
                break;
            case 5:
                $recurr_type = Text::_('COM_JEM_YEARLY');
                $recurr_info = str_ireplace('[placeholder]',
                    $this->item->recurr_bak->recurrence_number,
                    Text::_('COM_JEM_OUTPUT_YEAR'));
                break;
            default:
                break;
        }

        if (!empty($recurr_type)) {
            ?>
            <hr class="jem-hr" />
            <p><strong><?php echo Text::_('COM_JEM_RECURRING_INFO_TITLE'); ?></strong></p>
            <dl class="adminformlist jem-dl">
                <dt><label><?php echo Text::_('COM_JEM_RECURRING_FIRST_EVENT_ID'); ?></label></dt>
                <dd><input type="text" class="readonly" readonly="readonly" value="<?php echo $recurrence_first_id; ?>"></dd>
                <dt><label><?php echo Text::_('COM_JEM_RECURRENCE'); ?></label></dt>
                <dd><input type="text" class="readonly" readonly="readonly" value="<?php echo $recurr_type; ?>"></dd>
                <dt><label> </label></dt>
                <dd><?php echo $recurr_info; ?></dd>
                <dt><label><?php echo Text::_('COM_JEM_RECURRENCE_COUNTER'); ?></label></dt>
                <dd><input type="text" class="readonly" readonly="readonly" value="<?php echo $recurr_limit_date; ?>"></dd>
            </dl>
            <?php
        }
    } ?>
</fieldset>
<!-- RECURRENCE END -->
<hr />
<!-- CONTACT START -->
<fieldset class="panelform"  style="margin:0">
    <legend><?php echo Text::_('COM_JEM_EDITEVENT_FIELD_CONTACT'); ?></legend>
    <dl class="jem-dl">
        <dt><?php echo $this->form->getLabel('contactid'); ?></dt>
        <dd><?php echo $this->form->getInput('contactid'); ?></dd>
    </dl>
</fieldset>
<!-- CONTACT END -->
<hr />
<!-- REGISTRATION START -->
<fieldset class="" style="margin:0">
    <legend><?php echo Text::_('COM_JEM_EVENT_REGISTRATION_LEGEND'); ?></legend>
    <?php if ($this->jemsettings->showfroregistra == 0) : ?>
        <dl class="adminformlist jem-dl">
            <dt><?php echo $this->form->getLabel('registra'); ?></dt>
            <dd><?php echo Text::_('JNO'); ?></dd>
        </dl>
    <?php else : ?>
        <dl class="adminformlist jem-dl">
            <dt><?php echo $this->form->getLabel('registra'); ?></dt>
            <dd class="registra-status"><?php echo $this->form->getInput('registra'); ?></dd>
        </dl>
        <div id="optional-limited">
            <dl class="adminformlist jem-dl jem-dl-rest">
                <dt><div id="registra_from"><span id="jform_registra_from2"><?php echo Text::_('COM_JEM_EVENT_FIELD_REGISTRATION_FROM'); ?><?php echo Text::_('COM_JEM_EVENT_FIELD_REGISTRATION_FROM_POSTFIX'); ?></span></div></dt>
                <dd><div id="registra_from2"><?php echo $this->form->getInput('registra_from'); ?></div> </dd>
                <dt><div id="registra_until"><span id="jform_registra_until2"><?php echo Text::_('COM_JEM_EVENT_FIELD_REGISTRATION_UNTIL'); ?><?php echo Text::_('COM_JEM_EVENT_FIELD_REGISTRATION_UNTIL_POSTFIX'); ?></span></div></dt>
                <dd><div id="registra_until2"><?php echo $this->form->getInput('registra_until'); ?></span></div></dd>
            </dl>
        </div>
        <div id="optional-fields">
            <dl class="adminformlist jem-dl jem-dl-rest">
                <?php if ($this->jemsettings->regallowinvitation == 1) : ?>
                    <dt><?php echo $this->form->getLabel('reginvitedonly'); ?></dt>
                    <dd><?php echo $this->form->getInput('reginvitedonly'); ?></dd>
                <?php endif; ?>
                <dt><?php echo $this->form->getLabel('unregistra'); ?></dt>
                <dd><?php echo $this->form->getInput('unregistra'); ?></dd>
                <dt><div id="unregistra_until"><span id="jform_unregistra_until2"><?php echo Text::_('COM_JEM_EVENT_FIELD_ANNULATION_UNTIL'); ?></span></div></dt>
                <dd><div id="unregistra_until2"><?php echo $this->form->getInput('unregistra_until'); ?></div></dd>
                <dt><?php echo $this->form->getLabel('maxplaces'); ?></dt>
                <dd><?php echo $this->form->getInput('maxplaces'); ?></dd>
                <dt><?php echo $this->form->getLabel('minbookeduser'); ?></dt>
                <dd><?php echo $this->form->getInput('minbookeduser'); ?></dd>
                <dt><?php echo $this->form->getLabel('maxbookeduser'); ?></dt>
                <dd><?php echo $this->form->getInput('maxbookeduser'); ?></dd>
                <dt><label style='margin-top: 1rem;'><?php echo Text::_ ('COM_JEM_EDITEVENT_FIELD_RESERVED_PLACES');?></label><br></dt>
                <dd><?php echo $this->form->getInput('reservedplaces'); ?></dd>
                <dt><?php echo $this->form->getLabel('waitinglist'); ?></dt>
                <dd><?php echo $this->form->getInput('waitinglist'); ?></dd>
                <dt><?php echo $this->form->getLabel('requestanswer'); ?></dt>
                <dd><?php echo $this->form->getInput('requestanswer'); ?></dd>
                <dt><?php echo $this->form->getLabel('seriesbooking'); ?></dt>
                <dd><?php echo $this->form->getInput('seriesbooking'); ?></dd>
                <dt><?php echo $this->form->getLabel('singlebooking'); ?></dt>
                <dd><?php echo $this->form->getInput('singlebooking'); ?></dd>

                <?php if ($this->jemsettings->regallowinvitation == 1) : ?>
                    <dt><?php echo $this->form->getLabel('invited'); ?></dt>
                    <dd><?php echo $this->form->getInput('invited'); ?></dd>
                <?php endif; ?>
                <dt><label style='margin-top: 1rem;'><?php echo Text::_ ('COM_JEM_EDITEVENT_FIELD_BOOKED_PLACES');?></label><br></dt>
                <dd><?php echo '<input id="event-booked" class="form-control readonly inputbox" type="text" readonly="true" value="' . $this->item->booked . '" />'; ?></dd>
                <?php if ($this->item->maxplaces) : ?>
                    <dt><?php echo $this->form->getLabel('avplaces'); ?></dt>
                    <dd><?php echo '<input id="event-available" class="form-control readonly inputbox" type="text" readonly="true" value="' . ($this->item->maxplaces-$this->item->booked-$this->item->reservedplaces) . '" />'; ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    <?php endif; ?>
</fieldset>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const registraSelect  = document.getElementById('jform_registra');
        const optionalFields  = document.getElementById('optional-fields');
        const optionalLimited = document.getElementById('optional-limited');

        const updateOptionalFieldsVisibility = () => {
            const selectedValue = registraSelect.value;
            if (selectedValue === '1') {
                optionalFields.style.display  = 'block';
                optionalLimited.style.display = 'none';
            } else if (selectedValue === '2') {
                optionalFields.style.display  = 'block';
                optionalLimited.style.display = 'block';
            } else {
                optionalFields.style.display  = 'none';
                optionalLimited.style.display = 'none';
            }
        };
        updateOptionalFieldsVisibility();
        registraSelect.addEventListener('change', updateOptionalFieldsVisibility);
    });
</script>