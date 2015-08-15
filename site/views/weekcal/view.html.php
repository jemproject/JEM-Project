<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


/**
 * Weekcal-View
 */
class JemViewWeekcal extends JViewLegacy
{
	/**
	 * Creates the Calendar View
	 */
	function display($tpl = null)
	{
		$app = JFactory::getApplication();

		// Load tooltips behavior
		JHtml::_('behavior.tooltip');

		// initialize variables
		$menu 		  = $app->getMenu();
		$menuitem 	  = $menu->getActive();
		$jemsettings  = JemHelper::config();
		$user         = JemFactory::getUser();
		$params 	  = $app->getParams();
		$top_category = (int)$params->get('top_category', 0);
		$this->param_topcat = $top_category > 0 ? ('&topcat='.$top_category) : '';

		// Load css
		JemHelper::loadCss('calendar');
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		$evlinkcolor = $params->get('eventlinkcolor');
		$evbackgroundcolor = $params->get('eventbackgroundcolor');
		$currentdaycolor = $params->get('currentdaycolor');
		$eventandmorecolor = $params->get('eventandmorecolor');

		$style = '
		div#jem .eventcontentinner a,
		div#jem .eventandmore a {
			color: ' . $evlinkcolor . ';
		}
		.eventcontentinner {
			background-color:'.$evbackgroundcolor .';
		}
		.eventandmore {
			background-color:'.$eventandmorecolor .';
		}
		.today .daynum {
			background-color:'.$currentdaycolor.';
		}';

		$this->document->addStyleDeclaration($style);

		// add javascript (using full path - see issue #590)
		JHtml::_('script', 'media/com_jem/js/calendar.js');

		$model = $this->getModel();
		$rows = $this->get('Items');
		$currentweek = $this->get('Currentweek');
		$currentyear =  Date("Y");

		// Set Page title
		$pagetitle = $params->def('page_title', $menuitem->title);
		$params->def('page_heading', $pagetitle);
		$pageclass_sfx = $params->get('pageclass_sfx');

		// Add site name to title if param is set
		if ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
		}

		$this->document->setTitle($pagetitle);
		$this->document->setMetaData('title', $pagetitle);

		// Check if the user has permission to add things
		$catIds = $model->getCategories('all');
		$canAddEvent = (int)$user->can('add', 'event', false, false, $catIds);
		$canAddVenue = (int)$user->can('add', 'venue', false, false, $catIds);

		// init calendar
		$cal = new activeCalendarWeek($currentyear,1,1);
		$cal->enableWeekNum(JText::_('COM_JEM_WKCAL_WEEK'),null,''); // enables week number column with linkable week numbers
		$cal->setFirstWeekDay($params->get('firstweekday', 0));
		$cal->enableDayLinks('index.php?option=com_jem&view=day' . $this->param_topcat);

		$this->rows 		 = $rows;
		$this->params		 = $params;
		$this->jemsettings	 = $jemsettings;
		$this->canAddEvent   = $canAddEvent;
		$this->canAddVenue   = $canAddVenue;
		$this->currentweek	 = $currentweek;
		$this->cal			 = $cal;
		$this->pageclass_sfx = htmlspecialchars($pageclass_sfx);

		parent::display($tpl);
	}
}
?>
