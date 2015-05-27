<?php
/**
 * @version 2.1.4
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;
jimport('joomla.application.component.controllerform');

/**
 * Venue Controller
 */
class JEMControllerVenue extends JControllerForm
{
	protected $view_item = 'editvenue';
	protected $view_list = 'venues';

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
		//$categoryId	= JArrayHelper::getValue($data, 'catid', JFactory::getApplication()->input->getInt('catid', 0), 'int');
		$allow		= null;

		//if ($categoryId) {
		//	// If the category has been passed in the data or URL check it.
		//	$allow	= $user->authorise('core.create', 'com_jem.category.'.$categoryId);
		//}

		$jemsettings	= JemHelper::config();
		$maintainer 	= JEMUser::venuegroups('add');
		$delloclink 	= JEMUser::validate_user($jemsettings->locdelrec, $jemsettings->deliverlocsyes);

		if ($maintainer || $delloclink) {
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
		$asset		= 'com_jem.venue.'.$recordId;

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
		$maintainer 	= JEMUser::venuegroups('edit');
		$genaccess 		= JEMUser::editaccess($jemsettings->venueowner, $record->created_by, $jemsettings->venueeditrec, $jemsettings->venueedit);
		if ($maintainer || $genaccess) {
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
	public function getModel($name = 'editvenue', $prefix = '', $config = array('ignore_request' => true))
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
		$jinput = JFactory::getApplication()->input;
		$tmpl   = $jinput->getCmd('tmpl', '');
		$layout = $jinput->getCmd('layout', 'edit');
		$append = '';

		// Setup redirect info.
		if ($tmpl) {
			$append .= '&tmpl='.$tmpl;
		}

		$append .= '&layout=edit';

		if ($recordId) {
			$append .= '&'.$urlVar.'='.$recordId;
		}

		$itemId	= $jinput->getInt('Itemid', 0);
		//$catId = JFactory::getApplication()->input->getInt('catid', 0);
		$return	= $this->getReturnPage();

		if ($itemId) {
			$append .= '&Itemid='.$itemId;
		}

		//if($catId) {
		//	$append .= '&catid='.$catId;
		//}

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
		$return = JFactory::getApplication()->input->get('return', null, 'base64');

		if (empty($return) || !JUri::isInternal(urldecode(base64_decode($return)))) {
			return JUri::base();
		}
		else {
			return urldecode(base64_decode($return));
		}
	}


	/**
	 * Function that allows child controller access to model data
	 * after the data has been saved.
	 * Here used to trigger the jem plugins, mainly the mailer.
	 *
	 * @param   JModel(Legacy)  $model      The data model object.
	 * @param   array           $validData  The validated data.
	 *
	 * @return  void
	 */
	protected function postSaveHook($model, $validData = array())
	{
		$task = $this->getTask();
		if ($task == 'save') {
			$isNew = $model->getState('editvenue.new');
			$id    = $model->getState('editvenue.id');

			// trigger all jem plugins
			JPluginHelper::importPlugin('jem');
			$dispatcher = JDispatcher::getInstance();
			$dispatcher->trigger('onVenueEdited', array($id, $isNew));

			// but show warning if mailer is disabled
			if (!JPluginHelper::isEnabled('jem', 'mailer')) {
				JError::raiseNotice(100, JText::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'));
			}
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
}