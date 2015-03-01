<?php
/**
 * @version     2.1.3
 * @package     JEM
 * @copyright   Copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('JPATH_BASE') or die;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');
jimport('joomla.form.formfield');

require_once dirname(__FILE__) . '/../../helpers/helper.php';

/**
 * CatOptions Field class.
 *
 *
 */
class JFormFieldCatOptions extends JFormField
{
	/**
	 * The form field type.
	 *
	 */
	protected $type = 'CatOptions';

	public function getInput()
	{
		$attr = '';

		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="'.(string) $this->element['class'].'"' : '';

		// To avoid user's confusion, readonly="true" should imply disabled="true".
		if ((string) $this->element['readonly'] == 'true' || (string) $this->element['disabled'] == 'true') {
			$attr .= ' disabled="disabled"';
		}

		//$attr .= $this->element['size'] ? ' size="'.(int) $this->element['size'].'"' : '';
		$attr .= $this->multiple ? ' multiple="multiple"' : '';

		// Initialize JavaScript field attributes.
		$attr .= $this->element['onchange'] ? ' onchange="'.(string) $this->element['onchange'].'"' : '';

		// Output
		$currentid = JFactory::getApplication()->input->getInt('a_id');

		//$categories = JEMCategories::getCategoriesTree();
		$categories = self::getCategories($currentid);

		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations WHERE itemid = '. $db->quote($currentid);

		$db->setQuery($query);
		$selectedcats = $db->loadColumn();

		return JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, trim($attr));
	}


	/**
	 * logic to get the categories
	 *
	 * @access public
	 * @return void
	 */
	function getCategories($id)
	{
		$db          = JFactory::getDbo();
		$jemsettings = JEMHelper::config();
		$user        = JFactory::getUser();
		$userid      = (int) $user->get('id');
		$glob_author = $user->authorise('core.create', 'com_jem');
		$jem_author  = JEMUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes);

		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		// on frontend access levels have ALWAYS to be resspected, also for superusers
		$where = ' WHERE c.published = 1 AND c.access IN (' . implode(',', $levels) . ')';

		// administrators, superusers, and global authors have access to all categories, other users maybe limited
		if (!$glob_author) {
			//get the ids of the categories the user maintaines
			$query = $db->getQuery(true);
			$query = 'SELECT gr.id'
					. ' FROM #__jem_groups AS gr'
					. ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
					. ' WHERE g.member = ' . $userid
					. ' AND ' .$db->quoteName('gr.addevent') . ' = 1 '
					. ' AND g.member NOT LIKE 0';
			$db->setQuery($query);

			$groupids = $db->loadColumn();

			// Check if user is allowed to submit events in general (by JEM settings, not ACL!),
			//  if yes allow to submit into categories which aren't assigned to a group.
			// Otherwise restrict submission into maintained categories only
			if ($jem_author) {
				$groupids[] = 0;
			}

			if (count($groupids)) {
					$where .= ' AND c.groupid IN (' . implode(',', $groupids) . ')';
			} else {
					$where .= ' AND 0'; // NO ACCESS!
			}
		}

		//get the maintained categories and the categories whithout any group
		//or just get all if somebody have edit rights
		$query = $db->getQuery(true);
		$query = 'SELECT c.*'
				. ' FROM #__jem_categories AS c'
				. $where
				. ' ORDER BY c.lft';
		$db->setQuery($query);

		$mitems = $db->loadObjectList();

		// Check for a database error.
		if ($db->getErrorNum())
		{
			JError::raiseNotice(500, $db->getErrorMsg());
		}

		if (!$mitems)
		{
			$mitems = array();
			$children = array();

			$parentid = 0;
		}
		else
		{
			$children = array();
			// First pass - collect children
			foreach ($mitems as $v)
			{
				$pt = $v->parent_id;
				$list = @$children[$pt] ? $children[$pt] : array();
				array_push($list, $v);
				$children[$pt] = $list;
			}

			// list childs of "root" which has no parent and normally id 1
			$parentid = intval(@isset($children[0][0]->id) ? $children[0][0]->id : 1);
		}

		//get list of the items
		$list = JEMCategories::treerecurse($parentid, '', array(), $children, 9999, 0, 0);

		// append orphaned categories
		if (count($mitems) > count($list)) {
			foreach ($children as $k => $v) {
				if (($k > 1) && !array_key_exists($k, $list)) {
					$list = JEMCategories::treerecurse($k, '?&nbsp;', $list, $children, 999, 0, 0);
				}
			}
		}

		return $list;
	}
}