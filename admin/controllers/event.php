<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */

// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.controllerform');

/**
 * Venue controller class.
 *
*/
class JEMControllerEvent extends JControllerForm
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 *
	 */
	protected $text_prefix = 'COM_JEM_EVENT';


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
	
	
	
	/**
	 * Function that allows child controller access to model data after the data has been saved.
	 *
	 * @param   JModelLegacy  $model  The data model object.
	 * @param   array         $validData   The validated data.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	
	/*protected function postSaveHook($model, $validData)
	{
		
		
	}
	*/


}