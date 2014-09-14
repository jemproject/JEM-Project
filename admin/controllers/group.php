<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controllerform');

/**
 * JEM Component Group Controller
 *
*/
class JEMControllerGroup extends JControllerForm
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 *
	 */
	protected $text_prefix = 'COM_JEM_GROUP';


	/**
	 * Constructor.
	 *
	 * @param	array An optional associative array of configuration settings.
	 * @see		JController
	 *
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}
}