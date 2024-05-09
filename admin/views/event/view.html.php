<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Event View
 */
class JemViewEvent extends JemAdminView
{
	protected $form;
	protected $item;
	protected $state;

	public function display($tpl = null)
	{
		// Initialise variables.
		$this->form	 = $this->get('Form');
		$this->item	 = $this->get('Item');
		$this->state = $this->get('State');

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
			return false;
		}

		//initialise variables
		$jemsettings 	= JemHelper::config();
		$app            = Factory::getApplication();
		$this->document = $app->getDocument();
		$user 			= JemFactory::getUser();
		$this->settings	= JemAdmin::config();
		$task			= $app->input->get('task', '');
		$this->task 	= $task;
		$uri            = Uri::getInstance();
		$url 			= $uri->root();

		$categories 	= JemCategories::getCategoriesTree(1);
		$selectedcats 	= $this->get('Catsselected');

		$Lists = array();
		$Lists['category'] = JemCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, 'multiple="multiple" size="8"');

		// Load css
		$wa = $app->getDocument()->getWebAssetManager();
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		// Load scripts
		$wa->useScript('jquery');
		$wa->registerScript('jem.attachments', 'com_jem/attachments.js')->useScript('jem.attachments');
		$wa->registerScript('jem.recurrence', 'com_jem/recurrence.js')->useScript('jem.recurrence');
		$wa->registerScript('jem.unlimited', 'com_jem/unlimited.js')->useScript('jem.unlimited');
		$wa->registerScript('jem.seo', 'com_jem/seo.js')->useScript('jem.seo');
		

		// JQuery noConflict
		//$document->addCustomTag('<script type="text/javascript">jQuery.noConflict();</script>');
		//$document->addScript('https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');
		//$document->addScript('https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js');

		$access2 			= JemHelper::getAccesslevelOptions();
		$this->access		= $access2;
		$this->jemsettings	= $jemsettings;
		$this->Lists 		= $Lists;

		$this->addToolbar();
		parent::display($tpl);
	}


	/**
	 * Add the page title and toolbar.
	 */
	protected function addToolbar()
	{
		Factory::getApplication()->input->set('hidemainmenu', true);

		$user		= JemFactory::getUser();
		$isNew		= ($this->item->id == 0);
		$checkedOut	= !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
		$canDo		= JemHelperBackend::getActions();

		ToolBarHelper::title($isNew ? Text::_('COM_JEM_ADD_EVENT') : Text::_('COM_JEM_EDIT_EVENT'), 'eventedit');

		// If not checked out, can save the item.
		if (!$checkedOut && ($canDo->get('core.edit')||$canDo->get('core.create'))) {
			ToolBarHelper::apply('event.apply');
			ToolBarHelper::save('event.save');
		}
		if (!$checkedOut && $canDo->get('core.create')) {
			ToolBarHelper::save2new('event.save2new');
		}
		// If an existing item, can save to a copy.
		if (!$isNew && $canDo->get('core.create')) {
			ToolBarHelper::save2copy('event.save2copy');
		}

		if (empty($this->item->id))  {
			ToolBarHelper::cancel('event.cancel');
		} else {
			ToolBarHelper::cancel('event.cancel', 'JTOOLBAR_CLOSE');
		}

		ToolBarHelper::divider();
		ToolbarHelper::inlinehelp();
		ToolBarHelper::help('editevents', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/events/add-event');
	}
}
?>
