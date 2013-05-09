<?php
/**
 * @version 1.9 $Id$
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

defined('_JEXEC') or die;

// Component Helper
jimport('joomla.application.component.helper');

/**
 * JEM Component Route Helper
 * based on Joomla ContentHelperRoute
 *
 * @static
 * @package		JEM
 * @since 0.9
 */
class JEMHelperRoute
{
	/**
	 * Determines an JEM Link
	 *
	 * @param int The id of an JEM item
	 * @param string The view
	 * @since 0.9
	 *
	 * @return string determined Link
	 */
static	function getRoute($id, $view = 'details')
	{
		//Not needed currently but kept because of a possible hierarchic link structure in future
		$needles = array(
			$view  => (int) $id
		);

		//Create the link
		$link = 'index.php?option=com_jem&view='.$view.'&id='. $id;

		if($item = JEMHelperRoute::_findItem($needles)) {
			$link .= '&Itemid='.$item->id;
		};

		return $link;
	}

	/**
	 * Determines the Itemid
	 *
	 * searches if a menuitem for this item exists
	 * if not the first match will be returned
	 *
	 * @param array The id and view
	 * @since 0.9
	 *
	 * @return int Itemid
	 */
static	function _findItem($needles)
	{
		$component = JComponentHelper::getComponent('com_jem');

		$app =  JFactory::getApplication();

		$menus	= $app->getMenu();
		$items	= $menus->getItems('component_id', $component->id);
		$user 	= JFactory::getUser();
		
		
		if (JFactory::getUser()->authorise('core.manage')) {
              $access = (int) 3;  //viewlevel Special
          } else {
              if($user->get('id')) {
                  $access = (int) 2;  //viewlevel Registered
              } else {
                 $access = (int) 1;   //viewlevel Public
              }
          }
        //false if there exists no menu item at all
		if (!$items)  {
            return false;
        }
        else {
		  //Not needed currently but kept because of a possible hierarchic link structure in future
		  foreach($needles as $needle => $id)
		  {
		      	foreach($items as $item)
			     {

				    if ((@$item->query['view'] == $needle) && (@$item->query['id'] == $id) && ($item->published == 1) && ($item->access <= $access)) {
					return $item;
				    }
			     }

		      /*	//no menuitem exists -> return first possible match
		      	foreach($items as $item)
		      	{
			     	if ($item->published == 1 && $item->access <= $access) {
				        	return $item;
			     	}
		      	}  */

		  }
		}

		return false;
	}
}
?>