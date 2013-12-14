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
		JHtml::_('behavior.tooltip');

		//initialize variables
		//$document 	= JFactory::getDocument();
		$menu 		= $app->getMenu();
		$jemsettings = JEMHelper::config();
		$item 		= $menu->getActive();
		$params 	= $app->getParams();

		// Load css
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
		JHtml::_('stylesheet', 'com_jem/calendar.css', array(), true);
		$this->document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');

		$evlinkcolor = $params->get('eventlinkcolor');
		$evbackgroundcolor = $params->get('eventbackgroundcolor');
		$currentdaycolor = $params->get('currentdaycolor');
		$eventandmorecolor = $params->get('eventandmorecolor');

		$style = '
		div#jem a .eventtitle{
			color: ' . $evlinkcolor . ';
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

		$this->document->addStyleDeclaration($style);

		// add javascript
		JHtml::_('script', 'com_jem/calendar.js', false, true);

		$rows = $this->get('Data');
		$currentweek = $this->get('Currentweek');
		$currentyear =  Date("Y");

		//Set Meta data
		$this->document->setTitle($item->title);

		//Set Page title
		$pagetitle = $params->def('page_title', $item->title);
		$this->document->setTitle($pagetitle);
		$this->document->setMetaData('title', $pagetitle);


		$cal = new activeCalendarWeek($currentyear,1,1);
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