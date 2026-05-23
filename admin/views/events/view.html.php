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
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolBarHelper::title(Text::_('COM_JEM_EVENTS'), 'events');
        $toolbar = $this->getToolbarInstance();

        /* retrieving the allowed actions for the user */
        $canDo = JemHelperBackend::getActions(0);
        $canChangeState = $canDo->get('core.edit.state') || $canDo->get('core.admin');
        $canDelete = $canDo->get('core.delete');
        $showActionDropdown = $canChangeState || ($this->state->get('filter_state') == -2 && $canDelete);

        /* create */
        if (($canDo->get('core.create'))) {
            ToolBarHelper::addNew('event.add');
        }

        /* edit */
        if (($canDo->get('core.edit'))) {
            ToolBarHelper::editList('event.edit');
            ToolBarHelper::divider();
        }

        /* state */
        if ($showActionDropdown && $this->supportsToolbarDropdown($toolbar)) {
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

            if ($this->state->get('filter_state') == -2 && $canDelete) {
                $childBar->delete('events.delete', 'JTOOLBAR_EMPTY_TRASH')
                    ->message('COM_JEM_CONFIRM_DELETE')
                    ->listCheck(true);
            } elseif ($canChangeState) {
                $childBar->trash('events.trash')->listCheck(true);
            }
        } elseif ($showActionDropdown) {
            if ($canChangeState && $this->state->get('filter_state') != 2) {
                ToolbarHelper::publishList('events.publish');
                ToolbarHelper::unpublishList('events.unpublish');
                ToolbarHelper::custom('events.featured', 'featured.png', 'featured_f2.png', 'JFEATURED', true);
            }

            if ($canChangeState && $this->state->get('filter_state') != -1) {
                if ($this->state->get('filter_state') != 2) {
                    ToolbarHelper::archiveList('events.archive');
                } elseif ($this->state->get('filter_state') == 2) {
                    ToolbarHelper::publishList('events.publish', 'JTOOLBAR_UNARCHIVE');
                }
            }

            if ($canChangeState) {
                ToolbarHelper::checkin('events.checkin');
            }

            if ($this->state->get('filter_state') == -2 && $canDelete) {
                ToolbarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'events.delete', 'JTOOLBAR_EMPTY_TRASH');
            } elseif ($canChangeState) {
                ToolbarHelper::trash('events.trash');
            }
        }

        ToolBarHelper::divider();
        ToolBarHelper::help('listevents', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/events');
    }
}
?>
