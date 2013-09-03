<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class for the JEM categories screen
 *
 *
*/
class JEMViewCategories extends JViewLegacy {

	public function display($tpl = null)
	{
		$app = JFactory::getApplication();

		//initialise variables
		$user 		= JFactory::getUser();
		$db  		= JFactory::getDBO();
		$document	= JFactory::getDocument();

		JHTML::_('behavior.tooltip');

		//get vars
		$filter_order		= $app->getUserStateFromRequest('com_jem.categories.filter_order',		'filter_order', 	'c.catname', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.categories.filter_order_Dir',	'filter_order_Dir',	'', 'word');
		$filter_state 		= $app->getUserStateFromRequest('com_jem.categories.filter_state', 	'filter_state', 	'', 'string');
		$search 			= $app->getUserStateFromRequest('com_jem.categories.filter_search', 	'filter_search', 	'', 'string');
		$search 			= $db->escape(trim(JString::strtolower($search)));

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//Get data from the model
		$rows = $this->get('Data');
		$pagination = $this->get('Pagination');

		//publish unpublished filter
		$lists['state']	= $filter_state;
		// search filter
		$lists['search']= $search;

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		$ordering = ($lists['order'] == 'c.ordering');

		//assign data to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->ordering 	= $ordering;
		$this->user 		= $user;

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

		//create the toolbar
		JToolBarHelper::title(JText::_('COM_JEM_CATEGORIES'), 'elcategories');

		JToolBarHelper::addNew('categories.add');
		JToolBarHelper::spacer();
		JToolBarHelper::editList('categories.edit');
		JToolBarHelper::publishList('categories.publish');
		JToolBarHelper::unpublishList('categories.unpublish');
		JToolBarHelper::divider();
		JToolBarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'categories.remove', 'JACTION_DELETE');

		JToolBarHelper::help('listcategories', true);
	}
}
?>