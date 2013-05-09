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

/**
 * Holds all authentication logic
 *
 * @package JEM
 * @since 0.9
 */
class JEMUser {
	/**
	 * Checks access permissions of the user regarding on the groupid
	 *
	 * @author Christoph Lukes
	 * @since 0.9
	 *
	 * @param int $recurse
	 * @param int $level
	 * @return boolean True on success
	 */
	static function validate_user($recurse, $level) {
		$user 		= JFactory::getUser();

		//only check when user is logged in
		if ( $user->get('id') ) {
			//open for superuser or registered and thats all what is needed
			//level = -1 all registered users
			//level = -2 disabled
			if ((( $level == -1 ) && ( $user->get('id') )) || (( JFactory::getUser()->authorise('core.manage') ) && ( $level == -2 ))) {
				return true;
			}
		//end logged in check
		}
		//oh oh, user has no permissions
		return false;
	}

	/**
	 * Checks if the user is allowed to edit an item
	 *
	 * @author Christoph Lukes
	 * @since 0.9
	 *
	 * @param int $allowowner
	 * @param int $ownerid
	 * @param int $recurse
	 * @param int $level
	 * @return boolean True on success
	 */
	static function editaccess($allowowner, $ownerid, $recurse, $level) {
		$user		= JFactory::getUser();

		$generalaccess = JEMUser::validate_user( $recurse, $level );

		if ($allowowner == 1 && ( $user->get('id') == $ownerid && $ownerid != 0 ) ) {
			return true;
		} elseif ($generalaccess == 1) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if the user is a superuser
	 * A superuser will allways have access if the feature is activated
	 *
	 * @since 0.9
	 * 
	 * @return boolean True on success
	 */
	static function superuser() {
		$user 		= JFactory::getUser();
		$userGroups = $user->getAuthorisedGroups();

		$group_ids = array(
					7, //administrator
					8  //super administrator
					);

		foreach ($userGroups as $gid) {
			if (in_array($gid, $group_ids)) return true;
		}

		return false;
	}

	/**
	 * Checks if the user has the privileges to use the wysiwyg editor
	 *
	 * We could use the validate_user method instead of this to allow to set a groupid
	 * Not sure if this is a good idea
	 *
	 * @since 0.9
	 * 
	 * @return boolean True on success
	 */
	static function editoruser() {
		$user 		= JFactory::getUser();
		$userGroups = $user->getAuthorisedGroups();

		$group_ids = array(
 					2, // registered
					3, // author
					4, // editor
					5, // publisher
					6, // manager
					7, // administrator
					8  // Super Users
					);

		foreach ($userGroups as $gid) {
			if (in_array($gid, $group_ids)) return true;
		}

		return false;
	}

	/**
	 * Checks if the user is a maintainer of a category
	 *
	 * @since 0.9
	 */
	static function ismaintainer() {
		//lets look if the user is a maintainer
		$db 	= JFactory::getDBO();
		$user	= JFactory::getUser();

		$query = 'SELECT g.group_id'
				. ' FROM #__jem_groupmembers AS g'
				. ' WHERE g.member = '.(int) $user->get('id')
				;
		$db->setQuery( $query );

		$catids = $db->loadColumn();

		//no results, no maintainer
		if (!$catids) {
			return null;
		}

		$categories = implode(' OR groupid = ', $catids);

		//count the maintained categories
		$query = 'SELECT COUNT(id)'
				. ' FROM #__jem_categories'
				. ' WHERE published = 1'
				. ' AND groupid = '.$categories
				;
		$db->setQuery( $query );

		$maintainer = $db->loadResult();

		return $maintainer;
	}
}