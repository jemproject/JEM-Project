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
 * View class: Venues
 */

 class JemViewVenues extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;

    public function display($tpl = null)
    {
        $user     = JemFactory::getUser();
        $app      = Factory::getApplication();
        $document = $app->getDocument();
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
        $wa->useScript('jquery');

        if ($highlighter) {
            $wa->registerScript('jem.highlighter', 'com_jem/highlighter.js')->useScript('jem.highlighter');
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

        //assign data to template
        $this->lists = $lists;
        $this->user  = $user;

        // add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }


    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_VENUES'), 'venues');
        $toolbar = Toolbar::getInstance('toolbar');

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
        if ($canChangeState || $canDelete) {
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
        }

        ToolbarHelper::divider();
        ToolBarHelper::help('listvenues', true, 'https://www.joomlaeventmanager.net/documentation/backend/venues');
    }
}
