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
 * HTML View class for the Calendar View
 *
 * @package JEM
 *
 */
class JEMViewCalendar extends JViewLegacy
{
	/**
	 * Creates the Calendar View
	 *
	 *
	 */
	function display($tpl = null)
	{
		$app = JFactory::getApplication();

		// Load tooltips behavior
		JHtml::_('behavior.tooltip');

		//initialize variables
		$document 	= JFactory::getDocument();
		$menu 		= $app->getMenu();
		$jemsettings = JEMHelper::config();
		$item 		= $menu->getActive();
		$params 	= $app->getParams();
		
		// Load css
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
		JHtml::_('stylesheet', 'com_jem/calendar.css', array(), true);
				
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		$evlinkcolor = $params->get('eventlinkcolor');
		$evbackgroundcolor = $params->get('eventbackgroundcolor');
		$currentdaycolor = $params->get('currentdaycolor');
		$eventandmorecolor = $params->get('eventandmorecolor');

		$style = '
		div[id^=\'catz\'] a {
			color:' . $evlinkcolor . ';
		}
		div[id^=\'catz\'] {
			background-color:'.$evbackgroundcolor .';
		}
		.eventandmore {
			background-color:'.$eventandmorecolor .';
		}

		.today .daynum {
			background-color:'.$currentdaycolor.';
		}';

		$document->addStyleDeclaration($style);

		// add javascript
		JHtml::_('script', 'com_jem/calendar.js', false, true);

		$year 	= (int)JRequest::getVar('yearID', strftime("%Y"));
		$month 	= (int)JRequest::getVar('monthID', strftime("%m"));

		//get data from model and set the month
		$model = $this->getModel();
		$model->setDate(mktime(0, 0, 1, $month, 1, $year));

		$rows = $this->get('Data');

		//Set Meta data
		$document->setTitle($item->title);

		//Set Page title
		$pagetitle = $params->def('page_title', $item->title);
		$document->setTitle($pagetitle);
		$document->setMetaData('title', $pagetitle);

		//init calendar
		$cal = new JEMCalendar($year, $month, 0, $app->getCfg('offset'));
		$cal->enableMonthNav('index.php?view=calendar');
		$cal->setFirstWeekDay($params->get('firstweekday', 1));
		$cal->enableDayLinks(false);

		$this->rows 		= $rows;
		$this->params		= $params;
		$this->jemsettings	= $jemsettings;
		$this->cal			= $cal;

		parent::display($tpl);
	}

}
?>
