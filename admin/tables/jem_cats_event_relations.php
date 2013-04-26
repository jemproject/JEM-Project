<?php
/**
 * @version 1.1 $Id$
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
 * JEM table class
 *
 * @package JEM
 * @since 1.1
 */
class jem_cats_event_relations extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $catid 				= null;
	/**
	 * Primary Key
	 * @var int
	 */
	var $itemid				= null;
	/**
	 * Ordering
	 * @var int
	 * @todo implement
	 */
	var $ordering			= null;

	function jem_cats_event_relations(& $db) {
		parent::__construct('#__jem_cats_event_relations', 'catid', $db);
	}
	
	// overloaded check function
	function check()
	{
		return true;
	}
	
		  /**
   * try to insert first, update if fails
   *
   * Can be overloaded/supplemented by the child class
   *
   * @access public
   * @param boolean If false, null object variables are not updated
   * @return null|string null if successful otherwise returns and error message
   */
  function insertIgnore( $updateNulls=false )
  {
    $k = $this->_tbl_key;

    $ret = $this->_insertIgnoreObject( $this->_tbl, $this, $this->_tbl_key );
    if( !$ret )
    {
      $this->setError(get_class( $this ).'::store failed - '.$this->_db->getErrorMsg());
      return false;
    }
    return true;
  }

  /**
   * Inserts a row into a table based on an objects properties, ignore if already exists
   *
   * @access  public
   * @param string  The name of the table
   * @param object  An object whose properties match table fields
   * @param string  The name of the primary key. If provided the object property is updated.
   * @return int number of affected row
   */
  function _insertIgnoreObject( $table, &$object, $keyName = NULL )
  {
    $fmtsql = 'INSERT IGNORE INTO '.$this->_db->quoteName($table).' ( %s ) VALUES ( %s ) ';
    $fields = array();
    foreach (get_object_vars( $object ) as $k => $v) {
      if (is_array($v) or is_object($v) or $v === NULL) {
        continue;
      }
      if ($k[0] == '_') { // internal field
        continue;
      }
      $fields[] = $this->_db->quoteName( $k );
      $values[] = $this->_db->isQuoted( $k ) ? $this->_db->Quote( $v ) : (int) $v;
    }
    $this->_db->setQuery( sprintf( $fmtsql, implode( ",", $fields ) ,  implode( ",", $values ) ) );
    if (!$this->_db->query()) {
      return false;
    }
    $id = $this->_db->insertid();
    if ($keyName && $id) {
      $object->$keyName = $id;
    }
    return $this->_db->getAffectedRows();
  }
	
	
	
	
	
	
	
	
	
}
?>