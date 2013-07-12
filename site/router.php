<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

function jemBuildRoute(&$query)
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

function jemParseRoute($segments)
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

		case 'eventslist':
		{
			$vars['view'] = 'eventslist';
			
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
    	
    	case 'myattending':
    		{
    			$vars['view'] = 'myattending';
    		} break;
    		
    		case 'attendees':
    			{
    				$id = explode(':', $segments[1]);
    				$vars['id'] = $id[0];
    				$vars['view'] = 'attendees';
    				$count = count($segments);
    				if($count > 2) {
    					$vars['task'] = $segments[2];
    				}
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