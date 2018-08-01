<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

// ensure JemFactory is loaded (because this class is used by modules or plugins too)
require_once(JPATH_SITE.'/components/com_jem/factory.php');

/**
 * Holds the logic for attachments manipulation
 *
 * @package JEM
 */
class JemAttachment extends JObject
{
	/**
	 * upload files for the specified object
	 *
	 * @param  array  data from JInputFiles (as array of n arrays of params, [n][params])
	 * @param  string object identification (should be event<eventid>, category<categoryid>, etc...)
	 */
	static public function postUpload($post_files, $object)
	{
		require_once JPATH_SITE.'/components/com_jem/classes/image.class.php';

		$user = JemFactory::getUser();
		$jemsettings = JemHelper::config();

		$path = JPATH_SITE.'/'.$jemsettings->attachments_path.'/'.$object;

		if (!(is_array($post_files) && count($post_files))) {
			return false;
		}

		$allowed = explode(",", $jemsettings->attachments_types);
		foreach ($allowed as $k => $v) {
			$allowed[$k] = trim($v);
		}

		$maxsizeinput = $jemsettings->attachments_maxsize*1024; //size in kb

		foreach ($post_files as $k => $rec)
		{
			$file = array_key_exists('name', $rec) ? $rec['name'] : '';
			if (empty($file)) {
				continue;
			}

			// check if the filetype is valid
			$fileext = strtolower(JFile::getExt($file));
			if (!in_array($fileext, $allowed)) {
				JError::raiseWarning(0, JText::_('COM_JEM_ERROR_ATTACHEMENT_EXTENSION_NOT_ALLOWED').': '.$file);
				continue;
			}
			// check size
			if ($rec['size'] > $maxsizeinput) {
				JError::raiseWarning(0, JText::sprintf('COM_JEM_ERROR_ATTACHEMENT_FILE_TOO_BIG', $file, $rec['size'], $maxsizeinput));
				continue;
			}

			if (!JFolder::exists($path)) {
				// try to create it
				$res = JFolder::create($path);
				if (!$res) {
					JError::raiseWarning(0, JText::_('COM_JEM_ERROR_COULD_NOT_CREATE_FOLDER').': '.$path);
					return false;
				}
			}

			// TODO: Probably move this to a helper class

			$sanitizedFilename = JemImage::sanitize($path, $file);

			// Make sure that the full file path is safe.
			$filepath = JPath::clean( $path.'/'.$sanitizedFilename);
			// Since Joomla! 3.4.0 JFile::upload has some more params to control new security parsing
			// Unfortunately this parsing is partially stupid so it may reject archives for non-understandable reason.
			if (version_compare(JVERSION, '3.4', 'lt')) {
				JFile::upload($rec['tmp_name'], $filepath);
			} else {
				// switch off parsing archives for byte sequences looking like a script file extension
				// but keep all other checks running
				JFile::upload($rec['tmp_name'], $filepath, false, false, array('fobidden_ext_in_content' => false));
			}

			$table = JTable::getInstance('jem_attachments', '');
			$table->file = $sanitizedFilename;
			$table->object = $object;
			if (isset($rec['customname']) && !empty($rec['customname'])) {
				$table->name = $rec['customname'];
			}
			if (isset($rec['description']) && !empty($rec['description'])) {
				$table->description = $rec['description'];
			}
			if (isset($rec['access'])) {
				$table->access = intval($rec['access']);
			}
			$table->added = strftime('%F %T');
			$table->added_by = $user->get('id');

			if (!($table->check() && $table->store())) {
				JError::raiseWarning(0, JText::_('COM_JEM_ERROR_ATTACHMENT_SAVING_TO_DB').': '.$table->getError());
			}
		} // foreach

		return true;
	}

	/**
	 * update attachment record in db
	 * @param  array (id, name, description, access)
	 */
	static public function update($attach)
	{
		if (!is_array($attach) || !isset($attach['id']) || !(intval($attach['id']))) {
			return false;
		}

		$table = JTable::getInstance('jem_attachments', '');
		$table->load($attach['id']);
		$table->bind($attach);

		if (!($table->check() && $table->store())) {
			JError::raiseWarning(0, JText::_('COM_JEM_ERROR_ATTACHMENT_UPDATING_RECORD').': '.$table->getError());
			return false;
		}

		return true;
	}

