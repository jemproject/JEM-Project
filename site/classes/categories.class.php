<?php
/**
 * @version 1.9
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
	function JEMCategories($cid)
	{
		$this->id = $cid;
		$this->buildParentCats($this->id);
		$this->getParentCats();
	}

	/**
	 * sets array of parent categories, with top category first
	 *
	 */
	static function getParentCats()
	{
		$db = JFactory::getDBO();

		$parentcats = array();
		$parentcats = array_reverse($parentcats);

		foreach ($parentcats as $cid) {
			$query = 'SELECT catname,' . ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as categoryslug'
					. ' FROM #__jem_categories' . ' WHERE id =' . (int) $cid . ' AND published = 1';

			$db->setQuery($query);
			$this->category[] = $db->loadObject();
		}
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
	 *
	 * @return array
	 */
	static function getCategoriesTree($published)
	{
		$db = JFactory::getDBO();

		if ($published) {
			$where = ' WHERE published = 1';
		} else {
			$where = '';
		}

		$query = 'SELECT *, id AS value, catname AS text' . ' FROM #__jem_categories' . $where . ' ORDER BY parent_id, ordering';

		$db->setQuery($query);

		$rows = $db->loadObjectList();

		//set depth limit
		$levellimit = 10;

		//get children
		$children = array();
		foreach ($rows as $child) {
			$parent = $child->parent_id;
			$list = @$children[$parent] ? $children[$parent] : array();
			array_push($list, $child);
			$children[$parent] = $list;
		}
		//get list of the items
		$list = JEMCategories::treerecurse(0, '', array(), $children, true, max(0, $levellimit - 1));

		return $list;
	}

	/**
	 * Get the categorie tree
	 * based on the joomla 1.0 treerecurse
	 *
	 * @access public
	 * @return array
	 */
	static function treerecurse($id, $indent, $list, &$children, $title, $maxlevel = 9999, $level = 0, $type = 1)
	{
		if (@$children[$id] && $level <= $maxlevel) {
			foreach ($children[$id] as $v) {
				$id = $v->id;

				if ($type) {
					$pre = '&nbsp;|_&nbsp;';
					$spacer = '&nbsp;&nbsp;&nbsp;';
				} else {
					$pre = '- ';
					$spacer = '&nbsp;&nbsp;';
				}

				if ($title) {
					if ($v->parent_id == 0) {
						$txt = '' . $v->catname;
					} else {
						$txt = $pre . $v->catname;
					}
				} else {
					if ($v->parent_id == 0) {
						$txt = '';
					} else {
						$txt = $pre;
					}
				}

				$pt = $v->parent_id;
				$list[$id] = $v;
				$list[$id]->treename = "$indent$txt";
				$list[$id]->children = count(@$children[$id]);

				$list = JEMCategories::treerecurse($id, $indent . $spacer, $list, $children, $title, $maxlevel, $level + 1, $type);
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
			$catlist[] = JHTML::_('select.option', '0', JText::_('COM_JEM_TOPLEVEL'));
		}

		$catlist = array_merge($catlist, JEMCategories::getcatselectoptions($list));

		return JHTML::_('select.genericlist', $catlist, $name, $class, 'value', 'text', $selected);
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

		// if (is_array($list))
		// {
		foreach ($list as $item) {
			$catlist[] = JHTML::_('select.option', $item->id, $item->treename);
		}
		// }

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