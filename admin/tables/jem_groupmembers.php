<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
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

	public function __construct(& $db) {
		parent::__construct('#__jem_groupmembers', 'id', $db);
	}
}
?>