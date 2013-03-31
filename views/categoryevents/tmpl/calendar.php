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
defined( '_JEXEC' ) or die( 'Restricted access' );
?>

<?php 
require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'calendar.class.php');

$cal = new ELCalendar($this->year, $this->month, $this->day);
$cal->enableMonthNav('index.php?view=categoryevents&layout=calendar&id='. $this->category->slug);
$cal->setFirstWeekDay(1);

foreach ($this->rows as $row) 
{
  $year = JHTML::date( $row->dates, "%Y" );
  $month = JHTML::date( $row->dates, "%m" );
  $day = JHTML::date( $row->dates, "%d" );
  if ($this->elsettings->showtime == 1)
  {
  	$time = $row->times;
  }
  //Link to details
  $detaillink 	= JRoute::_( EventListHelperRoute::getRoute($row->slug));
  
  //title
  if (($this->elsettings->showtitle == 1 ) && ($this->elsettings->showdetails == 1) ) {
    $title = $this->escape($row->title);
  }
  
  // venue
  if ($this->elsettings->showlocate == 1) {
  	if ($this->elsettings->showlinkvenue == 1 ) {
  		$venue = $row->locid != 0 ? "<a href='".JRoute::_('index.php?view=venueevents&id='.$row->venueslug)."'>".$this->escape($row->venue)."</a>" : '-';
  	}
  	else {
  		$venue = $row->locid ? $this->escape($row->venue) : '-';
  	}
  }
  
  // categories
  if ($this->elsettings->showcat == 1) 
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