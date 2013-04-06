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

if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR); 
defined('_JEXEC') or die;

/**
 * Holds the logic for all output related things
 *
 * @package Joomla
 * @subpackage EventList
 */
class ELOutput {

	/**
	* Writes footer. Official copyright! Do not remove!
	*
	* @author Christoph Lukes
	* @since 0.9
	*/
static function footer( )
	{
		
		$app =  JFactory::getApplication();
        $params 		=  $app->getParams();
        
        if ($params->get('copyright') == 1) {
		echo '<font color="grey">Powered by <a href="http://www.schlu.net" target="_blank">Schlu.net</a> | <a href="http://www.joomlaeventmanager.net" target="_blank">JEM</a></font>';
        } else {
	     echo '';   
        }
		
		}

	/**
	* Writes Event submission button
	*
	* @author Christoph Lukes
	* @since 0.9
	*
	* @param int $dellink Access of user
	* @param array $params needed params
	* @param string $view the view the user will redirected to
	**/
	static function submitbutton( $dellink, &$params )
	{
		$settings =  ELHelper::config();
		
		
		if ($dellink == 1) {

			JHTML::_('behavior.tooltip');

			if ( $settings->icons ) {
				$image = JHTML::image("components/com_jem/assets/images/submitevent.png",JText::_( 'COM_JEM_DELIVER_NEW_EVENT' ));
			} else {
				$image = JText::_( 'COM_JEM_DELIVER_NEW_EVENT' );
			}

                        if (JRequest::getInt('print')) {
				//button in popup
				$output = '';
                          
			}else {
			$link 		= 'index.php?view=editevent';
			$overlib 	= JText::_( 'COM_JEM_SUBMIT_EVENT_TIP' );
			$output		= '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.JText::_( 'COM_JEM_DELIVER_NEW_EVENT' ).'::'.$overlib.'">'.$image.'</a>';

                        }
			return $output;
		}

		return;
	}

	/**
	* Writes Archivebutton
	*
	* @author Christoph Lukes
	* @since 0.9
	*
	* @param int $oldevent Archive used or not
	* @param array $params needed params
	* @param string $task The current task
	* @param int $categid The cat id
	*/
	static function archivebutton( &$params, $task = NULL, $id = NULL )
	{

		$settings =  ELHelper::config();
		if ($settings->show_archive_icon) {
		
		if ( $settings->oldevent == 2 ) {

			JHTML::_('behavior.tooltip');
			
			$view = JRequest::getWord('view');
			
			if ($task == 'archive') {
				
				if ( $settings->icons ) {
					$image = JHTML::image("components/com_jem/assets/images/eventlist.png",JText::_( 'COM_JEM_SHOW_EVENTS' ));
				} else {
					$image = JText::_( 'COM_JEM_SHOW_EVENTS' );
				}
				$overlib 	= JText::_( 'COM_JEM_SHOW_EVENTS_TIP' );
				$title 		= JText::_( 'COM_JEM_SHOW_EVENTS' );
				
				if ($id) {
						$link 		= JRoute::_( 'index.php?view='.$view.'&id='.$id );
				} else {
						$link 		= JRoute::_( 'index.php' );
				}
				
			} else {
				
				if ( $settings->icons ) {
					$image = JHTML::image("components/com_jem/assets/images/archive_front.png",JText::_( 'COM_JEM_SHOW_ARCHIVE' ));
				} else {
					$image = JText::_( 'COM_JEM_SHOW_ARCHIVE' );
				}
				$overlib 	= JText::_( 'COM_JEM_SHOW_ARCHIVE_TIP' );
				$title 		= JText::_( 'COM_JEM_SHOW_ARCHIVE' );
					
				if ($id) {
					$link 		= JRoute::_( 'index.php?view='.$view.'&id='.$id.'&task=archive' );
				} else {
					$link		= JRoute::_('index.php?view='.$view.'&task=archive');
				}
			}

                       if (JRequest::getInt('print')) {
				//button in popup
				$output = '';
			}else{
			$output = '<a href="'.$link.'" class="editlinktip hasTip" title="'.$title.'::'.$overlib.'">'.$image.'</a>';

		
			return $output;
}
}

		}
		return;
	}

