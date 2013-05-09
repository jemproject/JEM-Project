<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @subpackage JEM Mailer Plugin
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access
defined( '_JEXEC' ) or die;
 
// Import library dependencies
jimport('joomla.event.plugin');
jimport('joomla.utilities.mail');

//Load the Plugin language file out of the administration
//JPlugin::loadLanguage( 'plg_jem_mailer', JPATH_ADMINISTRATOR);
$lang = JFactory::getLanguage();
$lang->load('plg_jem_mailer', JPATH_ADMINISTRATOR);

include_once(JPATH_SITE.'/components/com_jem/helpers/route.php');

class plgJEMMailer extends JPlugin {

	private $_SiteName = '';
	
	private $_MailFrom = '';
	
	private $_FromName = '';
	
	private $_receivers = array();
	
	
	
	 public function __construct(& $subject, $config)
    {
            parent::__construct($subject, $config);
            $this->loadLanguage();
    }
	
	
	
	/**
	 * Constructor
	 *
	 * @param object $subject The object to observe
	 * @param 	array  $config  An array that holds the plugin configuration
	 * @since 1.0
	 */
	public function plgJEMMailer(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
		
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		
		$this->_SiteName 	= $app->getCfg('sitename');
		$this->_MailFrom	= $app->getCfg('mailfrom');
		$this->_FromName 	= $app->getCfg('fromname');
		
		if( $this->params->get('fetch_admin_mails', '0') ) {
			//get list of admins who receive system mails
			$query = 'SELECT id, email, name' .
					' FROM #__users' .
					' WHERE sendEmail = 1';
			$db->setQuery($query);
			
			if (!$db->query()) {
				JError::raiseError( 500, $db->stderr(true));
				return;
			}
			
			$admin_mails 		= $db->loadColumn(1);
			$additional_mails 	= explode( ',', trim($this->params->get('receivers')));
			$this->_receivers	= array_merge($admin_mails, $additional_mails);
			
		} else {
			$this->_receivers	= explode( ',', trim($this->params->get('receivers')));
		}
	}
	
