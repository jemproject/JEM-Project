<?php
/**
 * @version 2.0.0
 * @package JEM
 * @subpackage JEM Mailer Plugin
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * @todo: change onEventUserRegistered
 * there is a check for the waitinglist and that one is looking
 * at the option "reg_email_to". The onEventUnregistered function
 * has no check for the waitinglist.
 *
 * @todo: check output time/date
 * it's possible that there is no time or date for an event.
 * add check for global time/date format. At the moment the output
 * format is not respecting the global-setting
 */
defined('_JEXEC') or die;

// Import library dependencies
jimport('joomla.event.plugin');
jimport('joomla.utilities.mail');


include_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
include_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');

class plgJEMMailer extends JPlugin {

	private $_SiteName = '';
	private $_MailFrom = '';
	private $_FromName = '';
	private $_AdminDBList = '';
	private $_UseLoginName = false; // false: name true: username

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
		$jemsettings = JemHelper::globalattribs();

		$this->_SiteName     = $app->getCfg('sitename');
		$this->_MailFrom     = $app->getCfg('mailfrom');
		$this->_FromName     = $app->getCfg('fromname');
		$this->_AdminDBList  = self::Adminlist();
		$this->_UseLoginName = !$jemsettings->get('global_regname', 1); // regname == 1: name, 0: username (login name)

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
		// skip if processing not needed
		if (!$this->params->get('reg_mail_user', '1') && !$this->params->get('reg_mail_admin', '0')) {
			return true;
		}

		$user 	= JFactory::getUser();
		$username = empty($this->_UseLoginName) ? $user->name : $user->username;