	/**
	 * return attachments for objects
	 * @param  string object identification (should be event<eventid>, category<categoryid>, etc...)
	 * @return array
	 */
	static public function getAttachments($object)
	{
		$jemsettings = JemHelper::config();

		$path = JPATH_SITE.'/'.$jemsettings->attachments_path.'/'.$object;

		if (!file_exists($path)) {
			return array();
		}

		// first list files in the folder
		$files = JFolder::files($path, null, false, false);

		// then get info for files from db
		$db = JFactory::getDBO();
		$fnames = array();
		foreach ($files as $f) {
			$fnames[] = $db->Quote($f);
		}

		if (!count($fnames)) {
			return array();
		}

		// Check access level if not a Super User on Backend.
		$user = JemFactory::getUser();
		if (JFactory::getApplication()->isAdmin() && $user->authorise('core.manage')) {
			$qAccess = '';
		} else {
			$levels = $user->getAuthorisedViewLevels();
			$qAccess = '   AND access IN (' . implode(',', $levels) . ')';
		}

		$query = 'SELECT * '
		       . ' FROM #__jem_attachments '
		       . ' WHERE file IN ('. implode(',', $fnames) .')'
		       . '   AND object = '. $db->Quote($object)
		       . $qAccess
		       . ' ORDER BY ordering ASC ';

		$db->setQuery($query);
		$res = $db->loadObjectList();

		return $res;
	}

	/**
	 * get the file
	 *
	 * @param  int $id
	 */
	static public function getAttachmentPath($id)
	{
		$jemsettings = JemHelper::config();

		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$db = JFactory::getDBO();
		$query = 'SELECT * '
		       . ' FROM #__jem_attachments '
		       . ' WHERE id = '. $db->Quote(intval($id));

		$db->setQuery($query);
		$res = $db->loadObject();

		if (!$res) {
			JError::raiseError(404, JText::_('COM_JEM_FILE_NOT_FOUND'));
		}

		if (!in_array($res->access, $levels)) {
			JError::raiseError(403, JText::_('COM_JEM_NO_ACCESS'));
		}

		$path = JPATH_SITE.'/'.$jemsettings->attachments_path.'/'.$res->object.'/'.$res->file;
		if (!file_exists($path)) {
			JError::raiseError(404, JText::_('COM_JEM_FILE_NOT_FOUND'));
		}

		return $path;
	}

	/**
	 * remove attachment for objects
	 *
	 * @param  id from db
	 * @param  string object identification (should be event<eventid>, category<categoryid>, etc...)
	 * @return boolean
	 */
	static public function remove($id)
	{
		$jemsettings = JemHelper::config();

		$user = JemFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();
		$userid = $user->get('id');

		// then get info for files from db
		$db = JFactory::getDBO();

		$query = 'SELECT file, object, added_by '
		       . ' FROM #__jem_attachments '
		       . ' WHERE id = ' . $db->Quote($id) . ' AND access IN (0,' . implode(',', $levels) . ')';

		$db->setQuery($query);
		$res = $db->loadObject();

		if (!$res) {
			return false;
		}

		// check permission
		if (empty($userid) || ($userid != $res->added_by)) {
			if (strncasecmp($res->object, 'event', 5) == 0) {
				$type = 'event';
				$itemid = (int)substr($res->object, 5);
				$table = '#__jem_events';
			} elseif (strncasecmp($res->object, 'venue', 5) == 0) {
				$type = 'venue';
				$itemid = (int)substr($res->object, 5);
				$table = '#__jem_venues';
			} else {
				return false;
			}

			// get item owner
			$query = 'SELECT created_by FROM ' . $table . ' WHERE id = ' . $db->Quote($itemid);
			$db->setQuery($query);
			$created_by = $db->loadResult();

			if (!$user->can('edit', $type, $itemid, $created_by)) {
				JemHelper::addLogEntry("User ${userid} is not permritted to remove attachment " . $res->object, __METHOD__);
				return false;
			}
		}

		JemHelper::addLogEntry("User ${userid} removes attachment " . $res->object.'/'.$res->file, __METHOD__);
		$path = JPATH_SITE.'/'.$jemsettings->attachments_path.'/'.$res->object.'/'.$res->file;
		if (file_exists($path)) {
			JFile::delete($path);
		}

		$query = 'DELETE FROM #__jem_attachments '
		       . ' WHERE id = '. $db->Quote($id);

		$db->setQuery($query);
		$res = $db->execute();

		if (!$res) {
			return false;
		}

		return true;
	}
}