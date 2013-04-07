/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @author Sascha Karnatz
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
**/

window.addEvent('domready', function(){
/*
  $('filter_date').addEvent('change', function() {
    this.form.submit();
  });
    */
  if ($('filter_continent'))
  {
	  $('filter_continent').addEvent('change', function() {
	    if (country = $('filter_country')) {
	      country.selectedIndex = 0;
	    }
	    if (city = $('filter_city')) {
	      city.selectedIndex = 0;
	    }
	    this.form.submit();
	  });
  }
  if (country = $('filter_country')) {  
    country.addEvent('change', function() {
	    if (city = $('filter_city')) {
	      city.selectedIndex = 0;
	    }
	    this.form.submit();
	  });
  }
  
  if (city = $('filter_city')) {  
	  city.addEvent('change', function() {
	    this.form.submit();
	  });
  }
  
  if ($('filter_category')) 
  {
	  $('filter_category').addEvent('change', function() {
	    this.form.submit();
	  });
  }
  
});