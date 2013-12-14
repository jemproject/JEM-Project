<?php
/**
 * @version 1.9.5
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
 *
 */
class jem_groupmembers extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 		= null;
	/** @var int */
	var $group_id	= null;
	/** @var int */
	var $member		= null;

	function __construct(& $db) {
		parent::__construct('#__jem_groupmembers', 'id', $db);
	}
}
?>