	/**
	 * This method handles any mailings triggered by an event registration action
	 *
	 * @access	public
	 * @param   int 	$event_id 	 Integer Event identifier
	 * @return	boolean
	 * @since 1.0
	 */
	public function onEventUserRegistered($register_id)
	{
		//simple, skip if processing not needed
		if (!$this->params->get('reg_mail_user', '1') && !$this->params->get('reg_mail_admin', '0')) {
			return true;
		}
				
		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();
		
		$query = ' SELECT a.id, a.title, r.waiting, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug '
				. ' FROM  #__jem_register AS r '
				. ' INNER JOIN #__jem_events AS a ON r.event = a.id '
				. ' WHERE r.id = ' . (int)$register_id;
		$db->setQuery($query);
    
		if (!$event = $db->loadObject()) {
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}
		
		//create link to event
		$link = JRoute::_(JURI::base().JEMHelperRoute::getRoute($event->slug), false);
		
		if ($event->waiting) // registered to the waiting list
		{
			//handle usermail
			if ($this->params->get('reg_mail_user', '1')) {
				
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('COM_JEM_MAIL_USER_REG_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('COM_JEM_MAIL_USER_REG_WAITING_BODY', $user->name, $user->username, $event->title, $link, $SiteName);
				$data->receivers 	= $user->email;
				
				$this->_mailer($data);
			}
			 
			//handle adminmail
			if ($this->params->get('reg_mail_admin', '0') && $this->params->get('receivers')) {
				
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('COM_JEM_MAIL_ADMIN_REG_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('COM_JEM_MAIL_ADMIN_REG_WAITING_BODY', $user->name, $user->username, $event->title, $link, $SiteName);
				$data->receivers 	= $this->_receivers;
				
				$this->_mailer($data);
			}
		}
		else
		{
			//handle usermail
			if ($this->params->get('reg_mail_user', '1')) {
				
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('COM_JEM_MAIL_USER_REG_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('COM_JEM_MAIL_USER_REG_BODY', $user->name, $user->username, $event->title, $link, $SiteName);
				$data->receivers 	= $user->email;
				
				$this->_mailer($data);
			}
			 
			//handle adminmail
			if ($this->params->get('reg_mail_admin', '0') && $this->params->get('receivers')) {
				
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('COM_JEM_MAIL_ADMIN_REG_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('COM_JEM_MAIL_ADMIN_REG_BODY', $user->name, $user->username, $event->title, $link, $SiteName);
				$data->receivers 	= $this->_receivers;
				
				$this->_mailer($data);
			}
			
		}
		
		return true;
	}
	
/**
	 * This method handles any mailings triggered by an attendees being bumped on/off waiting list
	 *
	 * @access	public
	 * @param   int 	$event_id 	 Integer Event identifier
	 * @return	boolean
	 * @since 1.0
	 */
	public function onUserOnOffWaitinglist($register_id)
	{
		//simple, skip if processing not needed
		if (!$this->params->get('reg_mail_user_onoff', '1') && !$this->params->get('reg_mail_admin_onoff', '0')) {
			return true;
		}
				
		$db 	= JFactory::getDBO();
		
		$query = ' SELECT a.id, a.title, waiting, uid, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug '
				. ' FROM  #__jem_register AS r '
				. ' INNER JOIN #__jem_events AS a ON r.event = a.id '
				. ' WHERE r.id = ' . (int)$register_id;
		$db->setQuery($query);
    
		if (!$details = $db->loadObject()) 
		{
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}
		
		$user 	= JFactory::getUser($details->uid);
		//create link to event
		$app = JFactory::getApplication();
		$url 		= $app->isAdmin() ? $app->getSiteURL() : JURI::base();
		$link =JRoute::_($url. JEMHelperRoute::getRoute($details->slug), false);
		
		if ($details->waiting) // added to the waiting list
		{
			//handle usermail
			if ($this->params->get('reg_mail_user_onoff', '1')) {
				
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_WAITING_BODY', $user->name, $user->username, $details->title, $link, $SiteName);
				$data->receivers 	= $user->email;
				
				$this->_mailer($data);
			}
			 
			//handle adminmail
			if ($this->params->get('reg_mail_admin_onoff', '0') && $this->params->get('receivers')) {
				
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_BODY', $user->name, $user->username, $details->title, $link, $SiteName);
				$data->receivers 	= $this->_receivers;
				
				$this->_mailer($data);
			}
		}
		else // bumped from waiting list to attending list
		{
			//handle usermail
			if ($this->params->get('reg_mail_user_onoff', '1')) {
				
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_ATTENDING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_ATTENDING_BODY', $user->name, $user->username, $details->title, $link, $SiteName);
				$data->receivers 	= $user->email;
				
				$this->_mailer($data);
			}
			 
			//handle adminmail
			if ($this->params->get('reg_mail_admin_onoff', '0') && $this->params->get('receivers')) {
				
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_BODY', $user->name, $user->username, $details->title, $link, $SiteName);
				$data->receivers 	= $this->_receivers;
				
				$this->_mailer($data);
			}
		}
		
		return true;
	}
	
	/**
	 * This method handles any mailings triggered by an event unregister action
	 *
	 * @access	public
	 * @param   int 	$event_id 	 Integer Event identifier
	 * @return	boolean
	 * @since 1.0
	 */
	public function onEventUserUnregistered($event_id)
	{
		//simple, skip if processing not needed
		if (!$this->params->get('unreg_mail_user', '1') && !$this->params->get('unreg_mail_admin', '0')) {
			return true;
		}
				
		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();
		
		$query = ' SELECT a.id, a.title, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug '
				. ' FROM #__jem_events AS a '
				. ' WHERE a.id = ' . (int)$event_id;
		$db->setQuery($query);
    
		if (!$event = $db->loadObject()) {
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}
		
		//create link to event
		$link = JRoute::_(JURI::base().JEMHelperRoute::getRoute($event->slug), false);
		
		//handle usermail
		if ($this->params->get('unreg_mail_user', '1')) {
			
			$data 				= new stdClass();
			$data->subject 		= JText::sprintf('COM_JEM_MAIL_USER_UNREG_SUBJECT', $this->_SiteName);
			$data->body			= JText::sprintf('COM_JEM_MAIL_USER_UNREG_BODY', $user->name, $user->username, $event->title, $link, $SiteName);
			$data->receivers 	= $user->email;
			
			$this->_mailer($data);
		}
		 
		//handle adminmail
		if ($this->params->get('unreg_mail_admin', '0') && $this->params->get('receivers')) {
			
			$data 				= new stdClass();
			$data->subject 		= JText::sprintf('COM_JEM_MAIL_ADMIN_UNREG_SUBJECT', $this->_SiteName);
			$data->body			= JText::sprintf('COM_JEM_MAIL_ADMIN_UNREG_BODY', $user->name, $user->username, $event->title, $link, $SiteName);
			$data->receivers 	= $this->_receivers;
			
			$this->_mailer($data);
		}
		
		return true;
	}
	
	/**
	 * This method handles any mailings triggered by an event store action
	 *
	 * @access	public
	 * @param   int 	$event_id 	 Integer Event identifier
	 * @param   int 	$edited 	 Integer Event new or edited
	 * @return	boolean
	 * @since 1.0
	 */
	public function onEventEdited($event_id, $edited)
	{
		//simple, skip if processing not needed
		if (!$this->params->get('newevent_mail_user', '1') && !$this->params->get('newevent_mail_admin', '0')
			&& !$this->params->get('editevent_mail_user', '1') && !$this->params->get('editevent_mail_admin', '0')) {
			return true;
		}
				
		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();
				
		$query = ' SELECT a.id, a.title, a.dates, a.times, a.datdescription, a.locid, a.published, a.created, a.modified,'
				. ' v.venue, v.city,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug'
				. ' FROM #__jem_events AS a '
				. ' LEFT JOIN #__jem_venues AS v ON v.id = a.locid'
				. ' WHERE a.id = ' . (int)$event_id;
		$db->setQuery($query);
    
		if (!$event = $db->loadObject()) {
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}
				
		//link for event
		$link   = JRoute::_(JURI::base().JEMHelperRoute::getRoute($event->slug), false);
		
		//strip description from tags / scripts, etc...
		$text_description = JFilterOutput::cleanText($event->datdescription);
		
		$modified_ip 	= getenv('REMOTE_ADDR');
		$edited 		= JHTML::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
		
		$state 	= $event->published ? JText::sprintf('COM_JEM_MAIL_EVENT_PUBLISHED', $link) : JText::_('COM_JEM_MAIL_EVENT_UNPUBLISHED');
			
		if (edited) {
			
			if ($this->params->get('editevent_mail_admin', '0')) {
			
				$data 				= new stdClass();
				$edited 			= JHTML::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject 		= JText::sprintf('COM_JEM_EDIT_EVENT_MAIL', $this->_SiteName);
				$data->body			= JText::sprintf('COM_JEM_MAIL_EDIT_EVENT', $user->name, $user->username, $user->email, $modified_ip, $edited, $event->title, $event->dates, $event->times, $event>venue, $event->city, $text_description, $state);
				$data->receivers 	= $this->_receivers;
			
				$this->_mailer($data);
			}

		} else {
			
			if ($this->params->get('newevent_mail_admin', '0')) {

				$data 				= new stdClass();
				$created 			= JHTML::Date( $event->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject		= JText::sprintf('COM_JEM_NEW_EVENT_MAIL', $this->_SiteName);
				$data->body 		= JText::sprintf('COM_JEM_MAIL_NEW_EVENT', $user->name, $user->username, $user->email, $event->author_ip, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $state);
				$data->receivers 	= $this->_receivers;
			
				$this->_mailer($data);
			}
		}

		//overwrite $state with usermail text
		$state 	= $event->published ? JText::sprintf('COM_JEM_USER_MAIL_EVENT_PUBLISHED', $link) : JText::_('COM_JEM_USER_MAIL_EVENT_UNPUBLISHED');

		if (edited) {
				
			if ($this->params->get('editevent_mail_user', '1')) {
				
				$data 				= new stdClass();
				$edited 			= JHTML::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body			= JText::sprintf('COM_JEM_USER_MAIL_EDIT_EVENT', $user->name, $user->username, $edited, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $state);
				$data->subject		= JText::sprintf( 'COM_JEM_EDIT_USER_EVENT_MAIL', $this->_SiteName );
				$data->receivers 	= $user->email;
				
				$this->_mailer($data);
			}

		} else {
			if ($this->params->get('newevent_mail_user', '1')) {
				$data 				= new stdClass();
				$created 			= JHTML::Date( $event->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body 		= JText::sprintf('COM_JEM_USER_MAIL_NEW_EVENT', $user->name, $user->username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $state);
				$data->subject		= JText::sprintf( 'COM_JEM_NEW_USER_EVENT_MAIL', $this->_SiteName );
				$data->receivers 	= $user->email;
			
				$this->_mailer($data);
			}
		}
		
		return true;
	}
	
	/**
	 * This method handles any mailings triggered by an venue store action
	 *
	 * @access	public
	 * @param   int 	$venue_id 	 Integer Venue identifier
	 * @param   int 	$edited 	 Integer Venue new or edited
	 * @return	boolean
	 * @since 1.0
	 */
	public function onVenueEdited($venue_id, $edited)
	{
		//simple, skip if processing not needed
		if (!$this->params->get('newvenue_mail_user', '1') && !$this->params->get('newvenue_mail_admin', '0')
			&& !$this->params->get('editvenue_mail_user', '1') && !$this->params->get('editvenue_mail_admin', '0')) {
			return true;
		}
				
		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();
				
		$query = ' SELECT v.id, v.published, v.venue, v.city, v.street, v.plz, v.url, v.country, v.locdescription, v.created, v.modified,'
				. ' CASE WHEN CHAR_LENGTH(v.alias) THEN CONCAT_WS(\':\', v.id, v.alias) ELSE v.id END as slug'
				. ' FROM #__jem_venues AS v'
				. ' WHERE v.id = ' . (int)$venue_id;
		$db->setQuery($query);
    
		if (!$venue = $db->loadObject()) {
			if ($db->getErrorNum()) {
				JError::raiseWarning('0', $db->getErrorMsg());
			}
			return false;
		}
				
		//link for event
		$link   = JRoute::_(JURI::base().'index.php?option=com_jem&view=venueevents&id='.$venue->slug, false);
		
		//strip description from tags / scripts, etc...
		$text_description = JFilterOutput::cleanText($venue->locdescription);
		
		$modified_ip 	= getenv('REMOTE_ADDR');
		$edited 		= JHTML::Date( $venue->modified, JText::_( 'DATE_FORMAT_LC2' ) );
		
		$state 	= $venue->published ? JText::sprintf('COM_JEM_MAIL_VENUE_PUBLISHED', $link) : JText::_('COM_JEM_MAIL_VENUE_UNPUBLISHED');
			
		if (edited) {
			
			if ($this->params->get('editvenue_mail_admin', '0')) {
			
				$data 				= new stdClass();
				$edited 			= JHTML::Date( $venue->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject 		= JText::sprintf('COM_JEM_EDIT_VENUE_MAIL', $this->_SiteName);
				$data->body			= JText::sprintf('COM_JEM_MAIL_EDIT_VENUE', $user->name, $user->username, $user->email, $modified_ip, $edited, $venue->venue, $venue->url, $venue->street, $venue->plz, $venue->city, $venue->country, $text_description, $state);
				$data->receivers 	= $this->_receivers;
			
				$this->_mailer($data);
			}

		} else {
			
			if ($this->params->get('newvenue_mail_admin', '0')) {

				$data 				= new stdClass();
				$created 			= JHTML::Date( $venue->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject		= JText::sprintf('COM_JEM_NEW_VENUE_MAIL', $this->_SiteName);
				$data->body 		= JText::sprintf('COM_JEM_MAIL_NEW_VENUE', $user->name, $user->username, $user->email, $venue->author_ip, $created, $venue->venue, $venue->url, $venue->street, $venue->plz, $venue->city, $venue->country, $text_description, $state);
				$data->receivers 	= $this->_receivers;
			
				$this->_mailer($data);
			}
		}

		//overwrite $state with usermail text
		$state 	= $venue->published ? JText::sprintf('COM_JEM_USER_MAIL_VENUE_PUBLISHED', $link) : JText::_('COM_JEM_USER_MAIL_VENUE_UNPUBLISHED');

		if (edited) {
				
			if ($this->params->get('editvenue_mail_user', '1')) {
				
				$data 				= new stdClass();
				$edited 			= JHTML::Date( $venue->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body			= JText::sprintf('COM_JEM_USER_MAIL_EDIT_VENUE', $user->name, $user->username, $isNew, $venue->venue, $venue->url, $venue->street, $venue->plz, $venue->city, $venue->country, $text_description, $state);
				$data->subject		= JText::sprintf( 'COM_JEM_EDIT_USER_VENUE_MAIL', $this->_SiteName );
				$data->receivers 	= $user->email;
				
				$this->_mailer($data);
			}

		} else {
			if ($this->params->get('newvenue_mail_user', '1')) {
				$data 				= new stdClass();
				$created 			= JHTML::Date( $venue->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body 		= JText::sprintf('COM_JEM_USER_MAIL_NEW_VENUE', $user->name, $user->username, $created, $venue->venue, $venue->url, $venue->street, $venue->plz, $venue->city, $venue->country, $text_description, $state);
				$data->subject		= JText::sprintf( 'COM_JEM_NEW_USER_VENUE_MAIL', $this->_SiteName );
				$data->receivers 	= $user->email;
			
				$this->_mailer($data);
			}
		}
		
		return true;
	}
	
	/**
	 * This method executes and send the mail 
	 *
	 * @access	private
	 * @param   object 	$data 	 mail data object
	 * @return	boolean
	 * @since 1.0
	 */
	private function _mailer($data)
	{			
		$mail = JFactory::getMailer();
		
		$mail->addRecipient( $data->receivers );
		$mail->setSender( array( $this->_MailFrom, $this->_FromName ) );
		$mail->setSubject( $data->subject );
		$mail->setBody( $data->body );

		$mail->send();
		
		return true;
	}
}
?>