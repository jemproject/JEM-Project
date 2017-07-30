<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
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
	public function display($tpl = null)
	{
		// Load tooltips behavior
		JHtml::_('behavior.tooltip');

		// initialize variables
		$app          = JFactory::getApplication();
		$document     = JFactory::getDocument();
		$menu         = $app->getMenu();
		$menuitem     = $menu->getActive();
		$jemsettings  = JemHelper::config();
		$settings     = JemHelper::globalattribs();
		$user         = JemFactory::getUser();
		$params       = $app->getParams();
		$top_category = (int)$params->get('top_category', 0);
		$jinput       = $app->input;
		$print        = $jinput->getBool('print', false);

		$this->param_topcat = $top_category > 0 ? ('&topcat='.$top_category) : '';

		// Load css
		JemHelper::loadCss('jem');
		JemHelper::loadCss('calendar');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		if ($print) {
			JemHelper::loadCss('print');
			$document->setMetaData('robots', 'noindex, nofollow');
		}

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

		$document->addStyleDeclaration($style);

		// add javascript (using full path - see issue #590)
		JHtml::_('script', 'media/com_jem/js/calendar.js');

		$model = $this->getModel();
		$rows = $this->get('Items');
		$currentweek = $this->get('Currentweek');
		$currentyear = Date("Y");

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

		$document->setTitle($pagetitle);
		$document->setMetaData('title', $pagetitle);

		// Check if the user has permission to add things
		$permissions = new stdClass();
		$catIds = $model->getCategories('all');
		$permissions->canAddEvent = $user->can('add', 'event', false, false, $catIds);
		$permissions->canAddVenue = $user->can('add', 'venue', false, false, $catIds);

		$itemid = $jinput->getInt('Itemid', 0);
		$partItemid = ($itemid > 0) ? '&Itemid=' . $itemid : '';
		$print_link = JRoute::_('index.php?option=com_jem&view=weekcal' . $partItemid . '&print=1&tmpl=component');

		// init calendar
		$cal = new activeCalendarWeek($currentyear,1,1);
		$cal->enableWeekNum(JText::_('COM_JEM_WKCAL_WEEK'),null,''); // enables week number column with linkable week numbers
		$cal->setFirstWeekDay($params->get('firstweekday', 0));
		$cal->enableDayLinks('index.php?option=com_jem&view=day' . $this->param_topcat);

		$this->rows          = $rows;
		$this->params        = $params;
		$this->jemsettings   = $jemsettings;
		$this->settings      = $settings;
		$this->permissions   = $permissions;
		$this->currentweek   = $currentweek;
		$this->cal           = $cal;
		$this->pageclass_sfx = htmlspecialchars($pageclass_sfx);
		$this->print_link    = $print_link;
		$this->print         = $print;

		parent::display($tpl);
	}
}
?>
