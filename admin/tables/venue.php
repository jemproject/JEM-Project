<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\File;

/**
 * JEM Venue Table
 */
class JemTableVenue extends Table
{
	public function __construct(&$db)
	{
		parent::__construct('#__jem_venues', 'id', $db);
	}

	/**
	 * Overloaded bind method for the Venue table.
	 */
	public function bind($array, $ignore = '')
	{
		// in here we are checking for the empty value of the checkbox

		if (!isset($array['map'])) {
			$array['map'] = 0 ;
		}

		//don't override without calling base class
		return parent::bind($array, $ignore);
	}

	/**
	 * overloaded check function
	 */
	public function check()
	{
		$jinput = Factory::getApplication()->input;

		if (trim($this->venue) == '') {
			$this->setError(Text::_('COM_JEM_VENUE_ERROR_NAME'));
			return false;
		}

		// Set alias
		$this->alias = JemHelper::stringURLSafe($this->alias);
		if (empty($this->alias)) {
			$this->alias = JemHelper::stringURLSafe($this->venue);
			if (trim(str_replace('-', '', $this->alias)) == '') {
				$this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
			}
		}

		if ($this->map) {
			if (!trim($this->street) || !trim($this->city) || !trim($this->country) || !trim($this->postalCode)) {
				if ((!trim($this->latitude) && !trim($this->longitude))) {
					$this->setError(Text::_('COM_JEM_VENUE_ERROR_MAP_ADDRESS'));
					return false;
				}
			}
		}

		if (trim($this->url)) {
			$this->url = strip_tags($this->url);

			if (strlen($this->url) > 199) {
				$this->setError(Text::_('COM_JEM_VENUE_ERROR_URL_LENGTH'));
				return false;
			}
			if (!preg_match('/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9äöüáéíóúñ]+)*\.[a-z]{2,5}'
			               .'((:[0-9]{1,5})?\/.*)?$/i' , $this->url))
			{
				$this->setError(Text::_('COM_JEM_VENUE_ERROR_URL_FORMAT'));
				return false;
			}
		}

		$this->street = strip_tags($this->street);
		$streetlength = \Joomla\String\StringHelper::strlen($this->street);
		if ($streetlength > 50) {
			$this->setError(Text::_('COM_JEM_VENUE_ERROR_STREET'));
			return false;
		}

		$this->postalCode = strip_tags($this->postalCode);
		if (\Joomla\String\StringHelper::strlen($this->postalCode) > 10) {
			$this->setError(Text::_('COM_JEM_VENUE_ERROR_POSTALCODE'));
			return false;
		}

		$this->city = strip_tags($this->city);
		if (\Joomla\String\StringHelper::strlen($this->city) > 50) {
			$this->setError(Text::_('COM_JEM_VENUE_ERROR_CITY'));
			return false;
		}

		$this->state = strip_tags($this->state);
		if (\Joomla\String\StringHelper::strlen($this->state) > 50) {
			$this->setError(Text::_('COM_JEM_VENUE_ERROR_STATE'));
			return false;
		}

		$this->country = strip_tags($this->country);
		if (\Joomla\String\StringHelper::strlen($this->country) > 2) {
			$this->setError(Text::_('COM_JEM_VENUE_ERROR_COUNTRY'));
			return false;
		}

		return true;
	}

	/**
	 * Overloaded store method for the Venue table.
	 */
	public function store($updateNulls = false)
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
			// Existing venue
			$this->modified = $date->toSql();
			$this->modified_by = $userid;
		} else {
			// New venue
			if (!intval($this->created)) {
				$this->created = $date->toSql();
			}
			if (empty($this->created_by)) {
				$this->created_by = $userid;
			}
		}

		// Check if image was selected
		jimport('joomla.filesystem.file');
		$image_dir = JPATH_SITE.'/images/jem/venues/';
		$filetypes = $jemsettings->image_filetypes ?: 'jpg,gif,png,webp';
		$allowable = explode(',', strtolower($filetypes));
		array_walk($allowable, function(&$v){$v = trim($v);});
		$image_to_delete = false;

		// get image (frontend) - allow "removal on save" (Hoffi, 2014-06-07)
		if (!$backend) {
			if (($jemsettings->imageenabled == 2 || $jemsettings->imageenabled == 1)) {
				$file = $jinput->files->get('userfile', array(), 'array');
				$removeimage = $jinput->getInt('removeimage', 0);
				$locimage = $jinput->getCmd('locimage', '');

				if (empty($file)) {
					$file2 = $jinput->files->get('jform', array(), 'array');
					if (!empty($file2['userfile'])) {
						$file = $file2['userfile'];
					}
				}

				if (!empty($file['name'])) {
					//check the image
					$check = JemImage::check($file, $jemsettings);

					if ($check !== false) {
						//sanitize the image filename
						$filename = JemImage::sanitize($image_dir, $file['name']);
						$filepath = $image_dir . $filename;

						if (File::upload($file['tmp_name'], $filepath)) {
							$image_to_delete = $this->locimage; // delete previous image
							$this->locimage = $filename;
						}
					}
				} elseif (!empty($removeimage)) {
					// if removeimage is non-zero remove image from venue
					// (file will be deleted later (e.g. housekeeping) if unused)
					$image_to_delete = $this->locimage;
					$this->locimage = '';
				} elseif (!$this->id && is_null($this->locimage) && !empty($locimage)) {
					// venue is a copy so copy locimage too
					if (File::exists($image_dir . $locimage)) {
						// if it's already within image folder it's safe
						$this->locimage = $locimage;
					}
				}
			} // end image if
		} // if (!backend)

		$format = File::getExt($image_dir . $this->locimage);
		if (!in_array($format, $allowable))
		{
			$this->locimage = '';
		}

		if (!$backend) {
			// check if the user has the required rank to publish this venue
			if (!$this->id && !$user->can('publish', 'venue', $this->id, $this->created_by)) {
				$this->published = 0;
			}
		}

		// item must be stored BEFORE image deletion
		$ret = parent::store($updateNulls);
		if ($ret && $image_to_delete) {
			JemHelper::delete_unused_image_files('venue', $image_to_delete);
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
}
?>
