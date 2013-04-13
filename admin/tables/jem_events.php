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
 * JEM events Model class
 *
 * @package JEM
 * @since 0.9
 */
class jem_events extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var int */
	var $locid 				= null;
	/** @var date */
	var $dates 				= null;
	/** @var date */
	var $enddates 			= null;
	/** @var date */
	var $times 				= null;
	/** @var date */
	var $endtimes 			= null;
	/** @var string */
	var $title 				= '';
	/** @var string */
	var $alias	 			= '';
	/** @var date */
	var $created	 		= null;
	/** @var int */
	var $created_by			= null;
	/** @var int */
	var $modified 			= 0;
	/** @var int */
	var $modified_by 		= null;
	/** @var int */
	var $version	 		= 0;
	/** @var string */
	var $datdescription 	= null;
	/** @var string */
	var $meta_description 	= '';
	/** @var string */
	var $meta_keywords		= '';
	/**
	 * repetition intervall
	 *
	 * @var int
	 */
	var $recurrence_number	= 0;
	/**
	 * type of recurrence (daily, weekly, monthly)
	 *
	 * @var int
	 */
	var $recurrence_type	= 0;
	/**
	 * occurence counter
	 *
	 * @var int
	 */
	var $recurrence_counter = 0;
  	/**
   	* limit counter for repetition
   	*
   	* @var string
   	*/
  	var $recurrence_limit = 0;
  	/**
   	* limit date for repetition
	*
   	* @var string
   	*/
  	var $recurrence_limit_date = null;
  	/**
   	* list of day the event occurs on (2 letters, separated by comma)
   	*
   	* @var string
   	*/
  	var $recurrence_byday = '';
  	/** @var int id of first event for recurrence events*/
	var $recurrence_first_id = 0;
	/** @var string */
	var $datimage 			= '';
	/** @var string */
	var $author_ip 			= null;
	/** @var int */
	var $published 			= null;
	/** @var int */
	var $registra 			= null;
	/** @var int */
	var $unregistra 		= null;
	/** @var int */
	var $maxplaces 			= 0;
	/** @var int */
	var $waitinglist 		= 0;
	/** @var int */
	var $hits				= 0;
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time 	= 0;

	function jem_events(& $db) {
		parent::__construct('#__jem_events', 'id', $db);
	}

	// overloaded check function
	function check($elsettings=null)
	{
		// Check fields
		if (empty($this->enddates)) {
			$this->enddates = NULL;
		}

		if ($this->times == '00:00') {
			$this->times = NULL;
		}

		if ($this->endtimes == '00:00') {
			$this->endtimes = NULL;
		}

		$this->title = strip_tags(trim($this->title));
		$titlelength = JString::strlen($this->title);

		if ( $this->title == '' ) {
			$this->_error = JText::_( 'ADD TITLE' );
      		JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
      		return false;
		}

		if ( $titlelength > 100 ) {
      		$this->_error = JText::_( 'ERROR TITLE LONG' );
      		JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
      		return false;
		}

		$alias = JFilterOutput::stringURLSafe($this->title);

		if(empty($this->alias) || $this->alias === $alias ) {
			$this->alias = $alias;
		}

		if ($this->dates && !preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $this->dates)) {
	      	$this->_error = JText::_( 'DATE WRONG' );
	      	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
	      	return false;
		}

		if (isset($this->enddates)) {
			if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $this->enddates)) {
				$this->_error = JText::_( 'ENDDATE WRONG FORMAT');
				JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
				return false;
			}
		}

/*		if (isset($this->recurrence_limit_date)) {
			if (!preg_match("/^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/", $this->recurrence_limit_date)) {
	 		     	$this->_error = JText::_( 'WRONGRECURRENCEDATEFORMAT' );
	 		     	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
	 		     	return false;
			}
		}
		*/

		if (isset($this->times) && $this->times) {
   			if (!preg_match("/^[0-2][0-9]:[0-5][0-9](:[0-5][0-9])?$/", $this->times)) {
      			$this->_error = JText::_( 'WRONGSTARTTIMEFORMAT'.': '.$this->times );
      			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
      			return false;
	  		}
		}

		if (isset($this->endtimes) && $this->endtimes) {
   			if (!preg_match("/^[0-2][0-9]:[0-5][0-9](:[0-5][0-9])?$/", $this->endtimes)) {
      			$this->_error = JText::_( 'WRONGENDTIMEFORMAT' );
      			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
      			return false;
	  		}
		}

		//No venue or category choosen?
		if($this->locid == '') {
			$this->_error = JText::_( 'VENUE EMPTY');
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
			return false;
		}

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
    $fmtsql = 'INSERT IGNORE INTO '.$this->_db->nameQuote($table).' ( %s ) VALUES ( %s ) ';
    $fields = array();
    foreach (get_object_vars( $object ) as $k => $v) {
      if (is_array($v) or is_object($v) or $v === NULL) {
        continue;
      }
      if ($k[0] == '_') { // internal field
        continue;
      }
      $fields[] = $this->_db->nameQuote( $k );
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