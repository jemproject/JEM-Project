<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * HTML Event View class of the JEM component
 *
 * @package JEM
 * 
 */
class JEMViewEvent extends JViewLegacy
{
	/**
	 * Creates the output for the Event view
	 *
 	 * 
	 */
	function display($tpl = null)
	{
		$app = JFactory::getApplication();

		$document		= JFactory::getDocument();
		$user			= JFactory::getUser();
		$dispatcher		= JDispatcher::getInstance();
		$jemsettings	= JEMHelper::config();
		$params			= $app->getParams('com_jem');

		$row			= $this->get('Event');
		$categories		= $this->get('Categories');
		$registers		= $this->get('Registers');
		$isregistered	= $this->get('UserIsRegistered');

		

		//get menu information
		$menu			= $app->getMenu();
		$item			= $menu->getActive();

		//Check if the id exists
		if ($row->did == 0) {
			// TODO Translation
			return JError::raiseError(404, JText::sprintf('Event #%d not found', $row->did));
		}

		//Check if user has access to the event
		if ($jemsettings->showdetails == 0) {
			return JError::raiseError(403, JText::_('COM_JEM_NO_ACCESS'));
		}

		$cid		= JRequest::getInt('cid', 0);

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');


		$params->def('page_title', JText::_('COM_JEM_EVENT'));


		$print	= JRequest::getBool('print');

		if ($print) {
			$document = JFactory::getDocument();
			$document->addStyleSheet($this->baseurl.'/media/com_jem/css/print.css');
			$document->setMetaData('robots', 'noindex, nofollow');
		}


		$print_link = JRoute::_(JEMHelperRoute::getRoute($row->slug).'&print=1&tmpl=component');

		//pathway
		$cats		= new JEMCategories($cid);
		$parents	= $cats->getParentlist();
		$pathway 	= $app->getPathWay();

		foreach($parents as $parent) {
			$pathway->addItem($this->escape($parent->catname), JRoute::_(JEMHelperRoute::getCategoryRoute($parent->categoryslug)));
		}
		$pathway->addItem($this->escape($row->title), JRoute::_(JEMHelperRoute::getRoute($row->slug)));

		//Get images
		$dimage = JEMImage::flyercreator($row->datimage, 'event');
		$limage = JEMImage::flyercreator($row->locimage, 'venue');

		//Check user if he can edit

		$allowedtoeditevent = JEMUser::editaccess($jemsettings->eventowner, $row->created_by, $jemsettings->eventeditrec, $jemsettings->eventedit);

		//Check if the user has access to the edit-venueform
		$maintainer3 = JEMUser::editvenuegroups();
		$genaccess3 	= JEMUser::editaccess($jemsettings->venueowner, $row->venueowner, $jemsettings->venueeditrec, $jemsettings->venueedit);
		if ($maintainer3 || $genaccess3 )
		{
			$allowedtoeditvenue = 1;
		} else {
			$allowedtoeditvenue = 0;
		}

		//Timecheck for registration
		$jetzt = date("Y-m-d");
		$now = strtotime($jetzt);
		$date = strtotime($row->dates);
		$timecheck = $now - $date;

		//let's build the registration handling
		$formhandler = 0;

		//is the user allready registered at the event
		if ($isregistered) {
			$formhandler = 3;
		} else if ($timecheck > 0) { //check if it is too late to register and overwrite $formhandler
			$formhandler = 1;
		} else if (!$user->get('id')) { //is the user registered at joomla and overwrite $formhandler if not
			$formhandler = 2;
		} else {
			$formhandler = 4;
		}
		
		
		if ($formhandler >= 3) {
			$js = "function check(checkbox, senden) {
				if(checkbox.checked==true){
					senden.disabled = false;
				} else {
					senden.disabled = true;
				}}";
			$document->addScriptDeclaration($js);
		}