		// get data
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);

		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('a.alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('a.id');
		$case_when .= $query->concatenate(array($id, 'a.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('a.id','a.title','r.waiting',$case_when));
		$query->from($db->quoteName('#__jem_register').' AS r');
		$query->join('INNER', '#__jem_events AS a ON r.event = a.id');
		$query->where(array('r.id= '.$db->quote($register_id)));

		$db->setQuery($query);
		if (is_null($event = $db->loadObject())) return false;

		//create link to event
		$link = JRoute::_(JURI::base().JEMHelperRoute::getEventRoute($event->slug), false);


		############################
		## SENDMAIL - WAITINGLIST ##
		############################

		if ($event->waiting) // registered to the waiting list
		{
			###################################
			## SENDMAIL - WAITINGLIST - USER ##
			###################################
			if ($this->params->get('reg_mail_user', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_WAITING_BODY_4', $username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;
				$this->_mailer($data);
			}

			####################################
			## SENDMAIL - WAITINGLIST - ADMIN ##
			####################################
			if ($this->params->get('reg_mail_admin', '0')) {
				# check if we've something in the Adminlist
				if ($this->_AdminDBList){
					$data 				= new stdClass();
					$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_WAITING_SUBJECT', $this->_SiteName);
					$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_WAITING_BODY_4', $username, $event->title, $link, $this->_SiteName);
					$data->receivers 	= $this->_AdminDBList;
					$this->_mailer($data);
				}
			}
		} else {
			#####################
			## SENDMAIL - USER ##
			#####################
			if ($this->params->get('reg_mail_user', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_BODY_4', $username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;
				$this->_mailer($data);
			}

			######################
			## SENDMAIL - ADMIN ##
			######################
			if ($this->params->get('reg_mail_admin', '0')) {

				# check if we've something in the Adminlist
				if ($this->_AdminDBList){
					$data 				= new stdClass();
					$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_SUBJECT', $this->_SiteName);
					$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_BODY_4', $username, $event->title, $link, $this->_SiteName);
					$data->receivers 	= $this->_AdminDBList;
					$this->_mailer($data);
				}
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
		// skip if processing not needed
		if (!$this->params->get('reg_mail_user_onoff', '1') && !$this->params->get('reg_mail_admin_onoff', '0')) {
			return true;
		}

		// get data
		$db 	= JFactory::getDBO();
		$query = $db->getQuery(true);

		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('a.alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('a.id');
		$case_when .= $query->concatenate(array($id, 'a.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('a.id','a.title','r.waiting','r.uid',$case_when));
		$query->from($db->quoteName('#__jem_register').' AS r');
		$query->join('INNER', '#__jem_events AS a ON r.event = a.id');
		$query->where(array('r.id= '.$db->quote($register_id)));

		$db->setQuery($query);
		if (is_null($details = $db->loadObject())) return false;


		$user 	= JFactory::getUser($details->uid);
		$username = empty($this->_UseLoginName) ? $user->name : $user->username;

		// create link to event
		$url = JURI::root();
		$link =JRoute::_($url. JEMHelperRoute::getEventRoute($details->slug), false);

		if ($details->waiting) // added to the waiting list
		{
			// handle usermail
			if ($this->params->get('reg_mail_user_onoff', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_WAITING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_WAITING_BODY_4', $username, $details->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;
				$this->_mailer($data);
			}

			// handle adminmail
			if ($this->params->get('reg_mail_admin_onoff', '0')) {
				if ($this->_AdminDBList){
					$data 				= new stdClass();
					$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_SUBJECT', $this->_SiteName);
					$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_BODY_4', $username, $details->title, $link, $this->_SiteName);
					$data->receivers 	= $this->_AdminDBList;
					$this->_mailer($data);
				}
			}
		} else { // bumped from waiting list to attending list
			// handle usermail
			if ($this->params->get('reg_mail_user_onoff', '1')) {
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_ATTENDING_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_REG_ON_ATTENDING_BODY_4', $username, $details->title, $link, $this->_SiteName);
				$data->receivers 	= $user->email;
				$this->_mailer($data);
			}

			// handle adminmail
			if ($this->params->get('reg_mail_admin_onoff', '0')) {
				if ($this->_AdminDBList){
					$data 				= new stdClass();
					$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_SUBJECT', $this->_SiteName);
					$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_BODY_4', $username, $details->title, $link, $this->_SiteName);
					$data->receivers 	= $this->_AdminDBList;
					$this->_mailer($data);
				}
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
		// skip if processing not needed
		if (!$this->params->get('unreg_mail_user', '1') && !$this->params->get('unreg_mail_admin', '0')) {
			return true;
		}

		$user 	= JFactory::getUser();
		$username = empty($this->_UseLoginName) ? $user->name : $user->username;

		// get data
		$db 	= JFactory::getDBO();
		$query	= $db->getQuery(true);

		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('a.alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('a.id');
		$case_when .= $query->concatenate(array($id, 'a.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('a.id','a.title',$case_when));
		$query->from($db->quoteName('#__jem_events').' AS a');
		$query->where(array('a.id= '.$db->quote($event_id)));

		$db->setQuery($query);
		if (is_null($event = $db->loadObject())) return false;


		// create link to event
		$link = JRoute::_(JURI::base().JEMHelperRoute::getEventRoute($event->slug), false);

		#####################
		## SENDMAIL - USER ##
		#####################
		if ($this->params->get('unreg_mail_user', '1')) {
			$data 				= new stdClass();
			$data->subject 		= JText::sprintf('PLG_JEM_MAILER_USER_UNREG_SUBJECT', $this->_SiteName);
			$data->body			= JText::sprintf('PLG_JEM_MAILER_USER_UNREG_BODY_4', $username, $event->title, $link, $this->_SiteName);
			$data->receivers 	= $user->email;

			$this->_mailer($data);
		}

		######################
		## SENDMAIL - ADMIN ##
		######################
		if ($this->params->get('unreg_mail_admin', '0')) {
			if ($this->_AdminDBList){
				$data 				= new stdClass();
				$data->subject 		= JText::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_SUBJECT', $this->_SiteName);
				$data->body			= JText::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_BODY_4', $username, $event->title, $link, $this->_SiteName);
				$data->receivers 	= $this->_AdminDBList;

				$this->_mailer($data);
			}
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
	public function onEventEdited($event_id, $is_new)
	{

		####################
		## DEFINING ARRAY ##
		####################

		$send_to = array(
			'user' => $is_new ? $this->params->get('newevent_mail_user', '1') : $this->params->get('editevent_mail_user', '1'),
			'admin' => $is_new ? $this->params->get('newevent_mail_admin', '0') : $this->params->get('editevent_mail_admin', '0'),
			'registered' => !$is_new && $this->params->get('editevent_mail_registered', '0'),
			'category' => $is_new ? $this->params->get('newevent_mail_category', '0') : $this->params->get('editevent_mail_category', '0'),
			'group' => $is_new ? $this->params->get('newevent_mail_group', '0') : $this->params->get('editevent_mail_group', '0'),
		);

		// skip if processing not needed
		if (!array_filter($send_to)) return true;

		$user 	= JFactory::getUser();
		$username = empty($this->_UseLoginName) ? $user->name : $user->username;

		// get data
		$db 	= JFactory::getDBO();
		$query	= $db->getQuery(true);

		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('a.alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('a.id');
		$case_when .= $query->concatenate(array($id, 'a.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('a.id','a.title','a.dates','a.times','a.locid','a.published','a.created','a.modified'));
		$query->select($query->concatenate(array('a.introtext', 'a.fulltext')).' AS text');
		$query->select(array('v.venue','v.city'));
		$query->select($case_when);
		$query->from($db->quoteName('#__jem_events').' AS a');
		$query->join('LEFT', '#__jem_venues AS v ON v.id = a.locid');
		$query->where(array('a.id= '.$db->quote($event_id)));

		$db->setQuery($query);
		if (is_null($event = $db->loadObject())) return false;

		// Link for event
		$link = JRoute::_(JURI::base().JEMHelperRoute::getEventRoute($event->slug), false);

		// Strip tags/scripts, etc. from description
		$text_description = JFilterOutput::cleanText($event->text);

		// Define published-state message
		switch ($event->published) {
		case 1:
			$adminstate = JText::sprintf('PLG_JEM_MAILER_EVENT_PUBLISHED', $link);
			$userstate = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_EVENT_PUBLISHED', $link);
			break;
		case -2:
			$adminstate = JText::_('PLG_JEM_MAILER_EVENT_TRASHED');
			$userstate = JText::_('PLG_JEM_MAILER_USER_MAIL_EVENT_TRASHED');
			break;
		case 0:
			$adminstate = JText::_('PLG_JEM_MAILER_EVENT_UNPUBLISHED');
			$userstate = JText::_('PLG_JEM_MAILER_USER_MAIL_EVENT_UNPUBLISHED');
			break;
		case 2:
			$adminstate = JText::_('PLG_JEM_MAILER_EVENT_ARCHIVED');
			$userstate = JText::_('PLG_JEM_MAILER_USER_MAIL_EVENT_ARCHIVED');
			break;
		default: /* TODO: fallback unknown / undefined */
			$adminstate = JText::_('PLG_JEM_MAILER_EVENT_UNKNOWN');
			$userstate = JText::_('PLG_JEM_MAILER_USER_MAIL_EVENT_UNKNOWN');
			break;
		}

		#######################
		## RECEIVERS - ADMIN ##
		#######################

		# in here we selected the option to send to admin.
		# we selected admin so we can use the adminDBList.

		if ($send_to['admin']) {
			$admin_receivers = $this->_AdminDBList;
		} else {
			$admin_receivers = false;
		}


		############################
		## RECEIVERS - REGISTERED ##
		############################

		# in here we selected the option to send an email to all people registered to the event.
		# there is no check for the waitinglist

		# $registered_receivers is defined in here
		if ($send_to['registered']) {

			// get data
			$query = $db->getQuery(true);
			$query->select(array('u.email'));
			$query->from($db->quoteName('#__users').' AS u');
			$query->join('INNER', '#__jem_register AS reg ON reg.uid = u.id');
			$query->where(array('reg.event= '.$db->quote($event_id)));

			$db->setQuery($query);
			if (is_null($registered_receivers = $db->loadColumn(0))) return false;

		} else {
			$registered_receivers = false;
		}


		############################
		## RECEIVERS - CATEGORY ##
		############################

		# in here we selected the option to send an email to the email-address
		# that's filled in the category-view.

		# the data within categoryDBList needs to be validated.
		# if the categoryDBList is empty we shoudln't send an email

		# $category_receivers is defined in here

		if ($send_to['category']) {
			// get data
			$query = $db->getQuery(true);
			$query->select(array('c.email'));
			$query->from($db->quoteName('#__jem_categories').' AS c');
			$query->join('INNER', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');
			$query->where(array('rel.itemid= '.$db->quote($event_id)));

			$db->setQuery($query);
			if (is_null($category_receivers = $db->loadColumn(0))) {
				return false;
			} else {
				$category_receivers = self::categoryDBList($category_receivers);
			}
		} else {
			$category_receivers = false;
		}


		#######################
		## RECEIVERS - GROUP ##
		#######################

		# in here we selected the option to send an email to the email-address
		# of the users within the maintainer-group of the category where
		# the event is assigned too.

		# $group_receivers is defined in here

		if ($send_to['group']) {
			// get data
			$query = $db->getQuery(true);
			$query->select(array('u.email'));
			$query->from($db->quoteName('#__users').' AS u');
			$query->join('INNER', '#__jem_groupmembers AS gm ON gm.member = u.id');
			$query->join('INNER', '#__jem_categories AS c ON c.groupid = gm.group_id');
			$query->join('INNER', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');
			$query->where(array('rel.itemid= '.$db->quote($event_id)));

			$db->setQuery($query);
			if (is_null($group_receivers = $db->loadColumn(0))) return false;

		} else {
			$group_receivers = false;
		}


		######################
		## RECEIVERS - USER ##
		######################

		# in here we selected the option to send an email to the logged-in user

		# $user_receiver is defined in here

		if ($send_to['user']) {
			$user_receiver = $user->email;
		} else {
			$user_receiver = false;
		}


		##############################
		## SENDMAIL: $user_receiver ##
		##############################

		if ($user_receiver) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date($event->created, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_USER_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_NEW_EVENT_9', $username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $userstate);
			} else {
				$modified = JHtml::Date($event->modified, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_USER_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_EDIT_EVENT_9', $username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $userstate);
			}

			$data->receivers = $user_receiver;
			$this->_mailer($data);
		}


		################################
		## SENDMAIL: $admin_receivers ##
		################################

		if ($admin_receivers) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date($event->created, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_9', $username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			} else {
				$modified = JHtml::Date($event->modified, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_9', $username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			}

			$data->receivers = $admin_receivers;
			$this->_mailer($data);
		}


		################################
		## SENDMAIL: $group_receivers ##
		################################

		if ($group_receivers) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date($event->created, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_9', $username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			} else {
				$modified = JHtml::Date($event->modified, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_9', $username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			}

			$data->receivers = array_unique($group_receivers);
			$this->_mailer($data);
		}

		#####################################
		## SENDMAIL: $registered_receivers ##
		#####################################

		if ($registered_receivers) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date($event->created, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_CAT_NOTIFY_9', $username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			} else {
				$modified = JHtml::Date($event->modified, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_CAT_NOTIFY_9', $username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			}

			$data->receivers = array_unique($registered_receivers);
			$this->_mailer($data);
		}

		###################################
		## SENDMAIL: $category_receivers ##
		###################################

		if ($category_receivers) {
			$data			= new stdClass();

			if ($is_new) {
				$created = JHtml::Date($event->created, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_NEW_EVENT_CAT_NOTIFY_9', $username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			} else {
				$modified = JHtml::Date($event->modified, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = JText::sprintf('PLG_JEM_MAILER_EDIT_EVENT_CAT_NOTIFY_9', $username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			}

			$data->receivers = $category_receivers;
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
		// Sendto
		$send_to = array(
			'user' => $is_new ? $this->params->get('newvenue_mail_user', '1') : $this->params->get('editvenue_mail_user', '0'),
			'admin' => $is_new ? $this->params->get('newvenue_mail_admin', '1') : $this->params->get('editvenue_mail_admin', '0'),
		);

		// Skip if processing not needed
		if (!array_filter($send_to)) return true;


		$user 	= JFactory::getUser();
		$username = empty($this->_UseLoginName) ? $user->name : $user->username;

		// get data
		$db 	= JFactory::getDBO();
		$query	= $db->getQuery(true);

		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('id');
		$case_when .= $query->concatenate(array($id, 'alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('id','published','venue','city','street','postalCode','url','country','locdescription','created','modified',$case_when));
		$query->from('#__jem_venues');
		$query->where(array('id= '.$db->quote($venue_id)));

		$db->setQuery($query);
		if (is_null($venue = $db->loadObject())) return false;

		# at this point we do have a result

		// Define link for venue
		$link = JRoute::_(JURI::base().JEMHelperRoute::getVenueRoute($venue->slug), false);

		// Define published-state message
		$adminstate = $venue->published ? JText::sprintf('PLG_JEM_MAILER_VENUE_PUBLISHED', $link) : JText::_('PLG_JEM_MAILER_VENUE_UNPUBLISHED');
		$userstate = $venue->published ? JText::sprintf('PLG_JEM_MAILER_USER_MAIL_VENUE_PUBLISHED', $link) : JText::_('PLG_JEM_MAILER_USER_MAIL_VENUE_UNPUBLISHED');

		// Strip tags/scripts,etc from description
		$text_description = JFilterOutput::cleanText($venue->locdescription);


		#######################
		## RECEIVERS - ADMIN ##
		#######################

		# in here we selected the option to send to admin.
		# we selected admin so we can use the adminDBList.
		# if the adminDBList is empty mailing should stop!

		if ($send_to['admin']) {

			$admin_receivers = $this->_AdminDBList;

			if ($admin_receivers) {
				$data = new stdClass();

				# is the venue new or edited?
				if ($is_new) {
					# the venue is new and we send a mail to adminDBList
					$created = JHtml::Date($venue->created, JText::_('DATE_FORMAT_LC2'));
					$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_VENUE_MAIL', $this->_SiteName, $venue->venue);
					$data->body = JText::sprintf('PLG_JEM_MAILER_NEW_VENUE_A', $username, $created, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $adminstate);
				} else {
					# the venue is edited and we send a mail to adminDBList
					$modified = JHtml::Date($venue->modified, JText::_('DATE_FORMAT_LC2'));
					$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_VENUE_MAIL', $this->_SiteName, $venue->venue);
					$data->body = JText::sprintf('PLG_JEM_MAILER_EDIT_VENUE_A', $username, $modified, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $adminstate);
				}
				$data->receivers = $admin_receivers;

				$this->_mailer($data);
			} else {
				return false;
			}
		}

		######################
		## RECEIVERS - USER ##
		######################

		# here we selected the option to send to a logged in user
		# we make a selection between added/edited venue
		# -> here we don't specify an extra variable

		if ($send_to['user']) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date($venue->created, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_NEW_USER_VENUE_MAIL', $this->_SiteName, $venue->venue);
				$data->body = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_NEW_VENUE_A', $username, $created, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $userstate);
			} else {
				$modified = JHtml::Date($venue->modified, JText::_('DATE_FORMAT_LC2'));
				$data->subject = JText::sprintf('PLG_JEM_MAILER_EDIT_USER_VENUE_MAIL', $this->_SiteName, $venue->venue);
				$data->body = JText::sprintf('PLG_JEM_MAILER_USER_MAIL_EDIT_VENUE_A', $username, $modified, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $userstate);
			}

			$data->receivers = $user->email;
			$this->_mailer($data);
		}

		return true;
	}


	/**
	 * This method executes and send the mail
	 * info: http://docs.joomla.org/Sending_email_from_extensions
	 *
	 * @access	private
	 * @param   object 	$data 	 mail data object
	 * @return	boolean
	 */
	private function _mailer($data)
	{
		$receivers = is_array($data->receivers) ? $data->receivers : array($data->receivers);

		// validate the $receivers-array
		$receivers	= filter_var_array($receivers,FILTER_VALIDATE_EMAIL);
		$receivers	= array_filter($receivers);
		$receivers	= array_unique($receivers);

		if ($receivers) {
			foreach ($receivers as $receiver) {
				$mail = JFactory::getMailer();
				$mail->setSender(array($this->_MailFrom, $this->_FromName));
				$mail->setSubject($data->subject);

				# check if we did select the option to output html mail
				if ($this->params->get('send_html','0')== 1) {
					$mail->isHTML(true);
					$mail->Encoding = 'base64';
					$body_html = nl2br ($data->body);
					$mail->setBody($body_html);
				} else {
					$mail->setBody($data->body);
				}
				$mail->addRecipient($receiver);
				$mail->send();
			}
		}

		return true;
	}

	/**
	 * This method assembles the adminDBList
	 */
	private function Adminlist()
	{
		$additional_mails	= array_filter(explode(',', trim($this->params->get('admin_receivers'))));
		// remove whitespaces around each entry, then check if valid email address
		foreach ($additional_mails as $k => $v) {
			$additional_mails[$k] = filter_var(trim($v), FILTER_VALIDATE_EMAIL);
		}
		$additional_mails	= array_filter($additional_mails);

		if ($this->params->get('fetch_admin_mails', '0')) {

			// get data
			$db 	= JFactory::getDBO();
			$query	= $db->getQuery(true);

			$query->select(array('u.id','u.email','u.name'));
			$query->from($db->quoteName('#__users').' AS u');
			$query->where(array('u.sendEmail = 1'));

			$db->setQuery($query);

			if (!$db->query()) {
				JError::raiseError(500, $db->stderr(true));
				return;
			}

			$admin_mails = $db->loadColumn(1);
			$AdminList   = array_merge($admin_mails, $additional_mails);
			$AdminList   = array_unique($AdminList);
		} else {
			$AdminList	= array_unique($additional_mails);
		}

		return $AdminList;
	}


	/**
	 * This method checks the categoryDBList
	 */
	private function categoryDBlist($list)
	{
		if ($list) {
			// maybe event has multiple categories - merge them
			if (is_array($list)) {
				$list = implode(',', $list);
			}
			$CategoryDBList	= array_filter(explode(',', trim($list)));
			// remove whitespaces around each entry, then check if valid email address
			foreach ($CategoryDBList as $k => $v) {
				$CategoryDBList[$k] = filter_var(trim($v), FILTER_VALIDATE_EMAIL);
			}
			$CategoryDBList = array_unique($CategoryDBList);
			$CategoryDBList = array_filter($CategoryDBList);
		} else {
			$CategoryDBList = '';
		}

		return $CategoryDBList;
	}
}
?>
