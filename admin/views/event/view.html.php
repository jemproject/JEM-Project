<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


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
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}
		JHtml::_('behavior.framework');
		JHtml::_('behavior.modal', 'a.modal');
		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.formvalidation');

		//initialise variables
		$jemsettings 	= JemHelper::config();
		$document		= JFactory::getDocument();
		$user 			= JemFactory::getUser();
		$this->settings	= JemAdmin::config();
		$task			= JFactory::getApplication()->input->get('task', '');
		$this->task 	= $task;
		$url 			= JUri::root();

		$categories 	= JemCategories::getCategoriesTree(1);
		$selectedcats 	= $this->get('Catsselected');

		$Lists = array();
		$Lists['category'] = JemCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, 'multiple="multiple" size="8"');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		if (version_compare(JVERSION, '3.0', 'lt')) {
			$style = 'select.required {'
					. 'background-color: #D5EEFF;'
					. '}';
			$document->addStyleDeclaration($style);
		}

		// Load scripts
		JHtml::_('script', 'com_jem/attachments.js', false, true);
		JHtml::_('script', 'com_jem/recurrence.js', false, true);
		JHtml::_('script', 'com_jem/unlimited.js', false, true);
		JHtml::_('script', 'com_jem/seo.js', false, true);

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
		JFactory::getApplication()->input->set('hidemainmenu', true);

		$user		= JemFactory::getUser();
		$isNew		= ($this->item->id == 0);
		$checkedOut	= !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
		$canDo		= JemHelperBackend::getActions();

		JToolBarHelper::title($isNew ? JText::_('COM_JEM_ADD_EVENT') : JText::_('COM_JEM_EDIT_EVENT'), 'eventedit');

		// If not checked out, can save the item.
		if (!$checkedOut && ($canDo->get('core.edit')||$canDo->get('core.create'))) {
			JToolBarHelper::apply('event.apply');
			JToolBarHelper::save('event.save');
		}
		if (!$checkedOut && $canDo->get('core.create')) {
			JToolBarHelper::save2new('event.save2new');
		}
		// If an existing item, can save to a copy.
		if (!$isNew && $canDo->get('core.create')) {
			JToolBarHelper::save2copy('event.save2copy');
		}

		if (empty($this->item->id))  {
			JToolBarHelper::cancel('event.cancel');
		} else {
			JToolBarHelper::cancel('event.cancel', 'JTOOLBAR_CLOSE');
		}

		JToolBarHelper::divider();
		JToolBarHelper::help('editevents', true);
	}
}
?>