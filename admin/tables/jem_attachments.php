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
 * JEM attachments table class
 *
 * @package JEM
 * @since 1.1
 */
class jem_attachments extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var int */
	var $file				= '';
	/** @var int */
	var $object				= '';
	/** @var string */
	var $name 		= null;
	/** @var string */
	var $description 		= null;
	/** @var string */
	var $icon 		= null;
	/** @var int */
	var $frontend		= 1;
	/** @var int */
	var $access 		= 0;
	/** @var int */
	var $ordering 		= 0;
	/** @var string */
	var $added 		= '';
	/** @var int */
	var $added_by 		= 0;

	function jem_attachments(& $db) {
		parent::__construct('#__jem_attachments', 'id', $db);
	}

	// overloaded check function
	function check()
	{
		return true;
	}
}
?>