<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

require JPATH_COMPONENT_SITE.'/classes/view.class.php';

/**
 * Event-View
 */
class JemViewEvent extends JEMView
{
	protected $item;
	protected $params;
	protected $print;
	protected $state;
	protected $user;

	function __construct($config = array()) {
		parent::__construct($config);

		// additional path for common templates + corresponding override path
		$this->addCommonTemplatePath();
	}

	/**
	 * Creates the output for the Event view
	 */
	function display($tpl = null)
	{
		$jemsettings		= JemHelper::config();
		$settings			= JemHelper::globalattribs();
		$app				= JFactory::getApplication();
		$user				= JFactory::getUser();
		$userId				= $user->get('id');
		$dispatcher			= JDispatcher::getInstance();
		$document 			= JFactory::getDocument();
		$model 				= $this->getModel();
		$menu 				= $app->getMenu();
		$menuitem			= $menu->getActive();
		$pathway 			= $app->getPathway();

		$this->params		= $app->getParams('com_jem');
		$this->item			= $this->get('Item');
		$this->print		= JRequest::getBool('print');
		$this->state		= $this->get('State');
		$this->user			= $user;
		$this->jemsettings	= $jemsettings;
		$this->settings		= $settings;

		$categories			= $this->get('Categories');
		$this->categories	= $categories;

		$this->registers	= $model->getRegisters($this->state->get('event.id'));
		$isregistered		= $this->get('UserIsRegistered');

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseWarning(500, implode("\n", $errors));
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
		$item->slug			= $item->alias ? ($item->id.':'.$item->alias) : $item->id;
		$item->venueslug	= $item->localias ? ($item->locid.':'.$item->localias) : $item->locid;

		// Check to see which parameters should take priority
		if ($useMenuItemParams) {
			// Merge so that the menu item params take priority
			$pagetitle = $params->def('page_title', $menuitem->title ? $menuitem->title : $item->title);
			$params->def('page_heading', $pagetitle);
			$pathway->setItemName(1, $menuitem->title);

			// Load layout from active query (in case it is an alternative menu item)
			if (isset($menuitem->query['layout'])) {
				$this->setLayout($menuitem->query['layout']);
			} else
			// Single-event menu item layout takes priority over alt layout for an event
			if ($layout = $item->params->get('event_layout')) {
				$this->setLayout($layout);
			}

			$item->params->merge($params);
		} else {
			// Merge the menu item params with the event params so that the event params take priority
			$pagetitle = $item->title;
			$params->set('page_title', $pagetitle);
			$params->set('page_heading', $pagetitle);
			$params->set('show_page_heading', 1); // ensure page heading is shown
			$pathway->addItem($pagetitle, JRoute::_(JemHelperRoute::getEventRoute($item->slug)));

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
		if (!$item->params->get('access-view') && !$item->params->get('show_noauth') &&  $user->get('guest')) {
			JError::raiseWarning(403, JText::_('JERROR_ALERTNOAUTHOR'));
			return;
		}

		if ($item->params->get('show_intro', '1')=='1') {
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
		$results = $dispatcher->trigger('onContentPrepare', array ('com_jem.event', &$item, &$this->params, $offset));

		$item->event = new stdClass();
		$results = $dispatcher->trigger('onContentAfterTitle', array('com_jem.event', &$item, &$this->params, $offset));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onContentBeforeDisplay', array('com_jem.event', &$item, &$this->params, $offset));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onContentAfterDisplay', array('com_jem.event', &$item, &$this->params, $offset));
		$item->event->afterDisplayContent = trim(implode("\n", $results));

		// Increment the hit counter of the event.
		if (!$this->params->get('intro_only') && $offset == 0) {
			$model->hit();
		}

		//Escape strings for HTML output
		$this->pageclass_sfx = htmlspecialchars($this->item->params->get('pageclass_sfx'));

		$this->print_link = JRoute::_(JemHelperRoute::getRoute($item->slug).'&print=1&tmpl=component');

		//Get images
		$this->dimage = JemImage::flyercreator($item->datimage, 'event');
		$this->limage = JemImage::flyercreator($item->locimage, 'venue');

		// Check if user can edit
		$maintainer5 = JemUser::ismaintainer('edit',$item->did);
		$genaccess5  = JemUser::editaccess($jemsettings->eventowner, $item->created_by, $jemsettings->eventeditrec, $jemsettings->eventedit);

		if ($maintainer5 || $genaccess5 || $user->authorise('core.edit','com_jem')) {
			$this->allowedtoeditevent = 1;
		} else {
			$this->allowedtoeditevent = 0;
		}

		//Check if the user has access to the edit-venueform
		$maintainer3 = JemUser::venuegroups('edit');
		$genaccess3  = JemUser::editaccess($jemsettings->venueowner, $item->venueowner, $jemsettings->venueeditrec, $jemsettings->venueedit);
		if ($maintainer3 || $genaccess3 ) {
			$this->allowedtoeditvenue = 1;
		} else {
			$this->allowedtoeditvenue = 0;
		}

		//Timecheck for registration
		$now = strtotime(date("Y-m-d"));
		$date = strtotime($item->dates);
		$timecheck = $now - $date;

		//let's build the registration handling
		$formhandler = 0;

		//is the user allready registered at the event
		if ($isregistered) {
			$formhandler = 3;
		} else if ($timecheck > 0 && !is_null($item->dates)) { //check if it is too late to register and overwrite $formhandler
			$formhandler = 1;
		} else if (!$user->get('id')) { //is the user registered at joomla and overwrite $formhandler if not
			$formhandler = 2;
		} else {
			$formhandler = 4;
		}

		if ($formhandler >= 3) {
			$js = "function check(checkbox, send) {
				if(checkbox.checked==true){
					send.disabled = false;
				} else {
					send.disabled = true;
				}}";
			$document->addScriptDeclaration($js);
		}
		$this->formhandler			= $formhandler;

		// generate Metatags
		$meta_keywords_content = "";
		if (!empty($this->item->meta_keywords)) {
			$keywords = explode(",", $this->item->meta_keywords);
			foreach($keywords as $keyword) {
				if ($meta_keywords_content != "") {
					$meta_keywords_content .= ", ";
				}
				if (preg_match("/[\/[\/]/",$keyword)) {
					$keyword = trim(str_replace("[", "", str_replace("]", "", $keyword)));
					$buffer = $this->keyword_switcher($keyword, $this->item, $categories, $jemsettings->formattime, $jemsettings->formatdate);
					if ($buffer != "") {
						$meta_keywords_content .= $buffer;
					} else {
						$meta_keywords_content = substr($meta_keywords_content, 0, strlen($meta_keywords_content) - 2);	// remove the comma and the white space
					}
				} else {
					$meta_keywords_content .= $keyword;
				}
			}

			$document->setMetadata('keywords', $meta_keywords_content);
		}

		if (!empty($this->item->meta_description)) {
			$description = explode("[",$this->item->meta_description);
			$description_content = "";
			foreach($description as $desc) {
				$keyword = substr($desc, 0, strpos($desc,"]",0));
				if ($keyword != "") {
					$description_content .= $this->keyword_switcher($keyword, $this->item, $categories, $jemsettings->formattime, $jemsettings->formatdate);
					$description_content .= substr($desc, strpos($desc,"]",0)+1);
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
			JPluginHelper::importPlugin('jem','comments');
			$results = $dispatcher->trigger('onEventEnd', array ($item->did, $this->escape($item->title)));
			$item->pluginevent->onEventEnd = trim(implode("\n", $results));
		}

		//create flag
		if ($item->country) {
			$item->countryimg = JemHelperCountries::getCountryFlag($item->country);
		}

		$this->isregistered			= $isregistered;
		$this->dispatcher			= $dispatcher;
		$this->pageclass_sfx 		= htmlspecialchars($item->params->get('pageclass_sfx'));

		$this->_prepareDocument();

		parent::display($tpl);
	}

	/**
	 * structures the keywords
	 */
	function keyword_switcher($keyword, $row, $categories, $formattime, $formatdate) {
		switch ($keyword) {
			case "categories":
				$i = 0;
				$content = '';
				$n = count($categories);
				foreach ($categories as $category) {
					$content .= $this->escape($category->catname);
					$i++;
					if ($i != $n) {
						$content .= ', ';
					}
				}
				break;
			case "a_name":
				$content = $row->venue;
				break;
			case "times":
			case "endtimes":
				$content = '';
				if ($row->$keyword) {
					$content = JemOutput::formattime($row->$keyword);
				}
				break;
			case "dates":
				$content = JemOutput::formatdate($row->dates);
				break;
			case "enddates":
				$content = JemOutput::formatdate($row->enddates);
				break;
			case "title":
				$content = $row->title;
				break;
			default:
				$content = "";
				if(isset($row->$keyword)) {
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
		$app	= JFactory::getApplication();
		$menus	= $app->getMenu();
		$pathway = $app->getPathway();
		$title = null;

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
			$this->params->def('page_heading', JText::_('JGLOBAL_JEM_EVENT'));
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
				$path[] = array('title' => $category->catname, 'link' => JEMHelperRoute::getCategoryRoute($category->id));
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
			$title = $app->getCfg('sitename');
		} elseif ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
		} elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
		}
		if (empty($title)) {
			$title = $this->item->title;
		}
		$this->document->setTitle($title);

		if ($this->params->get('robots')) {
			$this->document->setMetadata('robots', $this->params->get('robots'));
		}

		if ($app->getCfg('MetaAuthor') == '1') {
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
					. JText::sprintf('PLG_CONTENT_PAGEBREAK_PAGE_NUM', $this->state->get('list.offset') + 1));
		}
	}
}
?>
