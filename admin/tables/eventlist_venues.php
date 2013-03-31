<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined('_JEXEC') or die('Restricted access');

/**
 * EventList venues Model class
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class eventlist_venues extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var string */
	var $venue 				= '';
	/** @var string */
	var $alias	 			= '';
	/** @var string */
	var $url 				= '';
	/** @var string */
	var $street 			= '';
	/** @var string */
	var $plz 				= '';
	/** @var string */
	var $city 				= '';
	/** @var string */
	var $state				= '';
	/** @var string */
	var $country			= '';
  	/** @var float */
  	var $latitude      		= null;
  	/** @var float */
  	var $longitude     		= null;
	/** @var string */
	var $locdescription 	= null;
	/** @var string */
	var $meta_description 	= '';
	/** @var string */
	var $meta_keywords		= '';
	/** @var string */
	var $locimage 			= '';
	/** @var int */
	var $map		 		= null;
	/** @var int */
	var $created_by			= null;
	/** @var string */
	var $author_ip	 		= null;
	/** @var date */
	var $created		 	= null;
	/** @var date */
	var $modified 			= 0;
	/** @var int */
	var $modified_by 		= null;
	/** @var int */
	var $version	 		= null;
	/** @var int */
	var $published	 		= null;
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time 	= 0;
	/** @var int */
	var $ordering 			= null;

	function eventlist_venues(& $db) {
		parent::__construct('#__eventlist_venues', 'id', $db);
	}

	// overloaded check function
	//function check($elsettings)
	function check()
	
	{
		// not typed in a venue name
		if(!trim($this->venue)) {
	      	$this->_error = JText::_( 'ADD VENUE');
	      	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
	       	return false;
		}

		$alias = JFilterOutput::stringURLSafe($this->venue);

		if(empty($this->alias) || $this->alias === $alias ) {
			$this->alias = $alias;
		}

		if ( $this->map ){
			if ( !trim($this->street) || !trim($this->city) || !trim($this->country) || !trim($this->plz) ) {
				if (( !trim($this->latitude) && !trim($this->longitude))) {
					$this->_error = JText::_( 'ERROR ADDRESS');
					JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
					return false;
				}
			}
		}
		
		if (JFilterInput::checkAttribute(array ('href', $this->url))) {
			$this->_error = JText::_( 'ERROR URL WRONG FORMAT' );
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
			return false;
		}

		if (trim($this->url)) {
			$this->url = strip_tags($this->url);
			$urllength = strlen($this->url);

			if ($urllength > 199) {
      			$this->_error = JText::_( 'ERROR URL LONG' );
      			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
      			return false;
			}
			if (!preg_match( '/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}'
       		.'((:[0-9]{1,5})?\/.*)?$/i' , $this->url)) {
				$this->_error = JText::_( 'ERROR URL WRONG FORMAT' );
				JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
				return false;
			}
		}

		$this->street = strip_tags($this->street);
		$streetlength = JString::strlen($this->street);
		if ($streetlength > 50) {
     	 	$this->_error = JText::_( 'ERROR STREET LONG' );
     	 	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
     	 	return false;
		}

		$this->plz = strip_tags($this->plz);
		$plzlength = JString::strlen($this->plz);
		if ($plzlength > 10) {
      		$this->_error = JText::_( 'ERROR ZIP LONG' );
      		JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
      		return false;
		}

		$this->city = strip_tags($this->city);
		$citylength = JString::strlen($this->city);
		if ($citylength > 50) {
    	  	$this->_error = JText::_( 'ERROR CITY LONG' );
    	  	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
    	  	return false;
		}

		$this->state = strip_tags($this->state);
		$statelength = JString::strlen($this->state);
		if ($statelength > 50) {
    	  	$this->_error = JText::_( 'ERROR STATE LONG' );
    	  	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
    	  	return false;
		}

		$this->country = strip_tags($this->country);
		$countrylength = JString::strlen($this->country);
		if ($countrylength > 2) {
     	 	$this->_error = JText::_( 'ERROR COUNTRY LONG' );
     	 	JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
     	 	return false;
		}
		
		/** check for existing name */
		$query = 'SELECT id FROM #__eventlist_venues WHERE venue = '.$this->_db->Quote($this->venue);
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('VENUE NAME ALREADY EXIST', $this->venue));
			return false;
		}

		return true;
	}
}
?>