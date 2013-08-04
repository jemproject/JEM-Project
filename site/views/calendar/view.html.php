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
class JEMViewCalendar extends JViewLegacy
{
    /**
     * Creates the Calendar View
     *
     * 
     */
    function display($tpl = null)
    {
        $app =  JFactory::getApplication();

        // Load tooltips behavior
        JHTML::_('behavior.tooltip');

        //initialize variables
        $document 	=  JFactory::getDocument();
        $menu 		=  $app->getMenu();
        $jemsettings =  JEMHelper::config();
        $item 		= $menu->getActive();
        $params 	=  $app->getParams();
        $uri 		=  JFactory::getURI();
        $pathway 	=  $app->getPathWay();

        //add css file
        $document->addStyleSheet($this->baseurl.'/media/com_jem/css/jem.css');
        $document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');
        $document->addStyleSheet($this->baseurl.'/media/com_jem/css/calendar.css');
        
        
        
       
        $evlinkcolor = $params->get('eventlinkcolor');
        $evbackgroundcolor = $params->get('eventbackgroundcolor');
        $currentdaycolor = $params->get('currentdaycolor');
        $eventandmorecolor = $params->get('eventandmorecolor');
        
        
        $style = '
        
        .eventcontent a:link, a:visited, a:active {
        color:' . $evlinkcolor . ';
        }
        .eventcontent {		
        background-color:'.$evbackgroundcolor .';		
        		}
        .eventandmore {		
        background-color:'.$eventandmorecolor .';		
        		}
        				
        .today .daynum {
 		 background-color:'.$currentdaycolor.';
		}		

 		 		
        '
        ;
        $document->addStyleDeclaration( $style );
        
        
        
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
     * 
     */
    function caltooltip($tooltip, $title = '', $text = '', $href = '', $class = '')
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
