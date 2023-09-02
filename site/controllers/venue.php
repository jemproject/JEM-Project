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
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

require_once (JPATH_COMPONENT_SITE.'/classes/controller.form.class.php');

/**
 * Venue Controller
 */
class JemControllerVenue extends JemControllerForm
{
	protected $view_item = 'editvenue';
	protected $view_list = 'venues';

	/**
	 * Method to add a new record.
	 *
	 * @return boolean True if the event can be added, false if not.
	 */
	public function add()
	{
		if (!parent::add()) {
			// Redirect to the return page.
			$this->setRedirect($this->getReturnPage());
		}
	}

	/**
	 * Method override to check if you can add a new record.
	 *
	 * @param  array An array of input data.
	 *
	 * @return boolean
	 */
	protected function allowAdd($data = array())
	{
		// Initialise variables.
		$user = JemFactory::getUser();
		// venues don't have a category yet
		//$categoryId = \Joomla\Utilities\ArrayHelper::getValue($data, 'catid', Factory::getApplication()->input->getInt('catid', 0), 'int');

		if ($user->can('add', 'venue')) {
			return true;
		}

		// In the absense of better information, revert to the component permissions.
		return parent::allowAdd();
	}

	/**
	 * Method override to check if you can edit an existing record.
	 * @todo: check if the user is allowed to edit/save
	 *
	 * @param  array  $data An array of input data.
	 * @param  string $key  The name of the key for the primary key.
	 *
	 * @return boolean
	 */
	protected function allowEdit($data = array(), $key = 'id')
	{
		// Initialise variables.
		$recordId = (int) isset($data[$key]) ? $data[$key] : 0;
		$user     = JemFactory::getUser();

		if (isset($data['created_by'])) {
			$created_by = $data['created_by'];
		} else {
			$record = $this->getModel()->getItem($recordId);
			$created_by = isset($record->created_by) ? $record->created_by : false;
		}

		if ($user->can('edit', 'venue', $recordId, $created_by)) {
			return true;
		}

		// Since there is no asset tracking, revert to the component permissions.
		return parent::allowEdit($data, $key);
	}

	/**
	 * Method to cancel an edit.
	 *
	 * @param  string $key The name of the primary key of the URL variable.
	 *
	 * @return Boolean True if access level checks pass, false otherwise.
	 */
	public function cancel($key = 'a_id')
	{
		// Check for request forgeries
		JSession::checkToken() or jexit('Invalid Token');

		parent::cancel($key);

		// Redirect to the return page.
		$this->setRedirect($this->getReturnPage());
	}

	/**
	 * Method to edit an existing record.
	 *
	 * @param  string $key    The name of the primary key of the URL variable.
	 * @param  string $urlVar The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
	 *
	 * @return boolean True if access level check and checkout passes, false otherwise.
	 */
	public function edit($key = null, $urlVar = 'a_id')
	{
		$result = parent::edit($key, $urlVar);

		return $result;
	}

	/**
	 * Method to add a new record based on existing record.
	 *
	 * @return boolean True if the venue can be added, false if not.
	 */
	public function copy()
	{
		if (!parent::add()) {
			// Redirect to the return page.
			$this->setRedirect($this->getReturnPage());
		}
	}

	/**
	 * Method to get a model object, loading it if required.
	 *
	 * @param  string $name   The model name. Optional.
	 * @param  string $prefix The class prefix. Optional.
	 * @param  array  $config Configuration array for model. Optional.
	 *
	 * @return object The model.
	 */
	public function getModel($name = 'editvenue', $prefix = '', $config = array('ignore_request' => true))
	{
		$model = parent::getModel($name, $prefix, $config);

		return $model;
	}

	/**
	 * Gets the URL arguments to append to an item redirect.
	 *
	 * @param  int    $recordId The primary key id for the item.
	 * @param  string $urlVar   The name of the URL variable for the id.
	 *
	 * @return string The arguments to append to the redirect URL.
	 */
	protected function getRedirectToItemAppend($recordId = null, $urlVar = 'a_id')
	{
		// Need to override the parent method completely.
		$jinput = Factory::getApplication()->input;
		$tmpl   = $jinput->getCmd('tmpl', '');
		$layout = $jinput->getCmd('layout', 'edit');
		$task   = $jinput->getCmd('task', '');
		$append = '';

		// Setup redirect info.
		if ($tmpl) {
			$append .= '&tmpl='.$tmpl;
		}

		$append .= '&layout=edit';

		if ($recordId) {
			$append .= '&'.$urlVar.'='.$recordId;
		}
		elseif (($task === 'copy') && ($fromId = $jinput->getInt('a_id', 0))) {
			$append .= '&from_id='.$fromId;
		}

		$itemId = $jinput->getInt('Itemid', 0);
		//$catId  = $jinput->getInt('catid', 0);
		$return = $this->getReturnPage();

		if ($itemId) {
			$append .= '&Itemid='.$itemId;
		}

		//if ($catId) {
		//	$append .= '&catid='.$catId;
		//}

		if ($return) {
			$append .= '&return='.base64_encode($return);
		}

		return $append;
	}

	/**
	 * Get the return URL.
	 *
	 * If a "return" variable has been passed in the request
	 *
	 * @return string The return URL.
	 */
	protected function getReturnPage()
	{
        $uri = Uri::getInstance();
		$return = Factory::getApplication()->input->get('return', null, 'base64');

		if (empty($return) || !Uri::isInternal(base64_decode($return))) {
			return $uri->base();
		}
		else {
			return base64_decode($return);
		}
	}

	/**
	 * Function that allows child controller access to model data
	 * after the data has been saved.
	 * Here used to trigger the jem plugins, mainly the mailer.
	 *
	 * @param  JModel(Legacy)  $model      The data model object.
	 * @param  array           $validData  The validated data.
	 *
	 * @return void
	 */
	protected function _postSaveHook($model, $validData = array())
	{
		$task = $this->getTask();
		if ($task == 'save') {
			$isNew = $model->getState('editvenue.new');
			$id    = $model->getState('editvenue.id');

			// trigger all jem plugins
			JPluginHelper::importPlugin('jem');
			$dispatcher = JemFactory::getDispatcher();
			$dispatcher->triggerEvent('onVenueEdited', array($id, $isNew));

			// but show warning if mailer is disabled
			if (!JPluginHelper::isEnabled('jem', 'mailer')) {
				Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'notice');
			}
		}
	}

	/**
	 * Method to save a record.
	 *
	 * @param  string $key    The name of the primary key of the URL variable.
	 * @param  string $urlVar The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
	 *
	 * @return boolean True if successful, false otherwise.
	 */
	public function save($key = null, $urlVar = 'a_id')
	{
		// Check for request forgeries
		JSession::checkToken() or jexit('Invalid Token');

		$result = parent::save($key, $urlVar);

		// If ok, redirect to the return page.
		if ($result) {
			$this->setRedirect($this->getReturnPage());
		}

		return $result;
	}
}
