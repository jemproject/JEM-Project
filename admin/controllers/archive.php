<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controlleradmin');

/**
 * JEM Component Archive Controller
 *
 */
class JEMControllerArchive extends JControllerAdmin
{


	/**
	 * @var		string	The prefix to use with controller messages.
	 *
	 */
	protected $text_prefix = 'COM_JEM_ARCHIVE';


	/**
	 * unarchives an Event
	 *
	 * @access public
	 * @return void
	 *
	 */
	function unarchivetask()
	{

		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$ids 	= JRequest::getVar('cid',array(),'','array');
		$values	= array('unarchivetask' => 0);
		$task	= $this->getTask();
		$value	= JArrayHelper::getValue($values, $task, 0, 'int');

		if (empty($ids)) {
			JError::raiseWarning(500, JText::_('COM_JEM_SELECT_ITEM_TO_UNARCHIVE'));
		} else {
			// Get the model.
			$model	= $this->getModel('archive');

			if(!$model->publish($ids, $value)) {
				JError::raiseWarning(500, $model->getError());
			}

			$ntext = JText::_('COM_JEM_EVENT_UNARCHIVED');
			$this->setMessage(JText::plural($ntext, count($ids)));
			$this->setRedirect( 'index.php?option=com_jem&view=archive');

	}

	}


	/**
	 * removes an Event
	 *
	 * @access public
	 * @return void
	 *
	 */
	function removetask()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$ids 	= JRequest::getVar('cid',array(),'','array');


		if (empty($ids)) {
			JError::raiseWarning(500, JText::_('COM_JEM_SELECT_ITEM_TO_DELETE'));
		} else {
			// Get the model.
			$model	= $this->getModel('archive');

			if(!$model->delete($ids)) {
				JError::raiseWarning(500, $model->getError());
			}


		$ntext = JText::_('COM_JEM_EVENTS_DELETED');
		$this->setMessage(JText::plural($ntext, count($ids)));
		$this->setRedirect( 'index.php?option=com_jem&view=archive');

	}

	}




}
?>