		//Generate Eventdescription
		if ((!$row->datdescription == '') || (!$row->datdescription == '<br />')) {
			//Execute Plugins
			$row->text	= $row->datdescription;

			JPluginHelper::importPlugin('content');
			$results = $dispatcher->trigger('onContentPrepare', array ('com_jem.detail', & $row, & $params, 0));
			$row->datdescription = $row->text;
		}

		//Generate Venuedescription
		if ((!$row->locdescription == '') || (!$row->locdescription == '<br />')) {
			//execute plugins
			$row->text	=	$row->locdescription;

			JPluginHelper::importPlugin('content');
			$results = $dispatcher->trigger('onContentPrepare', array ('com_jem.detail', & $row, & $params, 0));
			$row->locdescription = $row->text;
		}

		// generate Metatags
		$meta_keywords_content = "";
		if (!empty($row->meta_keywords)) {
			$keywords = explode(",", $row->meta_keywords);
			foreach($keywords as $keyword) {
				if ($meta_keywords_content != "") {
					$meta_keywords_content .= ", ";
				}
				if (preg_match("/[\/[\/]/",$keyword)) {
					$keyword = trim(str_replace("[", "", str_replace("]", "", $keyword)));
					$buffer = $this->keyword_switcher($keyword, $row, $categories, $jemsettings->formattime, $jemsettings->formatdate);
					if ($buffer != "") {
						$meta_keywords_content .= $buffer;
					} else {
						$meta_keywords_content = substr($meta_keywords_content, 0, strlen($meta_keywords_content) - 2);	// remove the comma and the white space
					}
				} else {
					$meta_keywords_content .= $keyword;
				}
			}
		}
		if (!empty($row->meta_description)) {
			$description = explode("[",$row->meta_description);
			$description_content = "";
			foreach($description as $desc) {
					$keyword = substr($desc, 0, strpos($desc,"]",0));
					if ($keyword != "") {
						$description_content .= $this->keyword_switcher($keyword, $row, $categories, $jemsettings->formattime, $jemsettings->formatdate);
						$description_content .= substr($desc, strpos($desc,"]",0)+1);
					} else {
						$description_content .= $desc;
					}
			}
		} else {
			$description_content = "";
		}

		//set page title and meta stuff
		$document->setTitle($row->title);
		$document->setMetadata('keywords', $meta_keywords_content);
		$document->setDescription(strip_tags($description_content));

		//build the url
		if(!empty($row->url) && strtolower(substr($row->url, 0, 7)) != "http://") {
			$row->url = 'http://'.$row->url;
		}

		//create flag
		if ($row->country) {
			$row->countryimg = JEMOutput::getFlag($row->country);
		}

		// load dispatcher for plugins
		JPluginHelper::importPlugin('jem');
		$row->pluginevent = new stdClass();
		$results = $dispatcher->trigger('onEventEnd', array ($row->did, $this->escape($row->title)));
		$row->pluginevent->onEventEnd = trim(implode("\n", $results));

		//assign vars to jview
		$this->print				= $print;
		$this->row					= $row;
		$this->categories			= $categories;
		$this->params				= $params;
		$this->allowedtoeditevent	= $allowedtoeditevent;
		$this->allowedtoeditvenue	= $allowedtoeditvenue;
		$this->dimage				= $dimage;
		$this->limage				= $limage;
		$this->print_link			= $print_link;
		$this->registers			= $registers;
		$this->isregistered			= $isregistered;
		$this->formhandler			= $formhandler;
		$this->jemsettings			= $jemsettings;
		$this->item					= $item;
		$this->user					= $user;
		$this->dispatcher			= $dispatcher;

		parent::display($tpl);
	}

	/**
	 * structures the keywords
	 *
 	 * 
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
					$content = strftime($formattime ,strtotime($row->$keyword));
				}
				break;
			case "dates":
				$content = JEMOutput::formatdate($row->dates);
				break;
			case "enddates":
				//$content = strftime($formatdate ,strtotime($row->$keyword));
				$content = JEMOutput::formatdate($row->enddates);
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
}
?>