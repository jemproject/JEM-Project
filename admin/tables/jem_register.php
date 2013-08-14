<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * JEM registration Model class
 *
 * @package JEM
 * 
 */
class jem_register extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 		= null;
	/** @var int */
	var $event 		= null;
	/** @var int */
	var $uid 		= null;
	/** @var date */
	var $uregdate 	= null;
	/** @var string */
	var $uip 		= null;
	/** @var int */
	var $waiting 		= 0;

	function jem_register(& $db) {
		parent::__construct('#__jem_register', 'id', $db);
	}
}
?>