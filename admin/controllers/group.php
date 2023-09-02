<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

/**
 * JEM Component Group Controller
 *
*/
class JemControllerGroup extends FormController
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 *
	 */
	protected $text_prefix = 'COM_JEM_GROUP';


	/**
	 * Constructor.
	 *
	 * @param  array  An optional associative array of configuration settings.
	 * @see    JController
	 *
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}
}
