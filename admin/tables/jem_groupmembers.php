<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;

/**
 * JEM groupmembers Model class
 *
 * @package JEM
 *
 */
class jem_groupmembers extends Table
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
