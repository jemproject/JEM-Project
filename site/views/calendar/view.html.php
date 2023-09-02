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
 * Calendar-View
 */
class JemViewCalendar extends JemView
{
	/**
	 * Creates the Calendar View
	 */
	public function display($tpl = null)
	{
		// Load tooltips behavior
		// JHtml::_('behavior.tooltip');
		// JHtml::_('behavior.framework');

		// initialize variables
		$app          = Factory::getApplication();
		$document     = $app->getDocument();
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
		$url 			= Uri::root();

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
			color:' . $evlinkcolor . ';
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
		// JHtml::_('script', 'media/com_jem/js/calendar.js');
		$document->addScript($url.'media/com_jem/js/calendar.js');

		$year  = (int)$jinput->getInt('yearID', date("Y"));
		$month = (int)$jinput->getInt('monthID', date("m"));

		// get data from model and set the month
		$model = $this->getModel();
		$model->setDate(mktime(0, 0, 1, $month, 1, $year));

		$rows = $this->get('Items');

		// Set Page title
		$pagetitle = $params->def('page_title', $menuitem->title);
		$params->def('page_heading', $pagetitle);
		$pageclass_sfx = $params->get('pageclass_sfx');

		// Add site name to title if param is set
		if ($app->get('sitename_pagetitles', 0) == 1) {
			$pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2) {
			$pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
		}

		$document->setTitle($pagetitle);
		$document->setMetaData('title', $pagetitle);

		// Check if the user has permission to add things
		$permissions = new stdClass();
		$catIds = $model->getCategories('all');
		$permissions->canAddEvent = $user->can('add', 'event', false, false, $catIds);
		$permissions->canAddVenue = $user->can('add', 'venue', false, false, $catIds);

		$itemid  = $jinput->getInt('Itemid', 0);

		$partItemid = ($itemid > 0) ? '&Itemid=' . $itemid : '';
		$partDate = ($year ? ('&yearID=' . $year) : '') . ($month ? ('&monthID=' . $month) : '');
		$url_base = 'index.php?option=com_jem&view=calendar' . $partItemid;

		$print_link = Route::_($url_base . $partDate . '&print=1&tmpl=component');

		// init calendar
		$cal = new JemCalendar($year, $month, 0);
		$cal->enableMonthNav($url_base . ($print ? '&print=1&tmpl=component' : ''));
		$cal->setFirstWeekDay($params->get('firstweekday', 1));
		$cal->enableDayLinks('index.php?option=com_jem&view=day' . $this->param_topcat);

		$this->rows          = $rows;
		$this->catIds        = $catIds;
		$this->params        = $params;
		$this->jemsettings   = $jemsettings;
		$this->settings      = $settings;
		$this->permissions   = $permissions;
		$this->cal           = $cal;
		$this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;
		$this->print_link    = $print_link;
		$this->print         = $print;

		parent::display($tpl);
	}
}
?>
