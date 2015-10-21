<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */
defined('_JEXEC') or die();

/**
 * JEM Component Settings Controller
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
	 * Method to check if you can add a new record.
	 *
	 * Extended classes can override this if necessary.
	 *
	 * @param array	An array of input data.
	 * @param string	The name of the key for the primary key.
	 *
	 * @return boolean
	 */
	protected function allowEdit()
	{
		return JemFactory::getUser()->authorise('core.manage', 'com_jem');
	}

	/**
	 * Method to check if you can save a new or existing record.
	 *
	 * Extended classes can override this if necessary.
	 *
	 * @param array	An array of input data.
	 * @param string	The name of the key for the primary key.
	 *
	 * @return boolean
	 */
	protected function allowSave()
	{
		return $this->allowEdit();
	}

	/**
	 * Method to get a model object, loading it if required.
	 *
	 * @param string	The model name. Optional.
	 * @param string	The class prefix. Optional.
	 * @param array	Configuration array for model. Optional (note, the empty
	 *        array is atypical compared to other models).
	 *
	 * @return object model.
	 */
	public function getModel($name = 'Settings', $prefix = 'JEMModel', $config = array())
	{
		$model = parent::getModel($name, $prefix, $config);
		return $model;
	}

	/**
	 * Method to save the configuration data.
	 *
	 * @param array	An array containing all global config data.
	 * @return bool on success, false on failure.
	 * @since 1.6
	 */
	public function save()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		// Initialise variables.
		$app = JFactory::getApplication();
		$data = $app->input->get('jform', array(), 'array');

		$task = $this->getTask();
		$model = $this->getModel();
		$context = 'com_jem.edit.settings';

		// Access check.
		if (!$this->allowSave()) {
			return JError::raiseWarning(403, JText::_('JERROR_SAVE_NOT_PERMITTED'));
		}

		// Validate the posted data.
		$form = $model->getForm();
		if (!$form) {
			JError::raiseError(500, $model->getError());
			return false;
		}

		// Validate the posted data.
		$form = $model->getForm();
		$data = $model->validate($form, $data);

		// Check for validation errors.
		if ($data === false) {
			// Get the validation messages.
			$errors = $model->getErrors();

			// Push up to three validation messages out to the user.
			for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
				if ($errors[$i] instanceof Exception) {
					$app->enqueueMessage($errors[$i]->getMessage(), 'warning');
				}
				else {
					$app->enqueueMessage($errors[$i], 'warning');
				}
			}

			// Save the data in the session.
			$app->setUserState($context . '.data', $data);

			// Redirect back to the edit screen.
			$this->setRedirect(JRoute::_('index.php?option=com_jem&view=settings', false));
			return false;
		}

		// Attempt to save the data.
		if (!$model->store($data)) {
			// Save the data in the session.
			$app->setUserState($context . '.data', $data);

			// Redirect back to the edit screen.
			$this->setMessage(JText::sprintf('JERROR_SAVE_FAILED', $model->getError()), 'warning');
			$this->setRedirect(JRoute::_('index.php?option=com_jem&view=settings', false));
			return false;
		}

		$this->setMessage(JText::_('COM_JEM_SETTINGS_SAVED'));

		// Redirect the user and adjust session state based on the chosen task.
		switch ($task)
		{
			case 'apply':
				// Reset the record data in the session.
				$app->setUserState($context . '.data', null);

				// Redirect back to the edit screen.
				$this->setRedirect(JRoute::_('index.php?option=com_jem&view=settings', false));
				break;

			default:
				// Clear the record id and data from the session.
				$app->setUserState($context . '.id', null);
				$app->setUserState($context . '.data', null);

				// Redirect to the list screen.
				$this->setRedirect(JRoute::_('index.php?option=com_jem&view=main', false));
				break;
		}
	}

	/**
	 * Cancel operation
	 */
	public function cancel()
	{
		// Check if the user is authorized to do this.
		if (!JemFactory::getUser()->authorise('core.admin', 'com_jem')) {
			JFactory::getApplication()->redirect('index.php', JText::_('JERROR_ALERTNOAUTHOR'));
			return;
		}

		$this->setRedirect('index.php?option=com_jem');
	}

}