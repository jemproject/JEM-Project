<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @subpackage JEM Mailer Plugin
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Plugin\CMSPlugin;

// Import library dependencies
jimport('joomla.utilities.mail');

require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/factory.php');


class plgJemMailer extends CMSPlugin
{
	private $_SiteName = '';
	private $_MailFrom = '';
	private $_FromName = '';
	private $_AdminDBList = '';
	private $_UseLoginName = false; // false: name true: username

	/**
	 * Constructor
	 *
	 * @param   object &$subject  The object to observe
	 * @param   array   $config    An array that holds the plugin configuration
	 *
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();

		$app = Factory::getApplication();
		$jemsettings = JemHelper::globalattribs();

		$this->_SiteName     = $app->get('sitename');
		$this->_MailFrom     = $app->get('mailfrom');
		$this->_FromName     = $app->get('fromname');
		$this->_AdminDBList  = $this->Adminlist();
		$this->_UseLoginName = !$jemsettings->get('global_regname', 1); // regname == 1: name, 0: username (login name)
	}

	/**
	 * This method handles any mailings triggered by an event registration action
	 *
	 * @access  public
	 * @param   int  $register_id  Integer Registration record identifier
	 * @return  boolean
	 *
	 */
	public function onEventUserRegistered($register_id, $registration = false)
	{
		####################
		## DEFINING ARRAY ##
		####################

		$send_to = array(
			'user'     => $this->params->get('reg_mail_user', '1'),
			'admin'    => $this->params->get('reg_mail_admin', '0'),
			'creator'  => $this->params->get('reg_mail_creator', '0'),
			'category' => $this->params->get('reg_mail_category', '0'),
			'group'    => $this->params->get('reg_mail_group', '0'),
		);

		// skip if processing not needed
		if (!array_filter($send_to)) {
			return true;
		}

		$uri      = Uri::getInstance();
		$user     = JemFactory::getUser();
		$userid   = $user->get('id');
		$username = empty($this->_UseLoginName) ? $user->name : $user->username;

		// get data
		$db       = Factory::getContainer()->get('DatabaseDriver');
		$query    = $db->getQuery(true);

		$case_when  = ' CASE WHEN ';
		$case_when .= $query->charLength('a.alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('a.id');
		$case_when .= $query->concatenate(array($id, 'a.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('a.id', 'a.title', 'a.dates', 'a.times', 'a.locid', 'a.published', 'a.created', 'a.modified', 'a.created_by',
			'r.waiting', $case_when, 'r.uid', 'r.status', 'r.comment', 'r.places'));
		$query->select($query->concatenate(array('a.introtext', 'a.fulltext')).' AS text');
		$query->select(array('v.venue', 'v.city'));
		$query->from($db->quoteName('#__jem_register').' AS r');
		$query->join('INNER', '#__jem_events AS a ON r.event = a.id');
		$query->join('LEFT', '#__jem_venues AS v ON v.id = a.locid');
		$query->where(array('r.id= '.$db->quote($register_id)));

		$db->setQuery($query);
		if (is_null($event = $db->loadObject())) {
			return false;
		}

		// check if currrent user handles on behalf of
		$attendeeid = $event->uid;
		if ($attendeeid != $userid) {
			$attendee = JemFactory::getUser($attendeeid);
			$attendeename = empty($this->_UseLoginName) ? $attendee->name : $attendee->username;
		} else {
			$attendee = $user;
			$attendeename = $username;
		}

		//create link to event
		$link = JRoute::_($uri->root() . JEMHelperRoute::getEventRoute($event->slug), false);

		// Strip tags/scripts, etc. from description and comment
		$text_description = JFilterOutput::cleanText($event->text);
		$comment = empty($event->comment) ? false : JFilterOutput::cleanText($event->comment);

		$recipients = $this->_getRecipients($send_to, array('user'), $event->id, $event->created_by, $attendeeid);

		$waiting = $event->waiting ? '_WAITING' : '';

		#####################
		## SENDMAIL - USER ##
		#####################

		if (!empty($recipients['user'])) {
			$data = new stdClass();
			switch ($event->status) {
				case -1: // not attanding
					$txt_subject = 'PLG_JEM_MAILER_USER_REG_NOT_ATTEND_SUBJECT';
					if ($attendeeid != $userid) {
						$txt_body = 'PLG_JEM_MAILER_USER_REG_ONBEHALF_NOT_ATTEND_BODY_' . ($comment ? 'B' : 'A');
					} else {
						$txt_body = 'PLG_JEM_MAILER_USER_REG_NOT_ATTEND_BODY_' . ($comment ? 'A' : '9');
					}
					break;
				case  1: // attending
					$txt_subject = 'PLG_JEM_MAILER_USER_REG'.$waiting.'_SUBJECT';
					if ($attendeeid != $userid) {
						$txt_body = 'PLG_JEM_MAILER_USER_REG_ONBEHALF'.$waiting.'_BODY_' . ($comment ? 'B' : 'A');
					} else {
						$txt_body = 'PLG_JEM_MAILER_USER_REG'.$waiting.'_BODY_' . ($comment ? 'A' : '9');
					}
					break;
				default: // whatever
					if ($attendeeid != $userid) {
						$txt_subject = 'PLG_JEM_MAILER_USER_REG_INVITATION_SUBJECT';
						$txt_body = 'PLG_JEM_MAILER_USER_REG_INVITATION_BODY_' . ($comment ? 'B' : 'A');
					} else {
						$txt_subject = 'PLG_JEM_MAILER_USER_REG_UNKNOWN_SUBJECT';
						$txt_body = 'PLG_JEM_MAILER_USER_REG_UNKNOWN_BODY_' . ($comment ? 'A' : '9');
					}
					break;
			}
			$data->subject = Text::sprintf($txt_subject, $this->_SiteName);
			if ($attendeeid != $userid) {
				if ($comment) {
					$data->body = Text::sprintf($txt_body, $attendeename, $username, $comment, $event->title, $event->dates, $event->times, $event->venue, $event->city, ($event->status<0?$registration:$event->places), $text_description, $link, $this->_SiteName);
				} else {
					$data->body = Text::sprintf($txt_body, $attendeename, $username, $event->title, $event->dates, $event->times, $event->venue, $event->city, ($event->status<0?$registration:$event->places), $text_description, $link, $this->_SiteName);
				}
			} else {
				if ($comment) {
					$data->body = Text::sprintf($txt_body, $attendeename, $comment, $event->title, $event->dates, $event->times, $event->venue, $event->city, ($event->status<0?$registration:$event->places), $text_description, $link, $this->_SiteName);
				} else {
					$data->body = Text::sprintf($txt_body, $attendeename, $event->title, $event->dates, $event->times, $event->venue, $event->city, ($event->status<0?$registration:$event->places), $text_description, $link, $this->_SiteName);
				}
			}
			$data->receivers = $recipients['user'];
			$this->_mailer($data);
		}

		#############################
		## SENDMAIL - ALL THE REST ##
		#############################

		if (!empty($recipients['all'])) {
			$data = new stdClass();
			switch ($event->status) {
				case -1: // not attanding
					$txt_subject = 'PLG_JEM_MAILER_ADMIN_REG_NOT_ATTEND_SUBJECT';
					if ($attendeeid != $userid) {
						$txt_body = 'PLG_JEM_MAILER_ADMIN_REG_ONBEHALF_NOT_ATTEND_BODY_' . ($comment ? 'A' : '9');
					} else {
						$txt_body = 'PLG_JEM_MAILER_ADMIN_REG_NOT_ATTEND_BODY_' . ($comment ? '9' : '8');
					}
					break;
				case  1: // attending
					$txt_subject = 'PLG_JEM_MAILER_ADMIN_REG'.$waiting.'_SUBJECT';
					if ($attendeeid != $userid) {
						$txt_body = 'PLG_JEM_MAILER_ADMIN_REG_ONBEHALF'.$waiting.'_BODY_' . ($comment ? 'A' : '9');
					} else {
						$txt_body = 'PLG_JEM_MAILER_ADMIN_REG'.$waiting.'_BODY_' . ($comment ? '9' : '8');
					}
					break;
				default: // whatever
					if ($attendeeid != $userid) {
						$txt_subject = 'PLG_JEM_MAILER_ADMIN_REG_INVITATION_SUBJECT';
						$txt_body = 'PLG_JEM_MAILER_ADMIN_REG_INVITATION_BODY_' . ($comment ? 'A' : '9');
					} else {
						$txt_subject = 'PLG_JEM_MAILER_ADMIN_REG_UNKNOWN_SUBJECT';
						$txt_body = 'PLG_JEM_MAILER_ADMIN_REG_UNKNOWN_BODY_' . ($comment ? '9' : '8');
					}
					break;
			}
			$data->subject = Text::sprintf($txt_subject, $this->_SiteName);
			if ($attendeeid != $userid) {
				if ($comment) {
					$data->body = Text::sprintf($txt_body, $attendeename, $username, $comment, $event->title, $event->dates, $event->times, $event->venue, $event->city, ($event->status<0?$registration:$event->places), $link, $this->_SiteName);
				} else {
					$data->body = Text::sprintf($txt_body, $attendeename, $username, $event->title, $event->dates, $event->times, $event->venue, $event->city, ($event->status<0?$registration:$event->places), $link, $this->_SiteName);
				}
			} else {
				if ($comment) {
					$data->body = Text::sprintf($txt_body, $attendeename, $comment, $event->title, $event->dates, $event->times, $event->venue, $event->city, ($event->status<0?$registration:$event->places), $link, $this->_SiteName);
				} else {
					$data->body = Text::sprintf($txt_body, $attendeename, $event->title, $event->dates, $event->times, $event->venue, $event->city, ($event->status<0?$registration:$event->places), $link, $this->_SiteName);
				}
			}
			$data->recipients = $recipients['all'];
			$this->_mailer($data);
		}

		return true;
	}

	/**
	 * This method handles any mailings triggered by an attendees being bumped on/off waiting list
	 *
	 * @access public
	 * @param  int  $register_id  Integer Registration record identifier
	 * @return boolean
	 *
	 */
	public function onUserOnOffWaitinglist($register_id)
	{
		####################
		## DEFINING ARRAY ##
		####################

		$send_to = array(
			'user'     => $this->params->get('reg_mail_user_onoff', '1'),
			'admin'    => $this->params->get('reg_mail_admin_onoff', '0'),
			'creator'  => $this->params->get('reg_mail_creator_onoff', '0'),
			'category' => $this->params->get('reg_mail_category_onoff', '0'),
			'group'    => $this->params->get('reg_mail_group_onoff', '0'),
		);

		// skip if processing not needed
		if (!array_filter($send_to)) {
			return true;
		}

		$uri = Uri::getInstance();

		// get data
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);

		$case_when  = ' CASE WHEN ';
		$case_when .= $query->charLength('a.alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('a.id');
		$case_when .= $query->concatenate(array($id, 'a.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('a.id', 'a.title', 'a.dates', 'a.times', 'a.locid', 'a.published', 'a.created', 'a.modified', 'a.created_by',
			'r.waiting', $case_when, 'r.uid', 'r.status', 'r.comment', 'r.places'));
		$query->select($query->concatenate(array('a.introtext', 'a.fulltext')).' AS text');
		$query->select(array('v.venue', 'v.city'));
		$query->from($db->quoteName('#__jem_register').' AS r');
		$query->join('INNER', '#__jem_events AS a ON r.event = a.id');
		$query->join('LEFT', '#__jem_venues AS v ON v.id = a.locid');
		$query->where(array('r.id= '.$db->quote($register_id)));

		$db->setQuery($query);
		if (is_null($event = $db->loadObject())) {
			return false;
		}

		$attendee     = JemFactory::getUser($event->uid);
		$attendeename = empty($this->_UseLoginName) ? $attendee->name : $attendee->username;

		// create link to event
		$link = JRoute::_($uri->root() . JEMHelperRoute::getEventRoute($event->slug), false);

		// Strip tags/scripts, etc. from description
		$text_description = JFilterOutput::cleanText($event->text);

		$recipients = $this->_getRecipients($send_to, array('user'), $event->id, $event->created_by, $attendee->get('id'));

		#####################
		## SENDMAIL - USER ##
		#####################

		if (!empty($recipients['user'])) {
			$data            = new stdClass();
			$txt_subject     = $event->waiting ? 'PLG_JEM_MAILER_USER_REG_ON_WAITING_SUBJECT' : 'PLG_JEM_MAILER_USER_REG_ON_ATTENDING_SUBJECT';
			$data->subject   = Text::sprintf($txt_subject, $this->_SiteName);
			$txt_body        = $event->waiting ? 'PLG_JEM_MAILER_USER_REG_ON_WAITING_BODY_9' : 'PLG_JEM_MAILER_USER_REG_ON_ATTENDING_BODY_9';
			$data->body      = Text::sprintf($txt_body, $attendeename, $event->title, $event->dates, $event->times, $event->venue, $event->city, $event->places, $text_description, $link, $this->_SiteName);
			$data->receivers = $recipients['user'];
			$this->_mailer($data);
		}

		#############################
		## SENDMAIL - ALL THE REST ##
		#############################

		if (!empty($recipients['all'])) {
			$data             = new stdClass();
			$txt_subject      = $event->waiting ? 'PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_SUBJECT' : 'PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_SUBJECT';
			$data->subject    = Text::sprintf($txt_subject, $this->_SiteName);
			$txt_body         = $event->waiting ? 'PLG_JEM_MAILER_ADMIN_REG_ON_WAITING_BODY_8' : 'PLG_JEM_MAILER_ADMIN_REG_ON_ATTENDING_BODY_8';
			$data->body       = Text::sprintf($txt_body, $attendeename, $event->title, $event->dates, $event->times, $event->venue, $event->city, $event->places, $link, $this->_SiteName);
			$data->recipients = $recipients['all'];
			$this->_mailer($data);
		}

		return true;
	}

	/**
	 * This method handles any mailings triggered by an event unregister action
	 *
	 * @access public
	 * @param  int     $event_id      Integer Event identifier
	 * @param  object  $registration  Entry from register table deleted now (optional)
	 * @param  int     $register_id   Integer Registration record identifier (optional)
	 * @return boolean
	 *
	 */
	public function onEventUserUnregistered($event_id, $registration = false, $register_id = 0)
	{
		####################
		## DEFINING ARRAY ##
		####################

		$send_to = array(
			'user'     => $this->params->get('unreg_mail_user', '1'),
			'admin'    => $this->params->get('unreg_mail_admin', '0'),
			'creator'  => $this->params->get('unreg_mail_creator', '0'),
			'category' => $this->params->get('unreg_mail_category', '0'),
			'group'    => $this->params->get('unreg_mail_group', '0'),
		);

		// skip if processing not needed
		if (!array_filter($send_to)) {
			return true;
		}

		$uri = Uri::getInstance();

		$user     = JemFactory::getUser();
		$userid   = $user->get('id');
		$username = empty($this->_UseLoginName) ? $user->name : $user->username;

		// get data
		$db       = Factory::getContainer()->get('DatabaseDriver');
		$query	  = $db->getQuery(true);

		$case_when  = ' CASE WHEN ';
		$case_when .= $query->charLength('a.alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('a.id');
		$case_when .= $query->concatenate(array($id, 'a.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('a.id', 'a.title', 'a.dates', 'a.times', 'a.locid', 'a.published', 'a.created', 'a.modified', 'a.created_by', $case_when));
		$query->select($query->concatenate(array('a.introtext', 'a.fulltext')).' AS text');
		$query->select(array('v.venue', 'v.city'));
		if (empty($registration) && ((int)$register_id > 0)) {
			$query->select(array('r.uid', 'r.status', 'r.waiting', 'r.comment', 'r.places'));
			$query->from($db->quoteName('#__jem_register').' AS r');
			$query->join('INNER', '#__jem_events AS a ON r.event = a.id');
			$query->join('LEFT', '#__jem_venues AS v ON v.id = a.locid');
			$query->where(array('r.id= '.$db->quote($register_id)));
		} else {
			$query->from($db->quoteName('#__jem_events').' AS a');
			$query->join('LEFT', '#__jem_venues AS v ON v.id = a.locid');
			$query->where(array('a.id = '.$db->quote($event_id)));
		}

		$db->setQuery($query);
		if (is_null($event = $db->loadObject())) {
			return false;
		}

		if (empty($registration)) {
			$registration = $event;
		}

		// check if currrent user handles on behalf of
		$attendeeid = (!empty($registration->uid) ? $registration->uid : $userid);
		if ($attendeeid != $userid) {
			$attendee = JemFactory::getUser($attendeeid);
			$attendeename = empty($this->_UseLoginName) ? $attendee->name : $attendee->username;
		} else {
			$attendee = $user;
			$attendeename = $username;
		}

		// create link to event
		$link = JRoute::_($uri->root() . JEMHelperRoute::getEventRoute($event->slug), false);

		// Strip tags/scripts, etc. from description
		$text_description = JFilterOutput::cleanText($event->text);
		$comment = empty($event->comment) ? false : JFilterOutput::cleanText($event->comment);

		$recipients = $this->_getRecipients($send_to, array('user'), $event->id, $event->created_by, $attendeeid);

		#####################
		## SENDMAIL - USER ##
		#####################

		if (!empty($recipients['user'])) {
			$data            = new stdClass();
			$data->subject   = Text::sprintf('PLG_JEM_MAILER_USER_UNREG_SUBJECT', $this->_SiteName);
			if ($attendeeid != $userid) {
				if ($comment) {
					$data->body  = Text::sprintf('PLG_JEM_MAILER_USER_UNREG_ONBEHALF_BODY_B', $attendeename, $username, $comment, $event->title, $event->dates, $event->times, $event->venue, $event->city, $registration->places, $text_description, $link, $this->_SiteName);
				} else {
					$data->body  = Text::sprintf('PLG_JEM_MAILER_USER_UNREG_ONBEHALF_BODY_A', $attendeename, $username, $event->title, $event->dates, $event->times, $event->venue, $event->city, $registration->places, $text_description, $link, $this->_SiteName);
				}
			} else {
				if ($comment) {
					$data->body  = Text::sprintf('PLG_JEM_MAILER_USER_UNREG_BODY_A', $username, $comment, $event->title, $event->dates, $event->times, $event->venue, $event->city, $registration->places, $text_description, $link, $this->_SiteName);
				} else {
					$data->body  = Text::sprintf('PLG_JEM_MAILER_USER_UNREG_BODY_9', $username, $event->title, $event->dates, $event->times, $event->venue, $event->city, $registration->places, $text_description, $link, $this->_SiteName);
				}
			}
			$data->receivers = $recipients['user'];
			$this->_mailer($data);
		}

		#############################
		## SENDMAIL - ALL THE REST ##
		#############################

		if (!empty($recipients['all'])) {
			$data             = new stdClass();
			$data->subject    = Text::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_SUBJECT', $this->_SiteName);
			if ($attendeeid != $userid) {
				if ($comment) {
					$data->body   = Text::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_ONBEHALF_BODY_A', $attendeename, $username, $comment, $event->title, $event->dates, $event->times, $event->venue, $event->city, $registration->places, $link, $this->_SiteName);
				} else {
					$data->body   = Text::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_ONBEHALF_BODY_9', $attendeename, $username, $event->title, $event->dates, $event->times, $event->venue, $event->city, $registration->places, $link, $this->_SiteName);
				}
			} else {
				if ($comment) {
					$data->body   = Text::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_BODY_9', $username, $comment, $event->title, $event->dates, $event->times, $event->venue, $event->city, $registration->places, $link, $this->_SiteName);
				} else {
					$data->body   = Text::sprintf('PLG_JEM_MAILER_ADMIN_UNREG_BODY_8', $username, $event->title, $event->dates, $event->times, $event->venue, $event->city, $registration->places, $link, $this->_SiteName);
				}
			}
			$data->recipients = $recipients['all'];
			$this->_mailer($data);
		}

		return true;
	}

	/**
	 * This method handles any mailings triggered by change of publishing state of events or venues.
	 *
	 * @access  public
	 * @param   string $context  The context, i.e. 'com_jem.event'
	 * @param   array  $ids      Array of Event or Venue identifiers
	 * @param   int    $value    Publishing state ('publish' => 1, 'unpublish' => 0, 'archive' => 2, 'trash' => -2)
	 * @return  boolean
	 */
	public function onContentChangeState($context, $ids, $value)
	{
		JemHelper::addLogEntry('context: ' . $context . ', ids: (' . implode(',', $ids) . '), value: ' . $value, __METHOD__, JLog::DEBUG);

		$ids = (array) $ids;
		list($component, $item) = explode('.', $context);
		if (($component === 'com_jem') && ($item === 'event')) {
			foreach ($ids as $id) {
				$this->onEventEdited($id, false);
			}
		} elseif (($component === 'com_jem') && ($item === 'venue')) {
			foreach ($ids as $id) {
				$this->onVenueEdited($id, false);
			}
		}

		return true;
	}

	/**
	 * This method handles any mailings triggered by an event store action
	 *
	 * @access  public
	 * @param   int  $event_id  Event identifier
	 * @param   int  $is_new    Event new or edited
	 * @return  boolean
	 *
	 */
	public function onEventEdited($event_id, $is_new)
	{
		####################
		## DEFINING ARRAY ##
		####################

		$send_to = array(
			'user'       => $is_new ? $this->params->get('newevent_mail_user', '1') : $this->params->get('editevent_mail_user', '1'),
			'admin'      => $is_new ? $this->params->get('newevent_mail_admin', '0') : $this->params->get('editevent_mail_admin', '0'),
			'creator'    => !$is_new && $this->params->get('editevent_mail_creator', '0'),
			'registered' => !$is_new && $this->params->get('editevent_mail_registered', '0'),
			'category'   => $is_new ? $this->params->get('newevent_mail_category', '0') : $this->params->get('editevent_mail_category', '0'),
			'category_acl'   => $is_new ? $this->params->get('newevent_mail_category_acl', '0') : $this->params->get('editevent_mail_category_acl', '0'),
			'group'      => $is_new ? $this->params->get('newevent_mail_group', '0') : $this->params->get('editevent_mail_group', '0'),
		);

		// skip if processing not needed
		if (!array_filter($send_to)) {
			return true;
		}

		$uri = Uri::getInstance();

		$user     = JemFactory::getUser();
		$userid   = $user->get('id');
		$username = empty($this->_UseLoginName) ? $user->name : $user->username;

		// get data
		$db       = Factory::getContainer()->get('DatabaseDriver');
		$query    = $db->getQuery(true);

		$case_when  = ' CASE WHEN ';
		$case_when .= $query->charLength('a.alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('a.id');
		$case_when .= $query->concatenate(array($id, 'a.alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('a.id', 'a.title', 'a.dates', 'a.times', 'a.locid', 'a.published', 'a.created', 'a.modified', 'a.created_by'));
		$query->select($query->concatenate(array('a.introtext', 'a.fulltext')).' AS text');
		$query->select(array('v.venue', 'v.city'));
		$query->select($case_when);
		$query->from($db->quoteName('#__jem_events').' AS a');
		$query->join('LEFT', '#__jem_venues AS v ON v.id = a.locid');
		$query->where(array('a.id = '.$db->quote($event_id)));

		$db->setQuery($query);
		if (is_null($event = $db->loadObject())) {
			return false;
		}

		// Link for event
		$link = JRoute::_($uri->root() . JEMHelperRoute::getEventRoute($event->slug), false);

		// Strip tags/scripts, etc. from description
		$text_description = JFilterOutput::cleanText($event->text);

		// Define published-state message
		switch ($event->published) {
			case 1:
				$adminstate = Text::sprintf('PLG_JEM_MAILER_EVENT_PUBLISHED', $link);
				$userstate = Text::sprintf('PLG_JEM_MAILER_USER_MAIL_EVENT_PUBLISHED', $link);
				break;
			case -2:
				$adminstate = Text::_('PLG_JEM_MAILER_EVENT_TRASHED');
				$userstate = Text::_('PLG_JEM_MAILER_USER_MAIL_EVENT_TRASHED');
				break;
			case 0:
				$adminstate = Text::_('PLG_JEM_MAILER_EVENT_UNPUBLISHED');
				$userstate = Text::_('PLG_JEM_MAILER_USER_MAIL_EVENT_UNPUBLISHED');
				break;
			case 2:
				$adminstate = Text::_('PLG_JEM_MAILER_EVENT_ARCHIVED');
				$userstate = Text::_('PLG_JEM_MAILER_USER_MAIL_EVENT_ARCHIVED');
				break;
			default: /* TODO: fallback unknown / undefined */
				$adminstate = Text::_('PLG_JEM_MAILER_EVENT_UNKNOWN');
				$userstate = Text::_('PLG_JEM_MAILER_USER_MAIL_EVENT_UNKNOWN');
				break;
		}

		$recipients = $this->_getRecipients($send_to, array('user'), $event->id, ($event->created_by != $userid) ? $event->created_by : 0, $userid);

		if ($event->modified == 0) {  //when state switches modified date is not updated
			$event->modified = 'now'; //set to now to avoid confusing email message
		}
		#####################
		## SENDMAIL - USER ##
		#####################

		if (!empty($recipients['user'])) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date($event->created, Text::_('DATE_FORMAT_LC2'));
				$data->subject = Text::sprintf('PLG_JEM_MAILER_NEW_USER_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = Text::sprintf('PLG_JEM_MAILER_USER_MAIL_NEW_EVENT_9', $username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $userstate);
			} else {
				$modified = JHtml::Date($event->modified, Text::_('DATE_FORMAT_LC2'));
				$data->subject = Text::sprintf('PLG_JEM_MAILER_EDIT_USER_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = Text::sprintf('PLG_JEM_MAILER_USER_MAIL_EDIT_EVENT_9', $username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $userstate);
			}

			$data->receivers = $recipients['user'];
			$this->_mailer($data);
		}

		#############################
		## SENDMAIL - ALL THE REST ##
		#############################

		if (!empty($recipients['all'])) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date($event->created, Text::_('DATE_FORMAT_LC2'));
				$data->subject = Text::sprintf('PLG_JEM_MAILER_NEW_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = Text::sprintf('PLG_JEM_MAILER_NEW_EVENT_9', $username, $created, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			} else {
				$modified = JHtml::Date($event->modified, Text::_('DATE_FORMAT_LC2'));
				$data->subject = Text::sprintf('PLG_JEM_MAILER_EDIT_EVENT_MAIL', $this->_SiteName, $event->title);
				$data->body = Text::sprintf('PLG_JEM_MAILER_EDIT_EVENT_9', $username, $modified, $event->title, $event->dates, $event->times, $event->venue, $event->city, $text_description, $adminstate);
			}

			$data->recipients = $recipients['all'];
			$this->_mailer($data);
		}

		return true;
	}

	/**
	 * This method handles any mailings triggered by an venue store action
	 *
	 * @access  public
	 * @param   int  $venue_id  Integer Venue identifier
	 * @param   int  $is_new    Integer Venue new or edited
	 * @return  boolean
	 *
	 */
	public function onVenueEdited($venue_id, $is_new)
	{
		// Sendto
		$send_to = array(
			'user'       => $is_new ? $this->params->get('newvenue_mail_user', '1') : $this->params->get('editvenue_mail_user', '0'),
			'admin'      => $is_new ? $this->params->get('newvenue_mail_admin', '1') : $this->params->get('editvenue_mail_admin', '0'),
			'creator'    => !$is_new && $this->params->get('editvenue_mail_creator', '0'),
			'ev-creator' => !$is_new && $this->params->get('editvenue_mail_ev-creator', '0'),
			'registered' => !$is_new && $this->params->get('editvenue_mail_registered', '0'),
			'category'   => !$is_new && $this->params->get('editvenue_mail_category', '0'),
			'group'      => !$is_new && $this->params->get('editvenue_mail_group', '0'),
		);

		// Skip if processing not needed
		if (!array_filter($send_to)) {
			return true;
		}

		$uri = Uri::getInstance();

		$user     = JemFactory::getUser();
		$userid   = $user->get('id');
		$username = empty($this->_UseLoginName) ? $user->name : $user->username;

		// get data
		$db     = Factory::getContainer()->get('DatabaseDriver');
		$query	= $db->getQuery(true);

		$case_when = ' CASE WHEN ';
		$case_when .= $query->charLength('alias');
		$case_when .= ' THEN ';
		$id = $query->castAsChar('id');
		$case_when .= $query->concatenate(array($id, 'alias'), ':');
		$case_when .= ' ELSE ';
		$case_when .= $id.' END as slug';

		$query->select(array('id', 'published', 'venue', 'city', 'street', 'postalCode', 'url', 'country', 'locdescription', 'created', 'created_by', 'modified' ,$case_when));
		$query->from('#__jem_venues');
		$query->where(array('id = '.$db->quote($venue_id)));

		$db->setQuery($query);
		if (is_null($venue = $db->loadObject())) {
			return false;
		}

		# at this point we do have a result

		// Define link for venue
		$link = JRoute::_($uri->root().JEMHelperRoute::getVenueRoute($venue->slug), false);

		// Define published-state message
		$adminstate = $venue->published ? Text::sprintf('PLG_JEM_MAILER_VENUE_PUBLISHED', $link) : Text::_('PLG_JEM_MAILER_VENUE_UNPUBLISHED');
		$userstate = $venue->published ? Text::sprintf('PLG_JEM_MAILER_USER_MAIL_VENUE_PUBLISHED', $link) : Text::_('PLG_JEM_MAILER_USER_MAIL_VENUE_UNPUBLISHED');

		// Strip tags/scripts,etc from description
		$text_description = JFilterOutput::cleanText($venue->locdescription);

		$recipients = $this->_getRecipients($send_to, array('user'), 0, ($venue->created_by != $userid) ? $venue->created_by : 0, $userid, $venue_id);
		if ($venue->modified == 0) {  //when state switches modified date is not updated
			$venue->modified = 'now'; //set to now to avoid confusing email message
		}
		#####################
		## SENDMAIL - USER ##
		#####################

		# here we selected the option to send to a logged in user
		# we make a selection between added/edited venue

		if (!empty($recipients['user'])) {
			$data = new stdClass();

			if ($is_new) {
				$created = JHtml::Date($venue->created, Text::_('DATE_FORMAT_LC2'));
				$data->subject = Text::sprintf('PLG_JEM_MAILER_NEW_USER_VENUE_MAIL', $this->_SiteName, $venue->venue);
				$data->body = Text::sprintf('PLG_JEM_MAILER_USER_MAIL_NEW_VENUE_A', $username, $created, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $userstate);
			} else {
				$modified = JHtml::Date($venue->modified, Text::_('DATE_FORMAT_LC2'));
				$data->subject = Text::sprintf('PLG_JEM_MAILER_EDIT_USER_VENUE_MAIL', $this->_SiteName, $venue->venue);
				$data->body = Text::sprintf('PLG_JEM_MAILER_USER_MAIL_EDIT_VENUE_A', $username, $modified, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $userstate);
			}

			$data->receivers = $recipients['user'];
			$this->_mailer($data);
		}

		#############################
		## SENDMAIL - ALL THE REST ##
		#############################

		if (!empty($recipients['all'])) {
			$data = new stdClass();

			# is the venue new or edited?
			if ($is_new) {
				# the venue is new and we send a mail to adminDBList
				$created = JHtml::Date($venue->created, Text::_('DATE_FORMAT_LC2'));
				$data->subject = Text::sprintf('PLG_JEM_MAILER_NEW_VENUE_MAIL', $this->_SiteName, $venue->venue);
				$data->body = Text::sprintf('PLG_JEM_MAILER_NEW_VENUE_A', $username, $created, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $adminstate);
			} else {
				# the venue is edited and we send a mail to adminDBList
				$modified = JHtml::Date($venue->modified, Text::_('DATE_FORMAT_LC2'));
				$data->subject = Text::sprintf('PLG_JEM_MAILER_EDIT_VENUE_MAIL', $this->_SiteName, $venue->venue);
				$data->body = Text::sprintf('PLG_JEM_MAILER_EDIT_VENUE_A', $username, $modified, $venue->venue, $venue->url, $venue->street, $venue->postalCode, $venue->city, $venue->country, $text_description, $adminstate);
			}

			$data->recipients = $recipients['all'];
			$this->_mailer($data);
		}

		return true;
	}

	/**
	 * Returns array of all the different email recipients.
	 */
	private function _getRecipients(array $send_to, array $skip, $eventid, $creatorid, $userid, $venueid = 0)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		######################
		## RECEIVERS - USER ##
		######################

		# in here we selected the option to send an email to the logged-in user

		if (!empty($send_to['user'])) {
			$user = JemFactory::getUser($userid);
			$recipients['user'] = array($user->email);
		} else {
			$recipients['user'] = false;
		}

		#########################
		## RECEIVERS - CREATOR ##
		#########################

		# in here we selected the option to send an email to the event's creator if different from editor.

		if (!empty($send_to['creator'])) {
			// get data
			$query = $db->getQuery(true);
			$query->select(array('u.email'));
			$query->from($db->quoteName('#__users').' AS u');
			$query->where('u.block = 0');
			$query->where(array('u.id = '.$db->quote($creatorid)));

			$db->setQuery($query);
			if (is_null($recipients['creator'] = $db->loadColumn(0))) {
				$recipients['creator'] = false;
			} else {
				$recipients['creator'] = array_unique($recipients['creator']);
			}
		} else {
			$recipients['creator'] = false;
		}

		###############################
		## RECEIVERS - EVENT CREATOR ##
		###############################

		# in here we selected the option to send an email to the creator of all events attached to changed venue.

		if (!empty($send_to['ev-creator'])) {
			// get data
			$query = $db->getQuery(true);
			$query->select(array('u.email'));
			$query->from($db->quoteName('#__users').' AS u');
			$query->where('u.block = 0');
			if (!empty($venueid)) {
				$query->join('INNER', '#__jem_events AS a ON a.locid = ' . $db->quote($venueid) . ' AND a.created_by = u.id');
			} else {
				$query->where('0');
			}

			$db->setQuery($query);
			if (is_null($recipients['ev-creator'] = $db->loadColumn(0))) {
				$recipients['ev-creator'] = false;
			} else {
				$recipients['ev-creator'] = array_unique($recipients['ev-creator']);
			}
		} else {
			$recipients['ev-creator'] = false;
		}

		#######################
		## RECEIVERS - ADMIN ##
		#######################

		# in here we selected the option to send to admin.
		# we selected admin so we can use the adminDBList.

		if (!empty($send_to['admin'])) {
			$recipients['admin'] = array_unique($this->_AdminDBList);
		} else {
			$recipients['admin'] = false;
		}

		############################
		## RECEIVERS - REGISTERED ##
		############################

		# in here we selected the option to send an email to all people registered to the event.
		# there is no check for the waitinglist

		if (!empty($send_to['registered'])) {
			# get data
			$query = $db->getQuery(true);
			$query->select(array('u.email'));
			$query->from($db->quoteName('#__users').' AS u');
			$query->where('u.block = 0');
			$query->join('INNER', '#__jem_register AS reg ON reg.uid = u.id');
			if (!empty($eventid)) {
				$query->join('INNER', '#__jem_events AS a ON reg.event = a.id');
				$query->where('reg.event= '.$db->quote($eventid));
				$query->where('a.published = 1');
			} elseif (!empty($venueid)) {
				$query->join('INNER', '#__jem_events AS a ON a.locid = ' . $db->quote($venueid) . ' AND reg.event = a.id');
				$query->join('LEFT', '#__jem_venues AS l ON a.locid = l.id');
				$query->where('a.published = 1');
				$query->where('l.published = 1');
			} else {
				$query->where('0');
			}

			# since 2.1.6/7 there is a registration status but we will ignore it here
			#  because it maybe usefull for "non-attendees" too to get information about changes, maybe they will attend now...

			# inform attendees only if event had not finished since one or more hours
			$query->where('((a.dates IS NULL) OR (TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(IFNULL(a.enddates, a.dates), " ", IFNULL(a.endtimes, "23:59:59"))) > -60))');

			$db->setQuery($query);
			if (is_null($recipients['registered'] = $db->loadColumn(0))) {
				return array();
			} else {
				$recipients['registered'] = array_unique($recipients['registered']);
			}
		} else {
			$recipients['registered'] = false;
		}

		##########################
		## RECEIVERS - CATEGORY ##
		##########################

		# in here we selected the option to send an email to the email-address
		# that's filled in the category-view.

		# the data within categoryDBList needs to be validated.
		# if the categoryDBList is empty we shoudln't send an email

		if (!empty($send_to['category'])) {
			// get data
			$query = $db->getQuery(true);
			$query->select(array('c.email'));
			$query->from($db->quoteName('#__jem_categories').' AS c');
			$query->join('INNER', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');
			if (!empty($eventid)) {
				$query->where('rel.itemid = '.$db->quote($eventid));
			} elseif (!empty($venueid)) {
				$query->join('INNER', '#__jem_events AS a ON a.locid = ' . $db->quote($venueid) . ' AND rel.itemid = a.id');
			} else {
				$query->where('0');
			}

			$db->setQuery($query);
			if (is_null($category_receivers = $db->loadColumn(0))) {
				return array();
			} else {
				$recipients['category'] = array_unique($this->categoryDBList($category_receivers));
			}
		} else {
			$recipients['category'] = false;
		}

		##############################
		## RECEIVERS - CATEGORY ACL ##
		##############################

		# in here we selected the option to send an email to the email-address
		# that's filled in the category-view.

		# the data within categoryDBList needs to be validated.
		# if the categoryDBList is empty we shoudln't send an email

		if (!empty($send_to['category_acl'])) {
			// get list groups associated of ACL category from event
			$query = $db->getQuery(true);
			$query->select(array('vl.rules'));
			$query->from($db->quoteName('#__jem_cats_event_relations').' AS cer');
			$query->join('INNER', '#__jem_categories AS cat ON cat.id = cer.catid');
			$query->join('INNER', '#__viewlevels AS vl ON vl.id = cat.access');
			$query->where('cer.itemid = '.$db->quote($eventid));
			$query->where('cat.emailacljl = 1');
			$db->setQuery($query);
			$list_groups_jl = $db->loadResult();

			//List user emails of groups list
			if($list_groups_jl) {
			$list_groups_jl = substr ($list_groups_jl, 1, -1);
			$query = $db->getQuery(true);
			$query->select(array('u.email'));
			$query->from($db->quoteName('#__user_usergroup_map').' AS um');
			$query->join('INNER', '#__users AS u ON u.id = um.user_id');
			$query->where('um.group_id IN ('.$list_groups_jl.')');
			$query->where('u.block = 0');
			$db->setQuery($query);
			if (is_null($category_acl_receivers = $db->loadColumn(0))) {
				return array();
			} else {
				$recipients['category_acl'] = array_unique($category_acl_receivers);
			}
			}else{
				$recipients['category_acl'] = false;
			}
		} else {
			$recipients['category_acl'] = false;
		}

		#######################
		## RECEIVERS - GROUP ##
		#######################

		# in here we selected the option to send an email to the email-address
		# of the users within the maintainer-group of the category where
		# the event is assigned too.

		if (!empty($send_to['group'])) {
			// get data
			$query = $db->getQuery(true);
			$query->select(array('u.email'));
			$query->from($db->quoteName('#__users').' AS u');
			$query->where('u.block = 0');
			$query->join('INNER', '#__jem_groupmembers AS gm ON gm.member = u.id');
			$query->join('INNER', '#__jem_categories AS c ON c.groupid = gm.group_id');
			$query->join('INNER', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');
			if (!empty($eventid)) {
				$query->where('rel.itemid = '.$db->quote($eventid));
			} elseif (!empty($venueid)) {
				$query->join('INNER', '#__jem_events AS a ON a.locid = ' . $db->quote($venueid) . ' AND rel.itemid = a.id');
			} else {
				$query->where('0');
			}

			$db->setQuery($query);
			if (is_null($recipients['group'] = $db->loadColumn(0))) {
				return array();
			} else {
				$recipients['group'] = array_unique($recipients['group']);
			}
		} else {
			$recipients['group'] = false;
		}

		foreach ($recipients as $k => $v) {
			if (empty($v) || array_search($k, $skip) !== false) continue;
			foreach ($v as $email) {
				$recipients['all'][$email][] = $k;
			}
		}
		return $recipients;
	}

	/**
	 * This method executes and send the mail
	 * info: https://docs.joomla.org/Sending_email_from_extensions
	 *
	 * @access  private
	 * @param   object  $data  mail data object
	 * @return  boolean
	 */
	private function _mailer($data)
	{
		$app  = Factory::getApplication();
		$user = JemFactory::getUser();
		$sent = array('ok' => 0, 'failed' => 0);

		# $data->receivers contains single or array of email addresses
		if (isset($data->receivers)) {
			$receivers = is_array($data->receivers) ? $data->receivers : array($data->receivers);

			# remove empty fields and duplicates
			$receivers	= array_filter($receivers);
			$receivers	= array_unique($receivers);

			if ($receivers) {
				foreach ($receivers as $receiver)
				{
					$ret = $this->_send($receiver, $data->subject, $data->body);
					++$sent[$ret ? 'ok' : 'failed'];
				}

				# show a message if something failed and user is at least event editor
				if (!empty($sent['failed']) && $user->can('edit', 'event')) {
					$app->enqueueMessage(Text::sprintf('PLG_JEM_MAILER_MAILS_NOT_SENT_1', $sent['failed']), 'notice');
				}
			}

			return true;
		}
		# $data->recipients contains email addresses as array keys with cause(s) as value
		elseif (isset($data->recipients) && is_array($data->recipients)) {
			$txt_because = array(
				'admin'      => Text::_('PLG_JEM_MAILER_RECIPIENT_BECAUSE_ADMIN'),
				'creator'    => Text::_('PLG_JEM_MAILER_RECIPIENT_BECAUSE_ITEM_CREATOR'),
				'ev-creator' => Text::_('PLG_JEM_MAILER_RECIPIENT_BECAUSE_EVENT_CREATOR'),
				'group'      => Text::_('PLG_JEM_MAILER_RECIPIENT_BECAUSE_GROUP_MEMBER'),
				'category'   => Text::_('PLG_JEM_MAILER_RECIPIENT_BECAUSE_CATEGORY_LISTED'),
				'registered' => Text::_('PLG_JEM_MAILER_RECIPIENT_BECAUSE_ATTENDEE')
			);

			# for all recipients...
			#  key is the email address
			#  value is an array of roles which cause this user to get this email
			foreach ($data->recipients as $receiver => $causes)
			{
				# collect why tis user gets this email
				$why = array();
				foreach ($causes as $cause) {
					if (array_key_exists($cause, $txt_because)) {
						$why[] = $txt_because[$cause];
					}
				}

				$body = $data->body;
				if (!empty($why)) {
					$body .= Text::sprintf('PLG_JEM_MAILER_RECIPIENT_BECAUSE_1', implode(', ', $why));
				}

				$ret = $this->_send($receiver, $data->subject, $body);
				++$sent[$ret ? 'ok' : 'failed'];
			}

			# show a message if something failed and user is at least event editor
			if (!empty($sent['failed']) && $user->can('edit', 'event')) {
				$app->enqueueMessage(Text::sprintf('PLG_JEM_MAILER_MAILS_NOT_SENT_1', $sent['failed']), 'notice');
			}

			return true;
		}
		else {
			return false;
		}
	}

	/**
	 * This method sends the mail
	 * info: https://docs.joomla.org/Sending_email_from_extensions
	 *
	 * @access  private
	 * @param   string  $recipient  mail recipient
	 * @param   string  $subject    mail subject
	 * @param   string  $body       mail body
	 * @return  boolean true on success, false on error
	 */
	private function _send($recipient, $subject, $body)
	{
		$result = false;

		try {
			$mail = Factory::getMailer();
			//	$mail->set('exceptions', false);
			$mail->setSender(array($this->_MailFrom, $this->_FromName));
			$mail->setSubject($subject);

			# check if we did select the option to output html mail
			if ($this->params->get('send_html','0')== 1) {
				$mail->isHTML(true);
				$mail->Encoding = 'base64';
				$body_html = nl2br ($body);
				$mail->setBody($body_html);
			} else {
				$mail->setBody($body);
			}
			$mail->addRecipient($recipient);
			$ret = $mail->send();
			// Check for an error
			if ($ret instanceof Exception) {
				JemHelper::addLogEntry(Text::sprintf('PLG_JEM_MAILER_LOG_SEND_ERROR', $recipient) . ' : ' . $ret->getMessage(), __METHOD__ . '#' . __LINE__, JLog::WARNING);
			}
			elseif (empty($ret)) {
				JemHelper::addLogEntry(Text::sprintf('PLG_JEM_MAILER_LOG_SEND_ERROR', $recipient), __METHOD__ . '#' . __LINE__, JLog::WARNING);
			}
			else {
				$result = true;
			}
		}
		catch (Exception $e) {
			JemHelper::addLogEntry(Text::sprintf('PLG_JEM_MAILER_LOG_SEND_ERROR', $recipient) . ' : ' . $e->getMessage(), __METHOD__ . '#' . __LINE__, JLog::WARNING);
		}

		return $result;
	}

	/**
	 * This method assembles the adminDBList
	 */
	private function Adminlist()
	{
		$admin_receiver = $this->params->get('admin_receivers');
		$additional_mails	=(!empty($admin_receiver) ? (array_filter(explode(',', ($admin_receiver  ? trim($admin_receiver) : $admin_receiver)))) :array()) ;
		// remove whitespaces around each entry, then check if valid email address
		foreach ($additional_mails as $k => $v) {
			$additional_mails[$k] = filter_var(trim($v), FILTER_VALIDATE_EMAIL);
		}
		$additional_mails	= array_filter($additional_mails);

		if ($this->params->get('fetch_admin_mails', '0')) {

			// get data
			$db     = Factory::getContainer()->get('DatabaseDriver');
			$query	= $db->getQuery(true);

			$query->select(array('u.id','u.email','u.name'));
			$query->from($db->quoteName('#__users').' AS u');
			$query->where(array('u.sendEmail = 1'));

			$db->setQuery($query);

			if ($db->execute() === false) {
				Factory::getApplication()->enqueueMessage($db->stderr(true), 'error');
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
			$CategoryDBList = array();
		}

		return $CategoryDBList;
	}
}
?>
