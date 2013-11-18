<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport( 'joomla.application.component.view');

/**
 * View class for the JEM categoryelement screen
 *
 * @package JEM
 *
 */
class JEMViewCategoryelement extends JViewLegacy {

	public function display($tpl = null)
	{
		//initialise variables
		$document	= JFactory::getDocument();
		$db			= JFactory::getDBO();
		$app 		= JFactory::getApplication();

		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.modal');

		//get vars
		$filter_order		= $app->getUserStateFromRequest('com_jem.categoryelement.filter_order', 'filter_order', 'c.ordering', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.categoryelement.filter_order_Dir',	'filter_order_Dir',	'', 'word');
		$filter_state 		= $app->getUserStateFromRequest('com_jem.categoryelement.filter_state', 'filter_state', '*', 'word');
		$search 			= $app->getUserStateFromRequest('com_jem.categoryelement.filter_search', 'filter_search', '', 'string');
		$search 			= $db->escape(trim(JString::strtolower($search)));

		//prepare document
		$document->setTitle(JText::_('COM_JEM_SELECT_CATEGORY'));
		
		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Get data from the model
		$rows = $this->get('Data');
		$pagination = $this->get('Pagination');

		//publish unpublished filter
		$lists['state'] = JHtml::_('grid.state', $filter_state);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;

		parent::display($tpl);
	}
}
?>