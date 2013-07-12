<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

// Component Helper
jimport('joomla.application.component.helper');
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');

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
	 * @param string The category of the item
	 * @since 0.9
	 *
	 * @return string determined Link
	 */
	static function getRoute($id, $view = 'details', $category = null)
	{
		// Not needed currently but kept because of a possible hierarchic link structure in future
		$needles = array(
			$view => (int) $id
		);

		// Create the link
		$link = 'index.php?option=com_jem&view='.$view.'&id='. $id;

		// Add category, if available
		if(!is_null($category)) {
			$link .= '&catid='.$category;
		}

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
	static function _findItem($needles)
	{
		$component = JComponentHelper::getComponent('com_jem');

		$app = JFactory::getApplication();

		$menus = $app->getMenu();
		$items = $menus->getItems('component_id', $component->id);
		$user = JFactory::getUser();
		$gid = JEMHelper::getGID($user);

		//false if there exists no menu item at all
		if (!$items) {
			return false;
		} else {
			//Not needed currently but kept because of a possible hierarchic link structure in future
			foreach($needles as $needle => $id)
			{
				foreach($items as $item)
				{
					if ((@$item->query['view'] == $needle) && (@$item->query['id'] == $id) && ($item->access <= $gid)) {
						return $item;
					}
				}

				/*
				//no menuitem exists -> return first possible match
				foreach($items as $item)
				{
					if ($item->published == 1 && $item->access <= $gid) {
						return $item;
					}
				}
				*/
			}
		}

		return false;
	}
}
?>