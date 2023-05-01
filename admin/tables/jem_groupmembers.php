<?php
/**
 * @version 2.3.6
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
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
	public $id = null;
	/** @var int */
	public $group_id = null;
	/** @var int */
	public $member = null;


	public function __construct(& $db)
	{
		parent::__construct('#__jem_groupmembers', 'id', $db);
	}
}
?>
