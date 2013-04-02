<?php
/**
 * @version development 
 * @package Joomla
 * @subpackage JEM
 * @copyright JEM (C) 2013 Joomlaeventmanager.net / EventList (C) 2005 - 2008 Christoph Lukes
 *
 * @license GNU/GPL, see LICENSE.php
 * JEM is based on EventList made by Christoph Lukes from schlu.net
 *
 * JEM can be downloaded from www.joomlaeventmanager.net
 * You can visit the site for support & downloads
 * 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with redEVENT; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined('_JEXEC') or die;

/**
 * Holds the logic for attachments manipulation
 *
 * @package Joomla
 * @subpackage EventList
 */
class ELAttach extends JObject {

	/**
	 * upload files for the specified object
	 * 
	 * @param array data from JRequest 'files'
	 * @param string object identification (should be event<eventid>, category<categoryid>, etc...)
	 */
static	function postUpload($post_files, $object)
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		$app = JFactory::getApplication();
		$user = JFactory::getUser();
		$params = JComponentHelper::getParams('com_eventlist');

		$path = JPATH_SITE.DS.$params->get('attachments_path', 'media'.DS.'com_eventlist'.DS.'attachments').DS.$object;
		
		if (!(is_array($post_files) && count($post_files))) {
			return false;
		}
		
		$allowed = explode(",", $params->get('attachments_types'));
		foreach ($allowed as $k => $v) {
			$allowed[$k] = trim($v);
		}
		
		$maxsize = $params->get('attachments_maxsize', 1000)*1000;
		foreach ($post_files['name'] as $k => $file)
		{
			if (empty($file)) {
				continue;
			}
			// check extension
			
			$tmp = explode(".", strtolower($file));
			if (!in_array(end($tmp), $allowed)) {
				JError::raiseWarning(0, JText::_('COM_EVENTLIST_ERROR_ATTACHEMENT_EXTENSION_NOT_ALLOWED').': '.$file);
				continue;
			}
			// check size
			if ($post_files['size'][$k] > $maxsize) {
				JError::raiseWarning(0, JText::sprintf('COM_EVENTLIST_ERROR_ATTACHEMENT_FILE_TOO_BIG', $file, $post_files['size'][$k], $maxsize));
				continue;
			}
			
			if (!JFolder::exists($path)) 
			{
				// try to create it
				$res = JFolder::create($path);
				if (!$res) {
					JError::raiseWarning(0, JText::_('COM_EVENTLIST_ERROR_COULD_NOT_CREATE_FOLDER').': '.$path);
					return false;
				}
				$text = '<html><body bgcolor="#FFFFFF"></body></html>';
				JFile::write($path.DS.'index.html', $text);
			}
			
			JFile::copy($post_files['tmp_name'][$k], $path.DS.$file);
			
			$table = JTable::getInstance('eventlist_attachments', '');
			$table->file = $file;
			$table->object = $object;
			if (isset($post_files['customname'][$k]) && !empty($post_files['customname'][$k])) {
				$table->name = $post_files['customname'][$k];				
			}
			if (isset($post_files['description'][$k]) && !empty($post_files['description'][$k])) {
				$table->description = $post_files['description'][$k];				
			}
			if (isset($post_files['access'][$k])) {
				$table->access = intval($post_files['access'][$k]);				
			}			
			$table->added = strftime('%F %T');
			$table->added_by = $user->get('id');
			
			if (!($table->check() && $table->store())) {
				JError::raiseWarning(0, JText::_('COM_EVENTLIST_ATTACHMENT_ERROR_SAVING_TO_DB').': '.$table->getError());				
			}
		}
		return true;
	}
	
	/**
	 * update attachment record in db
	 * @param array (id, name, description, access)
	 */
static	function update($attach)
	{
		if (!is_array($attach) || !isset($attach['id']) || !(intval($attach['id']))) {
			return false;
		}
		$table = JTable::getInstance('eventlist_attachments', '');
		$table->load($attach['id']);
		$table->bind($attach);
		if (!($table->check() && $table->store())) {
			JError::raiseWarning(0, JText::_('COM_EVENTLIST_ATTACHMENT_ERROR_UPDATING_RECORD').': '.$table->getError());		
			return false;		
		}		
		return true;
	}
	
	/**
	 * return attachments for objects
	 * @param string object identification (should be event<eventid>, category<categoryid>, etc...)
	 * @return array
	 */
