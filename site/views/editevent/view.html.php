<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * HTML View class for the EditeventView
 *
 * @package JEM
 *
 */
class JEMViewEditevent extends JViewLegacy
{
	/**
	 * Creates the output for event submissions
	 */
	function display($tpl=null)
	{
		$this->addTemplatePath(JPATH_COMPONENT.'/common/views/tmpl');

		$app 		= JFactory::getApplication();
		$session 	= JFactory::getSession();
		$user 		= JFactory::getUser();

		//redirect if not logged in
		if (!$user->get('id')) {
			$app->enqueueMessage(JText::_('COM_JEM_NEED_LOGGED_IN'), 'error');
			return false;
		}

		if($this->getLayout() == 'choosevenue') {
			$this->_displaychoosevenue($tpl);
			return;
		}

		// Initialize variables
		$editor 	= JFactory::getEditor();
		$doc 		= JFactory::getDocument();
		$jemsettings = JEMHelper::config();
		$url 		= JURI::root();

		//Get Data from the model
		$row 		= $this->get('Event');

		//Cause of group limits we can't use class here to build the categories tree
		$categories = $this->get('Categories');

		//sticky form categorie data
		if ($session->has('eventform', 'com_jem')) {
			$eventform = $session->get('eventform', 0, 'com_jem');
			$selectedcats = $eventform['cid'];
		} else {
			$selectedcats = $this->get('Catsselected');
		}

		//build selectlists
		$categories = JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, 'multiple="multiple" size="8 class="inputbox required validate-cid"');
		//Get requests
		$id = JRequest::getInt('id');

		//Clean output
		JFilterOutput::objectHTMLSafe($row, ENT_QUOTES, 'datdescription');

		JHTML::_('behavior.formvalidation');
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal', 'a.flyermodal');
		jimport('joomla.html.pane');

		//add css file
		$doc->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$doc->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');
		$doc->addScript($url.'media/com_jem/js/seo.js');

		//Set page title
		$id ? $title = JText::_('COM_JEM_EDIT_EVENT') : $title = JText::_('COM_JEM_ADD_EVENT');

		$doc->setTitle($title);

		// Get the menu object of the active menu item
		$menu		= $app->getMenu();
		$item		= $menu->getActive();
		$params 	= $app->getParams('com_jem');

		//pathway
		$pathway 	= $app->getPathWay();
		if($item) $pathway->setItemName(1, $item->title);
		$pathway->addItem($title, '');

		//Has the user access to the editor and the add venue screen
		$editoruser = JEMUser::editoruser();
		$delloclink = JEMUser::validate_user($jemsettings->locdelrec, $jemsettings->deliverlocsyes);

		//transform <br /> and <br> back to \r\n for non editorusers
		if (!$editoruser) {
			$row->datdescription = JEMHelper::br2break($row->datdescription);
		}

		//Get image information
		$dimage = JEMImage::flyercreator($row->datimage, 'event');

		//Set the info image
		$infoimage = JHtml::_('image', 'com_jem/icon-16-hint.png',NULL,NULL,true);

		$js = "
		function elSelectVenue(id, venue) {
			document.getElementById('a_id').value = id;
			document.getElementById('a_name').value = venue;
			window.parent.SqueezeBox.close();
		}

		function closeAdd() {
			window.parent.SqueezeBox.close();
		}";

		$doc->addScriptDeclaration($js);
		// include the recurrence script
		$doc->addScript($url.'media/com_jem/js/recurrence.js');
		// include the unlimited script
		$doc->addScript($url.'media/com_jem/js/unlimited.js');

		$doc->addScript('media/com_jem/js/attachments.js');

		$lists = array();

		// recurrence type
		$rec_type = array();
		$rec_type[] = JHTML::_('select.option', 0, JText::_ ('COM_JEM_NOTHING'));
		$rec_type[] = JHTML::_('select.option', 1, JText::_ ('COM_JEM_DAYLY'));
		$rec_type[] = JHTML::_('select.option', 2, JText::_ ('COM_JEM_WEEKLY'));
		$rec_type[] = JHTML::_('select.option', 3, JText::_ ('COM_JEM_MONTHLY'));
		$rec_type[] = JHTML::_('select.option', 4, JText::_ ('COM_JEM_WEEKDAY'));
		$lists['recurrence_type'] = JHTML::_('select.genericlist', $rec_type, 'recurrence_type', '', 'value', 'text', $row->recurrence_type);

		//if only owned events are allowed
		if ($jemsettings->ownedvenuesonly) {
			$venues 		= $this->get('UserVenues');
			//build list
			$venuelist  	= array();
			$venuelist[]	= JHTML::_('select.option', '0', JText::_('COM_JEM_NO_VENUE'));
			$venuelist  	= array_merge($venuelist, $venues);

			$lists['venueselect'] = JHTML::_('select.genericlist', $venuelist, 'locid', 'size="1" class="inputbox"', 'value', 'text', $row->locid);
		}

		$this->row				= $row;
		$this->categories		= $categories;
		$this->editor			= $editor;
		$this->dimage			= $dimage;
		$this->infoimage		= $infoimage;
		$this->delloclink		= $delloclink;
		$this->editoruser		= $editoruser;
		$this->jemsettings		= $jemsettings;
		$this->item				= $item;
		$this->params			= $params;
		$this->lists			= $lists;
		$this->title			= $title;

		$access2 = JEMHelper::getAccesslevelOptions();
		$this->access			= $access2;

		parent::display($tpl);
	}

	/**
	 * Creates the output for the venue select listing
	 *
	 */
	function _displaychoosevenue($tpl)
	{
		$app = JFactory::getApplication();
		$jemsettings = JEMHelper::config();
		$document	= JFactory::getDocument();
		$jinput = JFactory::getApplication()->input;
		$limitstart = $jinput->get('limitstart','0','int');
		$limit				= $app->getUserStateFromRequest('com_jem.selectvenue.limit', 'limit', $jemsettings->display_num, 'int');

		$filter_order 		= $jinput->get('filter_order','l.venue','cmd');
		$filter_order_Dir	= $jinput->get('filter_order_Dir','ASC','word');
		$filter				= $jinput->get('filter_search','','string');
		$filter_type		= $jinput->get('filter_type','','int');

		// Get/Create the model
		$rows 	= $this->get('Venues');
		$total 	= $this->get('Countitems');

		JHTML::_('behavior.modal', 'a.flyermodal');

		// Create the pagination object
		jimport('joomla.html.pagination');
		$pagination = new JPagination($total, $limitstart, $limit);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] 	= $filter_order;

		$document->setTitle(JText::_('COM_JEM_SELECTVENUE'));
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');

		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_('COM_JEM_VENUE'));
		$filters[] = JHTML::_('select.option', '2', JText::_('COM_JEM_CITY'));
		$filters[] = JHTML::_('select.option', '3', JText::_('COM_JEM_STATE'));
		$searchfilter = JHTML::_('select.genericlist', $filters, 'filter_type', 'size="1" class="inputbox"', 'value', 'text', $filter_type);

		$this->rows				= $rows;
		$this->searchfilter		= $searchfilter;
		$this->pagination		= $pagination;
		$this->lists			= $lists;
		$this->filter			= $filter;

		parent::display($tpl);
	}
}
?>
