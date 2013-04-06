<?php
/**
 * @version development 
 * @package Joomla
 * @subpackage JEM
 * @copyright JEM (C) 2013 Joomlaeventmanager.net / EventList (C) 2005 - 2008 Christoph Lukes
 *
 * @license GNU/GPL, see LICENSE.php
 * JEM is based on EventList made by Christoph Lukes from schlu.net
 *
 * JEM can be downloaded from www.joomlaeventmanager.net
 * You can visit the site for support & downloads
 * 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with redEVENT; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die;

class eventlist_cats
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
	function eventlist_cats($cid)
	{
		$this->id = $cid;
		$this->buildParentCats($this->id);
		$this->getParentCats();
	}

	/**
	 * sets array of parent categories, with top category first
	 *
	 */
	function getParentCats()
	{
		$db	= JFactory::getDBO();
		
		$this->parentcats = array_reverse($this->parentcats);
				
		foreach($this->parentcats as $cid) {
			
			$query = 'SELECT catname,'
					.' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as categoryslug'
					.' FROM #__jem_categories'
					.' WHERE id ='. (int)$cid 
					.' AND published = 1'
					;
			
			$db->setQuery($query);
			$this->category[] 	= $db->loadObject();
		}
	}
	
	/**
	 * set the array (parentcats) of ascending parents categories, with initial category first.
	 *
	 * @param int category ids
	 */
	function buildParentCats($cid)
	{
		$db = JFactory::getDBO();
		
		$query = 'SELECT parent_id FROM #__jem_categories WHERE id = '.(int)$cid;
		$db->setQuery( $query );

		if($cid != 0) {
			array_push($this->parentcats, $cid);
		}

		//if we still have results
		if(sizeof($db->loadResult()) != 0) {
			$this->buildParentCats($db->loadResult());
		}
	}
	
	/**
	 * returns parent Categories (name, slug), with top category first
	 *
	 * @return array
	 */
	function getParentlist()
	{
		return $this->category;
	}
	
	/**
    * Get the categorie tree
    *
    * @return array
    */
	function getCategoriesTree($published)
	{
		$db	= JFactory::getDBO();
		
		if ($published) {
			$where = ' WHERE published = 1';
		} else {
			$where = '';
		}
		
		$query = 'SELECT *, id AS value, catname AS text'
				.' FROM #__jem_categories'
				.$where
				.' ORDER BY parent_id, ordering'
				;

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
		$list = eventlist_cats::treerecurse(0, '', array(), $children, true, max(0, $levellimit-1));
    	
		return $list;
	}
	
	/**
    * Get the categorie tree
    * based on the joomla 1.0 treerecurse 
    *
    * @access public
    * @return array
    */
	function treerecurse( $id, $indent, $list, &$children, $title, $maxlevel=9999, $level=0, $type=1 )
	{
		if (@$children[$id] && $level <= $maxlevel) {
			foreach ($children[$id] as $v) {
				$id = $v->id;

				if ( $type ) {
					$pre    = '&nbsp;|_&nbsp;';
					$spacer = '&nbsp;&nbsp;&nbsp;';
				} else {
					$pre    = '- ';
					$spacer = '&nbsp;&nbsp;';
				}

				if ($title) {
					if ( $v->parent_id == 0 ) {
						$txt    = ''.$v->catname;
					} else {
						$txt    = $pre.$v->catname;
					}
				} else {
					if ( $v->parent_id == 0 ) {
						$txt    = '';
					} else {
						$txt    = $pre;
					}
				}
				$pt = $v->parent_id;
				$list[$id] = $v;
				$list[$id]->treename = "$indent$txt";
				$list[$id]->children = count( @$children[$id] );

				$list = eventlist_cats::treerecurse( $id, $indent . $spacer, $list, $children, $title, $maxlevel, $level+1, $type );
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
	function buildcatselect($list, $name, $selected, $top, $class = 'class="inputbox"')
	{
		$catlist 	= array();
		
		if($top) {
			$catlist[] 	= JHTML::_( 'select.option', '0', JText::_( 'COM_JEM_TOPLEVEL' ) );
		}
		
		$catlist = array_merge($catlist, eventlist_cats::getcatselectoptions($list));
		
		return JHTML::_('select.genericlist', $catlist, $name, $class, 'value', 'text', $selected );
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
   function getcatselectoptions($list)
  {
    $catlist  = array();  
    
     // if (is_array($list))
     // {
    foreach ($list as $item) {
      $catlist[] = JHTML::_( 'select.option', $item->id, $item->treename);
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
	function getChilds($id)
	{
    $db = JFactory::getDBO();
    
		$query = ' SELECT id, parent_id '
        . ' FROM #__jem_categories '
        . ' WHERE published = 1 '
        ;
        
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
    return eventlist_cats::_getChildsRecurse($id, $children);    
	}
	
	/**
	 * recursive function to build the familly tree
	 *
	 * @param int category id
	 * @param array children indexed by parent id
	 * @return array of category descendants
	 */
	function _getChildsRecurse($id, $childs)
	{
		$result = array($id);
		if (@$childs[$id]) {
			foreach ($childs[$id] AS $c) {
				$result = array_merge($result, eventlist_cats::_getChildsRecurse($c->id, $childs));
			}
		}
    return $result;
	}
}
?>