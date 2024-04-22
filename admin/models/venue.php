<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

require_once __DIR__ . '/admin.php';

/**
 * Model: Venue
 */
class JemModelVenue extends JemModelAdmin
{
	/**
	 * Method to change the published state of one or more records.
	 *
	 * @param  array   &$pks  A list of the primary keys to change.
	 * @param  integer $value The value of the published state.
	 *
	 * @return boolean True on success.
	 *
	 * @since  2.2.2
	 */
	public function publish(&$pks, $value = 1)
	{
		// Additionally include the JEM plugins for the onContentChangeState event.
		PluginHelper::importPlugin('jem');

		return parent::publish($pks, $value);
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param  object  A record object.
	 * @return boolean True if allowed to delete the record. Defaults to the permission set in the component.
	 */
	protected function canDelete($record)
	{
		if (!empty($record->id))
		{
			$user = JemFactory::getUser();

			return $user->authorise('core.delete', 'com_jem');
		}
	}

	/**
	 * Method to delete a venue
	 */
	public function delete(&$pks = array())
	{
		$return = array();

		if ($pks)
		{
			$pksTodelete = array();
			$errorNotice = array();
            $db = Factory::getContainer()->get('DatabaseDriver');
			foreach ($pks as $pk)
			{
				$result = array();

				$query = $db->getQuery(true);
				$query->select(array('COUNT(e.locid) as AssignedEvents'));
				$query->from($db->quoteName('#__jem_venues').' AS v');
				$query->join('LEFT', '#__jem_events AS e ON e.locid = v.id');
				$query->where(array('v.id = '.$pk));
				$query->group('v.id');
				$db->setQuery($query);
				$assignedEvents = $db->loadResult();

				if ($assignedEvents > 0)
				{
					$result[] = Text::_('COM_JEM_VENUE_ASSIGNED_EVENT');
				}

				if ($result)
				{
					$pkInfo = array("id:".$pk);
					$result = array_merge($pkInfo,$result);
					$errorNotice[] = $result;
				}
				else
				{
					$pksTodelete[] = $pk;
				}
			}

			if ($pksTodelete)
			{
				$return['removed'] = parent::delete($pksTodelete);
				$return['removedCount'] = count($pksTodelete);
			}
			else
			{
				$return['removed'] = false;
				$return['removedCount'] = false;
			}

			if ($errorNotice)
			{
				$return['error'] = $errorNotice;
			}
			else
			{
				$return['error'] = false;
			}

			return $return;
		}

		$return['removed'] = false;
		$return['error'] = false;
		$return['removedCount'] = false;

		return $return;
	}

	/**
	 * Method to test whether a record can be deleted.
	 *
	 * @param  object  A record object.
	 * @return boolean True if allowed to change the state of the record. Defaults to the permission set in the component.
	 */
	protected function canEditState($record)
	{
		$user = JemFactory::getUser();

		if (!empty($record->catid)) {
			return $user->authorise('core.edit.state', 'com_jem.category.'.(int) $record->catid);
		} else {
			return $user->authorise('core.edit.state', 'com_jem');
		}
	}

	/**
	 * Returns a reference to the a Table object, always creating it.
	 *
	 * @param  string The table to instantiate
	 * @param  string A prefix for the table class name. Optional.
	 * @param  array  Configuration array for model. Optional.
	 * @return Table A database object
	 */
	public function getTable($type = 'Venue', $prefix = 'JemTable', $config = array())
	{
		return Table::getInstance($type, $prefix, $config);
	}

	/**
	 * Method to get the record form.
	 *
	 * @param  array   $data     Data for the form.
	 * @param  boolean $loadData True if the form is to load its own data (default case), false if not.
	 * @return mixed   A JForm object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_jem.venue', 'venue', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form)) {
			return false;
		}

		return $form;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param  integer The id of the primary key.
	 *
	 * @return mixed   Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$jemsettings = JemAdmin::config();

		if ($item = parent::getItem($pk)) {
			$files = JemAttachment::getAttachments('venue'.$item->id);
			$item->attachments = $files;
		}

		$item->author_ip = $jemsettings->storeip ? JemHelper::retrieveIP() : false;

		if (empty($item->id)) {
			$item->country = $jemsettings->defaultCountry;
		}

		return $item;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Factory::getApplication()->getUserState('com_jem.edit.venue.data', array());

		if (empty($data)) {
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * @param $table Table-object.
	 */
	protected function _prepareTable($table)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$table->venue = htmlspecialchars_decode($table->venue, ENT_QUOTES);

		// Increment version number.
		$table->version ++;
	}

