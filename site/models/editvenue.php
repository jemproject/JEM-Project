<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Editvenue Model
 *
 * @package JEM
 *
 */
class JEMModelEditvenue extends JModelLegacy
{
	/**
	 * Venue data in Venue array
	 *
	 * @var array
	 */
	var $_venue = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$id = JRequest::getInt('id');
		$this->setId($id);
	}

	/**
	 * Method to set the Venue id
	 *
	 * @access	public
	 * @param	int	Venue ID number
	 */
	function setId($id)
	{
		// Set new venue ID
		$this->_id = $id;
	}

	/**
	 * Logic to get the venue
	 *
	 * @return array
	 */
	function &getVenue()
	{
		$app = JFactory::getApplication();

		// Initialize variables
		$user		= JFactory::getUser();
		$jemsettings = JEMHelper::config();

		$view		= JRequest::getWord('view');

		// ID exists => edit
		if ($this->_id) {
			// Load the Event data
			$this->_loadVenue();

			// Error if allready checked out
			if ($this->_venue->isCheckedOut($user->get('id'))) {
				$app->redirect('index.php?view='.$view, JText::_('COM_JEM_THE_VENUE').' '.$this->_venue->venue.' '.JText::_('COM_JEM_EDITED_BY_ANOTHER_ADMIN'));
			} else {
				$this->_venue->checkout($user->get('id'));
			}

			//access check
			$maintainer3 = JEMUser::venuegroups('edit');
			$genaccess3 	= JEMUser::editaccess($jemsettings->venueowner, $this->_venue->created_by, $jemsettings->venueeditrec, $jemsettings->venueedit);
			if ($maintainer3 || $genaccess3) {
// 				$allowedtoeditvenue = 1;
			} else {
				throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
			}

		// ID does not exist => add
		} else {
			//access checks
			$maintainer2 = JEMUser::venuegroups('add');
			$delloclink = JEMUser::validate_user($jemsettings->locdelrec, $jemsettings->deliverlocsyes);

			if ($maintainer2 || $delloclink) {
				$addvenuelink = 1;
			} else {
				$addvenuelink = 0;
			}

			if ($addvenuelink == 0) {
				throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
			}

			//sticky forms
			$session = JFactory::getSession();
			if ($session->has('venueform', 'com_jem')) {
				$venueform 		= $session->get('venueform', 0, 'com_jem');
				$this->_venue 	= JTable::getInstance('jem_venues', '');

				if (!$this->_venue->bind($venueform)) {
					JError::raiseError(500, $this->_db->stderr());
					return false;
				}
			} else {
				//prepare output
				$this->_venue = new stdClass();
				$this->_venue->id				= '';
				$this->_venue->venue			= '';
				$this->_venue->url				= '';
				$this->_venue->street			= '';
				$this->_venue->postalCode		= '';
				$this->_venue->locdescription	= '';
				$this->_venue->city				= '';
				$this->_venue->state			= '';
				$this->_venue->country			= '';
				$this->_venue->latitude			= '';
				$this->_venue->longitude		= '';
				$this->_venue->map				= $jemsettings->showmapserv ? 1 : 0;
				$this->_venue->created			= '';
				$this->_venue->created_by		= '';
				$this->_venue->version			= 0;
				$this->_venue->author_ip		= '';
				$this->_venue->locimage			= '';
				$this->_venue->meta_keywords	= '';
				$this->_venue->meta_description	= '';
				$this->_venue->custom1				= '';
				$this->_venue->custom2				= '';
				$this->_venue->custom3				= '';
				$this->_venue->custom4				= '';
				$this->_venue->custom5				= '';
				$this->_venue->custom6				= '';
				$this->_venue->custom7				= '';
				$this->_venue->custom8				= '';
				$this->_venue->custom9				= '';
				$this->_venue->custom10				= '';
				$this->_venue->attachments		= array();
			}
		}

		return $this->_venue;
	}

	/**
	 * logic to get the venue
	 *
	 * @access private
	 * @return array
	 */
	function _loadVenue()
	{
		if (empty($this->_venue)) {

			$this->_venue = JTable::getInstance('jem_venues', '');
			$this->_venue->load($this->_id);
			$this->_venue->attachments = JEMAttachment::getAttachments('venue'.$this->_venue->id);

			return $this->_venue;
		}
		return true;
	}

	/**
	 * Method to checkin/unlock the item
	 *
	 * @access	public
	 * @return	boolean	True on success
	 *
	 */
	function checkin()
	{
		if ($this->_id)
		{
			$item = $this->getTable('jem_venues', '');
			if(! $item->checkin($this->_id)) {
				$this->setError($this->_db->getErrorMsg());
				return false;
			}
		}
		return false;
	}

	/**
	 * Method to store the venue
	 *
	 * @access	public
	 * @return	id
	 *
	 */
	function store($data, $file)
	{
		$app = JFactory::getApplication();

		$user 		= JFactory::getUser();
		$jemsettings = JEMHelper::config();

		$row 		= JTable::getInstance('jem_venues', '');

		$curimage = JRequest::getVar('curimage', '', 'post','string');

		//bind it to the table
		if (!$row->bind($data)) {
			JError::raiseError(500, $this->_db->stderr());
			return false;
		}

		//Are we saving from an item edit?
		if ($row->id) {
			//check if user is allowed to edit venues

			//access check
			$maintainer3 = JEMUser::venuegroups('edit');
			$genaccess3 	= JEMUser::editaccess($jemsettings->venueowner, $row->created_by, $jemsettings->venueeditrec, $jemsettings->venueedit);
			if ($maintainer3 || $genaccess3) {
// 				$allowedtoeditvenue = 1;
			} else {
				throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
			}

			$row->modified 		= gmdate('Y-m-d H:i:s');
			$row->modified_by 	= $user->get('id');

			//Is editor the owner of the venue
			//This extra Check is needed to make it possible
			//that the venue is published after an edit from an owner
			if ($jemsettings->venueowner == 1 && $row->created_by == $user->get('id')) {
				$owneredit = 1;
			} else {
				$owneredit = 0;
			}
		} else {
			//check if user is allowed to submit new venues

			$maintainer2 = JEMUser::venuegroups('add');
			$delloclink = JEMUser::validate_user($jemsettings->locdelrec, $jemsettings->deliverlocsyes);

			if ($maintainer2 || $delloclink) {
				$addvenuelink = 1;
			} else {
				$addvenuelink = 0;
			}

			if ($addvenuelink == 0) {
				throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
			}

			//get IP, time and userid
			$row->created 			= gmdate('Y-m-d H:i:s');

			$row->author_ip 		= $jemsettings->storeip ? getenv('REMOTE_ADDR') : 'DISABLED';
			$row->created_by		= $user->get('id');

			//set owneredit to false
			$owneredit = 0;
		}

		//Autopublish
		//check if the user has the required rank for autopublish
		$autopublgroups = JEMUser::venuegroups('publish');

		$autopublloc = JEMUser::validate_user($jemsettings->locpubrec, $jemsettings->autopublocate);
		if ($autopublloc || $owneredit || $autopublgroups) {
			$row->published = 1 ;
		} else {
			$row->published = 0 ;
		}

		//Image upload

		//If image upload is required we will stop here if no file was attached
		if (empty($file['name']) && $jemsettings->imageenabled == 2) {
			$this->setError(JText::_('COM_JEM_IMAGE_EMPTY'));
			return false;
		}

		if (($jemsettings->imageenabled == 2 || $jemsettings->imageenabled == 1) && (!empty($file['name']))) {

			jimport('joomla.filesystem.file');

			$base_Dir 	= JPATH_SITE.'/images/jem/venues/';

			//check the image
			$check = JEMImage::check($file, $jemsettings);

			if ($check === false) {
				$app->redirect($_SERVER['HTTP_REFERER']);
			}

			//sanitize the image filename
			$filename = JEMImage::sanitize($base_Dir, $file['name']);
			$filepath = $base_Dir . $filename;

			if (!JFile::upload($file['tmp_name'], $filepath)) {
				$this->setError(JText::_('COM_JEM_UPLOAD_FAILED'));
				return false;
			} else {
				$row->locimage = $filename;
			}
		} else {
			//keep image if edited and left blank
			$row->locimage = $curimage;
		}

		//Check description
		$editoruser = JEMUser::editoruser();

		if (!$editoruser) {
			//check description --> wipe out code
			$row->locdescription = strip_tags($row->locdescription, '<br><br/>');

			//convert the linux \n (Mac \r, Win \r\n) to <br /> linebreaks
			$row->locdescription = str_replace(array("\r\n", "\r", "\n"), "<br />", $row->locdescription);

			//cut too long words
			$row->locdescription = wordwrap($row->locdescription, 75, " ", 1);

			//check length
			$length = JString::strlen($row->locdescription);
			if ($length > $jemsettings->datdesclimit) {

				// if required shorten it
				$row->locdescription = JString::substr($row->locdescription, 0, $jemsettings->datdesclimit);
				//if shortened add ...
				$row->locdescription = $row->locdescription.'...';
			}
		}

		$row->version++;

		//Make sure the data is valid
		if (!$row->check($jemsettings)) {
			$this->setError($row->getError());
			return false;
		}

		//store it in the db
		if (!$row->store()) {
			$this->setError($this->_db->getErrorMsg());
			return false;
		}

		// attachments
		// new ones first
		$attachments = JRequest::getVar('attach', array(), 'files', 'array');
		$attachments['customname'] = JRequest::getVar('attach-name', array(), 'post', 'array');
		$attachments['description'] = JRequest::getVar('attach-desc', array(), 'post', 'array');
		$attachments['access'] = JRequest::getVar('attach-access', array(), 'post', 'array');
		JEMAttachment::postUpload($attachments, 'venue'.$row->id);

		// and update old ones
		$attachments = array();
		$old['id'] = JRequest::getVar('attached-id', array(), 'post', 'array');
		$old['name'] = JRequest::getVar('attached-name', array(), 'post', 'array');
		$old['description'] = JRequest::getVar('attached-desc', array(), 'post', 'array');
		$old['access'] = JRequest::getVar('attached-access', array(), 'post', 'array');
		foreach ($old['id'] as $k => $id)
		{
			$attach = array();
			$attach['id'] = $id;
			$attach['name'] = $old['name'][$k];
			$attach['description'] = $old['description'][$k];
			$attach['access'] = $old['access'][$k];
			JEMAttachment::update($attach);
		}

		//update item order
		$row->reorder();

		return $row->id;
	}
}
?>