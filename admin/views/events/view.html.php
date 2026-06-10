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
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;


/**
 * Events-View
 */

class JemViewEvents extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;

    public function display($tpl = null)
    {
        $app            = Factory::getApplication();
        $document       = $app->getDocument();
        $user             = JemFactory::getUser();
        $settings         = JemHelper::globalattribs();
        $jemsettings     = JemAdmin::config();

        // Initialise variables.
        $this->items        = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state        = $this->get('State');

        // Retrieving params
        $params = $this->state->get('params');

        // highlighter
        $highlighter = $settings->get('highlight','0');

        // Check for errors.
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        // Load css
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

        // Load Scripts
        $wa->useScript('jquery');

        if ($highlighter) {
            $wa->registerScript('jem.highlighter', 'com_jem/highlighter.js')->useScript('jem.highlighter');
            $style = '
                .red, .red a {
                color:red;}
                ';
            $this->document->addStyleDeclaration($style);
        }

        // add filter selection for the search
        $filters = array();
        $filters[] = HTMLHelper::_('select.option', '1', Text::_('COM_JEM_EVENT_TITLE'));
        $filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_VENUE'));
        $filters[] = HTMLHelper::_('select.option', '3', Text::_('COM_JEM_CITY'));
        $filters[] = HTMLHelper::_('select.option', '4', Text::_('COM_JEM_CATEGORY'));
        $filters[] = HTMLHelper::_('select.option', '5', Text::_('COM_JEM_STATE'));
        $filters[] = HTMLHelper::_('select.option', '6', Text::_('COM_JEM_COUNTRY'));
        $filters[] = HTMLHelper::_('select.option', '7', Text::_('JALL'));
        $lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'inputbox form-select m-0','onChange'=>"this.form.submit()"), 'value', 'text', $this->state->get('filter_type'));
        $lists['event_type_filter'] = HTMLHelper::_(
            'select.genericlist',
            $this->getTypeFilterOptions(1, 'COM_JEM_TYPE_FILTER_EVENT'),
            'filter_event_type_id',
            array('size'=>'1','class'=>'inputbox form-select wauto-minwmax m-0','onChange'=>"this.form.submit()"),
            'value',
            'text',
            (int) $this->state->get('filter_event_type_id'),
            'filter_event_type_id'
        );
        $lists['batch_category'] = HTMLHelper::_(
            'select.genericlist',
            $this->getCategoryMoveOptions(),
            'batch[category_id]',
            array('class'=>'inputbox form-select wauto-minwmax m-0'),
            'value',
            'text',
            '',
            'batch_category_id'
        );
        $lists['batch_venue'] = HTMLHelper::_(
            'select.genericlist',
            $this->getVenueMoveOptions(),
            'batch[venue_id]',
            array('class'=>'inputbox form-select wauto-minwmax m-0'),
            'value',
            'text',
            '',
            'batch_venue_id'
        );
        $lists['batch_type'] = HTMLHelper::_(
            'select.genericlist',
            $this->getTypeMoveOptions(),
            'batch[type_id]',
            array('class'=>'inputbox form-select wauto-minwmax m-0'),
            'value',
            'text',
            '',
            'batch_type_id'
        );

        //assign data to template
        $this->lists        = $lists;
        $this->user            = $user;
        $this->jemsettings  = $jemsettings;
        $this->settings        = $settings;

        // add toolbar
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
     * Build category options for bulk event moves.
     */
    protected function getCategoryMoveOptions()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'catname', 'level')))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('lft') . ' ASC');

        $db->setQuery($query);
        $categories = $db->loadObjectList() ?: array();
        $options = array(HTMLHelper::_('select.option', '', Text::_('COM_JEM_EVENTS_MOVE_CATEGORY_SELECT')));

        foreach ($categories as $category) {
            $options[] = HTMLHelper::_('select.option', (int) $category->id, str_repeat('- ', max(0, (int) $category->level - 1)) . $category->catname);
        }

        return $options;
    }

    /**
     * Build venue options for bulk event moves.
     */
    protected function getVenueMoveOptions()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'venue', 'city')))
            ->from($db->quoteName('#__jem_venues'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('venue') . ' ASC');

        $db->setQuery($query);
        $venues = $db->loadObjectList() ?: array();
        $options = array(HTMLHelper::_('select.option', '', Text::_('COM_JEM_EVENTS_MOVE_VENUE_SELECT')));

        foreach ($venues as $venue) {
            $label = $venue->venue . (!empty($venue->city) ? ' (' . $venue->city . ')' : '');
            $options[] = HTMLHelper::_('select.option', (int) $venue->id, $label);
        }

        return $options;
    }

    /**
     * Build type options for bulk event moves.
     */
    protected function getTypeMoveOptions()
    {
        $options = $this->getTypeFilterOptions(1, 'COM_JEM_EVENTS_MOVE_TYPE_SELECT');
        $options[0]->value = '';
        array_splice($options, 1, 0, array(HTMLHelper::_('select.option', '0', Text::_('COM_JEM_TYPE_SELECT_NONE'))));

        return $options;
    }


    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolBarHelper::title(Text::_('COM_JEM_EVENTS'), 'events');
        $toolbar = Toolbar::getInstance('toolbar');

        /* retrieving the allowed actions for the user */
        $canDo = JemHelperBackend::getActions(0);
        $canChangeState = $canDo->get('core.edit.state') || $canDo->get('core.admin');
        $canDelete = $canDo->get('core.delete');
        $showActionDropdown = $canChangeState;

        /* create */
        if (($canDo->get('core.create'))) {
            ToolBarHelper::addNew('event.add');
        }

        /* edit */
        if (($canDo->get('core.edit'))) {
            ToolBarHelper::editList('event.edit');
            ToolBarHelper::divider();
        }

        /* actions */
        if ($showActionDropdown) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();

            if ($canChangeState && $this->state->get('filter_state') != 2) {
                $childBar->publish('events.publish')->listCheck(true);
                $childBar->unpublish('events.unpublish')->listCheck(true);
                $childBar->standardButton('featured', 'JFEATURED', 'events.featured')
                    ->icon('icon-featured')
                    ->listCheck(true);
            }

            if ($canChangeState && $this->state->get('filter_state') != -1) {
                if ($this->state->get('filter_state') != 2) {
                    $childBar->archive('events.archive')->listCheck(true);
                } elseif ($this->state->get('filter_state') == 2) {
                    $childBar->publish('events.publish', 'JTOOLBAR_UNARCHIVE')->listCheck(true);
                }
            }

            if ($canChangeState) {
                $childBar->checkin('events.checkin')->listCheck(true);
            }

            if ($canDo->get('core.edit')) {
                $childBar->popupButton('batch', 'JTOOLBAR_BATCH')
                    ->popupType('inline')
                    ->textHeader(Text::_('COM_JEM_EVENTS_BATCH_OPTIONS'))
                    ->url('#joomla-dialog-batch')
                    ->modalWidth('800px')
                    ->modalHeight('fit-content')
                    ->listCheck(true);
            }

            if ($this->state->get('filter_state') != -2 && $canChangeState) {
                $childBar->trash('events.trash')->listCheck(true);
            }
        }

        if ($this->state->get('filter_state') == -2 && $canDelete) {
            ToolBarHelper::divider();
            $toolbar->delete('events.delete', 'JTOOLBAR_EMPTY_TRASH')
                ->message('COM_JEM_CONFIRM_DELETE')
                ->listCheck(true);
        }

        ToolBarHelper::divider();
        ToolBarHelper::help('listevents', true, 'https://www.joomlaeventmanager.net/documentation/backend/events');
    }
}
?>
