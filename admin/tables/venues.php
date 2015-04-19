<?php
/**
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

/**
 * Table: Venues
 */
class JEMTableVenues extends JTable
{

	public function __construct(&$db) {
		parent::__construct('#__jem_venues', 'id', $db);
	}

	/**
	 * Bind
	 */
	public function bind($array, $ignore = ''){
		// in here we are checking for the empty value of the checkbox

		if (!isset($array['map'])) {
			$array['map'] = 0 ;
		}

		//don't override without calling base class
		return parent::bind($array, $ignore);
	}

	/**
	 * Check
	 */
	public function check() {

		$jinput = JFactory::getApplication()->input;

		if (trim($this->venue) == ''){
			$this->setError(JText::_('COM_JEM_VENUE_ERROR_NAME'));
			return false;
		}

		// Set alias
		$this->alias = JApplication::stringURLSafe($this->alias);
		if (empty($this->alias)) {
			$this->alias = JApplication::stringURLSafe($this->venue);
			if (trim(str_replace('-', '', $this->alias)) == ''){
				$this->alias = JFactory::getDate()->format('Y-m-d-H-i-s');
			}
		}

		if ($this->map) {
			if (!trim($this->street) || !trim($this->city) || !trim($this->country) || !trim($this->postalCode)) {
				if ((!trim($this->latitude) && !trim($this->longitude))) {
					$this->setError(JText::_('COM_JEM_VENUE_ERROR_MAP_ADDRESS'));
					return false;
				}
			}
		}

		$this->street = strip_tags($this->street);
		$streetlength = JString::strlen($this->street);
		if ($streetlength > 50) {
			$this->setError(JText::_('COM_JEM_VENUE_ERROR_STREET'));
			return false;
		}

		$this->postalCode = strip_tags($this->postalCode);
		if (JString::strlen($this->postalCode) > 10) {
			$this->setError(JText::_('COM_JEM_VENUE_ERROR_POSTALCODE'));
			return false;
		}

		$this->city = strip_tags($this->city);
		if (JString::strlen($this->city) > 50) {
			$this->setError(JText::_('COM_JEM_VENUE_ERROR_CITY'));
			return false;
		}

		$this->state = strip_tags($this->state);
		if (JString::strlen($this->state) > 50) {
			$this->setError(JText::_('COM_JEM_VENUE_ERROR_STATE'));
			return false;
		}

		$this->country = strip_tags($this->country);
		if (JString::strlen($this->country) > 2) {
			$this->setError(JText::_('COM_JEM_VENUE_ERROR_COUNTRY'));
			return false;
		}

		return true;
	}

	/**
	 * Store
	 */
	public function store($updateNulls = false)
	{
		$date 			= JFactory::getDate();
		$user 			= JFactory::getUser();
		$app 			= JFactory::getApplication();
		$jinput 		= JFactory::getApplication()->input;
		$jemsettings 	= JEMHelper::config();

		// Check if we're in the front or back
		if ($app->isAdmin())
			$backend = true;
		else
			$backend = false;


		if ($this->id) {
			// Existing event
			$this->modified = $date->toSql();
			$this->modified_by = $user->get('id');
		}
		else
		{
			// New event
			if (!intval($this->created)){
				$this->created = $date->toSql();
			}
			if (empty($this->created_by)){
				$this->created_by = $user->get('id');
			}
		}

		// Check if image was selected
		jimport('joomla.filesystem.file');
		$image_dir = JPATH_SITE.'/images/jem/venues/';
		$allowable = array ('gif', 'jpg', 'png');

		// get image (frontend) - allow "removal on save" (Hoffi, 2014-06-07)
		if (!$backend) {
			if (($jemsettings->imageenabled == 2 || $jemsettings->imageenabled == 1)) {
				$file = JFactory::getApplication()->input->files->get('userfile', '', 'array');
				$removeimage = JFactory::getApplication()->input->get('removeimage', '', 'int');

				if (!empty($file['name'])) {
					//check the image
					$check = JEMImage::check($file, $jemsettings);

					if ($check !== false) {
						//sanitize the image filename
						$filename = JemHelper::sanitize($image_dir, $file['name']);
						$filepath = $image_dir . $filename;

						if (JFile::upload($file['tmp_name'], $filepath)) {
							$image_to_delete = $this->locimage; // delete previous image
							$this->locimage = $filename;
						}
					}
				} elseif (!empty($removeimage)) {
					// if removeimage is non-zero remove image from venue
					// (file will be deleted later (e.g. housekeeping) if unused)
					$image_to_delete = $this->locimage;
					$this->locimage = '';
				}
			} // end image if
		} // if (!backend)

		$format = JFile::getExt($image_dir . $this->locimage);
		if (!in_array($format, $allowable))
		{
			$this->locimage = '';
		}

		/*
		if (!$backend) {
			#	check if the user has the required rank for autopublish
			$autopublgroups = JEMUser::venuegroups('publish');
			$autopublloc 	= JEMUser::validate_user($jemsettings->locpubrec, $jemsettings->autopublocate);
			if (!($autopublloc || $autopublgroups || $user->authorise('core.edit','com_jem'))) {
				$this->published = 0;
			}
		}
		*/

		return parent::store($updateNulls);
	}


	/**
	 * try to insert first, update if fails
	 *
	 * Can be overloaded/supplemented by the child class
	 *
	 * @access public
	 * @param boolean If false, null object variables are not updated
	 * @return null|string null if successful otherwise returns and error message
	 */
	function insertIgnore($updateNulls=false)
	{
		$ret = $this->_insertIgnoreObject($this->_tbl, $this, $this->_tbl_key);
		if(!$ret) {
			$this->setError(get_class($this).'::store failed - '.$this->_db->getErrorMsg());
			return false;
		}
		return true;
	}

	/**
	 * Inserts a row into a table based on an objects properties, ignore if already exists
	 *
	 * @access protected
	 * @param string  The name of the table
	 * @param object  An object whose properties match table fields
	 * @param string  The name of the primary key. If provided the object property is updated.
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
		if (!$this->_db->execute()) {
			return false;
		}
		$id = $this->_db->insertid();
		if ($keyName && $id) {
			$object->$keyName = $id;
		}
		return $this->_db->getAffectedRows();
	}
}
