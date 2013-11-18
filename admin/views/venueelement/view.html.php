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
 * View class for the JEM venueselect screen
 *
 * @package JEM
 *
 */
class JEMViewVenueelement extends JViewLegacy {

	public function display($tpl = null)
	{
		$app = JFactory::getApplication();

		//initialise variables
		$db			= JFactory::getDBO();
		$document	= JFactory::getDocument();

		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.modal');

		//get vars
		$filter_order		= $app->getUserStateFromRequest('com_jem.venueelement.filter_order', 'filter_order', 'l.ordering', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.venueelement.filter_order_Dir', 'filter_order_Dir', '', 'word');
		$filter 			= $app->getUserStateFromRequest('com_jem.venueelement.filter', 'filter', '', 'int');
		$filter_state 		= $app->getUserStateFromRequest('com_jem.venueelement.filter_state', 'filter_state', '*', 'word');
		$search 			= $app->getUserStateFromRequest('com_jem.venueelement.filter_search', 'filter_search', '', 'string');
		$search 			= $db->escape(trim(JString::strtolower($search)));

		//prepare document
		$document->setTitle(JText::_('COM_JEM_SELECTVENUE'));
		
		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Get data from the model
		$rows = $this->get('Data');

		// add pagination
		$pagination = $this->get('Pagination');

		//publish unpublished filter
		$lists['state']	= JHtml::_('grid.state', $filter_state);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		//Build search filter
		$filters = array();
		$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_VENUE'));
		$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_CITY'));
		$filters[] = JHtml::_('select.option', '3', JText::_('COM_JEM_STATE'));
		$lists['filter'] = JHtml::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter);

		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->lists		= $lists;
		$this->rows			= $rows;
		$this->pagination	= $pagination;

		parent::display($tpl);
	}
}
?>