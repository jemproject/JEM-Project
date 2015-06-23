<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


/**
 * Eventelement-View
 */
class JemViewEventelement extends JViewLegacy {

	public function display($tpl = null)
	{
		$app = JFactory::getApplication();

		//initialise variables
		$user        = JemFactory::getUser();
		$db          = JFactory::getDBO();
		$jemsettings = JEMAdmin::config();
		$document    = JFactory::getDocument();
		$itemid      = $app->input->getInt('id', 0) . ':' . $app->input->getInt('Itemid', 0);

		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.modal');

		//get var
		$filter_order     = $app->getUserStateFromRequest('com_jem.eventelement.filter_order',     'filter_order', 'a.dates', 'cmd');
		$filter_order_Dir = $app->getUserStateFromRequest('com_jem.eventelement.filter_order_Dir', 'filter_order_Dir', '', 'word');
		$filter_type      = $app->getUserStateFromRequest('com_jem.eventelement.'.$itemid.'.filter_type',   'filter_type', '', 'int');
		$filter_state     = $app->getUserStateFromRequest('com_jem.eventelement.'.$itemid.'.filter_state',  'filter_state', '', 'string');
		$filter_search    = $app->getUserStateFromRequest('com_jem.eventelement.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$filter_search    = $db->escape(trim(JString::strtolower($filter_search)));

		//prepare the document
		$document->setTitle(JText::_('COM_JEM_SELECTEVENT'));

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		//Get data from the model
		$rows = $this->get('Data');
		$pagination = $this->get('Pagination');

		//publish unpublished filter
		//$lists['state']	= JHtml::_('grid.state', $filter_state);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		//Create the filter selectlist
		$filters = array();
		$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_EVENT_TITLE'));
		$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_VENUE'));
		$filters[] = JHtml::_('select.option', '3', JText::_('COM_JEM_CITY'));
		//$filters[] = JHtml::_('select.option', '4', JText::_('COM_JEM_CATEGORY'));
		$lists['filter'] = JHtml::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'inputbox'), 'value', 'text', $filter_type);

		// search filter
		$lists['search']= $filter_search;

		//assign data to template
		$this->lists 		= $lists;
		$this->filter_state = $filter_state;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->jemsettings 	= $jemsettings;
		$this->user 		= $user;

		parent::display($tpl);
	}
}
?>