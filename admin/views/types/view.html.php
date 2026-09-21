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

class JemViewTypes extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;
    public $filterForm;
    public $activeFilters;
    public $total;

    public function display($tpl = null)
    {
        if (!JemHelperBackend::can('type', 'access')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->total         = $this->get('Total');

        foreach (array('entity', 'access') as $filter) {
            if (isset($this->activeFilters[$filter]) && (string) $this->activeFilters[$filter] === '0') {
                unset($this->activeFilters[$filter]);
            }
        }

        if ($this->filterForm) {
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
        ToolbarHelper::title(Text::_('COM_JEM_TYPES'), 'tag');
        $toolbar = Toolbar::getInstance('toolbar');

        $canChangeState = JemHelperBackend::can('type', 'edit.state');
        $canDelete = JemHelperBackend::can('type', 'delete');
        $filterState = $this->state->get('filter_state');

        /* create */
        if (JemHelperBackend::can('type', 'create')) {
            ToolbarHelper::addNew('type.add');
        }

        /* edit */
        if (JemHelperBackend::can('type', 'edit')) {
            ToolbarHelper::editList('type.edit');
            ToolbarHelper::divider();
        }

        /* actions */
        if ($canChangeState) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();

            if ($canChangeState && $filterState != 2) {
                $childBar->publish('types.publish')->listCheck(true);
                $childBar->unpublish('types.unpublish')->listCheck(true);
            }

            if ($canChangeState) {
                if ($filterState != 2) {
                    $childBar->archive('types.archive')->listCheck(true);
                } else {
                    $childBar->publish('types.publish', 'JTOOLBAR_UNARCHIVE')->listCheck(true);
                }
            }

            if ($canChangeState) {
                $childBar->checkin('types.checkin')->listCheck(true);
            }

            if ($canChangeState && $filterState != -2) {
                $childBar->trash('types.trash')->listCheck(true);
            }
        }

        if ($filterState == -2 && $canDelete) {
            ToolbarHelper::divider();
            $toolbar->delete('types.remove', 'JTOOLBAR_EMPTY_TRASH')
                ->message('COM_JEM_CONFIRM_DELETE')
                ->listCheck(true);
        }

        ToolbarHelper::divider();
        ToolbarHelper::help('listtypes', true, 'https://www.joomlaeventmanager.net/documentation/backend/types');
    }
}
