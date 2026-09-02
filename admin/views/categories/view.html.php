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
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;

/**
 *  View class for the JEM Categories screen
 */
class JemViewCategories extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;
    public $filterForm;
    public $activeFilters;
    public $total;

    /**
     * Display the view
     */
    public function display($tpl = null)
    {
        $this->state        = $this->get('State');
        $this->items        = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->total         = $this->get('Total');

        foreach (array('category_type_id', 'access', 'level') as $filter) {
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
        $this->app          = Factory::getApplication();
        $this->document     = $this->app->getDocument();
        // Check for errors.
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            $this->app->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        // Preprocess the list of items to find ordering divisions.
        foreach ($this->items as &$item) {
            $this->ordering[$item->parent_id][] = $item->id;
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Build type filter options for the requested JEM entity.
     */
    protected function getTypeFilterOptions($entity, $emptyText)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'name')))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('entity') . ' = ' . (int) $entity)
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('name') . ' ASC');

        $db->setQuery($query);
        $types = $db->loadObjectList() ?: array();
        $options = array(HTMLHelper::_('select.option', '0', Text::_($emptyText)));

        foreach ($types as $type) {
            $options[] = HTMLHelper::_('select.option', (int) $type->id, $type->name);
        }

        return $options;
    }

    /**
     * Add the page title and toolbar.
     */
    protected function addToolbar()
    {
        $canDo = JemHelperBackend::getActions(0);
        $user  = JemFactory::getUser();

        ToolbarHelper::title(Text::_('COM_JEM_CATEGORIES'), 'elcategories');

        $canChangeState = $canDo->get('core.edit.state') || $canDo->get('core.admin');
        $filterState = $this->state->get('filter.published');

        /* create */
        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('category.add');
        }

        /* edit */
        if ($canDo->get('core.edit') || $canDo->get('core.edit.own')) {
            ToolbarHelper::editList('category.edit');
            ToolbarHelper::divider();
        }

        /* action dropdown */
        if ($canChangeState) {
            $toolbar  = Toolbar::getInstance('toolbar');
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();

            if ($canChangeState) {
                if ($filterState != 2) {
                    $childBar->publish('categories.publish')->listCheck(true);
                    $childBar->unpublish('categories.unpublish')->listCheck(true);
                }
                if ($filterState != 2) {
                    $childBar->archive('categories.archive')->listCheck(true);
                } else {
                    $childBar->publish('categories.publish', 'JTOOLBAR_UNARCHIVE')->listCheck(true);
                }
                $childBar->checkin('categories.checkin')->listCheck(true);

                /* "Trash" only visible if not in Trash view */
                if ($filterState != -2) {
                    $childBar->trash('categories.trash')->listCheck(true);
                }
            }
        }

        /* "Delete" as separate button – only in Trash-view (like in Joomla Categories or Articles)*/
        if ($filterState == -2 && $canDo->get('core.delete')) {
            ToolbarHelper::divider();
            $toolbar = Toolbar::getInstance('toolbar');
            $toolbar->delete('categories.remove', 'JTOOLBAR_EMPTY_TRASH')
                ->message('COM_JEM_CONFIRM_DELETE')
                ->listCheck(true);
        }

        /* rebuild */
        if ($canDo->get('core.admin')) {
            ToolbarHelper::divider();
            ToolbarHelper::custom('categories.rebuild', 'refresh.webp', 'refresh_f2.webp', 'JTOOLBAR_REBUILD', false);
        }

        ToolbarHelper::divider();
        ToolBarHelper::help('listcategories', true, 'https://www.joomlaeventmanager.net/documentation/backend/categories');
    }
}
