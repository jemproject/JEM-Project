<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die();

/**
 * Editevent-View         
 */
class JEMViewEditevent extends JViewLegacy
{
	protected $form;
	protected $item;
	protected $return_page;
	protected $state;

	public function display($tpl = null)
	{
		if ($this->getLayout() == 'choosevenue') {
			$this->_displaychoosevenue($tpl);
			return;
		}
		
		if ($this->getLayout() == 'choosecontact') {
			$this->_displaychoosecontact($tpl);
			return;
		}
		
		// Initialise variables.
		$app = JFactory::getApplication();
		$jemsettings	= JEMHelper::config();
		$user = JFactory::getUser();
		$document = JFactory::getDocument();
		$url = JURI::root();
		
		// Get model data.
		$this->state = $this->get('State');
		$this->item = $this->get('Item');
		
		// Create a shortcut for $item.
		$item = &$this->item;
		$this->form = $this->get('Form');
		$this->return_page = $this->get('ReturnPage');
		
		// check for guest
		if ($user->id == 0 || $user == false) {
			$app->enqueueMessage(JText::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}
		
		// Merge event params. If this is single-event view, menu params override event params
		// Otherwise, event params override menu item params
		$this->params	= $this->state->get('params');
		$active	= $app->getMenu()->getActive();
		$temp	= clone ($this->params);
		
		// Check to see which parameters should take priority
		if ($active) {
			$currentLink = $active->link;
			// If the current view is the active item and an event view for this event, then the menu item params take priority
			if (strpos($currentLink, 'view=editevent')) {
				// $item->params are the article params, $temp are the menu item params
				// Merge so that the menu item params take priority
				$item->params->merge($temp);
				// Load layout from active query (in case it is an alternative menu item)
				//if (isset($active->query['layout'])) {
				//	$this->setLayout($active->query['layout']);
				//}
			}
			else {
				// Current view is not a single event, so the event params take priority here
				// Merge the menu item params with the event params so that the event params take priority
				$temp->merge($item->params);
				$item->params = $temp;
		
				// Check for alternative layouts (since we are not in a single-event menu item)
				// Single-event menu item layout takes priority over alt layout for an event
				if ($layout = $item->params->get('event_layout')) {
					$this->setLayout($layout);
				}
			}
		}
		else {
			// Merge so that event params take priority
			$temp->merge($item->params);
			$item->params = $temp;
			// Check for alternative layouts (since we are not in a single-event menu item)
			// Single-event menu item layout takes priority over alt layout for an event
			if ($layout = $item->params->get('event_layout')) {
				$this->setLayout($layout);
			}
		}

		if (empty($this->item->id)) {
			// Check if the user has access to the form
			$maintainer = JEMUser::ismaintainer('add');
			$genaccess 	= JEMUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes );
			
			if ($maintainer || $genaccess ) {
				$dellink = true;
			} else {
				$dellink = false;
			}
			$authorised = $user->authorise('core.create','com_jem') || (count($user->getAuthorisedCategories('com_jem', 'core.create')) || $dellink);			
		} else {
			// Check if user can edit
			$maintainer5 = JEMUser::ismaintainer('edit',$this->item->id);
			$genaccess5 = JEMUser::editaccess($jemsettings->eventowner, $this->item->created_by, $jemsettings->eventeditrec, $jemsettings->eventedit);
	
			if ($maintainer5 || $genaccess5 )
			{
				$allowedtoeditevent = true;
			} else {
				$allowedtoeditevent = false;
			}
			
			$authorised = $this->item->params->get('access-edit') || $allowedtoeditevent ;
		}
		
		if ($authorised !== true) {
			$app->enqueueMessage(JText::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}
		
		if (!empty($this->item) && isset($this->item->id)) {
			// $this->item->images = json_decode($this->item->images);
			// $this->item->urls = json_decode($this->item->urls);
			
			$tmp = new stdClass();
			// $tmp->images = $this->item->images;
			// $tmp->urls = $this->item->urls;
			$this->form->bind($tmp);
		}
		
		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseWarning(500, implode("\n", $errors));
			return false;
		}
		
		JHtml::_('behavior.formvalidation');
		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.modal', 'a.flyermodal');
		
		// Create a shortcut to the parameters.
		$params = &$this->state->params;
		
		//
		$access2 = JEMHelper::getAccesslevelOptions();
		$this->access = $access2;
		
		// add css file
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
		
		// Load scripts
		$document->addScript(JURI::root() . 'media/com_jem/js/attachments.js');
		$document->addScript($url . 'media/com_jem/js/recurrence.js');
		$document->addScript($url . 'media/com_jem/js/unlimited.js');
		$document->addScript($url . 'media/com_jem/js/seo.js');
		
		// Escape strings for HTML output
		$this->pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));
		$this->dimage = JEMImage::flyercreator($this->item->datimage, 'event');
		$this->jemsettings = $jemsettings;
		$this->infoimage = JHtml::_('image', 'com_jem/icon-16-hint.png', JText::_('COM_JEM_NOTES'), NULL, true);
		
		$this->params = $params;
		$this->user = $user;
		
		if ($params->get('enable_category') == 1) {
			$this->form->setFieldAttribute('catid', 'default', $params->get('catid', 1));
			$this->form->setFieldAttribute('catid', 'readonly', 'true');
		}
		$this->_prepareDocument();
		parent::display($tpl);
	}

	/**
	 * Prepares the document
	 */
	protected function _prepareDocument()
	{
		$app = JFactory::getApplication();
		$menus = $app->getMenu();
		$pathway = $app->getPathway();
		$title = null;
		
		// Because the application sets a default page title,
		// we need to get it from the menu item itself
		$menu = $menus->getActive();
		if ($menu) {
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
		}
		else {
			$this->params->def('page_heading', JText::_('COM_JEM_EDITEVENT_EDIT_EVENT'));
		}
		
		$title = $this->params->def('page_title', JText::_('COM_JEM_EDITEVENT_EDIT_EVENT'));
		if ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
		}
		$this->document->setTitle($title);
		
		$pathway = $app->getPathWay();
		$pathway->addItem($title, '');
		
		if ($this->params->get('menu-meta_description')) {
			$this->document->setDescription($this->params->get('menu-meta_description'));
		}
		
		if ($this->params->get('menu-meta_keywords')) {
			$this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
		}
		
		if ($this->params->get('robots')) {
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}
	}

	/**
	 * Creates the output for the venue select listing
	 */
	function _displaychoosevenue($tpl)
	{
		$app = JFactory::getApplication();
		$jemsettings = JEMHelper::config();
		$document = JFactory::getDocument();
		$jinput = JFactory::getApplication()->input;
		$limitstart = $jinput->get('limitstart', '0', 'int');
		$limit = $app->getUserStateFromRequest('com_jem.selectvenue.limit', 'limit', $jemsettings->display_num, 'int');
		
		$filter_order = $jinput->get('filter_order', 'l.venue', 'cmd');
		$filter_order_Dir = $jinput->get('filter_order_Dir', 'ASC', 'word');
		$filter = $jinput->get('filter_search', '', 'string');
		$filter_type = $jinput->get('filter_type', '', 'int');
		
		// Get/Create the model
		$rows = $this->get('Venues');
		$total = $this->get('Countitems');
		
		JHtml::_('behavior.modal', 'a.flyermodal');
		
		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		$document->setTitle(JText::_('COM_JEM_SELECT_VENUE'));
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
		
		$filters = array();
		$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_VENUE'));
		$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_CITY'));
		$filters[] = JHtml::_('select.option', '3', JText::_('COM_JEM_STATE'));
		$searchfilter = JHtml::_('select.genericlist', $filters, 'filter_type', 'size="1" class="inputbox"', 'value', 'text', $filter_type);
		
		$this->rows = $rows;
		$this->searchfilter = $searchfilter;
		$this->pagination = $pagination;
		$this->lists = $lists;
		$this->filter = $filter;
		
		parent::display($tpl);
	}
	
	
	/**
	 * Creates the output for the contact select listing
	 */
	function _displaychoosecontact($tpl)
	{
		
		$app = JFactory::getApplication();
		$jinput = JFactory::getApplication()->input;
		$jemsettings = JEMHelper::config();

		//initialise variables
		$db			= JFactory::getDBO();
		$document	= JFactory::getDocument();

		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.modal', 'a.flyermodal');

		//get vars
		$filter_order		= $app->getUserStateFromRequest('com_jem.contactelement.filter_order', 'filter_order', 'con.name', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.contactelement.filter_order_Dir', 'filter_order_Dir', '', 'word');
		$filter 			= $app->getUserStateFromRequest('com_jem.contactelement.filter', 'filter', '', 'int');
		$filter_state 		= $app->getUserStateFromRequest('com_jem.contactelement.filter_state', 'filter_state', '*', 'word');
		$search 			= $app->getUserStateFromRequest('com_jem.contactelement.filter_search', 'filter_search', '', 'string');
		$search 			= $db->escape(trim(JString::strtolower($search)));
		
		$limitstart = $jinput->get('limitstart', '0', 'int');
		$limit = $app->getUserStateFromRequest('com_jem.selectcontact.limit', 'limit', $jemsettings->display_num, 'int');
		
		// Load css
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
		
		$document->setTitle(JText::_('COM_JEM_SELECT_CONTACT'));
		
		// Get/Create the model
		$rows = $this->get('Contact');
		$total = $this->get('CountContactitems');
		
		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);
		
		//publish unpublished filter
		$lists['state'] = JHtml::_('grid.state', $filter_state);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		//Build search filter
		$filters = array();
		$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_NAME'));
		$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_ADDRESS'));
		$filters[] = JHtml::_('select.option', '3', JText::_('COM_JEM_CITY'));
		$filters[] = JHtml::_('select.option', '4', JText::_('COM_JEM_STATE'));
		$searchfilter = JHtml::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter);

		// search filter
		$lists['search']= $search;

		//assign data to template
		$this->searchfilter = $searchfilter;
		$this->lists		= $lists;
		$this->rows			= $rows;
		$this->pagination	= $pagination;
	
		parent::display($tpl);
	}
		
}
?>