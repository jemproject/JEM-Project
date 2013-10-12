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
 * HTML View class for the Calendar View
 *
 * @package JEM
 *
 */
class JEMViewWeekcal extends JViewLegacy
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
		JHTML::_('behavior.tooltip');

		//initialize variables
		$document 	= JFactory::getDocument();
		$menu 		= $app->getMenu();
		$jemsettings = JEMHelper::config();
		$item 		= $menu->getActive();
		$params 	= $app->getParams();

		//add css file
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');
		$document->addStyleSheet($this->baseurl.'/media/com_jem/css/calendarweek.css');

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
		$document->addScript($this->baseurl.'/media/com_jem/js/calendar.js');
		$rows = $this->get('Data');
		$currentweek 	= $this->get('Currentweek');

		//Set Meta data
		$document->setTitle($item->title);

		//Set Page title
		$pagetitle = $params->def('page_title', $item->title);
		$document->setTitle($pagetitle);
		$document->setMetaData('title', $pagetitle);

		$cal = new activeCalendarWeek();
		$cal->enableWeekNum(JText::_('COM_JEM_WKCAL_WEEK'),null,''); // enables week number column with linkable week numbers
		$cal->setFirstWeekDay($params->get('firstweekday', 0));

		$this->rows 		= $rows;
		$this->params		= $params;
		$this->jemsettings	= $jemsettings;
		$this->currentweek	= $currentweek;
		$this->cal			= $cal;

		parent::display($tpl);
	}
}
?>
