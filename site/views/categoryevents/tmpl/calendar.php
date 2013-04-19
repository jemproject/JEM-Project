<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined( '_JEXEC' ) or die;
?>

<?php 
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'calendar.class.php');

$cal = new JEMCalendar($this->year, $this->month, $this->day);
$cal->enableMonthNav('index.php?view=categoryevents&layout=calendar&id='. $this->category->slug);
$cal->setFirstWeekDay(1);

foreach ($this->rows as $row) 
{
 $year = strftime('%Y', strtotime($row->dates));
 $month = strftime('%m', strtotime($row->dates));
 $day = strftime('%d', strtotime($row->dates));
	
	
	//  $year = JHTML::date( $row->dates, "%Y" );
//  $month = JHTML::date( $row->dates, "%m" );
 // $day = JHTML::date( $row->dates, "%d" );
  if ($this->jemsettings->showtime == 1)
  {
  	$time = $row->times;
  }
  //Link to details
  $detaillink 	= JRoute::_( JEMHelperRoute::getRoute($row->slug));
  
  //title
  if (($this->jemsettings->showtitle == 1 ) && ($this->jemsettings->showdetails == 1) ) {
    $title = $this->escape($row->title);
  }
  
  // venue
  if ($this->jemsettings->showlocate == 1) {
  	if ($this->jemsettings->showlinkvenue == 1 ) {
  		$venue = $row->locid != 0 ? "<a href='".JRoute::_('index.php?view=venueevents&id='.$row->venueslug)."'>".$this->escape($row->venue)."</a>" : '-';
  	}
  	else {
  		$venue = $row->locid ? $this->escape($row->venue) : '-';
  	}
  }
  
  // categories
  if ($this->jemsettings->showcat == 1) 
  {
  	$categories = '';
  	foreach ($row->categories as $key => $category)
  	{
  		$categories .= $category->id;
  	}
  }
  $cal->setEventContent($year, $month, $day, $title, $detaillink, $categories); 
}
print ($cal->showMonth());
?>