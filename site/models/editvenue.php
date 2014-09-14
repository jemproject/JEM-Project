<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

// Base this model on the backend version.
require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/venue.php';

/**
 * Editvenue Model
 */
class JemModelEditvenue extends JemModelVenue
{

	/**
	 * Model typeAlias string. Used for version history.
	 * @var        string
	 */
	public $typeAlias = 'com_jem.venue';


	/**
	 * Method to auto-populate the model state.
	 * Note. Calling getState in this method will result in recursion.
	 */
	protected function populateState()
	{
		$app = JFactory::getApplication();

		// Load state from the request.
		$pk = JRequest::getInt('a_id');
		$this->setState('venue.id', $pk);

		$return = JRequest::getVar('return', null, 'default', 'base64');
		$this->setState('return_page', urldecode(base64_decode($return)));

		// Load the parameters.
		$params = $app->getParams();
		$this->setState('params', $params);

		$this->setState('layout', JRequest::getCmd('layout'));
	}

	/**
	 * Method to get venue data.
	 *
	 * @param integer	The id of the venue.
	 * @return mixed item data object on success, false on failure.
	 */
	public function getItem($itemId = null)
	{
		$jemsettings = JEMHelper::config();

		// Initialise variables.
		$itemId = (int) (!empty($itemId)) ? $itemId : $this->getState('venue.id');

		// Get a row instance.
		$table = $this->getTable();

		// Attempt to load the row.
		$return = $table->load($itemId);

		// Check for a table object error.
		if ($return === false && $table->getError()) {
			$this->setError($table->getError());
			return false;
		}

		$properties = $table->getProperties(1);
		$value = JArrayHelper::toObject($properties, 'JObject');

		// Convert attrib field to Registry.
		//$registry = new JRegistry();
		//$registry->loadString($value->attribs);

		$globalsettings = JemHelper::globalattribs();
		$globalregistry = new JRegistry();
		$globalregistry->loadString($globalsettings);

		$value->params = clone $globalregistry;
		//$value->params->merge($registry);

		// Compute selected asset permissions.
		$user = JFactory::getUser();
		$userId = $user->get('id');
		$asset = 'com_jem.venue.' . $value->id;

		// Check general edit permission first.
		if ($user->authorise('core.edit', $asset)) {
			$value->params->set('access-edit', true);
		}
		// Now check if edit.own is available.
		elseif (!empty($userId) && $user->authorise('core.edit.own', $asset)) {
			// Check for a valid user and that they are the owner.
			if ($userId == $value->created_by) {
				$value->params->set('access-edit', true);
			}
		}

		// Check edit state permission.
		if ($itemId) {
			// Existing item
			$value->params->set('access-change', $user->authorise('core.edit.state', $asset));
		}
		else {
			$value->params->set('access-change', $user->authorise('core.edit.state', 'com_jem'));
		}

		$value->author_ip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;
		
		$files = JemAttachment::getAttachments('venue' . $itemId);
		$value->attachments = $files;

		if (empty($itemId)) {
			$value->country = $jemsettings->defaultCountry;
		}

		return $value;
	}

	protected function loadForm($name, $source = null, $options = array(), $clear = false, $xpath = false)
	{
		JForm::addFieldPath(JPATH_COMPONENT_ADMINISTRATOR . '/models/fields');

		return parent::loadForm($name, $source, $options, $clear, $xpath);
	}

	/**
	 * Get the return URL.
	 * @return string return URL.
	 */
	public function getReturnPage()
	{
		return base64_encode(urlencode($this->getState('return_page')));
	}

}