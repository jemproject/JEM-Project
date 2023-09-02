<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * JEM Component Settings Controller
 */
class JemControllerSettings extends BaseController
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
	 * @return boolean
	 */
	protected function allowEdit()
	{
		return JemFactory::getUser()->authorise('core.manage', 'com_jem');
	}

	/**
	 * Method to check if you can save a new or existing record.
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
	 * @param  string  The model name. Optional.
	 * @param  string  The class prefix. Optional.
	 * @param  array   Configuration data for model. Optional.
	 *
	 * @return object  The model.
	 */
	public function getModel($name = 'Settings', $prefix = 'JemModel', $config = array())
	{
		$model = parent::getModel($name, $prefix, $config);
		return $model;
	}

	/**
	 * Method to save the configuration data.
	 *
	 * @param  array  An array containing all global config data.
	 * @return bool   True on success, false on failure.
	 * @since 1.6
	 */
	public function save()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		// Initialise variables.
		$app = Factory::getApplication();
		$data = $app->input->get('jform', array(), 'array');

		$task = $this->getTask();
		$model = $this->getModel();
		$context = 'com_jem.edit.settings';

		// Access check.
		if (!$this->allowSave()) {
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_SAVE_NOT_PERMITTED'), 'warning');
		}

		// Validate the posted data.
		$form = $model->getForm();
		if (!$form) {
			Factory::getApplication()->enqueueMessage($model->getError(), 'error');
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
			$this->setMessage(Text::sprintf('JERROR_SAVE_FAILED', $model->getError()), 'warning');
			$this->setRedirect(JRoute::_('index.php?option=com_jem&view=settings', false));
			return false;
		}

		$this->setMessage(Text::_('COM_JEM_SETTINGS_SAVED'));

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
		// Check for request forgeries.
		JSession::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		// Check if the user is authorized to do this.
		if (!JemFactory::getUser()->authorise('core.admin', 'com_jem')) {
			Factory::getApplication()->redirect('index.php', Text::_('JERROR_ALERTNOAUTHOR'));
			return;
		}

		$this->setRedirect('index.php?option=com_jem');
	}

}
