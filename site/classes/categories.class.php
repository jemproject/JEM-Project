<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


class JEMCategories
{
	/**
	 * id
	 *
	 * @var int
	 */
	var $id = null;

	/**
	 * Parent Categories (name, slug), with top category first
	 *
	 * @var array
	 */
	var $parentcats = array();

	/**
	 * Category data
	 *
	 * @var array
	 */
	var $category = array();

	/**
	 * Constructor
	 *
	 * @param int category id
	 */
	function __construct($cid)
	{
		$this->id = $cid;
	}

	function getPath()
	{
		$db = JFactory::getDBO();
		$parentcats = array();
		$cid = $this->id;

		do {
			$sql = $db->getQuery(true);

			$sql->select('id, parent_id, catname');

			// Handle the alias CASE WHEN portion of the query
			$case_when_cat_alias = ' CASE WHEN ';
			$case_when_cat_alias .= $sql->charLength('alias');
			$case_when_cat_alias .= ' THEN ';
			$cat_id = $sql->castAsChar('id');
			$case_when_cat_alias .= $sql->concatenate(array($cat_id, 'alias'), ':');
			$case_when_cat_alias .= ' ELSE ';
			$case_when_cat_alias .= $cat_id.' END as slug';
			$sql->select($case_when_cat_alias);

			$sql->from('#__jem_categories');
			$sql->where('id = ' . (int) $cid);
			$sql->where('published = 1');

			$db->setQuery($sql);
			$row = $db->loadObject();

			if($row) {
				$parentcats[] = $row->slug;
				$parent_id = $row->parent_id;
			} else {
				$parent_id = 0;
			}

			$cid = $parent_id;
		} while($parent_id > 0);

		$parentcats = array_reverse($parentcats);
		return $parentcats;
	}

	/**
	 * set the array (parentcats) of ascending parents categories, with initial category first.
	 *
	 * @param int category ids
	 */
	static function buildParentCats($cid)
	{
		$db = JFactory::getDBO();

		$query = 'SELECT parent_id FROM #__jem_categories WHERE id = ' . (int) $cid;
		$db->setQuery($query);

		$parentcats = array();

		if ($cid != 0) {
			array_push($parentcats, $cid);
		}

		//if we still have results
		if (sizeof($db->loadResult()) != 0) {
			$db->loadResult();
		}
	}

	/**
	 * returns parent Categories (name, slug), with top category first
	 *
	 * @return array
	 */
	static function getParentlist()
	{
		$category = array();
		return $category;
	}


	/**
	 * Get the categorie tree
	 * Based on Joomla/html/menu.php
	 *
	 * @todo alter this function as the published value is set to false
	 *
	 * @return array
	 */
	static function getCategoriesTree($published=false)
	{
		$db = JFactory::getDBO();
		$state = array(0,1);

		if ($published) {
			$where = ' WHERE published = '.$published;
		} else {
			$where = ' WHERE published IN (' . implode(',', $state) . ')';
			$where .= ' AND alias NOT LIKE "root"';
		}

		$query = 'SELECT *, id AS value, catname AS text' . ' FROM #__jem_categories' . $where . ' ORDER BY parent_id, ordering';
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

			$parentid = $mitems;
		}
		else
		{
			$mitems_temp = $mitems;

			$children = array();
			// First pass - collect children
			foreach ($mitems as $v)
			{
				$pt = $v->parent_id;
				$list = @$children[$pt] ? $children[$pt] : array();
				array_push($list, $v);
				$children[$pt] = $list;
			}

			$parentid = intval($mitems[0]->parent_id);
		}

		//get list of the items
		$list = JEMCategories::treerecurse($parentid, '', array(), $children, 9999, 0, 0);

		return $list;
	}


	/**
	 * Get the categorie tree
	 * based on the joomla 1.0 treerecurse
	 *
	 * @access public
	 * @return array
	 */
	static function treerecurse($id, $indent, $list, &$children, $maxlevel=9999, $level=0, $type=1)
	{
		if (@$children[$id] && $level <= $maxlevel)
		{
			foreach ($children[$id] as $v)
			{
				$id = $v->id;

				if ($type) {
						$pre	= '<sup>|_</sup>&nbsp;';
						$spacer = '.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
				} else {
						$pre	= '- ';
						$spacer = '&nbsp;&nbsp;';
				}

				if ($v->parent_id == 0) {
						$txt = $v->catname;
				} else {
						$txt = $pre . $v->catname;
				}
				$pt = $v->parent_id;
				$list[$id] = $v;
				$list[$id]->treename = "$indent$txt";
				$list[$id]->children = count(@$children[$id]);

				$list = JEMCategories::treerecurse($id, $indent . $spacer, $list, $children, $maxlevel, $level+1, $type);
			}
		}
		return $list;
	}

	/**
	 * Build Categories select list
	 *
	 * @param array $list
	 * @param string $name
	 * @param array $selected
	 * @param bool $top
	 * @param string $class
	 * @return void
	 */
	static function buildcatselect($list, $name, $selected, $top, $class = 'class="inputbox"')
	{
		$catlist = array();

		if ($top) {
			$catlist[] = JHtml::_('select.option', '0', JText::_('COM_JEM_TOPLEVEL'));
		}

		$catlist = array_merge($catlist, JEMCategories::getcatselectoptions($list));

		return JHtml::_('select.genericlist', $catlist, $name, $class, 'value', 'text', $selected);
	}

	/**
	 * Build Categories select list
	 *
	 * @param array $list
	 * @param string $name
	 * @param array $selected
	 * @param bool $top
	 * @param string $class
	 * @return void
	 */
	static function getcatselectoptions($list)
	{
		$catlist = array();

		foreach ($list as $item) {
			$catlist[] = JHtml::_('select.option', $item->id, $item->treename);
		}

		return $catlist;
	}

	/**
	 * returns all descendants of a category
	 *
	 * @param int category id
	 * @return array int categories id
	 */
	static function getChilds($id)
	{
		$db = JFactory::getDBO();
		$query = ' SELECT id, parent_id ' . ' FROM #__jem_categories ' . ' WHERE published = 1 ';
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		//get array children
		$children = array();
		foreach ($rows as $child) {
			$parent = $child->parent_id;
			$list = @$children[$parent] ? $children[$parent] : array();
			array_push($list, $child);
			$children[$parent] = $list;
		}
		return JEMCategories::_getChildsRecurse($id, $children);
	}

	/**
	 * recursive function to build the familly tree
	 *
	 * @param int category id
	 * @param array children indexed by parent id
	 * @return array of category descendants
	 */
	static function _getChildsRecurse($id, $childs)
	{
		$result = array(
			$id
		);
		if (@$childs[$id]) {
			foreach ($childs[$id] AS $c) {
				$result = array_merge($result, JEMCategories::_getChildsRecurse($c->id, $childs));
			}
		}
		return $result;
	}
}
?>