	/**
	 * Creates the edit button
	 *
	 * @param int $Itemid
	 * @param int $id
	 * @param array $params
	 * @param int $allowedtoedit
	 * @param string $view
	 * @since 0.9
	 */
	static function editbutton( $Itemid, $id, &$params, $allowedtoedit, $view)
	{

		$settings =  ELHelper::config();
		
		
		if ( $allowedtoedit ) {
            // if (((ELUser::ismaintainer()) && (ELUser::groupmaintained($id)==true))|| (ELUser::hasadminrights()== true) || (ELUser::groupmaintained($id)==false) )  {
			JHTML::_('behavior.tooltip');

			switch ($view)
			{
				case 'editevent':
					if ( $settings->icons ) {
						$image = JHTML::image("components/com_jem/assets/images/calendar_edit.png",JText::_( 'COM_JEM_EDIT_EVENT' ));
					} else {
						$image = JText::_( 'COM_JEM_EDIT_EVENT' );
					}
					$overlib = JText::_( 'COM_JEM_EDIT_EVENT_TIP' );
					$text = JText::_( 'COM_JEM_EDIT_EVENT' );
					break;

				case 'editvenue':
					if ( $settings->icons ) {
						$image = JHTML::image("components/com_jem/assets/images/calendar_edit.png",JText::_( 'COM_JEM_EDIT_EVENT' ));
					} else {
						$image = JText::_( 'COM_JEM_EDIT_VENUE' );
					}
					$overlib = JText::_( 'COM_JEM_EDIT_VENUE_TIP' );
					$text = JText::_( 'COM_JEM_EDIT_VENUE' );
					break;
			}



                        if (JRequest::getInt('print')) {
				//button in popup
				$output = '';
			} else {                        

			$link 	= 'index.php?view='.$view.'&id='.$id.'&returnid='.$Itemid;
			$output	= '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
}
		     // }
        }
		return;
	}

	/**
	 * Creates the print button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 0.9
	 */	
	static function printbutton( $print_link, &$params )
	{
		$settings =  ELHelper::config();
		if ($settings->show_print_icon) {

			JHTML::_('behavior.tooltip');

			$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no';

			// checks template image directory for image, if non found default are loaded
			if ( $settings->icons ) {
				$image = JHTML::_('image','system/printButton.png', JText::_('JGLOBAL_PRINT'), NULL, true);
			} else {
				$image = JText::_( 'COM_JEM_PRINT' );
			}

			if (JRequest::getInt('print')) {
				//button in popup
				$overlib = JText::_( 'COM_JEM_PRINT_TIP' );
				$text = JText::_( 'COM_JEM_PRINT' );
				$output = '<a href="#" onclick="window.print();return false;" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
				
			} else {
				//button in view
				$overlib = JText::_( 'COM_JEM_PRINT_TIP' );
				$text = JText::_( 'COM_JEM_PRINT' );

				$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
			}

			return $output;
		}
		return;
	}

