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

function EventListBuildRoute(&$query)
{
	$segments = array();

	if(isset($query['view']))
	{
		$segments[] = $query['view'];
		unset($query['view']);
	}

	if(isset($query['id']))
	{
		$segments[] = $query['id'];
		unset($query['id']);
	};

	if(isset($query['task']))
	{
		$segments[] = $query['task'];
		unset($query['task']);
	};

	if(isset($query['returnid']))
	{
		$segments[] = $query['returnid'];
		unset($query['returnid']);
	};

	return $segments;
}

function EventListParseRoute($segments)
{
	$vars = array();

	//Handle View and Identifier
	switch($segments[0])
	{
		case 'categoryevents':
		{
			$id = explode(':', $segments[1]);
			$vars['id'] = $id[0];
			$vars['view'] = 'categoryevents';

			$count = count($segments);
			if($count > 2) {
				$vars['task'] = $segments[2];
			}

		} break;

		case 'details':
		{
			$id = explode(':', $segments[1]);
			$vars['id'] = $id[0];
			$vars['view'] = 'details';

		} break;

		case 'venueevents':
		{
			$id = explode(':', $segments[1]);
			$vars['id'] = $id[0];
			$vars['view'] = 'venueevents';
			$count = count($segments);
			if($count > 2) {
				$vars['task'] = $segments[2];
			}

		} break;

		case 'editevent':
		{
			$count = count($segments);
			
			$vars['view'] = 'editevent';

			if($count == 3) {
				$vars['id'] = $segments[1];
				$vars['returnid'] = $segments[2];
			}

		} break;

		case 'editvenue':
		{
			$count = count($segments);

			$vars['view'] = 'editvenue';
			
			if($count == 3) {
				$vars['id'] = $segments[1];
				$vars['returnid'] = $segments[2];
			}

		} break;

		case 'eventlist':
		{
			$vars['view'] = 'eventlist';
			
			$count = count($segments);
			if($count == 2) {
				$vars['task'] = $segments[1];
			}

		} break;
				
    	case 'search':
    	{
      		$vars['view'] = 'search';
    	} break;

		case 'categoriesdetailed':
		{
			$vars['view'] = 'categoriesdetailed';
			
			$count = count($segments);
			if($count == 2) {
				$vars['task'] = $segments[1];
			}

		} break;

		case 'categories':
		{
			$vars['view'] = 'categories';

			$count = count($segments);
			if($count == 2) {
				$vars['task'] = $segments[1];
			}

		} break;
		
		case 'calendar':
    	{
       //     $id = explode(':', $segments[1]);
		//	$vars['id'] = $id[0];
			$vars['view'] = 'calendar';

			$count = count($segments);
			if($count > 2) {
				$vars['task'] = $segments[2];
			}
    	} break;
   


		case 'venues':
		{
			$vars['view'] = 'venues';
			
			$count = count($segments);
			if($count == 2) {
				$vars['task'] = $segments[1];
			}

		} break;
		
		case 'day':
		{
			$vars['view'] = 'day';
			
			$count = count($segments);
			if($count == 2) {
				$vars['id'] = $segments[1];
			}

		} break;
		
		case 'my':
    	{
      		$vars['view'] = 'my';
    	} break;
		
    // some tasks !
		case 'getfile': 
    	{
      		$vars['task'] = 'getfile';
    	} break;

    	default:
    	{
      		$vars['view'] = $segments[0];
    	} break;
	}

	return $vars;
}
?>