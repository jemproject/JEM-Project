<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */

defined('_JEXEC') or die;


/**
 * View class for the Venues screen
 *
 * @package Joomla
 * @subpackage JEM
 *
 */

 class JEMViewVenues extends JViewLegacy {

	protected $items;
	protected $pagination;
	protected $state;

	public function display($tpl = null)
	{
		$user 		= JFactory::getUser();
		$document	= JFactory::getDocument();
		$url 		= JURI::root();
		$settings 	= JEMHelper::globalattribs();

		// Initialise variables.
		$this->items		= $this->get('Items');
		$this->pagination	= $this->get('Pagination');
		$this->state		= $this->get('State');
		$this->settings		= $settings;

		$params = $this->state->get('params');

		// highlighter
		$highlighter = $settings->get('highlight','0');

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		JHtml::_('behavior.framework');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Add Scripts
		$document->addScript('http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');
		$document->addCustomTag('<script type="text/javascript">jQuery.noConflict();</script>');

		if ($highlighter) {
			$document->addScript($url.'media/com_jem/js/highlighter.js');
			$style = '
			    .red a:link, .red a:visited, .red a:active {
			        color:red;}
			    ';
			$document->addStyleDeclaration($style);
		}

		//add style to description of the tooltip (hastip)
		JHtml::_('behavior.tooltip');

		// add filter selection for the search
		$filters = array();
		$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_VENUE'));
		$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_CITY'));
		$filters[] = JHtml::_('select.option', '3', JText::_('COM_JEM_STATE'));
		$filters[] = JHtml::_('select.option', '4', JText::_('COM_JEM_COUNTRY'));
		$filters[] = JHtml::_('select.option', '5', JText::_('JALL'));
		$lists['filter'] = JHtml::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $this->state->get('filter'));

		//assign data to template
		$this->lists = $lists;
		$this->user = $user;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
		}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		require_once JPATH_COMPONENT . '/helpers/helper.php';
		JToolBarHelper::title(JText::_('COM_JEM_VENUES'), 'venues');

		$canDo = JEMHelperBackend::getActions(0);

		/* create */
		if (($canDo->get('core.create'))) {
			JToolBarHelper::addNew('venue.add');
		}

		/* edit */
		if (($canDo->get('core.edit'))) {
			JToolBarHelper::editList('venue.edit');
			JToolBarHelper::divider();
		}

		/* state */
		if ($canDo->get('core.edit.state')) {
			if ($this->state->get('filter.state') != 2) {
				JToolBarHelper::publishList('venues.publish');
				JToolBarHelper::unpublishList('venues.unpublish');
				JToolBarHelper::divider();
			}
		}

		if ($canDo->get('core.edit.state')) {
			JToolBarHelper::checkin('venues.checkin');
		}

		/* delete-trash */
		if ($canDo->get('core.delete')) {
			JToolBarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'venues.remove', 'JACTION_DELETE');
		}

		JToolBarHelper::divider();
		JToolBarHelper::help('listvenues', true);
	}
}
?>