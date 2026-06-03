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
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;


/**
 * View class: Venues
 */

 class JemViewVenues extends JemAdminView
{
    protected $items;
    protected $pagination;
    protected $state;

    public function display($tpl = null)
    {
        $user     = JemFactory::getUser();
        $app      = Factory::getApplication();
        $document = $app->getDocument();
        $uri      = Uri::getInstance();
        $url      = $uri->root();
        $settings = JemHelper::globalattribs();

        // Initialise variables.
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');
        $this->settings   = $settings;

        $params = $this->state->get('params');

        // highlighter
        $highlighter = $settings->get('highlight','0');

        // Check for errors.
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            $app->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        // Load css
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

        // Add Scripts
        $document->addScript('https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');

        if ($highlighter) {
            $document->addScript($url.'media/com_jem/js/highlighter.js');
            $style = '.red, .red a { color:red; }';
            $document->addStyleDeclaration($style);
        }

        // add filter selection for the search
        $filters = array();
        $filters[] = HTMLHelper::_('select.option', '1', Text::_('COM_JEM_VENUE'));
        $filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_CITY'));
        $filters[] = HTMLHelper::_('select.option', '3', Text::_('COM_JEM_STATE'));
        $filters[] = HTMLHelper::_('select.option', '4', Text::_('COM_JEM_COUNTRY'));
        $filters[] = HTMLHelper::_('select.option', '5', Text::_('JALL'));
        $lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'inputbox form-select'), 'value', 'text', $this->state->get('filter_type'));
        $lists['venue_type_filter'] = HTMLHelper::_(
            'select.genericlist',
            $this->getTypeFilterOptions(3, 'COM_JEM_TYPE_FILTER_VENUE'),
            'filter_venue_type_id',
            array('size'=>'1','class'=>'inputbox form-select wauto-minwmax m-0','onChange'=>"this.form.submit()"),
            'value',
            'text',
            (int) $this->state->get('filter_venue_type_id'),
            'filter_venue_type_id'
        );

        //assign data to template
        $this->lists = $lists;
        $this->user  = $user;

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
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_VENUES'), 'venues');
        $toolbar = $this->getToolbarInstance();

        $canDo = JemHelperBackend::getActions(0);
        $canChangeState = $canDo->get('core.edit.state') || $canDo->get('core.admin');
        $canDelete = $canDo->get('core.delete');

        /* create */
        if (($canDo->get('core.create'))) {
            ToolbarHelper::addNew('venue.add');
        }

        /* edit */
        if (($canDo->get('core.edit'))) {
            ToolbarHelper::editList('venue.edit');
            ToolbarHelper::divider();
        }

        /* state */
        if (($canChangeState || $canDelete) && $this->supportsToolbarDropdown($toolbar)) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();

            if ($canChangeState && $this->state->get('filter.state') != 2) {
                $childBar->publish('venues.publish')->listCheck(true);
                $childBar->unpublish('venues.unpublish')->listCheck(true);
            }

            if ($canChangeState) {
                $childBar->checkin('venues.checkin')->listCheck(true);
            }

            /* delete-trash */
            if ($canDelete) {
                $childBar->delete('venues.remove', 'JACTION_DELETE')
                    ->message('COM_JEM_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        } elseif ($canChangeState || $canDelete) {
            if ($canChangeState && $this->state->get('filter.state') != 2) {
                ToolbarHelper::publishList('venues.publish');
                ToolbarHelper::unpublishList('venues.unpublish');
            }

            if ($canChangeState) {
                ToolbarHelper::checkin('venues.checkin');
            }

            if ($canDelete) {
                ToolbarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'venues.remove', 'JACTION_DELETE');
            }
        }

        ToolbarHelper::divider();
        ToolBarHelper::help('listvenues', true, 'https://www.joomlaeventmanager.net/documentation/backend/venues');
    }
}
