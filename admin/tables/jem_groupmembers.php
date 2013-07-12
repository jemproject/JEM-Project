<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * JEM groupmembers Model class
 *
 * @package JEM
 * @since 0.9
 */
class jem_groupmembers extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var int */
	var $member				= null;

	function jem_groupmembers(& $db) {
		parent::__construct('#__jem_groupmembers', '', $db);
	}
}
?>