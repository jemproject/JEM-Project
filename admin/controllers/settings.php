<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */

// No direct access
defined('_JEXEC') or die;



/**
 * Settings controller class.
 *
*/
class JEMControllerSettings extends JControllerLegacy
{

	public function __construct($config = array())
	{
		parent::__construct($config);

		// Map the apply task to the save method.
		$this->registerTask('apply', 'save');

	}

	/**
	 * Method to save the configuration data.
	 *
	 * @param	array	An array containing all global config data.
	 *
	 * @return	bool	True on success, false on failure.
	 *
	 * @since	1.6
	 */
	public function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );

		// Sanitize
		$task	= JRequest::getVar('task');
		$jinput = JFactory::getApplication()->input;

		$post = JRequest::getVar('jform', array(), 'post', 'array');
		$post2 = $jinput->getArray($_POST);
		//var_dump($post2);exit;


		//get model
		$model 	= $this->getModel('settings');

		if ($model->store($post,$post2)) {
			$msg	= JText::_('COM_JEM_SETTINGS_SAVED');
		} else {
			$msg	= JText::_('COM_JEM_SAVE_SETTINGS_FAILED');
		}

		switch ($task)
		{
			case 'apply':
				$link = 'index.php?option=com_jem&view=settings';
				break;

			default:
				$link = 'index.php?option=com_jem&view=jem';
				break;
		}
		$model->checkin();

		$this->setRedirect( $link, $msg );
	}



	 /**
	  * Cancel operation
	  */
	public function cancel()
			{
				 // Check if the user is authorized to do this.
				 if (!JFactory::getUser()->authorise('core.admin', 'com_jem'))
				 	{
				 	JFactory::getApplication()->redirect('index.php', JText::_('JERROR_ALERTNOAUTHOR'));
				 	return;
				 }


			$this->setRedirect('index.php?option=com_jem');
		}


	/**
	 * Function that allows child controller access to model data after the data has been saved.
	 *
	 * @param   JModelLegacy  $model  The data model object.
	 * @param   array         $validData   The validated data.
	 *
	 * @return  void
	 *
	 */

	/*protected function postSaveHook($model, $validData)
	{


	}
	*/


}