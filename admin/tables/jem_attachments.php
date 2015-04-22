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
 * JEM attachments table class
 *
 * @package JEM
 *
 */
class jem_attachments extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 			= null;
	/** @var int */
	var $file			= '';
	/** @var int */
	var $object			= '';
	/** @var string */
	var $name 			= null;
	/** @var string */
	var $description 	= null;
	/** @var string */
	var $icon 			= null;
	/** @var int */
	var $frontend		= 1;
	/** @var int */
	var $access 		= 0;
	/** @var int */
	var $ordering 		= 0;
	/** @var string */
	var $added 			= '';
	/** @var int */
	var $added_by 		= 0;

	public function __construct(& $db) {
		parent::__construct('#__jem_attachments', 'id', $db);
	}

	// overloaded check function
	function check()
	{
		return true;
	}
}
?>