	/**
	 * Method to save the form data.
	 *
	 * @param $data array
	 */
	public function save($data)
	{
		// Variables
		$app         = Factory::getApplication();
		$jinput      = $app->input;
		$jemsettings = JemHelper::config();
		$task        = $jinput->get('task', '', 'cmd');

		// Check if we're in the front or back
		$backend = (bool)$app->isClient('administrator');
		$new     = (bool)empty($data['id']);

		// Store IP of author only.
		if ($new) {
			$author_ip = $jinput->get('author_ip', '', 'string');
			$data['author_ip'] = $author_ip;
		}
	
		$data['modified'] = (isset($data['modified']) && !empty($data['modified'])) ? $data['modified'] : null;
		$data['publish_up'] = (isset($data['publish_up']) && !empty($data['publish_up'])) ? $data['publish_up'] : null;
		$data['publish_down'] = (isset($data['publish_down']) && !empty($data['publish_down'])) ? $data['publish_down'] : null;
		$data['publish_down'] = (isset($data['publish_down']) && !empty($data['publish_down'])) ? $data['publish_down'] : null;
		$data['attribs'] = (isset($data['attribs'])) ? $data['attribs'] : '';
		$data['language'] = (isset($data['language'])) ? $data['language'] : '';
		$data['latitude'] = (isset($data['latitude']) && !empty($data['latitude'])) ? $data['latitude'] : 0;
		$data['longitude'] = (isset($data['longitude']) && !empty($data['longitude'])) ? $data['longitude'] : 0;
	
		// Store as copy - reset creation date, modification fields, hit counter, version
		if ($task == 'save2copy') {
			unset($data['created']);
			unset($data['modified']);
			unset($data['modified_by']);
			unset($data['version']);
		//	unset($data['hits']);
		}

		//uppercase needed by mapservices
		if ($data['country']) {
			$data['country'] = \Joomla\String\StringHelper::strtoupper($data['country']);
		}

		// Save the venue
		$saved = parent::save($data);

		if ($saved) {
			// At this point we do have an id.
			$pk = $this->getState($this->getName() . '.id');

			// on frontend attachment uploads maybe forbidden
			// so allow changing name or description only
			$allowed = $backend || ($jemsettings->attachmentenabled > 0);

			if ($allowed) {
				// attachments, new ones first
				$attachments   = $jinput->files->get('attach', array(), 'array');
				$attach_name   = $jinput->post->get('attach-name', array(), 'array');
				$attach_descr  = $jinput->post->get('attach-desc', array(), 'array');
				$attach_access = $jinput->post->get('attach-access', array(), 'array');
				foreach($attachments as $n => &$a) {
					$a['customname']  = array_key_exists($n, $attach_access) ? $attach_name[$n]   : '';
					$a['description'] = array_key_exists($n, $attach_access) ? $attach_descr[$n]  : '';
					$a['access']      = array_key_exists($n, $attach_access) ? $attach_access[$n] : '';
				}
				JemAttachment::postUpload($attachments, 'venue' . $pk);
			}

			// and update old ones
			$old = array();
			$old['id']          = $jinput->post->get('attached-id', array(), 'array');
			$old['name']        = $jinput->post->get('attached-name', array(), 'array');
			$old['description'] = $jinput->post->get('attached-desc', array(), 'array');
			$old['access']      = $jinput->post->get('attached-access', array(), 'array');

			foreach ($old['id'] as $k => $id){
				$attach = array();
				$attach['id']          = $id;
				$attach['name']        = $old['name'][$k];
				$attach['description'] = $old['description'][$k];
				if ($allowed) {
					$attach['access']  = $old['access'][$k];
				} // else don't touch this field
				JemAttachment::update($attach);
			}
		}

		return $saved;
	}
}
