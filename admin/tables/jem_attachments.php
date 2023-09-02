<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;

/**
 * JEM attachments table class
 *
 * @package JEM
 *
 */
class jem_attachments extends Table
{
	/**
	 * Primary Key
	 * @var int
	 */
	public $id = null;
	/** @var int */
	public $file = '';
	/** @var int */
	public $object = '';
	/** @var string */
	public $name = null;
	/** @var string */
	public $description = null;
	/** @var string */
	public $icon = null;
	/** @var int */
	public $frontend = 1;
	/** @var int */
	public $access = 0;
	/** @var int */
	public $ordering = 0;
	/** @var string */
	public $added = '';
	/** @var int */
	public $added_by = 0;


	public function __construct(& $db)
	{
		parent::__construct('#__jem_attachments', 'id', $db);
	}

	// overloaded check function
	public function check()
	{
		return true;
	}
}
?>
