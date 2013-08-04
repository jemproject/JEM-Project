<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

//TODO: Improve error handling

/**
 * JEM Component Sampledata Model
 *
 * @package JEM
 * 
 */
class JEMModelSampledata extends JModelLegacy
{
	/**
	 * files data array
	 *
	 * @var array
	 */
	var $_filelist = array();

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		if ($this->_check()) {
			JError::raiseWarning(100, JText::_('COM_JEM_DATA_ALREADY_INSTALLED'));
			return false;
		}

		$this->_filelist = $this->_unpack();
	}

	 /**
	 * Process sampledata
	 *
	 * @access public
	 * @return true on success
	 * 
	 */
	function loaddata()
	{
		//determine sql file
		foreach ($this->_filelist['files'] as $key => $file)
		{
			if (JFile::getExt($file) == 'sql') {
				$scriptfile = $file;
				unset($this->_filelist['files'][$key]);
			}
		}

		//load sql file
		if( !($buffer = file_get_contents($this->_filelist['folder'].'/'.$scriptfile)) )
		{
			return false;
		}

		//extract queries out of sql file
		$queries = $this->_splitSql($buffer);

		//Process queries
		foreach ($queries as $query)
		{
			$query = trim($query);
			if ($query != '' && $query {0} != '#')
			{
				$this->_db->setQuery($query);
				$this->_db->query();
			}
		}

		//move images in proper directory
		$this->_moveimages();


		//delete temporary extraction folder
		if(!$this->_deletetmp()) {
			JError::raiseWarning('SOME ERROR CODE', JText::_('COM_JEM_UNABLE_TO_DELETE_TMP_FOLDER'));
		}

		return true;
	}

	/**
	 * Unpack archive and build array of files
	 *
	 * @access private
	 * @return array
	 * 
	 */
	function _unpack()
	{
		jimport('joomla.filesystem.archive');

		$filename	= 'sampledata.zip';
		$archive 	= JPATH_COMPONENT_ADMINISTRATOR.'/assets/'.$filename;

		// Temporary folder to extract the archive into
		$tmpdir = uniqid('sample_');

		// Clean the paths to use for archive extraction
		$extractdir = JPath::clean(JPATH_ROOT.'/tmp/'.$tmpdir);
		$archive 	= JPath::clean($archive);

		//extract archive
		$result = JArchive::extract( $archive, $extractdir);

		if ( $result === false ) {
			JError::raiseWarning('SOME ERROR CODE', JText::_('COM_JEM_UNABLE_TO_EXTRACT_ARCHIVE'));
			return false;
		}

		//return the files found in the extract folder and also folder name
		$files = array();

		if ($handle = opendir( $extractdir ))
		{
			while (false !== ($file = readdir($handle)))
			{
				if ($file != "." && $file != "..")
				{
					$files[] = $file;
					continue;
				}
			}
			closedir($handle);
		}
		$_filelist['files'] 	= $files;
		$_filelist['folder'] 	= $extractdir;

		return $_filelist;
	}

	/**
	 * Split sql to single queries
	 *
	 * @access private
	 * @return array
	 * 
	 */
	function _splitsql($sql)
	{
		$sql 		= trim($sql);
		$sql 		= preg_replace("/\n\#[^\n]*/", '', "\n".$sql);
		$buffer 	= array();
		$ret 		= array();
		$in_string 	= false;

		for ($i = 0; $i < strlen($sql) - 1; $i ++) {
			if ($sql[$i] == ";" && !$in_string)
			{
				$ret[] = substr($sql, 0, $i);
				$sql = substr($sql, $i +1);
				$i = 0;
			}

			if ($in_string && ($sql[$i] == $in_string) && $buffer[1] != "\\")
			{
				$in_string = false;
			}
			elseif (!$in_string && ($sql[$i] == '"' || $sql[$i] == "'") && (!isset ($buffer[0]) || $buffer[0] != "\\"))
			{
				$in_string = $sql[$i];
			}
			if (isset ($buffer[1]))
			{
				$buffer[0] = $buffer[1];
			}
			$buffer[1] = $sql[$i];
		}

		if (!empty ($sql))
		{
			$ret[] = $sql;
		}
		return ($ret);
	}

	/**
	 * Copy images into the venues/events folder
	 *
	 * @access private
	 * @return true on success
	 * 
	 */
	function _moveimages()
	{

		$imagebase = JPATH_ROOT.'/images/jem';

		foreach ($this->_filelist['files'] as $file)
		{
			if  (substr_count($file,"event")) 
				{
			   		JFile::copy($this->_filelist['folder'].'/'.$file, $imagebase.'/events/'.$file);
				}
			if  (substr_count($file,"evthumb"))
				{
			   		JFile::copy($this->_filelist['folder'].'/'.$file, $imagebase.'/events/small/'.$file);
				}
                	
			if  (substr_count($file,"venue"))
				{
			   		JFile::copy($this->_filelist['folder'].'/'.$file, $imagebase.'/venues/'.$file);
				}
			if  (substr_count($file,"vethumb"))
				{
			   		JFile::copy($this->_filelist['folder'].'/'.$file, $imagebase.'/venues/small/'.$file);
				}	
		
			if  (substr_count($file,"cat"))
				{
			   		JFile::copy($this->_filelist['folder'].'/'.$file, $imagebase.'/categories/'.$file);
				} 
			if  (substr_count($file,"catthumb"))
				{
					JFile::copy($this->_filelist['folder'].'/'.$file, $imagebase.'/categories/small/'.$file);
				}
		}
		
		return true; 
	}

	/**
	 * Delete temporary folder
	 *
	 * @access private
	 * @return true on success
	 * 
	 */
	function _deletetmp()
	{
		if ($this->_filelist['folder']) {
			if (!JFolder::delete($this->_filelist['folder'])) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Checks if Data exist
	 *
	 * @access private
	 * @return void
	 * 
	 */
	function _check()
	{
		$query = 'SELECT id FROM #__jem_categories';

		$this->_db->setQuery( $query );

		$result = $this->_db->loadResult();

		return $result;
	}
}
?>