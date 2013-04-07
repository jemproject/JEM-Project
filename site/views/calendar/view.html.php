<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// no direct access

if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'calendar.class.php');

/**
 * HTML View class for the Calendar View
 *
 * @package Joomla
 * @subpackage EventList
 * @since 1.1
 */
class EventListViewCalendar extends JViewLegacy
{
    /**
     * Creates the Calendar View
     *
     * @since 1.1
     */
    function display($tpl = null)
    {
        $app =  JFactory::getApplication();

        // Load tooltips behavior
        JHTML::_('behavior.tooltip');

        //initialize variables
        $document 	=  JFactory::getDocument();
        $menu 		=  $app->getMenu();
        $elsettings =  ELHelper::config();
        $item 		= $menu->getActive();
        $params 	=  $app->getParams();
        $uri 		=  JFactory::getURI();
        $pathway 	=  $app->getPathWay();

        //add css file
        $document->addStyleSheet($this->baseurl.'/media/com_jem/css/eventlist.css');
        $document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #eventlist dd { height: 1%; }</style><![endif]-->');
        $document->addStyleSheet($this->baseurl.'/media/com_jem/css/eventlistcalendar.css');
        
        // add javascript
        $document->addScript($this->baseurl.'/media/com_jem/js/calendar.js');

        $year 	= (int)JRequest::getVar('yearID', strftime("%Y"));
        $month 	= (int)JRequest::getVar('monthID', strftime("%m"));

        //get data from model and set the month
        $model =  $this->getModel();
        $model->setDate(mktime(0, 0, 1, $month, 1, $year));

        $rows =  $this->get('Data');

        //Set Meta data
        $document->setTitle($item->title);

        //Set Page title
        $pagetitle = $params->def('page_title', $item->title);
        $document->setTitle($pagetitle);
        $document->setMetaData('title', $pagetitle);

        //init calendar
				$cal = new ELCalendar($year, $month, 0, $app->getCfg('offset'));
				$cal->enableMonthNav('index.php?view=calendar');
				$cal->setFirstWeekDay($params->get('firstweekday', 1));
				$cal->enableDayLinks(false);
				
        $this->assignRef('rows', 		$rows);
        $this->assignRef('params', 		$params);
        $this->assignRef('elsettings', 	$elsettings);
        $this->assignRef('cal', 		$cal);

        parent::display($tpl);
    }

	/**
     * Creates a tooltip
     *
     * @access  public
     * @param string  $tooltip The tip string
     * @param string  $title The title of the tooltip
     * @param string  $text The text for the tip
     * @param string  $href An URL that will be used to create the link
     * @param string  $class the class to use for tip.
     * @return  string
     * @since 1.5
     */
    function caltooltip($tooltip, $title = '', $text = '', $href = '', $class = 'editlinktip hasTip')
    {
        $tooltip = (htmlspecialchars($tooltip));
        $title = (htmlspecialchars($title));
    
        if ($title) {
            $title = $title.'::';
        }
    
        if ($href) {
            $href = JRoute::_($href);
            $style = '';
            $tip = '<span class="'.$class.'" title="'.$title.$tooltip.'"><a href="'.$href.'">'.$text.'</a></span>';
        } else {
            $tip = '<span class="'.$class.'" title="'.$title.$tooltip.'">'.$text.'</span>';
        }
    
        return $tip;
    }
}
?>
