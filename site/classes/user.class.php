<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

/**
 * JEM user class with additional functions.
 * Because User::getInstance has different paramters on different versions
 *  we must split out class.
 *
 * @package JEM
 */
abstract class JemUserAbstract extends User
{
	/**
	 * @var    array  JemUser instances container.
	 */
	protected static $instances_jemuser = array();


	static protected function _getInstance($id = 0)
	{
		$id = (int)$id;

		// Check if the user ID is already cached.
		if (empty(self::$instances_jemuser[$id]) || !(self::$instances_jemuser[$id] instanceof JemUser))
		{
			$user = new JemUser($id);
			self::$instances_jemuser[$id] = $user;
		}

		return self::$instances_jemuser[$id];
	}

	/**
	 * Checks access permissions of the user regarding on the groupid
	 *
	 * @param int $recurse
	 * @param int $level
	 * @return boolean True on success
	 */
	public function validate_user($recurse, $level)
	{
		// Only check when user is logged in
		if ($this->id) {
			//open for superuser or registered and thats all what is needed
			//level = -1 all registered users
			//level = -2 disabled
			if ((($level == -1) && $this->id) || (($level == -2) && ($this->authorise('core.manage')))) {
				return true;
			}
		}

		// User has no permissions
		return false;
	}

	/**
	 * Checks if the user is allowed to edit an item
	 *
	 *
	 * @param int $allowowner
	 * @param int $ownerid
	 * @param int $recurse
	 * @param int $level
	 * @return boolean True on success
	 */
	public function editaccess($allowowner, $ownerid, $recurse, $level)
	{
		$generalaccess = $this->validate_user($recurse, $level);

		if ((($allowowner == 1) || $this->authorise('core.edit.own','com_jem')) && ($this->id == $ownerid) && ($ownerid != 0)) {
			return true;
		} elseif (($generalaccess == 1) || $this->authorise('core.edit','com_jem')) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if the user is a superuser
	 * A superuser will allways have access if the feature is activated
	 *
	 * @return boolean True on success
	 *
	 * @deprecated since version 2.1.5
	 */
	static function superuser()
	{
		$user = Factory::getApplication()->getIdentity();

    	if ($user->authorise('core.manage', 'com_jem')) {
    		return true;
    	} else {
    		return false;
    	}
	}

	/**
	 * Checks if the user has the privileges to use the wysiwyg editor
	 *
	 * We could use the validate_user method instead of this to allow to set a groupid
	 * Not sure if this is a good idea
	 *
	 * @return boolean True on success
	 *
	 * @deprecated since version 2.1.5
	 */
	static function editoruser()
	{
		return false;
	}

	/**
	 * Checks if the user is a maintainer of a category
	 *
	 * @return NULL int of maintained categories or null
	 *
	 * @deprecated Use JemUser::can($action, 'event', $eventid) instead.
	 */
	public function ismaintainer($action, $eventid = false)
	{
		// lets look if the user is a maintainer
        $db = Factory::getContainer()->get('DatabaseDriver');

		$query = 'SELECT gr.id' . ' FROM #__jem_groups AS gr'
				. ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
				. ' WHERE g.member = ' . (int)$this->id
				. ' AND ' . $db->quoteName('gr.' . $action . 'event') . ' = 1 '
				. ' AND g.member NOT LIKE 0';
		$db->setQuery($query);
		$groupnumber = $db->loadColumn();

		// no results, the user is not within a group with the required right
		if (!$groupnumber) {
			return false;
		}

		// the user is in a group with the required right but is there a
		// published category where he is allowed to post in?

		$categories = implode(' OR groupid = ', $groupnumber);

		if ($action == 'edit') {
			$query = 'SELECT a.catid' . ' FROM #__jem_cats_event_relations AS a'
					. ' LEFT JOIN #__jem_categories AS c ON c.id = a.catid'
					. ' WHERE c.published = 1'
					. ' AND (c.groupid = ' . $categories . ')'
					. ' AND a.itemid = ' . $eventid;
			$db->setQuery($query);
		}
		else {
			$query = 'SELECT id' . ' FROM #__jem_categories'
					. ' WHERE published = 1'
					. ' AND (groupid = ' . $categories . ')';
			$db->setQuery($query);
		}

		$maintainer = $db->loadResult();

		if (!$maintainer) {
			return false;
		}
		else {
			return true;
		}
	}


	/**
	 * Checks if an user is a groupmember and if so
	 * if the group is allowed for $action on venues
	 *
	 */
	public function venuegroups($action)
	{
		/*
		 * just a basic check to see if the current user is in an usergroup with
		 * access for submitting venues. if a result then return true, otherwise false
		 *
		 * Actions: addvenue, publishvenue, editvenue
		 *
		 * views: venues, venue, editvenue
		 */
        $db = Factory::getContainer()->get('DatabaseDriver');
		$query = 'SELECT gr.id'
				. ' FROM #__jem_groups AS gr'
				. ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
				. ' AND '.$db->quoteName('gr.'.$action.'venue').' = 1 '
				. ' WHERE g.member = ' . (int)$this->id
				. ' AND g.member NOT LIKE 0';
				;
		$db->setQuery($query);

		$groupnumber = $db->loadResult();

		return !empty($groupnumber);
	}

	/**
	 * Returns all JEM groups user is member of.
	 * The array returned is keyed by group id where the value is
	 * an assoziative array of ['field_name' => 'row_value'] pairs.
	 *
	 * @param  $asset mixed false, string or array of strings to restrict to groups
	 *                      with at least one of this asset(s) set;
	 *                      must be valid field names of #__jem_groups table. (optional)
	 * @return mixed List of JEM groups or null
	 */
	public function getJemGroups($asset = false)
	{
		$userId = (int)$this->id;

		// no registered user -> no groups
		if (empty($userId)) {
			return false;
		}

        $db = Factory::getContainer()->get('DatabaseDriver');

		if (is_array($asset) && !empty($asset)) {
			array_walk($asset, function(&$v, $k, $db) { $v = $db->quoteName($v); }, $db);
			$field = ' AND (' . implode(' > 0 OR ', $asset) . ' > 0)';
		} else {
			$field = empty($asset) ? '' : ' AND ' . $db->quoteName($asset) . ' > 0';
		}

		$query = 'SELECT gr.*'
		       . ' FROM #__jem_groups AS gr'
		       . ' LEFT JOIN #__jem_groupmembers AS gm ON gm.group_id = gr.id'
		       . ' WHERE gm.member = '. $userId . $field
		       . ' GROUP BY gr.id';
		$db->setQuery($query);

		$groups = $db->loadAssocList('id');
		return $groups;
	}

	/**
	 * Method to return a list of all JEM categories that a user has permission for a given action
	 *
	 * @param   mixed   $action  One or array of 'add', 'edit', 'publish'
	 * @param   string  $type    One of 'event', 'venue'
	 * @param   array   $options additional options as 'option' => value, supported are:
	 *                           'ignore_access' (bool) true if access level should be ignored
	 *                           'use_disable'   (bool) true to include non-allowed categories with attribute 'disable' set to true
	 *                           'owner'         (bbol) true if 'edit.own' can be used
	 *
	 * @return  array   List of categories that this user can do this action (empty array if none).
	 *                  Other categories will be returned too (with disable = true) if option 'disable' is set.
	 */
	public function getJemCategories($action, $type, $options = array())
	{
		$action = (array)$action;
		$ignore_access = array_key_exists('ignore_access', $options) ? (bool)$options['ignore_access'] : false;
		$use_disable   = array_key_exists('use_disable',   $options) ? (bool)$options['use_disable']   : false;
		$owner         = array_key_exists('owner',         $options) ? (bool)$options['owner']         : false;

		$jemsettings = JemHelper::config();
		$asset = 'com_jem';

		$all = (bool)$this->authorise('core.manage', $asset);

		if (!$all) {
			foreach ($action as $act) {
				switch ($act) {
				case 'add':
					$all = (bool)$this->authorise('core.create', $asset);
					break;
				case 'edit':
					$all = (bool)$this->authorise('core.edit', $asset) || (bool)$this->authorise('core.edit.own', $asset);
					break;
				case 'publish':
					$all = (bool)$this->authorise('core.edit.state', $asset);
					break;
				default:
					break;
				}
			}
		}

		$jemgroups = array();
		if (!$all && (($type == 'event') || ($type == 'venue'))) {
			$fields = array();
			foreach ($action as $act) {
				switch ($act) {
				case 'add':
					$fields[] = $act . $type;
					break;
				case 'edit':
					$fields[] = $act . $type;
					break;
				case 'publish':
					$fields[] = $act . $type;
					break;
				default:
					break;
				}
			}

			switch ($type) {
			case 'event':
				$create   = ($jemsettings->delivereventsyes == -1);
				$edit     = ($jemsettings->eventedit == -1);
				$edit_own = ($jemsettings->eventowner == 1) && $owner;
				break;
			case 'venue':
				$create   = ($jemsettings->deliverlocsyes == -1);
				$edit     = ($jemsettings->venueedit == -1);
				$edit_own = ($jemsettings->venueowner == 1) && $owner;
				break;
			default:
				$create = $edit = $edit_own = false;
				break;
			}

			// Get all JEM groups with requested permissions and user is member of.
			$jemgroups = empty($fields) ? array() : $this->getJemGroups($fields);
			// If registered users are generally allowed (by JEM Settings) to edit events/venues
			// add JEM group 0 and make category check
			if (($create && in_array('add', $action)) || (($edit || $edit_own) && in_array('edit', $action))) {
				$jemgroups[0] = true;
			}
		}

		$disable = '';
		$where   = $ignore_access ? '' : ' AND c.access IN (' . implode(',', $this->getAuthorisedViewLevels()) . ')';

		if (!empty($jemgroups)) {
			if ($use_disable) {
				$disable =  ', IF (c.groupid IN (' . implode(',', array_keys($jemgroups)) . '), 0, 1) AS disable';
			} else {
				$where .= ' AND c.groupid IN (' . implode(',', array_keys($jemgroups)) . ')';
			}
		}

		// We have to check ALL categories, also those not seen by user.
        $db = Factory::getContainer()->get('DatabaseDriver');
		$query  = 'SELECT DISTINCT c.*' . $disable
		        . ' FROM #__jem_categories AS c'
		        . ' WHERE c.published = 1'
		        . $where
		        . ' ORDER BY c.lft';
		$db->setQuery( $query );
		$cats = $db->loadObjectList('id');

		return $cats;
	}

	/**
	 * Checks if user is allowed to do actions on objects.
	 * Respects Joomla and JEM group permissions.
	 *
	 * @param  $action      mixed  One or array of 'add', 'edit', 'publish', 'delete'
	 * @param  $type        string One of 'event', 'venue'
	 * @param  $id          mixed  The event or venue id or false (default)
	 * @param  $created_by  mixed  User id of creator or false (default)
	 * @param  $categoryIds mixed  List of category IDs to limit for or false (default)
	 * @return true if allowed, false otherwise
	 * @note   If nno categoryIds are given this functions checks if there is any potential way
	 *         to allow requested action. To prevent this check set categoryIds to 1 (root category)
	 */
	public function can($action, $type, $id = false, $created_by = false, $categoryIds = false)
	{
		$userId = (int)$this->id;

		// guests are not allowed to do anything except looking
		if (empty($userId) || $this->get('guest', 0)) {
			return false;
		}

		$action = (array)$action;

		if (!empty($categoryIds)) {
			$categoryIds = (array)$categoryIds;
			$catIds = array();
			foreach ($categoryIds as $catId) {
				if ((int)$catId > 0) {  // allow 'root' category with which caller can skip "potentially allowed" check
					$catIds[] = (int)$catId;
				}
			}
			$categoryIds = $catIds; // non-zero integers
		} else {
			$categoryIds = array();
		}

		$created_by  = (int)$created_by;
		$id          = ($id === false) ? $id : (int)$id;
		$asset       = 'com_jem';
		$jemsettings = JemHelper::config();

		switch ($type) {
		case 'event':
			$create   = ($jemsettings->delivereventsyes == -1);
			$edit     = ($jemsettings->eventedit == -1);
			$edit_own = ($jemsettings->eventowner == 1);
			$autopubl = ($jemsettings->autopubl == -1); // auto-publish new events
			// not supported yet
			//if (!empty($id)) {
			//	$asset .= '.event.' . $id;
			//}
			break;
		case 'venue':
			$create   = ($jemsettings->deliverlocsyes == -1);
			$edit     = ($jemsettings->venueedit == -1);
			$edit_own = ($jemsettings->venueowner == 1);
			$autopubl = ($jemsettings->autopublocate == -1); // auto-publish new venues
			// not supported yet
			//if (!empty($id)) {
			//	$asset .= '.venue.' . $id;
			//}
			break;
		default:
			$create = $edit = $edit_own = $autopubl = false;
			break;
		}
		$assets[] = $asset;
		// not supported yet
		//foreach($categoryIds as $catId) {
		//	$assets[] = 'com_jem.category.'.$catId;
		//}

		// Joomla ACL system, JEM global settings

		$authorised = false;
		foreach ($assets as $asset) {
			if ($authorised) { break; }
			$authorised |= (boolean)$this->authorise('core.manage', $asset);

			foreach ($action as $act) {
				if ($authorised) { break; }
				switch ($act) {
				case 'add':
					$authorised |= $create || $this->authorise('core.create', $asset);
					break;
				case 'edit':
					$authorised |= $this->authorise('core.edit', $asset); // $edit is limited to events not attached to jem groups
					// user is owner and edit-own is enabled
					$authorised |= ($edit_own || $this->authorise('core.edit.own', $asset)) &&
					               !empty($created_by) && ($userId == $created_by);
					break;
				case 'publish':
					$authorised |= $this->authorise('core.edit.state', $asset);
					// user is creator of new item and auto-publish is enabled
					$authorised |= $autopubl && ($id === 0) &&
					               (empty($created_by) || ($userId == $created_by));
					// user is creator, can edit this item and auto-publish is enabled
					// (that's because we allowed user to not publish new item with auto-puplish enabled)
					$authorised |= $autopubl && ($edit || $edit_own) && ($id !== 0) &&
					               !empty($created_by) && ($userId == $created_by);
					break;
				case 'delete':
					$authorised |= $this->authorise('core.delete', $asset);
					break;
				}
			}
		}

		// JEM User groups
		if (!$authorised) {
			if (($type == 'event') || ($type == 'venue')) {
				$fields = array();
				foreach ($action as $act) {
					switch ($act) {
						case 'add':
						case 'edit':
						case 'publish':
							$fields[] = $act.$type;
							break;
						default:
							break;
					}
				}

				// Get all JEM groups with requested permissions and user is member of.
				$jemgroups = empty($fields) ? array() : $this->getJemGroups($fields);
				// If registered users are generally allowed (by JEM Settings) to edit events/venues
				// add JEM group 0 and make category check
				if ($edit && (in_array('edit', $action))) {
					$jemgroups[0] = true;
				}
				if (!empty($jemgroups)) {
					if (empty($categoryIds) && (($type != 'event') || (empty($id) && (!in_array('publish', $action))))) {
						$authorised = true; // new events and venues have no limiting categories, so generally authorised
					} else { // we have a valid event object so check event's categories against jem groups
						$whereCats = empty($categoryIds) ? '' : ' AND c.id IN ('.implode(',', $categoryIds).')';

						$levels = $this->getAuthorisedViewLevels();
						// We have to check ALL categories, also those not seen by user.
                        $db = Factory::getContainer()->get('DatabaseDriver');
						$query  = 'SELECT DISTINCT c.id, c.groupid, c.access'
						        . ' FROM #__jem_categories AS c';
						if (!empty($id)) {
							$query .= ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
							        . ' WHERE rel.itemid = ' . $id
						            . ' AND c.published = 1';
						} else {
							$query .= ' WHERE c.published = 1';
						}
						$query .= $whereCats;
						$db->setQuery( $query );
						$cats = $db->loadObjectList();
					}

					if (!empty($cats)) {
						$unspecific = in_array('publish', $action) ? -1 : 0; // publish requires jemgroup
						foreach($cats as $cat) {
							if (empty($cat->groupid)) {
								if ($unspecific === 0) {
									$unspecific = 1;
								}
							} else {
								$unspecific = -1; // at least one group assigned so group permissions take precedence
								if (in_array($cat->access, $levels) && array_key_exists($cat->groupid, $jemgroups)) {
									// user can "see" this category and is member of connected jem group granting permission
									$authorised = true;
									break; // foreach cats
								}
							}
						}
						if ($unspecific === 1) {
							// only categories without connected JEM group found, so user is authorised
							$authorised = true;
						}
					}
				}
			}
		}

		return (bool)$authorised;
	}

}

/**
 * JEM user class with additional functions.
 * Compatible with Joomla since 3.4.0.
 *
 * @package JEM
 *
 * @see JemUserAbstract
 */
class JemUser extends JemUserAbstract
{
    static function getInstance($id = 0, JUserWrapperHelper $userHelper = null)
    {
        // we don't need this helper
        return parent::_getInstance($id);
    }
}
