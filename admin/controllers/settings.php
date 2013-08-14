<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Settings Controller
 *
 * @package JEM
 * 
 */
class JEMControllerSettings extends JEMController
{
	/**
	 * Constructor
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'apply', 		'save' );
	}

	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function cancel()
	{
		$model = $this->getModel('settings');

		$model->checkin();

		$this->setRedirect( 'index.php?option=com_jem&view=jem' );
	}

	/**
	 * logic to create the edit venue view
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function edit( )
	{
		JRequest::setVar( 'view', 'settings' );

		parent::display();

		$model = $this->getModel('settings');

		$model->checkout();
	}

	/**
	 * saves the venue in the database
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );

		// Sanitize
		$task	= JRequest::getVar('task');
		$post 	= JRequest::get( 'post' );

		//get model
		$model 	= $this->getModel('settings');

		if ($model->store($post)) {
			$msg	= JText::_('COM_JEM_SETTINGS_SAVED');
		} else {
			$msg	= JText::_('COM_JEM_SAVE_SETTINGS_FAILED');
		}

		switch ($task)
		{
			case 'apply':
				$link = 'index.php?option=com_jem&task=settings.edit';
				break;

			default:
				$link = 'index.php?option=com_jem&view=jem';
				break;
		}
		$model->checkin();

		$this->setRedirect( $link, $msg );
	}
}
?>