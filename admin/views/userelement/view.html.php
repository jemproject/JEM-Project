<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class for the JEM userelement screen
 *
 * @package JEM
 *
 */
class JEMViewUserElement extends JViewLegacy {

	public function display($tpl = null)
	{
		$app = JFactory::getApplication();

		// initialise variables
		$document	= JFactory::getDocument();
		$jemsettings = JEMAdmin::config();
		$db = JFactory::getDBO();

		// get var
		$filter_order		= $app->getUserStateFromRequest('com_jem.userelement.filter_order', 'filter_order', 'u.name', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.userelement.filter_order_Dir', 'filter_order_Dir', '', 'word');
		$search 			= $app->getUserStateFromRequest('com_jem.userelement.filter_search', 'filter_search', '', 'string');
		$search 			= $db->escape(trim(JString::strtolower($search)));

		// prepare the document
		$document->setTitle(JText::_('COM_JEM_SELECTATTENDEE'));
		
		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Get data from the model
		$users			= $this->get('Data');
		$pagination 	= $this->get('Pagination');

		// build selectlists
		$lists = array();
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		// search filter
		$lists['search']= $search;

		// assign data to template
		$this->lists		= $lists;
		$this->rows			= $users;
		$this->jemsettings	= $jemsettings;
		$this->pagination	= $pagination;

		parent::display($tpl);
	}
}
?>