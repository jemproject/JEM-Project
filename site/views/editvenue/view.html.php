<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


/**
 * Editevents View
 */
class JEMViewEditvenue extends JViewLegacy
{
	protected $form;
	protected $item;
	protected $return_page;
	protected $state;


	/**
	 * Creates the output for venue submissions
	 */
	function display( $tpl=null )
	{
		//$this->addTemplatePath(JPATH_COMPONENT.'/common/views/tmpl');

		// Initialise variables.
		$app 			= JFactory::getApplication();
		$jemsettings	= JemHelper::config();
		$user			= JFactory::getUser();
		$document 		= JFactory::getDocument();
		$url 			= JURI::root();
		$editor 		= JFactory::getEditor();
		$doc 			= JFactory::getDocument();

		// Get requests
		$id				= JRequest::getInt('id');

		// Get model data.
		$this->state 	= $this->get('State');
		$this->item 	= $this->get('Item');

		// Create a shortcut for $item.
		$item = $this->item;
		$this->form = $this->get('Form');
		$this->return_page = $this->get('ReturnPage');

		if (empty($this->item->id)){
			$authorised = $user->authorise('core.create', 'com_jem');
		}else{
			$authorised = $this->item->params->get('access-edit');
		}

		// check for guest
		if ($user->id == 0 || $user == false) {
			$app->enqueueMessage(JText::_('JERROR_ALERTNOAUTHOR'), 'error');
			return false;
		}

		// Merge venue params. If this is single-venue view, menu params override venue params
		// Otherwise, venue params override menu item params
		$this->params	= $this->state->get('params');
		$active	= $app->getMenu()->getActive();
		$temp	= clone ($this->params);

		// Check to see which parameters should take priority
		if ($active) {
			$currentLink = $active->link;
			// If the current view is the active item and an venue view for this event, then the menu item params take priority
			if (strpos($currentLink, 'view=editvenue')) {
				// $item->params are the venue params, $temp are the menu item params
				// Merge so that the menu item params take priority
				$item->params->merge($temp);
				// Load layout from active query (in case it is an alternative menu item)
				//if (isset($active->query['layout'])) {
				//	$this->setLayout($active->query['layout']);
				//}
			}
			else {
				// Current view is not a single venue, so the venue params take priority here
				// Merge the menu item params with the venue params so that the venue params take priority
				$temp->merge($item->params);
				$item->params = $temp;

				// Check for alternative layouts (since we are not in a single-venue menu item)
				// Single-venue menu item layout takes priority over alt layout for an venue
				if ($layout = $item->params->get('venue_layout')) {
					$this->setLayout($layout);
				}
			}
		}
		else {
			// Merge so that venue params take priority
			$temp->merge($item->params);
			$item->params = $temp;
			// Check for alternative layouts (since we are not in a single-venue menu item)
			// Single-venue menu item layout takes priority over alt layout for an venue
			if ($layout = $item->params->get('venue_layout')) {
				$this->setLayout($layout);
			}
		}

		if (empty($this->item->id)) {
			$maintainer 	= JEMUser::venuegroups('add');
			$delloclink 	= JEMUser::validate_user($jemsettings->locdelrec, $jemsettings->deliverlocsyes);

			if ($maintainer || $delloclink) {
				$dellink = true;
			} else {
				$dellink = false;
			}
			$authorised = $user->authorise('core.create','com_jem') || $dellink;

		} else {
			// Check if user can edit
			$maintainer 	= JEMUser::venuegroups('edit');
			$genaccess 		= JEMUser::editaccess($jemsettings->venueowner, $this->item->created_by, $jemsettings->venueeditrec, $jemsettings->venueedit);
			if ($maintainer || $genaccess) {
				$edit = true;
			} else {
				$edit = false;
			}
			$authorised = $this->item->params->get('access-edit') || $edit;
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

		JHtml::_('behavior.framework');
		JHtml::_('behavior.formvalidation');
		JHtml::_('behavior.tooltip');

		// Load css
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
		JHtml::_('stylesheet', 'com_jem/geostyle.css', array(), true);

		$doc->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		JHtml::_('script', 'com_jem/attachments.js', false, true);
		$doc->addScript('http://maps.googleapis.com/maps/api/js?sensor=false&amp;libraries=places');

		// Noconflict
		$doc->addCustomTag( '<script type="text/javascript">jQuery.noConflict();</script>' );

		// JQuery scripts
		$doc->addScript('http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js');
		JHtml::_('script', 'com_jem/jquery.geocomplete.js', false, true);

		// Get the menu object of the active menu item
		$menu		= $app->getMenu();
		$item		= $menu->getActive();
		$params 	= $app->getParams('com_jem');

		$this->pageclass_sfx	= htmlspecialchars($params->get('pageclass_sfx'));
		$this->jemsettings		= $jemsettings;
		$this->limage 			= JEMImage::flyercreator($this->item->locimage, 'venue');
		$this->infoimage		= JHtml::_('image', 'com_jem/icon-16-hint.png', JText::_('COM_JEM_NOTES'), NULL, true);

		$this->params = $params;
		$this->user = $user;
		$this->_prepareDocument();

		$access2 		= JemHelper::getAccesslevelOptions();
		$this->access	= $access2;

		parent::display($tpl);
	}


	/**
	 * Prepares the document
	 */
	protected function _prepareDocument()
	{
		$app		= JFactory::getApplication();
		$menus		= $app->getMenu();
		$title 		= null;

		// Because the application sets a default page title,
		// we need to get it from the menu item itself
		$menu = $menus->getActive();
		if ($menu)
		{
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
		}
		else
		{
			$this->params->def('page_heading', JText::_('COM_JEM_EDITVENUE_VENUE_EDIT'));
		}

		$title = $this->params->def('page_title', JText::_('COM_JEM_EDITVENUE_VENUE_EDIT'));
		if ($app->getCfg('sitename_pagetitles', 0) == 1)
		{
			$title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2)
		{
			$title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
		}
		$this->document->setTitle($title);

		$pathway = $app->getPathWay();
		$pathway->addItem($title, '');

		if ($this->params->get('menu-meta_description'))
		{
			$this->document->setDescription($this->params->get('menu-meta_description'));
		}

		if ($this->params->get('menu-meta_keywords'))
		{
			$this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
		}

		if ($this->params->get('robots'))
		{
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}
	}


} // closing tag
?>