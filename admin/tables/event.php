<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;

/**
 * JEM Event Table
 */
class JemTableEvent extends Table
{
	public function __construct(&$db)
	{
		parent::__construct('#__jem_events', 'id', $db);
	}

	/**
	 * Overloaded bind method for the Event table.
	 */
	public function bind($array, $ignore = '')
	{
		// in here we are checking for the empty value of the checkbox
		
		if (!isset($array['registra'])) {
			$array['registra'] = 0 ;
		}
		if(isset($array['contactid'])){
			$array['contactid'] = (int) $array['contactid'];
		}
		if (!isset($array['unregistra'])) {
			$array['unregistra'] = 0 ;
		}

		if (!isset($array['waitinglist'])) {
			$array['waitinglist'] = 0 ;
		}

		if (!isset($array['requestanswer'])) {
			$array['requestanswer'] = 0 ;
		}

		// Search for the {readmore} tag and split the text up accordingly.
		if (isset($array['articletext'])) {
			$pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
			$tagPos = preg_match($pattern, $array['articletext']);

			if ($tagPos == 0) {
				$this->introtext = $array['articletext'];
				$this->fulltext = '';
			} else {
				list ($this->introtext, $this->fulltext) = preg_split($pattern, $array['articletext'], 2);
			}
		}

		if (isset($array['attribs']) && is_array($array['attribs'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['attribs']);
			$array['attribs'] = (string) $registry;
		}

		if (isset($array['metadata']) && is_array($array['metadata'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string) $registry;
		}

		// Bind the rules.
		/*
		if (isset($array['rules']) && is_array($array['rules'])) {
			$rules = new JAccessRules($array['rules']);
			$this->setRules($rules);
		}
		*/

		return parent::bind($array, $ignore);
	}

	/**
	 * overloaded check function
	 */
	public function check()
	{
		$jinput = Factory::getApplication()->input;

		if (trim($this->title) == '') {
			$this->setError(Text::_('COM_JEM_EVENT_ERROR_NAME'));
			return false;
		}

		if (trim($this->alias) == '') {
			$this->alias = $this->title;
		}

		$this->alias = JemHelper::stringURLSafe($this->alias);
		if (empty($this->alias)) {
			$this->alias = JemHelper::stringURLSafe($this->title);
			if (trim(str_replace('-', '', $this->alias)) == '') {
				$this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
			}
		}


		if (empty($this->times)) {
			$this->times = null;
		}

		if (empty($this->endtimes)) {
			$this->endtimes = null;
		}


		// Dates
		$db = Factory::getContainer()->get('DatabaseDriver');
		$nullDate = $db->getNullDate();

		if (empty($this->enddates) || ($this->enddates == $nullDate)) {
			$this->enddates = null;
		}

		if (empty($this->dates) || ($this->dates == $nullDate)) {
			$this->dates = null;
		}

		// check startDate - don't delete other fields; it's ok to know a time but not the day
		//if ($this->dates == NULL) {
		//	$this->times = NULL;
		//	$this->enddates = NULL;
		//	$this->endtimes = NULL;
		//}

		// Check begin date is before end date

		// Check if end date is set
		if ($this->enddates == null) {
			// Check if end time is set
			if ($this->endtimes == null) {
				$date1 = new DateTime('00:00');
				$date2 = new DateTime('00:00');
			} else {
				$date1 = new DateTime($this->times);
				$date2 = new DateTime($this->endtimes);
			}
		} else {
			// Check if end time is set
			if ($this->endtimes == null) {
				$date1 = new DateTime($this->dates);
				$date2 = new DateTime($this->enddates);
			} else {
				$date1 = new DateTime($this->dates.' '.$this->times);
				$date2 = new DateTime($this->enddates.' '.$this->endtimes);
			}
		}

		if ($date1 > $date2) {
			$this->setError(Text::_('COM_JEM_EVENT_ERROR_END_BEFORE_START'));
			return false;
		}

		return true;
	}

	/**
	 * store method for the Event table.
	 */
	public function store($updateNulls = true)
	{
		
		$date        = Factory::getDate();
		$user        = JemFactory::getUser();
		$userid      = $user->get('id');
		$app         = Factory::getApplication();
		$jinput      = $app->input;
		$jemsettings = JemHelper::config();

		// Check if we're in the front or back
		if ($app->isClient('administrator')) {
			$backend = true;
		} else {
			$backend = false;
		}
		if ($this->id) {
			// Existing event
			$this->modified = $date->toSql();
			$this->modified_by = $userid;
		} else {
			$this->modified = null;
			if(empty($this->created_by_alias))
				$this->created_by_alias='';
			if(empty($this->language))
				$this->language='';

			// New event
			if (!intval($this->created)) {
				$this->created = $date->toSql();
			}
			if (empty($this->created_by)) {
				$this->created_by = $userid;
			}
		}

		// Check if image was selected
		jimport('joomla.filesystem.file');
		$image_dir = JPATH_SITE.'/images/jem/events/';
		$filetypes = $jemsettings->image_filetypes ?: 'jpg,gif,png,webp';
		$allowable = explode(',', strtolower($filetypes));
		array_walk($allowable, function(&$v){$v = trim($v);});
		$image_to_delete = false;

		// get image (frontend) - allow "removal on save" (Hoffi, 2014-06-07)
		if (!$backend) {
			if (($jemsettings->imageenabled == 2 || $jemsettings->imageenabled == 1)) {
				$file = $jinput->files->get('userfile', array(), 'array');
				$removeimage = $jinput->getInt('removeimage', 0);
				$datimage = $jinput->getCmd('datimage', '');

				if (empty($file)) {
					$file2 = $jinput->files->get('jform', array(), 'array');
					if (!empty($file2['userfile'])) {
						$file = $file2['userfile'];
					}
				}

				if (!empty($file['name'])) {
					// only on first event, skip on recurrence events
					
					if (empty($this->recurrence_first_id)) {
						//check the image
						$check = JemImage::check($file, $jemsettings);

						if ($check !== false) {
							//sanitize the image filename
							$filename = JemImage::sanitize($image_dir, $file['name']);
							$filepath = $image_dir . $filename;

							if (JFile::upload($file['tmp_name'], $filepath)) {
								$image_to_delete = $this->datimage; // delete previous image
								$this->datimage = $filename;
							}
						}
					}
				} elseif (!empty($removeimage)) {
					// if removeimage is non-zero remove image from event
					// (file will be deleted later (e.g. housekeeping) if unused)
					$image_to_delete = $this->datimage;
					$this->datimage = '';
				} elseif (!$this->id && is_null($this->datimage) && !empty($datimage)) {
					// event is a copy so copy datimage too
					if (JFile::exists($image_dir . $datimage)) {
						// if it's already within image folder it's safe
						$this->datimage = $datimage;
					}
				}
			} // end image if
		} // if (!backend)

		$format = JFile::getExt($image_dir . $this->datimage);
		if (!in_array($format, $allowable))
		{
			$this->datimage = '';
		}

		// user check on frontend but not if caused by cleanup function (recurrence)
		if (!$backend && !(isset($this->_autocreate) && ($this->_autocreate === true))) {
			// check if the user has the required rank to publish this event
			if (!$this->id && !$user->can('publish', 'event', $this->id, $this->created_by)) {
				$this->published = 0;
			}
		}
		
		// item must be stored BEFORE image deletion
		$ret = parent::store($updateNulls);
		if ($ret && $image_to_delete) {
			JemHelper::delete_unused_image_files('event', $image_to_delete);
		}

		return $ret;
	}

	/**
	 * try to insert first, update if fails
	 *
	 * Can be overloaded/supplemented by the child class
	 *
	 * @access public
	 * @param  boolean If false, null object variables are not updated
	 * @return null|string null if successful otherwise returns and error message
	 */
	public function insertIgnore($updateNulls = false)
	{
		try {
			$ret = $this->_insertIgnoreObject($this->_tbl, $this, $this->_tbl_key);
		} catch (RuntimeException $e){
			$this->setError(get_class($this).'::store failed - '.$e->getMessage());
			return false;
		}
		return true;
	}

	/**
	 * Inserts a row into a table based on an objects properties, ignore if already exists
	 *
	 * @access protected
	 * @param  string  The name of the table
	 * @param  object  An object whose properties match table fields
	 * @param  string  The name of the primary key. If provided the object property is updated.
	 * @return int number of affected row
	 */
	protected function _insertIgnoreObject($table, &$object, $keyName = NULL)
	{
		$fmtsql = 'INSERT IGNORE INTO '.$this->_db->quoteName($table).' (%s) VALUES (%s) ';
		$fields = array();

		foreach (get_object_vars($object) as $k => $v) {
			if (is_array($v) or is_object($v) or $v === NULL) {
				continue;
			}
			if ($k[0] == '_') { // internal field
				continue;
			}
			$fields[] = $this->_db->quoteName($k);
			$values[] = $this->_db->quote($v);
		}

		$this->_db->setQuery(sprintf($fmtsql, implode(",", $fields), implode(",", $values)));
		if ($this->_db->execute() === false) {
			return false;
		}
		$id = $this->_db->insertid();
		if ($keyName && $id) {
			$object->$keyName = $id;
		}

		return $this->_db->getAffectedRows();
	}

	/**
	 * Method to set the publishing state for a row or list of rows in the database
	 * table. The method respects checked out rows by other users and will attempt
	 * to checkin rows that it can after adjustments are made.
	 *
	 * @param  mixed    $pks     An array of primary key values to update. If not set
	 *                           the instance property value is used. [optional]
	 * @param  integer  $state   The publishing state. eg. [0 = unpublished, 1 = published] [optional]
	 * @param  integer  $userId  The user id of the user performing the operation. [optional]
	 *
	 * @return boolean  True on success.
	 */
	public function publish($pks = null, $state = 1, $userId = 0)
	{
		// Initialise variables.
		$k = $this->_tbl_key;

		// Sanitize input.
		\Joomla\Utilities\ArrayHelper::toInteger($pks);
		$userId = (int) $userId;
		$state = (int) $state;

		// If there are no primary keys set check to see if the instance key is set.
		if (empty($pks)) {
			if ($this->$k) {
				$pks = array((int)$this->$k);
			} else {
				// Nothing to set publishing state on, return false.
				$this->setError(Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));
				return false;
			}
		}

		// Build the WHERE clause for the primary keys.
		$where = $this->_db->quoteName($k) . ' IN (' . implode(',', $pks) . ')';

		// Determine if there is checkin support for the table.
		if (!property_exists($this, 'checked_out') || !property_exists($this, 'checked_out_time')) {
			$checkin = '';
		} else {
			$checkin = ' AND (checked_out IS null OR checked_out = 0 OR checked_out = ' . (int) $userId . ')';
		}

		// Update the publishing state for rows with the given primary keys.
		$query = $this->_db->getQuery(true);
		$query->update($this->_db->quoteName($this->_tbl));
		$query->set($this->_db->quoteName('published') . ' = ' . (int) $state);
		$query->where($where);
		

		// Check for a database error.
		// TODO: use exception handling
		// if ($this->_db->getErrorNum()) {
		// 	$this->setError($this->_db->getErrorMsg());
		// 	return false;
		// }
		try
		{
			$this->_db->setQuery($query . $checkin);
			$this->_db->execute();
		}
		catch (RuntimeException $e)
		{			
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
		}

		// If checkin is supported and all rows were adjusted, check them in.
		if ($checkin && (count($pks) == $this->_db->getAffectedRows())) {
			// Checkin the rows.
			foreach ($pks as $pk) {
				$this->checkin($pk);
			}
		}

		// If the Table instance value is in the list of primary keys that were set, set the instance.
		if (in_array($this->$k, $pks)) {
			$this->published = $state;
		}

		$this->setError('');

		return true;
	}

	/**
	 * Method to delete a row from the database table by primary key value.
	 * After deletion all category relations are deleted from jem_cats_event_relations table.
	 *
	 * @param  mixed  $pk  An optional primary key value to delete.  If not set the instance property value is used.
	 *
	 * @return boolean  True on success.
	 *
	 * @note   With Joomla 3.1+ we should use an observer instead but J! 2.5 doesn't provide this.
	 *         Also on J! 2.5 $pk is a single key while on J! 3.x it's a list of keys.
	 *         We know the key is 'id', so keep it simple.
	 */
	public function delete($pk = null)
	{
		$id = $this->id;

		if (parent::delete($pk)) {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);
			$query->delete($db->quoteName('#__jem_cats_event_relations'));
			$query->where('itemid = '.$db->quote($id));
			$db->setQuery($query);
			$db->execute();

			$query = $db->getQuery(true);
			$query->delete($db->quoteName('#__jem_register'));
			$query->where('event = '.$db->quote($id));
			$db->setQuery($query);
			$db->execute();

			return true;
		}

		return false;
	}
}
?>
