<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';

class JemViewSpecialdays extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;
    public $filterForm;
    public $activeFilters;
    public $total;
    public $dayTypes;
    public $dayTypesById;
    public $years;

    public function display($tpl = null)
    {
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->total      = $this->get('Total');
        $this->dayTypes   = JemHelper::calendarSpecialDayTypes();
        $this->dayTypesById = JemHelper::calendarSpecialDayTypesById();
        $this->years      = $this->get('AvailableYears');
        ksort($this->years);

        if (isset($this->activeFilters['year'])
            && (int) $this->activeFilters['year'] === (int) $this->state->get('filter.default_year')) {
            unset($this->activeFilters['year']);
        }

        if ($this->filterForm) {
            $yearField = $this->filterForm->getField('year', 'filter');
            foreach ((array) $this->years as $year) {
                $yearField->addOption((string) $year, array('value' => (string) $year));
            }

            $dayTypeField = $this->filterForm->getField('day_type', 'filter');
            foreach ($this->dayTypes as $type) {
                $typeValue = !empty($type['id']) ? (string) (int) $type['id'] : (string) $type['name'];
                $dayTypeField->addOption((string) $type['name'], array('value' => $typeValue));
            }

            $this->filterForm->setValue(
                'fullordering',
                'list',
                $this->state->get('list.ordering') . ' ' . $this->state->get('list.direction')
            );
            $this->filterForm->setValue('limit', 'list', $this->state->get('list.limit'));
        }

        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useScript('table.columns');

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_SPECIAL_DAYS'), 'calendar');
        $toolbar = Toolbar::getInstance('toolbar');

        $canDo = JemHelperBackend::getActions(0);
        $canChangeState = $canDo->get('core.edit.state') || $canDo->get('core.admin');
        $canDelete = $canDo->get('core.delete');
        $filterState = $this->state->get('filter.state');

        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('specialday.add');
        }

        if ($canDo->get('core.edit')) {
            ToolbarHelper::editList('specialday.edit');
            ToolbarHelper::divider();
        }

        if ($canChangeState) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();

            if ($filterState != 2) {
                $childBar->publish('specialdays.publish')->listCheck(true);
                $childBar->unpublish('specialdays.unpublish')->listCheck(true);
            }

            if ($filterState != 2) {
                $childBar->archive('specialdays.archive')->listCheck(true);
            } else {
                $childBar->publish('specialdays.publish', 'JTOOLBAR_UNARCHIVE')->listCheck(true);
            }

            $childBar->checkin('specialdays.checkin')->listCheck(true);

            if ($filterState != -2) {
                $childBar->trash('specialdays.trash')->listCheck(true);
            }
        }

        if ($filterState == -2 && $canDelete) {
            ToolbarHelper::divider();
            $toolbar->delete('specialdays.remove', 'JTOOLBAR_EMPTY_TRASH')
                ->message('COM_JEM_CONFIRM_DELETE')
                ->listCheck(true);
        }

        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
    }
}