	/**
	 * Creates the email button
	 *
	 * @param object $slug
	 * @param array $params
	 * @since 0.9
	 */
 	static function mailbutton($slug, $view, $params)
	{
		$settings =  ELHelper::config();
		
		
		if ($settings->show_email_icon) {

			JHTML::_('behavior.tooltip');
			require_once JPATH_SITE . '/components/com_mailto/helpers/mailto.php';
                        $uri    = JURI::getInstance();
                         $base	= $uri->toString(array('scheme', 'host', 'port'));
                        $template = JFactory::getApplication()->getTemplate();
                        $link 	= $base.JRoute::_( 'index.php?view='.$view.'&id='.$slug, false );
			
			$url	= 'index.php?option=com_mailto&tmpl=component&template='.$template.'&link='.MailToHelper::addLink($link);
			$status = 'width=400,height=300,menubar=yes,resizable=yes';

			if ($settings->icons) 	{
				$image = JHTML::_('image','system/emailButton.png', JText::_('JGLOBAL_EMAIL'), NULL, true);
			} else {
				$image = JText::_( 'COM_JEM_EMAIL' );
			}
                        
                        if (JRequest::getInt('print')) {
				//button in popup
				$output = '';
			} else {
				//button in view
			$overlib = JText::_( 'COM_JEM_EMAIL_TIP' );
			$text = JText::_( 'COM_JEM_EMAIL' );

			$output	= '<a href="'. JRoute::_($url) .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
}

		}
		return;
	}


	
		/**
	 * Creates the ical button
	 *
	 * @param object $slug
	 * @param array $params
	 * @since 0.9
	 */
 	static function icalbutton($slug, $view)
	{
		
		$settings =  ELHelper::config();
		if ($settings->events_ical == 1) {

			JHTML::_('behavior.tooltip');

			// checks template image directory for image, if non found default are loaded
			if ( $settings->icons ) {
				$image = JHTML::image("components/com_jem/assets/images/iCal2.0.png",JText::_( 'COM_JEM_EXPORT_ICS' ));
			} else {
				$image = JText::_( 'COM_JEM_EXPORT_ICS' );
			}

			if (JRequest::getInt('print')) {
				//button in popup
				$output = '';
			} else {
				//button in view
				$overlib = JText::_( 'COM_JEM_ICAL_TIP' );
				$text = JText::_( 'COM_JEM_ICAL' );

				$print_link = 'index.php?view='.$view.'&id='.$slug.'&format=raw&layout=ics';
				$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip"  title="'.$text.'::'.$overlib.'">'.$image.'</a>';
			}

			return $output;
		}
		return;
	}
	
	
	
	
	
	/**
	 * Creates the map button
	 *
	 * @param obj $data
	 * @param obj $settings
	 *
	 * @since 0.9
	 */
 	static function mapicon($data)
	{
		$elsettings =  ELHelper::config();
		
		//Link to map
        $mapimage = JHTML::image("components/com_jem/assets/images/icon-48-globe.png",JText::_( 'COM_JEM_MAP' ));
		
        //set var
		$output 	= null;
		$attributes = null;

		//stop if disabled
		if (!$data->map) {
			return $output;
		}
		
		$data->country = JString::strtoupper($data->country);

		//google map link or include
		switch ($elsettings->showmapserv)
		{
			case 1:
			{
				$url = 'http://maps.google.'.$elsettings->tld.'/maps?hl='.$elsettings->lg.'&q='.str_replace(" ", "+", $data->street).', '.$data->plz.' '.str_replace(" ", "+", $data->city).', '.$data->country.'+ ('.$data->venue.')&ie=UTF8&t=&z=15&iwloc=B&output=embed" ';
				$attributes = ' rel="{handler: \'iframe\', size: {x: 800, y: 500}}" latitude="" longitude=""';
				$output		= '<div class="mapicon2"><a class="modal" title="'.JText::_( 'COM_JEM_MAP' ).'" target="_blank" href="'.$url.'"'.$attributes.'><div class="mapicon" align="center">'.$mapimage.'</div></a></div>';
				
			
				} break;

			case 2:
			{
				$output		= '<div style="border: 1px solid #000;width:500px;" color="black"><iframe width="500" height="250" src="http://maps.google.'.$elsettings->tld.'/maps?hl='.$elsettings->lg.'&q='.str_replace(" ", "+", $data->street).', '.$data->plz.' '.str_replace(" ", "+", $data->city).', '.$data->country.'+ ('.$data->venue.')&ie=UTF8&t=&z=15&iwloc=B&output=embed" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" ></iframe></div>';

			} break;
		}

		return $output;
	}