static	function getAttachments($object)
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		$app = JFactory::getApplication();
		$params = JComponentHelper::getParams('com_eventlist');
		
		$user	=  JFactory::getUser();
			
		  if (JFactory::getUser()->authorise('core.manage')) {
              $gid = (int) 3;          //viewlevel Special
          } else {
              if($user->get('id')) {
                  $gid = (int) 2;     //viewlevel Registered
              } else {
                 $gid = (int) 1;      //viewlevel Public
              }
                 }


		$path = JPATH_SITE.DS.$params->get('attachments_path', 'media'.DS.'com_eventlist'.DS.'attachments').DS.$object;
		
		if (!file_exists($path)) {
			return array();
		}
		// first list files in the folder
		$files = JFolder::files($path, null, false, false, array('index.html'));

		// then get info for files from db
		$db = JFactory::getDBO();
		$fnames = array();
		foreach ($files as $f) {
			$fnames[] = $db->Quote($f);
		}
		if (!count($fnames)) {
			return array();
		}
				
		$query = ' SELECT * ' 
		       . ' FROM #__eventlist_attachments ' 
		       . ' WHERE file IN ('. implode(',', $fnames) .')'
		       . '   AND object = '. $db->Quote($object);
		if (!is_null($gid)) {
			$query .= ' AND access <= '.$db->Quote($gid);
		}
		$query .= ' ORDER BY ordering ASC ';
		
		$db->setQuery($query);
		$res = $db->loadObjectList();
		
		return $res;
	}
	
	/**
	 * get the file
	 * 
	 * @param int $id
	 */
    static	function getAttachmentPath($id) 
	{		
		$params = JComponentHelper::getParams('com_eventlist');
		
		
		$user	=  JFactory::getUser();
			
		  if (JFactory::getUser()->authorise('core.manage')) {
              $gid = (int) 3;          //viewlevel Special
          } else {
              if($user->get('id')) {
                  $gid = (int) 2;     //viewlevel Registered
              } else {
                 $gid = (int) 1;      //viewlevel Public
              }
}
		
		
		$db = JFactory::getDBO();
		$query = ' SELECT * ' 
		       . ' FROM #__eventlist_attachments ' 
		       . ' WHERE id = '. $db->Quote(intval($id));
		$db->setQuery($query);
		$res = $db->loadObject();
		if (!$res) {
			JError::raiseError(404, JText::_('COM_EVENTLIST_FILE_UNKNOWN'));
		}		
		
		if (!is_null($gid) && $res->access > $gid) {
			JError::raiseError(403, JText::_('COM_EVENTLIST_YOU_DONT_HAVE_ACCESS_TO_THIS_FILE'));			
		}
		
		$path = JPATH_SITE.DS.$params->get('attachments_path', 'media'.DS.'com_eventlist'.DS.'attachments').DS.$res->object.DS.$res->file;		
		if (!file_exists($path)) {
			JError::raiseError(404, JText::_('COM_EVENTLIST_FILE_NOT_FOUND'));
		}
		
		return $path;
	}
	
	
	/**
	 * remove attachment for objects
	 * 
	 * @param id from db
	 * @param string object identification (should be event<eventid>, category<categoryid>, etc...)
	 * @return boolean
	 */
    static	function remove($id)
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		$app = JFactory::getApplication();
		$params = JComponentHelper::getParams('com_eventlist');

		
		// then get info for files from db
		$db = JFactory::getDBO();
		
		$query = ' SELECT file, object ' 
		       . ' FROM #__eventlist_attachments ' 
		       . ' WHERE id = ' . $db->Quote($id);
		$db->setQuery($query);
		$res = $db->loadObject();
		if (!$res)
		{
			return false;
		}
				
		$path = JPATH_SITE.DS.$params->get('attachments_path', 'media'.DS.'com_eventlist'.DS.'attachments').DS.$res->object.DS.$res->file;
		if (file_exists($path)) {
			JFile::delete($path);
		}
				
		$query = ' DELETE FROM #__eventlist_attachments ' 
		       . ' WHERE id = '. $db->Quote($id);
		$db->setQuery($query);
		$res = $db->query();
		if (!$res) {
			return false;
		}
		
		return true;
	}
}