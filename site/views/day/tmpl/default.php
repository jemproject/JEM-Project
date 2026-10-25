<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

require_once JPATH_SITE . '/components/com_jem/helpers/calendaragenda.php';
require_once JPATH_SITE . '/components/com_jem/helpers/calendarhourgrid.php';
require_once JPATH_SITE . '/components/com_jem/helpers/calendarfilter.php';
?>
<div id="jem" class="jem_day<?php echo $this->pageclass_sfx;?>">
    <div class="buttons">
        <?php
        $btn_params = array('task' => $this->task, 'print_link' => $this->print_link);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        echo JemCalendarAgendaHelper::renderCycleToggle('day', $this->params);
        ?>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
    <h1 class="componentheading">
        <?php echo $this->escape($this->params->get('page_heading')); ?>
    </h1>
    <?php endif; ?>

    <div class="clr"> </div>

    <?php if (isset($this->showdaydate)) : ?>
    <h2 class="jem">
        <?php echo $this->daydate; ?>
    </h2>
    <?php echo JemHelper::renderCalendarSpecialDayBadges($this->day); ?>
    <?php endif; ?>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
    <?php endif; ?>

    <?php
    $displayLegend = (int) $this->params->get('displayLegend', 1);
    $filterEndDate = JemCalendarAgendaHelper::getDayMode($this->params) === 'timetable'
        ? (string) $this->day : (string) $this->dayEnd;
    $dayLegend = JemCalendarFilterHelper::render((array) $this->rows, $displayLegend, (string) $this->day, $filterEndDate);
    if (in_array($displayLegend, array(2, 4, 6), true)) {
        echo $dayLegend;
    }
    ?>

    <?php if (JemCalendarAgendaHelper::getDayMode($this->params) === 'timetable') : ?>
        <?php echo JemCalendarHourGridHelper::renderNavigation('day', (string) $this->day); ?>
        <?php echo JemCalendarHourGridHelper::render((array) $this->rows, (string) $this->day, 1); ?>
    <?php else : ?>
    <!--table-->
    <form action="<?php echo htmlspecialchars($this->action); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo $this->loadTemplate('table'); ?>
        <p>
        <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
        <input type="hidden" name="filter_order_Dir" value="" />
        <input type="hidden" name="task" value="<?php echo $this->task; ?>" />
        <input type="hidden" name="view" value="day" />
        <?php echo HTMLHelper::_('form.token'); ?>
        </p>
    </form>
    <?php endif; ?>

    <?php if (in_array($displayLegend, array(1, 3, 5), true)) : ?>
        <?php echo $dayLegend; ?>
    <?php endif; ?>

    <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>

    <!--footer-->
    <?php if (JemCalendarAgendaHelper::getDayMode($this->params) === 'list') : ?>
        <div class="pagination">
            <?php echo $this->pagination->getPagesLinks(); ?>
        </div>
    <?php endif; ?>

    <div class="copyright">
        <?php echo JemOutput::footer( ); ?>
    </div>
</div>