	/**
	 * Creates the flyer
	 *
	 * @param obj $data
	 * @param obj $settings
	 * @param array $image
	 * @param string $type
	 *
	 * @since 0.9
	 */
 	static function flyer( $data, $image, $type = 'venue' )
	{
		$settings =  ELHelper::config();

		//define the environment based on the type
		if ($type == 'event') {
			$folder		= 'events';
			$imagefile	= $data->datimage;
			$info		= $data->title;
		} else {
			$folder 	= 'venues';
			$imagefile	= $data->locimage;
			$info		= $data->venue;
		}

		//do we have an image?
		if (empty($imagefile)) {

			//nothing to do
			return;

		} else {

			jimport('joomla.filesystem.file');

			//does a thumbnail exist?
			if (JFile::exists(JPATH_SITE.DS.'images'.DS.'eventlist'.DS.$folder.DS.'small'.DS.$imagefile)) {

				if ($settings->lightbox == 0) {

					$url		= '#';
					$attributes	= 'class="modal" onclick="window.open(\''.JURI::base().'/'.$image['original'].'\',\'Popup\',\'width='.$image['width'].',height='.$image['height'].',location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no\')"';

				} else {

					JHTML::_('behavior.modal');

					$url		= JURI::base().'/'.$image['original'];
					$attributes	= 'class="modal" title="'.$info.'"';

				}

				$icon	= '<img src="'.JURI::base().'/'.$image['thumb'].'" width="'.$image['thumbwidth'].'" height="'.$image['thumbheight'].'" alt="'.$info.'" title="'.JText::_( 'COM_JEM_CLICK_TO_ENLARGE' ).'" />';
				$output	= '<a href="'.$url.'" '.$attributes.'>'.$icon.'</a>';

			//No thumbnail? Then take the in the settings specified values for the original
			} else {

				$output	= '<img class="modal" src="'.JURI::base().'/'.$image['original'].'" width="'.$image['width'].'" height="'.$image['height'].'" alt="'.$info.'" />';

			}
		}

		return $output;
	}

	/**
	 * Creates the country flag
	 *
	 * @param string $country
	 *
	 * @since 0.9
	 */
static	function getFlag($country)
	{
        $country = JString::strtolower($country);

        jimport('joomla.filesystem.file');

        if (JFile::exists(JPATH_COMPONENT_SITE.DS.'assets'.DS.'images'.DS.'flags'.DS.$country.'.gif')) {
        	$countryimg = '<img src="'.JURI::base(true).'/components/com_jem/assets/images/flags/'.$country.'.gif" alt="'.JText::_( 'COM_JEM_COUNTRY' ).': '.$country.'" width="16" height="11" />';

        	return $countryimg;
        }

        return null;
	}
	
	/**
	 * Formats date
	 *
	 * @param string $date
	 * @param string $time
	 * 
	 * @return string $formatdate
	 *
	 * @since 0.9
	 */
static	function formatdate($date, $time)
	{
		$settings = & ELHelper::config();
		
		if(!$date) {
			return false;
		}
		
		if(!$time) {
			$time = '00:00:00';
		}
		
		//Format date
		$formatdate = strftime( $settings->formatdate, strtotime( $date.' '.$time ));
		
		return $formatdate;
	}
	
	/**
	 * Formats time
	 *
	 * @param string $date
	 * @param string $time
	 * 
	 * @return string $formattime
	 *
	 * @since 0.9
	 */
static	function formattime($date, $time)
	{
		$settings = & ELHelper::config();
		
		if(!$time) {
			return;
		}
		
		//Format time
		$formattime = strftime( $settings->formattime, strtotime( $date.' '.$time ));
		$formattime .= ' '.$settings->timename;
		
		return $formattime;
	}

	/**
	 * Returns an array for ical formatting
	 * @param string date
	 * @param string time
	 * @return array
	 */
static	function getIcalDateArray($date, $time = null)
	{
		if ($time) {
			$sec = strtotime($date. ' ' .$time);
		}
		else {
			$sec = strtotime($date);			
		}
		if (!$sec) {
			return false;
		}
		
		//Format date
		$parsed = strftime('%Y-%m-%d %H:%M:%S', $sec);

		$date = array( 'year'  => (int) substr($parsed, 0, 4), 
		               'month' => (int) substr($parsed, 5, 2), 
		               'day'   => (int) substr($parsed, 8, 2) );
			
		//Format time
		if (substr($parsed, 11, 8) != '00:00:00') 
		{
			$date['hour'] = substr($parsed, 11, 2);
			$date['min'] = substr($parsed, 14, 2);
			$date['sec'] = substr($parsed, 17, 2);
		}
		return $date;
	}
}
?>