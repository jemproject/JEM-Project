<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.controllerform');

/**
 * Event Controller
 */
class JEMControllerEvent extends JControllerForm
{
	/**
	 * @since	1.6
	 */
	protected $view_item = 'editevent';

	/**
	 * @since	1.6
	 */
	protected $view_list = 'eventslist';

	/**
	 * Method to add a new record.
	 *
	 * @return	boolean	True if the event can be added, false if not.
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
	 * @param	array	An array of input data.
	 *
	 * @return	boolean
	 */
	protected function allowAdd($data = array())
	{
		// Initialise variables.
		$user		= JFactory::getUser();
		$categoryId	= JArrayHelper::getValue($data, 'catid', JRequest::getInt('catid'), 'int');
		$allow		= null;
		
		if ($categoryId) {
			// If the category has been passed in the data or URL check it.
			$allow	= $user->authorise('core.create', 'com_jem.category.'.$categoryId);
		}
		
		$jemsettings	= JEMHelper::config();
		$maintainer		= JEMUser::ismaintainer('add');
		$genaccess		= JEMUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes);
		
		if ($maintainer || $genaccess) {
			return true;
		}		

		if ($allow === null) {
			// In the absense of better information, revert to the component permissions.
			return parent::allowAdd();
		}
		else {
			return $allow;
		}		
		
	}

	/**
	 * Method override to check if you can edit an existing record.
	 * @todo: check if the user is allowed to edit/save
	 *
	 * @param	array	$data	An array of input data.
	 * @param	string	$key	The name of the key for the primary key.
	 *
	 * @return	boolean
	 */
	protected function allowEdit($data = array(), $key = 'id')
	{
		// Initialise variables.
		$recordId	= (int) isset($data[$key]) ? $data[$key] : 0;
		$user		= JFactory::getUser();
		$userId		= $user->get('id');
		$asset		= 'com_jem.event.'.$recordId;

		// Check general edit permission first.
		if ($user->authorise('core.edit', $asset)) {
			return true;
		}

		// Fallback on edit.own.
		// First test if the permission is available.
		if ($user->authorise('core.edit.own', $asset)) {
			// Now test the owner is the user.
			$ownerId	= (int) isset($data['created_by']) ? $data['created_by'] : 0;
			if (empty($ownerId) && $recordId) {
				// Need to do a lookup from the model.
				$record		= $this->getModel()->getItem($recordId);

				if (empty($record)) {
					return false;
				}

				$ownerId = $record->created_by;
			}

			// If the owner matches 'me' then do the test.
			if ($ownerId == $userId) {
				return true;
			}
		}
		
		$record			= $this->getModel()->getItem($recordId);
		$jemsettings 	= JEMHelper::config();
		$editaccess		= JEMUser::editaccess($jemsettings->eventowner, $record->created_by, $jemsettings->eventeditrec, $jemsettings->eventedit);
		
		$maintainer 	= JEMUser::ismaintainer('edit',$record->id);
		
		
		if ($maintainer || $editaccess)
		{
			return true;
		}
		
		// Since there is no asset tracking, revert to the component permissions.
		return parent::allowEdit($data, $key);
	}

	/**
	 * Method to cancel an edit.
	 *
	 * @param	string	$key	The name of the primary key of the URL variable.
	 *
	 * @return	Boolean	True if access level checks pass, false otherwise.
	 */
	public function cancel($key = 'a_id')
	{
		parent::cancel($key);

		// Redirect to the return page.
		$this->setRedirect($this->getReturnPage());
	}

	/**
	 * Method to edit an existing record.
	 *
	 * @param	string	$key	The name of the primary key of the URL variable.
	 * @param	string	$urlVar	The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
	 *
	 * @return	Boolean	True if access level check and checkout passes, false otherwise.
	 */
	public function edit($key = null, $urlVar = 'a_id')
	{
		$result = parent::edit($key, $urlVar);

		return $result;
	}

	/**
	 * Method to get a model object, loading it if required.
	 *
	 * @param	string	$name	The model name. Optional.
	 * @param	string	$prefix	The class prefix. Optional.
	 * @param	array	$config	Configuration array for model. Optional.
	 *
	 * @return	object	The model.
	 *
	 */
	public function getModel($name = 'editevent', $prefix = '', $config = array('ignore_request' => true))
	{
		$model = parent::getModel($name, $prefix, $config);

		return $model;
	}

	/**
	 * Gets the URL arguments to append to an item redirect.
	 *
	 * @param	int		$recordId	The primary key id for the item.
	 * @param	string	$urlVar		The name of the URL variable for the id.
	 *
	 * @return	string	The arguments to append to the redirect URL.
	 */
	protected function getRedirectToItemAppend($recordId = null, $urlVar = 'a_id')
	{
		// Need to override the parent method completely.
		$tmpl		= JRequest::getCmd('tmpl');
		$layout		= JRequest::getCmd('layout', 'edit');
		$append		= '';

		// Setup redirect info.
		if ($tmpl) {
			$append .= '&tmpl='.$tmpl;
		}

		$append .= '&layout=edit';

		if ($recordId) {
			$append .= '&'.$urlVar.'='.$recordId;
		}

		$itemId	= JRequest::getInt('Itemid');
		$return	= $this->getReturnPage();
		$catId = JRequest::getInt('catid', null, 'get');

		if ($itemId) {
			$append .= '&Itemid='.$itemId;
		}

		if($catId) {
			$append .= '&catid='.$catId;
		}

		if ($return) {
			$append .= '&return='.base64_encode(urlencode($return));
		}

		return $append;
	}

	/**
	 * Get the return URL.
	 *
	 * If a "return" variable has been passed in the request
	 *
	 * @return	string	The return URL.
	 */
	protected function getReturnPage()
	{
		$return = JRequest::getVar('return', null, 'default', 'base64');
		
		if (empty($return) || !JUri::isInternal(urldecode(base64_decode($return)))) {
			return JURI::base();
		}
		else {
			return urldecode(base64_decode($return));
		}
	}


	protected function postSaveHook(JModel &$model, $validData = array())
	{
		
	$task = $this->getTask();
	if ($task == 'save') {
		// doesn't work on new events - get values from model instead
		//$isNew 	= ($validData['id']) ? false : true;
		//$id 	= $validData['id'];
		$isNew = $model->getState('editevent.new');
		$id    = $model->getState('editevent.id');

		JPluginHelper::importPlugin('jem');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onEventEdited', array($id, $isNew));
		}
	}
	
	/**
	 * Method to save a record.
	 *
	 * @param	string	$key	The name of the primary key of the URL variable.
	 * @param	string	$urlVar	The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
	 *
	 * @return	Boolean	True if successful, false otherwise.
	 */
	public function save($key = null, $urlVar = 'a_id')
	{
		// Load the backend helper for filtering.
		require_once JPATH_ADMINISTRATOR.'/components/com_jem/helpers/helper.php';

		$result = parent::save($key, $urlVar);
		
		// If ok, redirect to the return page.
		if ($result) {
			$this->setRedirect($this->getReturnPage());
		}

		return $result;
	}
	
	/**
	 * Saves the registration to the database
	 */
	function userregister()
	{
		
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');
	
		$id 	= JRequest::getInt('rdid', 0, 'post');
	
		// Get the model
		$model = $this->getModel('Event', 'JEMModel');
		$model->setId($id);
		$register_id = $model->userregister();
		
		if (!$register_id)
		{
			$msg = $model->getError();
			$this->setRedirect(JRoute::_(JEMHelperRoute::getEventRoute($id), false), $msg, 'error');
			$this->redirect();
			return;
		}
	
		JPluginHelper::importPlugin('jem');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onEventUserRegistered', array($register_id));
	
		$cache = JFactory::getCache('com_jem');
		$cache->clean();
	
		$msg = JText::_('COM_JEM_REGISTERED_SUCCESSFULL');
	
		$this->setRedirect(JRoute::_(JEMHelperRoute::getEventRoute($id), false), $msg);
	}
	
	/**
	 * Deletes a registered user
	 */
	function delreguser()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');
	
		$id = JRequest::getInt('rdid', 0, 'post');

		// Get/Create the model
		$model = $this->getModel('Event', 'JEMModel');
	
		$model->setId($id);
		$model->delreguser();
	
		JEMHelper::updateWaitingList($id);
	
		JPluginHelper::importPlugin('jem');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onEventUserUnregistered', array($id));
	
		$cache = JFactory::getCache('com_jem');
		$cache->clean();
	
		$msg = JText::_('COM_JEM_UNREGISTERED_SUCCESSFULL');
		$this->setRedirect(JRoute::_(JEMHelperRoute::getEventRoute($id), false), $msg);
	}
}
