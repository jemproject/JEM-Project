<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

function jemBuildRoute(&$query)
{
	$segments = array();
		
	if (isset($query['view'])) {
		$segments[] = $query['view'];
		unset($query['view']);
	}
	
	if (isset($query['id'])) {
		$segments[] = $query['id'];
		unset($query['id']);
	};
	
	return $segments;
}

function jemParseRoute($segments)
{
	$vars = array();
	
	// Count segments
	$count = count($segments);
	
	// Handle View and Identifier
	switch ($segments[0])
	{
		case 'category':
			{
				$id = explode(':', $segments[1]);
				$vars['id'] = $id[0];
				$vars['view'] = 'category';
			}
			break;
		
		case 'event':
			{
				$id = explode(':', $segments[1]);
				$vars['id'] = $id[0];
				$vars['view'] = 'event';
			}
			break;
		
		case 'venue':
			{
				$id = explode(':', $segments[1]);
				$vars['id'] = $id[0];
				$vars['view'] = 'venue';
			}
			break;
		
		case 'editvenue':
			{
				$vars['view'] = 'editvenue';
				if ($count == 2) {
					$vars['id'] = $segments[1];
				}
			}
			break;
		
		case 'eventslist':
			{
				$vars['view'] = 'eventslist';
			}
			break;
		
		case 'search':
			{
				$vars['view'] = 'search';
			}
			break;
		
		case 'categoriesdetailed':
			{
				$vars['view'] = 'categoriesdetailed';
			}
			break;
		
		case 'categories':
			{
				$vars['view'] = 'categories';
			}
			break;
		
		case 'calendar':
			{
				// $id = explode(':', $segments[1]);
				// $vars['id'] = $id[0];
				$vars['view'] = 'calendar';
			}
			break;
		
		case 'venues':
			{
				$vars['view'] = 'venues';
			}
			break;
		
		case 'day':
			{
				$vars['view'] = 'day';
				if ($count == 2) {
					$vars['id'] = $segments[1];
				}
			}
			break;
		
		case 'myattendances':
			{
				$vars['view'] = 'myattendances';
			}
			break;
		
		case 'myevents':
			{
				$vars['view'] = 'myevents';
			}
			break;
		
		case 'myvenues':
			{
				$vars['view'] = 'myvenues';
			}
			break;
		
		case 'attendees':
			{
				$id = explode(':', $segments[1]);
				$vars['id'] = $id[0];
				$vars['view'] = 'attendees';
			}
			break;
		
		default:
			{
				$vars['view'] = $segments[0];
			}
			break;
	}
	return $vars;
}
?>