<?php
/**
 * @version    4.1.0
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
use \Joomla\CMS\Uri\Uri;

/**
 * View class Group
 *
 * @package    Joomla
 * @subpackage JEM
 *
 */
class JemViewGroup extends JemAdminView
{
	protected $form;
	protected $item;
	protected $state;

	public function display($tpl = null)
	{
		// Initialise variables.
		$app = Factory::getApplication();
		$document = $app->getDocument();
		$this->form	 = $this->get('Form');
		$this->item	 = $this->get('Item');
		$this->state = $this->get('State');

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			$app->enqueueMessage(implode("\n", $errors), 'error');
			return false;
		}

		// HTMLHelper::_('behavior.modal', 'a.modal');
		// HTMLHelper::_('behavior.tooltip');
		// HTMLHelper::_('behavior.formvalidation');

		//initialise variables
		$jemsettings = JemHelper::config();
		$this->settings	= JemAdmin::config();
		$task		= $app->input->get('task', '');
		$this->task = $task;
		$url 		= Uri::root();

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = $app->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
		$maintainers 		= $this->get('Members');
		$available_users 	= $this->get('Available');

		//make data safe
		JFilterOutput::objectHTMLSafe($this->item);

		//create selectlists
		$lists = array();
		$lists['maintainers']		= HTMLHelper::_('select.genericlist', $maintainers, 'maintainers[]', array('class'=>'inputbox','size'=>'20','onDblClick'=>'moveOptions(document.adminForm[\'maintainers[]\'], document.adminForm[\'available_users\'])', 'multiple'=>'multiple', 'style'=>'padding: 6px; width: 98%;'), 'value', 'text');
		$lists['available_users']	= HTMLHelper::_('select.genericlist', $available_users, 'available_users', array('class'=>'inputbox','size'=>'20','onDblClick'=>'moveOptions(document.adminForm[\'available_users\'], document.adminForm[\'maintainers[]\'])', 'multiple'=>'multiple','style'=>'padding: 6px; width: 98%;'), 'value', 'text');

		$this->jemsettings		= $jemsettings;
		$this->lists 		= $lists;

		$this->addToolbar();
		parent::display($tpl);
	}


	/**
	 * Add the page title and toolbar.
	 *
	 */
	protected function addToolbar()
	{
		Factory::getApplication()->input->set('hidemainmenu', true);

		$user		= JemFactory::getUser();
		$isNew		= ($this->item->id == 0);
		$checkedOut	= !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
		$canDo		= JemHelperBackend::getActions();

		ToolbarHelper::title($isNew ? Text::_('COM_JEM_GROUP_ADD') : Text::_('COM_JEM_GROUP_EDIT'), 'groupedit');

		// If not checked out, can save the item.
		if (!$checkedOut && ($canDo->get('core.edit')||$canDo->get('core.create'))) {
			ToolbarHelper::apply('group.apply');
			ToolbarHelper::save('group.save');
		}
		if (!$checkedOut && $canDo->get('core.create')) {
			ToolbarHelper::save2new('group.save2new');
		}
		// If an existing item, can save to a copy.
		if (!$isNew && $canDo->get('core.create')) {
			ToolbarHelper::save2copy('group.save2copy');
		}

		if (empty($this->item->id))  {
			ToolbarHelper::cancel('group.cancel');
		} else {
			ToolbarHelper::cancel('group.cancel', 'JTOOLBAR_CLOSE');
		}

		ToolbarHelper::divider();
		ToolbarHelper::inlinehelp();
		ToolBarHelper::help('editgroup', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/groups/add-group');
	}
}
?>
