<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
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
		$menuitem 	= $menu->getActive();
		$jemsettings = JEMHelper::config();
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

		// add javascript (using full path - see issue #590)
		JHtml::_('script', 'media/com_jem/js/calendar.js');

		$rows = $this->get('Data');
		$currentweek = $this->get('Currentweek');
		$currentyear =  Date("Y");

		//Set Page title
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


		$cal = new activeCalendarWeek($currentyear,1,1);
		$cal->enableWeekNum(JText::_('COM_JEM_WKCAL_WEEK'),null,''); // enables week number column with linkable week numbers
		$cal->setFirstWeekDay($params->get('firstweekday', 0));

		$this->rows 		 = $rows;
		$this->params		 = $params;
		$this->jemsettings	 = $jemsettings;
		$this->currentweek	 = $currentweek;
		$this->cal			 = $cal;
		$this->pageclass_sfx = htmlspecialchars($pageclass_sfx);

		parent::display($tpl);
	}
}
?>