<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class Event
 *
 * @package Joomla
 * @subpackage JEM
 *
 */
class JEMViewEvent extends JViewLegacy {

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
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}
		JHtml::_('behavior.modal', 'a.modal');
		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.formvalidation');

		//initialise variables
		$jemsettings 	= JEMHelper::config();
		$document		= JFactory::getDocument();
		$this->settings	= JEMAdmin::config();
		$task			= JRequest::getVar('task');
		$this->task 	= $task;
		$url 			= JURI::root();

		$categories 	= JEMCategories::getCategoriesTree(1);
		$selectedcats 	= $this->get('Catsselected');

		$Lists = array();
		$Lists['category'] = JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, 'multiple="multiple" size="8"');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Load scripts
		JHtml::_('script', 'com_jem/attachments.js', false, true);
		JHtml::_('script', 'com_jem/recurrence.js', false, true);
		JHtml::_('script', 'com_jem/unlimited.js', false, true);
		JHtml::_('script', 'com_jem/seo.js', false, true);

		$access2 = JEMHelper::getAccesslevelOptions();
		$this->access		= $access2;
		$this->jemsettings		= $jemsettings;
		$this->Lists 		= $Lists;

		$js = "
		function jResetHits(id) {
			document.getElementById('a_hits').value = id;
		}";

		$document->addScriptDeclaration($js);

		$this->resethits = "\n&nbsp;<input class=\"inputbox\" type=\"button\" onclick=\"jResetHits(0, '".JText::_('COM_JEM_NO_HITS')."');\" value=\"".JText::_('COM_JEM_NO_HITS')."\" onblur=\"seo_switch()\" />";

		$this->addToolbar();
		parent::display($tpl);

	}


	/**
	 * Add the page title and toolbar.
	 *
	 */
	protected function addToolbar()
	{
		JRequest::setVar('hidemainmenu', true);

		$user		= JFactory::getUser();
		$isNew		= ($this->item->id == 0);
		$checkedOut	= !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
		$canDo		= JEMHelperBackend::getActions();

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
