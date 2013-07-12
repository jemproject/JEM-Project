<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class for the JEM user element screen
 *
 * @package JEM
 * @since 1.1
 */
class JEMViewUserElement extends JViewLegacy {

	public function display($tpl = null)
	{
		$mainframe = JFactory::getApplication();
		
		//initialise variables
		$document	=  JFactory::getDocument();
		$user 		=  JFactory::getUser();
		$jemsettings = JEMAdmin::config();
		$db = JFactory::getDBO();
		
		//get var
		$filter_order		= $mainframe->getUserStateFromRequest( 'com_jem.users.filter_order', 'filter_order', 'u.name', 'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( 'com_jem.users.filter_order_Dir', 'filter_order_Dir', '', 'word' );
		$search 			= $mainframe->getUserStateFromRequest( 'com_jem.users.search', 'search', '', 'string' );
		$search 			= $db->escape( trim(JString::strtolower( $search ) ) );
		
		//add css to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');
		
		$modelusers = JModelLegacy::getInstance('Users', 'JEMModel');
		
		$users = $modelusers->getData();
		$pagination = $modelusers->getPagination();
		
		//build selectlists
		$lists = array();
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->lists		= $lists;
		$this->rows			= $users;
		$this->jemsettings	= $jemsettings;
		$this->pagination	= $pagination;

		parent::display($tpl);
	}
}
?>