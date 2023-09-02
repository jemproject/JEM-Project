<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

/**
 * Event-View
 */
class JemViewEvent extends JemView
{
	protected $item;
	protected $params;
	protected $print;
	protected $state;
	protected $user;

	public function __construct($config = array())
	{
		parent::__construct($config);

		// additional path for common templates + corresponding override path
		$this->addCommonTemplatePath();
	}

	/**
	 * Creates the output for the Event view
	 */
	public function display($tpl = null)
	{
		$jemsettings       = JemHelper::config();
		$settings          = JemHelper::globalattribs();
		$app               = Factory::getApplication();
		$user              = JemFactory::getUser();
		$userId            = $user->get('id');
		$dispatcher        = JemFactory::getDispatcher();
		$document 		   = $app->getDocument();
		$model             = $this->getModel();
		$menu              = $app->getMenu();
		$menuitem          = $menu->getActive();
		$pathway           = $app->getPathway();
		$edit_att 	   = new \stdClass();
		$this->params      = $app->getParams('com_jem');
		$this->item        = $this->get('Item');
		$this->print       = $app->input->getBool('print', false);
		$this->state       = $this->get('State');
		$this->user        = $user;
		$this->jemsettings = $jemsettings;
		$this->settings    = $settings;

		$categories        = isset($this->item->categories) ? $this->item->categories : $this->get('Categories');
		$this->categories  = $categories;
		$this->registers   = null;

		$registration      = $this->get('UserRegistration');

		$this->regs['not_attending'] = $model->getRegisters($this->state->get('event.id'), -1);
		$this->regs['invited']       = $model->getRegisters($this->state->get('event.id'),  0);
		$this->regs['attending']     = $model->getRegisters($this->state->get('event.id'),  1);
		$this->regs['waiting']       = $model->getRegisters($this->state->get('event.id'),  2);
		$this->regs['all']           = $model->getRegisters($this->state->get('event.id'), 'all');

		// loop through attendees
		$registers_array = array();
		if($this->regs['all'])
		{
			if($userId){
				if ($this->settings->get('event_show_more_attendeedetails', '0'))
				{
					$this->registers = $this->regs['all'];
				}
				else
				{
					$this->registers = $this->regs['attending'];
				}
			}else{
				if ($this->settings->get('event_show_attendeenames', '0')==3)
				{
					$this->registers = $this->regs['attending'];
				}
			}
		}

		//JemHelper::addLogEntry("Attendees:\n" . print_r($this->registers, true), __METHOD__);
		//JemHelper::addLogEntry("Attendees:\n" . print_r($this->regs, true), __METHOD__);

		// check for data error
		if (empty($this->item)) {
			$app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
			return false;
		}

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'warning');
			return false;
		}

		// Create a shortcut for $item and params.
		$item   = $this->item;
		$params = $this->params;

		// Decide which parameters should take priority
		$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
			&& $menuitem->query['view']   == 'event'
			&& $menuitem->query['id']     == $item->id);

		// Add router helpers.
		$item->slug = $item->alias ? ($item->id.':'.$item->alias) : $item->id;
		$item->venueslug = $item->localias ? ($item->locid.':'.$item->localias) : $item->locid;

		// Check to see which parameters should take priority
		if ($useMenuItemParams) {
			// Merge so that the menu item params take priority
			$pagetitle = $params->def('page_title', $menuitem->title ? $menuitem->title : $item->title);
			$params->def('page_heading', $pagetitle);
			$pathwayKeys = array_keys($pathway->getPathway());
			$lastPathwayEntryIndex = end($pathwayKeys);
			$pathway->setItemName($lastPathwayEntryIndex, $menuitem->title);
			//$pathway->setItemName(1, $menuitem->title);

			// Load layout from active query (in case it is an alternative menu item)
			if (isset($menuitem->query['layout'])) {
				$this->setLayout($menuitem->query['layout']);
			} else {
				// Single-event menu item layout takes priority over alt layout for an event
				if ($layout = $item->params->get('event_layout')) {
					$this->setLayout($layout);
				}
            }
			$item->params->merge($params);
		} else {
			// Merge the menu item params with the event params so that the event params take priority
			$pagetitle = $item->title;
			$params->set('page_title', $pagetitle);
			$params->set('page_heading', $pagetitle);
			$params->set('show_page_heading', 1); // ensure page heading is shown
			$pathway->addItem($pagetitle, Route::_(JemHelperRoute::getEventRoute($item->slug)));

			// Check for alternative layouts (since we are not in a single-event menu item)
			// Single-event menu item layout takes priority over alt layout for an event
			if ($layout = $item->params->get('event_layout')) {
				$this->setLayout($layout);
			}

			$temp = clone($params);
			$temp->merge($item->params);
			$item->params = $temp;
		}

		$offset = $this->state->get('list.offset');

		// Check the view access to the event (the model has already computed the values).
		if (!$item->params->get('access-view')) { // && !$item->params->get('show_noauth') &&  $user->get('guest')) { - not supported yet
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
			return;
		}

		if ($item->params->get('show_intro', '1') == '1') {
			$item->text = $item->introtext.' '.$item->fulltext;
		}
		elseif ($item->fulltext) {
			$item->text = $item->fulltext;
		}
		else  {
			$item->text = $item->introtext;
		}

		// Process the content plugins //
		JPluginHelper::importPlugin('content');
		$results = $dispatcher->triggerEvent('onContentPrepare', array ('com_jem.event', &$item, &$this->params, $offset));

		$item->event = new stdClass();
		$results = $dispatcher->triggerEvent('onContentAfterTitle', array('com_jem.event', &$item, &$this->params, $offset));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = $dispatcher->triggerEvent('onContentBeforeDisplay', array('com_jem.event', &$item, &$this->params, $offset));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));

		$results = $dispatcher->triggerEvent('onContentAfterDisplay', array('com_jem.event', &$item, &$this->params, $offset));
		$item->event->afterDisplayContent = trim(implode("\n", $results));
		
		//use temporary class var to triggerEvent content prepare plugin for venue description
		$tempVenue = new stdClass();
		$tempVenue->text = $item->locdescription;
		$tempVenue->title = $item->venue;
		$results = $dispatcher->triggerEvent('onContentPrepare', array ('com_jem.event', &$tempVenue, &$this->params, $offset));
		$item->locdescription = $tempVenue->text;
		$item->venue = $tempVenue->title;
		
		// Increment the hit counter of the event.
		if (!$this->params->get('intro_only') && $offset == 0) {
			$model->hit();
		}

		// Escape strings for HTML output
		$pageclass_sfx 		 =  $this->item->params->get('pageclass_sfx');
		$this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;
		

		$this->print_link = Route::_(JemHelperRoute::getRoute($item->slug).'&print=1&tmpl=component');

		// Get images
		$this->dimage = JemImage::flyercreator($item->datimage, 'event');
		$this->limage = JemImage::flyercreator($item->locimage, 'venue');

		// Check if the user has permission to add things
		$permissions = new stdClass();
		$permissions->canAddEvent = $user->can('add', 'event');
		$permissions->canAddVenue = $user->can('add', 'venue');

		// Check if user can edit the event
		$permissions->canEditEvent = $user->can('edit', 'event', $item->id, $item->created_by);
		$permissions->canPublishEvent = $user->can('publish', 'event', $item->id, $item->created_by);

		// Check if user can edit the venue
		$permissions->canEditVenue = $user->can('edit', 'venue', $item->locid, $item->venueowner);
		$permissions->canPublishVenue = $user->can('publish', 'venue', $item->locid, $item->venueowner);

		// Check if user can edit attendees
		$isAuthor = $userId && ($userId == $item->created_by);
		//$permissions->canEditAttendees = $isAuthor;
		//new logic: user can edit events, suggested by jojo12
		$permissions->canEditAttendees = $user->can('edit', 'event', $item->id, $item->created_by);
		//suggestion by M59S to allow groupmembers too see line 230/231 too 
		$edit_att->canEditAttendees = $user->can('edit', 'event', $item->id, $item->created_by);

		$this->permissions = $permissions;
		$this->showeventstate = $permissions->canEditEvent || $permissions->canPublishEvent;
		$this->showvenuestate = $permissions->canEditVenue || $permissions->canPublishVenue;

		/** show attendees and registration form if registration globally allowed or optional and on event allowed
		 *  but if on event limited to invited users and limitation globally allowed user must be invited
		 *  (or event owner to see attendees)
		 */
		$g_reg = $this->jemsettings->showfroregistra;
		$g_inv = $this->jemsettings->regallowinvitation;
		$e_reg = $this->item->registra;
		$e_unreg = $item->unregistra;
		$e_dates = $item->dates;
		$e_times = $item->times;
		$e_hours = (int)$item->unregistra_until;

		//$this->showAttendees = (($g_reg == 1) || (($g_reg == 2) && ($e_reg & 1))) && ((!(($e_reg & 2) && ($g_inv > 0))) || (is_object($registration) || $isAuthor));
		$this->showAttendees = (($g_reg == 1) || (($g_reg == 2) && ($e_reg & 1))) && ((!(($e_reg & 2) && ($g_inv > 0))) || (is_object($registration) || $isAuthor) || $edit_att);
		$this->showRegForm   = (($g_reg == 1) || (($g_reg == 2) && ($e_reg & 1))) && ((!(($e_reg & 2) && ($g_inv > 0))) || (is_object($registration)));

		$this->allowAnnulation = ($e_unreg == 1) || (($e_unreg == 2) && (empty($e_dates) || (strtotime($e_dates.' '.$e_times.' -'.$e_hours.' hour') > strtotime('now'))));

		// Timecheck for registration
		$now = strtotime(date("Y-m-d"));
		$date = empty($item->dates) ? $now : strtotime($item->dates);
		$enddate = empty($item->enddates) ? $date : strtotime($item->enddates);
		$timecheck = $now - $date; // on open date $timecheck is 0

		// let's build the registration handling
		$formhandler = 0; // too late to unregister

		if (is_object($registration) && $registration->status != 0) { // is the user allready registered at the event
			if ($now <= $enddate) { // allows registration changes on unfinished events
				$formhandler = 4;
			}
			// else $formahandler = 0, see above
		} elseif ($timecheck > 0) { // check if it is too late to register and overwrite $formhandler
			$formhandler = 1;
		} elseif (!$userId) { // user doesn't have an ID (mostly guest)
			$formhandler = 2;
		} else {
			$formhandler = 4; // allow registration (changes)
		}

		if ($formhandler >= 3) {
			// user must click one of the radio buttons to enable Send button
			$js = "function check(box, send) {
				if(box.checked==true){
					send.disabled = false;
				} else {
					send.disabled = true;
				}}";
			$document->addScriptDeclaration($js);
		}

		$this->formhandler = $formhandler;

		// generate Metatags
		$meta_keywords = array();
		if (!empty($this->item->meta_keywords)) {
			$keywords = explode(",", $this->item->meta_keywords);
			foreach ($keywords as $keyword) {
				if (preg_match("/[\/[\/]/", $keyword)) {
					$keyword = trim(str_replace("[", "", str_replace("]", "", $keyword)));
					$buffer = $this->keyword_switcher($keyword, $this->item, $categories, $jemsettings->formattime, $jemsettings->formatdate);
					if (!empty($buffer)) {
						$meta_keywords[] = $buffer;
					}
				} else {
					$meta_keywords[] = $keyword;
				}
			}

			$document->setMetadata('keywords', implode(', ', $meta_keywords));
		}

		if (!empty($this->item->meta_description)) {
			$description = explode("[", $this->item->meta_description);
			$description_content = "";
			foreach ($description as $desc) {
				$endpos = \Joomla\String\StringHelper::strpos($desc, "]", 0);
				if ($endpos > 0) {
					$keyword = \Joomla\String\StringHelper::substr($desc, 0, $endpos);
					$description_content .= $this->keyword_switcher($keyword, $this->item, $categories, $jemsettings->formattime, $jemsettings->formatdate);
					$description_content .= \Joomla\String\StringHelper::substr($desc, $endpos + 1);
				} else {
					$description_content .= $desc;
				}
			}
		} else {
			$description_content = "";
		}

		$document->setDescription(strip_tags($description_content));

		// load dispatcher for JEM plugins (comments)
		$item->pluginevent = new stdClass();
		if ($this->print) {
			$item->pluginevent->onEventEnd = false;
		} else {
			JPluginHelper::importPlugin('jem', 'comments');
			$results = $dispatcher->triggerEvent('onEventEnd', array($item->did, $this->escape($item->title)));
			$item->pluginevent->onEventEnd = trim(implode("\n", $results));
		}

		// create flag
		if ($item->country) {
			$item->countryimg = JemHelperCountries::getCountryFlag($item->country);
		}

		/* a bit backwaard compaibility... */
		if (is_object($registration)) {
			$this->isregistered = $registration->status;
		} else {
			$this->isregistered = false;
		}
		$this->registration  = $registration;
		$this->dispatcher    = $dispatcher;
		$pageclass_sfx 		 =  $item->params->get('pageclass_sfx');
		$this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;
		$this->itemid        = $menuitem ? $menuitem->id : false;

		$this->_prepareDocument();
		
		parent::display($tpl);
	}

	/**
	 * structures the keywords
	 */
	protected function keyword_switcher($keyword, $row, $categories, $formattime, $formatdate)
	{
		$content = '';

		switch ($keyword)
		{
		case 'categories':
			$catnames = array();
			foreach ($categories as $category) {
				$catnames[] = $this->escape($category->catname);
			}
			$content = implode(', ', array_filter($catnames));
			break;

		case 'a_name':
			$content = $row->venue;
			break;

		case 'times':
		case 'endtimes':
			if (isset($row->$keyword)) {
				$content = JemOutput::formattime($row->$keyword);
			}
			break;

		case 'dates':
		case 'enddates':
			if (isset($row->$keyword)) {
				$content = JemOutput::formatdate($row->$keyword);
			}
			break;

		case 'title':
		default:
			if (isset($row->$keyword)) {
				$content = $row->$keyword;
			}
			break;
		}

		return $content;
	}

	/**
	 * Prepares the document
	 */
	protected function _prepareDocument()
	{
		$app     = Factory::getApplication();
	//	$menus   = $app->getMenu();
	//	$pathway = $app->getPathway();

		// add css file
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		if ($this->print) {
			JemHelper::loadCss('print');
			$this->document->setMetaData('robots', 'noindex, nofollow');
		}

	/*
		// Because the application sets a default page title,
		// we need to get it from the menu item itself
		$menu = $menus->getActive();

		if ($menu) {
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
		} else {
			$this->params->def('page_heading', Text::_('JGLOBAL_JEM_EVENT'));
		}
	*/
		$title = $this->params->get('page_title', '');
	/*
		$id = (int) @$menu->query['id'];

		// if the menu item does not concern this event
		if ($menu && ($menu->query['option'] != 'com_jem' || $menu->query['view'] != 'event' || $id != $this->item->id)) {
			// If this is not a single event menu item, set the page title to the event title
			if ($this->item->title) {
				$title = $this->item->title;
			}
			$path = array(array('title' => $this->item->title, 'link' => ''));
			$category = JCategories::getInstance('JEM2')->get($this->item->catid);
			while ($category && ($menu->query['option'] != 'com_jem' || $menu->query['view'] == 'event'
					|| $id != $category->id) && $category->id > 1) {
				$path[] = array('title' => $category->catname, 'link' => JemHelperRoute::getCategoryRoute($category->id));
				$category = $category->getParent();
			}
			$path = array_reverse($path);
			foreach($path as $item) {
				$pathway->addItem($item['title'], $item['link']);
			}
		}
	*/
		// Check for empty title and add site name if param is set
		if (empty($title)) {
			$title = $app->get('sitename');
		} elseif ($app->get('sitename_pagetitles', 0) == 1) {
			$title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		} elseif ($app->get('sitename_pagetitles', 0) == 2) {
			$title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}
		if (empty($title)) {
			$title = $this->item->title;
		}
		$this->document->setTitle($title);

		if ($this->params->get('robots')) {
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}

		if ($app->get('MetaAuthor') == '1') {
			$this->document->setMetaData('author', $this->item->author);
		}

		$mdata = $this->item->metadata->toArray();
		foreach ($mdata as $k => $v) {
			if ($v) {
				$this->document->setMetadata($k, $v);
			}
		}

		// If there is a pagebreak heading or title, add it to the page title
		if (!empty($this->item->page_title)) {
			$this->item->title = $this->item->title . ' - ' . $this->item->page_title;
			$this->document->setTitle($this->item->page_title . ' - '
					. Text::sprintf('PLG_CONTENT_PAGEBREAK_PAGE_NUM', $this->state->get('list.offset') + 1));
		}
	}
}
?>
