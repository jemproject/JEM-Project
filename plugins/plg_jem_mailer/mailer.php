<?php
/**
 * @version 1.9.6
 * @package JEM
 * @subpackage JEM Mailer Plugin
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

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

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An array that holds the plugin configuration
	 *
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();

		$app = JFactory::getApplication();
		$this->_SiteName 	= $app->getCfg('sitename');
		$this->_MailFrom	= $app->getCfg('mailfrom');
		$this->_FromName 	= $app->getCfg('fromname');
	}

	/**
	 * This method handles any mailings triggered by an event registration action
	 *
	 * @access	public
	 * @param   int 	$event_id 	 Integer Event identifier
	 * @return	boolean
	 *
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
		$link = JRoute::_(JURI::base().JEMHelperRoute::getEventRoute($event->slug), false);

		if ($event->waiting) // registered to the waiting list
		{
			//handle usermail
			if ($this->params->get('reg_mail_user', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_WAITING_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;

				$this->_mailer($data);
			}

			//handle adminmail
			if ($this->params->get('reg_mail_admin', '0') && $this->_receivers) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_WAITING_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $this->_receivers;

				$this->_mailer($data);
			}
		} else {
			//handle usermail
			if ($this->params->get('reg_mail_user', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;

				$this->_mailer($data);
			}

			//handle adminmail
			if ($this->params->get('reg_mail_admin', '0') && $this->_receivers) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
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
	 *
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
		$url = JURI::root();
		$link =JRoute::_($url. JEMHelperRoute::getEventRoute($details->slug), false);

		if ($details->waiting) // added to the waiting list
		{
			//handle usermail
			if ($this->params->get('reg_mail_user_onoff', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_WAITING_BODY', $user->name, $user->username, $details->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;

				$this->_mailer($data);
			}

			//handle adminmail
			if ($this->params->get('reg_mail_admin_onoff', '0') && $this->_receivers) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_BODY', $user->name, $user->username, $details->title, $link, $this->_SiteName);
				$data->receivers 	= array($this->_receivers);

				$this->_mailer($data);
			}
		} else { // bumped from waiting list to attending list
			//handle usermail
			if ($this->params->get('reg_mail_user_onoff', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_ATTENDING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_ATTENDING_BODY', $user->name, $user->username, $details->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;

				$this->_mailer($data);
			}

			//handle adminmail
			if ($this->params->get('reg_mail_admin_onoff', '0') && $this->_receivers) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_BODY', $user->name, $user->username, $details->title, $link, $this->_SiteName);
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
	 *
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
		$link = JRoute::_(JURI::base().JEMHelperRoute::getEventRoute($event->slug), false);

		//handle usermail
		if ($this->params->get('unreg_mail_user', '1')) {
			$data 				= new stdClass();
			$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_UNREG_SUBJECT', $this->_SiteName);
			$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_UNREG_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
			$data->receivers 	= $user->email;

			$this->_mailer($data);
		}

		//handle adminmail
		if ($this->params->get('unreg_mail_admin', '0') && $this->_receivers) {
			$data 				= new stdClass();
			$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_SUBJECT', $this->_SiteName);
			$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_BODY', $user->name, $user->username, $event->title, $link, $this->_SiteName);
			$data->receivers 	= $this->_receivers;

			$this->_mailer($data);
		}

		return true;
	}

	/**
	* This method handles any mailings triggered by an event store action
	* 
	* @access public
	* @param int $event_id Event identifier
	* @param int $is_new Event new or edited
	* @return  boolean
	* 
	*/
	public function onEventEdited($event_id, $is_new) {
		$send_to = array(
			'user' => $is_new ? $this->params->get('newevent_mail_user', '1') : $this->params->get('editevent_mail_user', '1'),
			'admin' => $is_new ? $this->params->get('newevent_mail_admin', '0') : $this->params->get('editevent_mail_admin', '0'),
			'registered' => !$is_new && $this->params->get('editevent_mail_registered', '0'),
			'category' => $is_new ? $this->params->get('newevent_mail_category', '0') : $this->params->get('editevent_mail_category', '0'),
			'group' => $is_new ? $this->params->get('newevent_mail_group', '0') : $this->params->get('editevent_mail_group', '0'),
		);

		// Simple, skip if processing not needed
		if (!array_filter($send_to)) return true;


		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();


		// Get event data
		$query = ' SELECT a.id, a.title, a.dates, a.times, CONCAT(a.introtext,a.fulltext) AS text, a.locid, a.published, a.created, a.modified,'
				. ' v.venue, v.city,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug'
				. ' FROM #__jem_events AS a '
				. ' LEFT JOIN #__jem_venues AS v ON v.id = a.locid'
				. ' WHERE a.id = ' . (int)$event_id;
		$db->setQuery($query);
		if(is_null($event = $db->loadObject())) return false;


		// Link for event
		$link = JRoute::_(JURI::base().JEMHelperRoute::getEventRoute($event->slug), false);

		// Strip description from tags / scripts, etc...
		$text_description = JFilterOutput::cleanText($event->text);		
		
		// Get user IP		
		if (getenv('HTTP_CLIENT_IP')) {
			$modified_ip =getenv('HTTP_CLIENT_IP');
		} elseif (getenv('HTTP_X_FORWARDED_FOR')) {
		    $modified_ip =getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('HTTP_X_FORWARDED')) {
			$modified_ip =getenv('HTTP_X_FORWARDED');
		} elseif (getenv('HTTP_FORWARDED_FOR')) {
		    $modified_ip =getenv('HTTP_FORWARDED_FOR');
		} elseif (getenv('HTTP_FORWARDED')) {
			$modified_ip = getenv('HTTP_FORWARDED');
		} else {
		    $modified_ip = $_SERVER['REMOTE_ADDR'];
		}

		// Get published-state message
		if ($event->published > 0) {
			$adminstate = JText::sprintf('PLG_JEM_MAILER_EVENT_PUBLISHED', $link);
			$userstate = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_EVENT_PUBLISHED', $link);
		} else if ($event->published == -2) {
			$adminstate = JText::_('PLG_JEM_MAILER_EVENT_TRASHED');
			$userstate = JText::_('PLG_JEM_MAILER_USER_MAIL_EVENT_TRASHED');
		} else {
			$adminstate = JText::_('PLG_JEM_MAILER_EVENT_UNPUBLISHED');
			$userstate = JText::_('PLG_JEM_MAILER_USER_MAIL_EVENT_UNPUBLISHED');
		}


		// Get receivers
		if ($send_to['admin']) {
			$admin_receivers = array_filter(explode(',', trim($this->params->get('admin_receivers'))));
		}

		if ($send_to['register']) {
			$query = ' SELECT u.email'
					. ' FROM #__users AS u'
					. ' INNER JOIN #__jem_register AS reg ON reg.uid = u.id'
					. ' WHERE reg.event = ' . (int)$event_id;
			$db->setQuery($query);
			if(is_null($registered_receivers = $db->loadColumn(0))) return false;
		}

		if ($send_to['category']) {
			$query = ' SELECT c.email'
					. ' FROM #__jem_categories AS c'
					. ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
					. ' WHERE rel.itemid = ' . (int)$event_id;
			$db->setQuery($query);
			if(is_null($category_receivers = $db->loadColumn(0))) return false;
		}

		if ($send_to['group']) {
			$query = 'SELECT u.email'
					. ' FROM #__users AS u'
					. ' INNER JOIN #__jem_groupmembers AS gm ON gm.member = u.id'
					. ' INNER JOIN #__jem_categories AS c ON c.groupid = gm.group_id'
					. ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
					. ' WHERE rel.itemid = ' . (int)$event_id;
			$db->setQuery($query);
			if(is_null($group_receivers = $db->loadColumn(0))) return false;
		}


		// Send emails
		if ($send_to['user']) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date( $event->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_NEW_EVENT', $user->name, $user->username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $userstate);
				$data->subject = JText::sprintf( 'PLG_JEM_MAILER_NEW_USER_EVENT_MAIL', $this->_SiteName );
			} else {
				$modified = JHtml::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_EDIT_EVENT', $user->name, $user->username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $userstate);
				$data->subject = JText::sprintf( 'PLG_JEM_MAILER_EDIT_USER_EVENT_MAIL', $this->_SiteName );
			}

			$data->receivers = $user->email;
			$this->_mailer($data);
		}

		if ($admin_receivers || $group_receivers) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date( $event->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_MAIL', $this->_SiteName);
				$data->body = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT', $user->name, $user->username, $user->email, $event->author_ip, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			} else {
				$modified = JHtml::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName);
				$data->body = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT', $user->name, $user->username, $user->email, $modified_ip, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			}

			$data->receivers = array_unique(array_merge((array) $admin_receivers, (array) $group_receivers));
			$this->_mailer($data);
		}
		
		if ($registered_receivers || $category_receivers) {
			$data = new stdClass();
			
			if ($is_new) {
				$created = JHtml::Date( $event->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_MAIL', $this->_SiteName);
				$data->body = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_CAT_NOTIFY', $user->name, $user->username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			} else {
				$modified = JHtml::Date( $event->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName);
				$data->body = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_CAT_NOTIFY', $user->name, $user->username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			}
			
			$data->receivers = array_unique(array_merge((array) $registered_receivers, (array) $category_receivers));
			$this->_mailer($data);
		}
		
		return true;
	}

	/**
	 * This method handles any mailings triggered by an venue store action
	 *
	 * @access  public
	 * @param   int 	$venue_id 	 Integer Venue identifier
	 * @param   int 	$is_new  	 Integer Venue new or edited
	 * @return  boolean
	 *
	 */
	public function onVenueEdited($venue_id, $is_new)
	{
		$send_to = array(
			'user' => $is_new ? $this->params->get('newvenue_mail_user', '1') : $this->params->get('editvenue_mail_user', '0'),
			'admin' => $is_new ? $this->params->get('newvenue_mail_admin', '1') : $this->params->get('editvenue_mail_admin', '0'),
		);

		// Simple, skip if processing not needed
		if (!array_filter($send_to)) return true;


		$db 	= JFactory::getDBO();
		$user 	= JFactory::getUser();


		// Get event data
		$query = ' SELECT v.id, v.published, v.venue, v.city, v.street, v.postalCode, v.url, v.country, v.locdescription, v.created, v.modified,'
				. ' CASE WHEN CHAR_LENGTH(v.alias) THEN CONCAT_WS(\':\', v.id, v.alias) ELSE v.id END as slug'
				. ' FROM #__jem_venues AS v'
				. ' WHERE v.id = ' . (int)$venue_id;
		$db->setQuery($query);
		if (is_null($venue = $db->loadObject())) return false;


		// Link for venue
		$link = JRoute::_(JURI::base().JEMHelperRoute::getVenueRoute($venue->slug), false);

		// Strip description from tags / scripts, etc...
		$text_description = JFilterOutput::cleanText($venue->locdescription);

		// Get user IP		
		if (getenv('HTTP_CLIENT_IP')) {
			$modified_ip =getenv('HTTP_CLIENT_IP');
		} elseif (getenv('HTTP_X_FORWARDED_FOR')) {
		    $modified_ip =getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('HTTP_X_FORWARDED')) {
			$modified_ip =getenv('HTTP_X_FORWARDED');
		} elseif (getenv('HTTP_FORWARDED_FOR')) {
		    $modified_ip =getenv('HTTP_FORWARDED_FOR');
		} elseif (getenv('HTTP_FORWARDED')) {
			$modified_ip = getenv('HTTP_FORWARDED');
		} else {
		    $modified_ip = $_SERVER['REMOTE_ADDR'];
		}

		// Get published-state message
		$adminstate = $venue->published ? JText::sprintf('PLG_JEM_MAILER_VENUE_PUBLISHED', $link) : JText::_('PLG_JEM_MAILER_VENUE_UNPUBLISHED');
		$userstate = $venue->published ? JText::sprintf('PLG_JEM_MAILER_USER_MAIL_VENUE_PUBLISHED', $link) : JText::_('PLG_JEM_MAILER_USER_MAIL_VENUE_UNPUBLISHED');
	

		// Get receivers
		if ($send_to['admin']) {
			$admin_receivers = array_filter(explode(',', trim($this->params->get('admin_receivers'))));
		}


		// Send emails
		if ($send_to['user']) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date( $venue->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_NEW_VENUE', $user->name, $user->username, $created, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $userstate);
				$data->subject = JText::sprintf( 'PLG_JEM_MAILER_NEW_USER_VENUE_MAIL', $this->_SiteName );
			} else {
				$modified = JHtml::Date( $venue->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->body = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_EDIT_VENUE', $user->name, $user->username, $modified, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $userstate);
				$data->subject = JText::sprintf( 'PLG_JEM_MAILER_EDIT_USER_VENUE_MAIL', $this->_SiteName );
			}

			$data->receivers = $user->email;
			$this->_mailer($data);
		}

		if ($admin_receivers) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date( $venue->created, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_VENUE_MAIL', $this->_SiteName);
				$data->body = JText::sprintf('PLG_JEM_MAILER_NEW_VENUE', $user->name, $user->username, $user->email, $venue->author_ip, $created, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $adminstate);
			} else {
				$modified = JHtml::Date( $venue->modified, JText::_( 'DATE_FORMAT_LC2' ) );
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_VENUE_MAIL', $this->_SiteName);
				$data->body = JText::sprintf('PLG_JEM_MAILER_EDIT_VENUE', $user->name, $user->username, $user->email, $modified_ip, $modified, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $adminstate);
			}

			$data->receivers = array_unique($admin_receivers);
			$this->_mailer($data);
		}

		return true;
	}

	/**
	 * This method executes and send the mail
	 *
	 * @access	private
	 * @param   object 	$data 	 mail data object
	 * @return	boolean
	 *
	 */
	private function _mailer($data)
	{
		$receivers = is_array($data->receivers) ? $data->receivers : array($data->receivers);

		foreach ($receivers as $receiver) {
			$mail = JFactory::getMailer();

			$mail->setSender( array( $this->_MailFrom, $this->_FromName ) );
			$mail->setSubject( $data->subject );
			$mail->setBody( $data->body );

			$mail->addRecipient($receiver);
			$mail->send();
		}

		return true;
	}
}
?>
