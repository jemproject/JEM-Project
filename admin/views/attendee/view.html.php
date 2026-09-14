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
use Joomla\CMS\MVC\View\HtmlView;
/**
 * View class: Attendee
 */
class JemViewAttendee extends HtmlView {

    public function display($tpl = null)
    {
        if (!JemHelperBackend::canManage('jem.attendees.manage')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        //initialise variables
        $app      = Factory::getApplication();
        $jinput   = $app->input;

        $this->jemsettings = JemHelper::config();

        //get id register user for event
        $id = $jinput->getInt('id', 0);
        $this->event = $jinput->getInt('eventid', 0);

        //Get data from the model
        $row = $this->get('Data');

        //build selectlists
        $lists = array();
        $lists['users'] = HTMLHelper::_('list.users', 'uid', $row->uid, false, NULL, 'name', 0);

        //assign data to template
        $this->lists     = $lists;
        $this->row        = $row;

        // add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }


    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        //get vars
        $user       = JemFactory::getUser();
        $checkedOut = false; // don't know, table hasn't such a field
        $canManage  = JemHelperBackend::canManage('jem.attendees.manage');
        $isNew      = empty($this->row->id);

        if ($isNew) {
            ToolbarHelper::title(Text::_('COM_JEM_ADD_ATTENDEE'), 'users');
        } else {
            ToolbarHelper::title(Text::_('COM_JEM_EDIT_ATTENDEE'), 'users');
        }

        // If not checked out, can save the item.
        if (!$checkedOut && $canManage) {
            ToolbarHelper::apply('attendee.apply');
            ToolbarHelper::save('attendee.save');
        }

        if (!$checkedOut && $canManage) {
            ToolbarHelper::save2new('attendee.save2new');
        }

        // If an existing item, can save to a copy.
        if (!$isNew && $canManage) {
            ToolbarHelper::save2copy('attendee.save2copy');
        }

        if ($isNew) {
            ToolbarHelper::cancel('attendee.cancel');
        } else {
            ToolbarHelper::cancel('attendee.cancel', 'JTOOLBAR_CLOSE');
        }

        ToolbarHelper::divider();
        ToolbarHelper::help('editattendee', true, 'https://www.joomlaeventmanager.net/documentation/backend/events/registered-users');
    }
}
