<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
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
		//initialise variables
        $app      = Factory::getApplication();
        $document = $app->getDocument();
		$jinput   = $app->input;

		$this->jemsettings = JemHelper::config();

        $wa = $app->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		// Load the form validation behavior
		// HTMLHelper::_('behavior.formvalidation');

		//get id register user for event
		$id = $jinput->getInt('id', 0);
		$this->event = $jinput->getInt('eventid', 0);

		// Load css
		$wa = $app->getDocument()->getWebAssetManager();
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		//Get data from the model
		$row = $this->get('Data');

		//build selectlists
		$lists = array();
		// TODO: On J! 2.5 we need last param 0 because it defaults to 1 activating a useless feature.
		//       On J! 3.x this param and the useless feature has been removed so we should remove last param.
		//       Such changes are of sort "grrr".
		$lists['users'] = HTMLHelper::_('list.users', 'uid', $row->uid, false, NULL, 'name', 0);

		//assign data to template
		$this->lists 	= $lists;
		$this->row		= $row;

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
		$cid        = Factory::getApplication()->input->get('cid', array(), 'array');
		$user       = JemFactory::getUser();
		$checkedOut = false; // don't know, table hasn't such a field
		$canDo      = JemHelperBackend::getActions();

		if (empty($cid[0])) {
			ToolbarHelper::title(Text::_('COM_JEM_ADD_ATTENDEE'), 'users');
		} else {
			ToolbarHelper::title(Text::_('COM_JEM_EDIT_ATTENDEE'), 'users');
		}

		// If not checked out, can save the item.
		if (!$checkedOut && ($canDo->get('core.edit')||$canDo->get('core.create'))) {
			ToolbarHelper::apply('attendee.apply');
			ToolbarHelper::save('attendee.save');
		}

		if (!$checkedOut && $canDo->get('core.create')) {
			ToolbarHelper::save2new('attendee.save2new');
		}

		// If an existing item, can save to a copy.
		if (!empty($cid[0]) && $canDo->get('core.create')) {
			ToolbarHelper::save2copy('attendee.save2copy');
		}

		if (empty($cid[0])) {
			ToolbarHelper::cancel('attendee.cancel');
		} else {
			ToolbarHelper::cancel('attendee.cancel', 'JTOOLBAR_CLOSE');
		}

		ToolbarHelper::divider();
		ToolbarHelper::help('editattendee', true);
	}
}
