<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\User\User;

require_once __DIR__ . '/../classes/onlinemeeting.class.php';

/** Private, read-only gateway; a public route is never itself a meeting credential. */
class JemControllerOnlinemeeting extends BaseController
{
    /** @return void Recheck event access and current confirmation before redirecting to the provider. */
    public function join(): void
    {
        $app = Factory::getApplication();
        JemOnlineMeeting::privateResponse();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $id = $app->input->getInt('id');
        if ($id < 1) {
            throw new RuntimeException(Text::_('COM_JEM_ONLINE_MEETING_ACCESS_DENIED'), 403);
        }
        $user = $app->getIdentity();
        $row = null;
        $external = false;
        $registrationId = $app->input->getInt('registration_id');
        if ($registrationId > 0) {
            $row = $db->setQuery('SELECT * FROM #__jem_register WHERE id=' . $registrationId)->loadObject();
            $holder = $app->getSession()->get('com_jem.invitation.holder', []);
            $holderValid = ($holder['until'] ?? 0) >= time()
                && in_array($registrationId, $holder['ids'] ?? [], true);
            $external = $row && (int) $row->uid === 0 && (int) $row->event === $id
                && ($holderValid || JemOnlineMeeting::verifyGuest($row, $app->input->getString('meeting_key', ''),
                    (string) $app->get('secret', '')));
            if (!$external) {
                throw new RuntimeException(Text::_('COM_JEM_ONLINE_MEETING_ACCESS_DENIED'), 403);
            }
            // An external email link cannot borrow permissions from another logged-in account.
            $user = new User(0);
        }
        $originalIdentity = $app->getIdentity();
        $originalSessionUser = $app->getSession()->get('user');
        try {
            if ($external) {
                $app->loadIdentity($user);
                $app->getSession()->set('user', $user);
            }
            $model = $this->getModel('Event', 'JemModel', ['ignore_request' => true]);
            $event = $model->getItem($id);
        } finally {
            $app->loadIdentity($originalIdentity);
            $app->getSession()->set('user', $originalSessionUser);
        }
        if (!$external && $event) {
            $row = JemOnlineMeeting::booking($id, (int) $user->id);
        }
        if (!$event || !JemOnlineMeeting::allowed($event, $user, $row,
            (bool) $event->params->get('access-view', false), $external)
            || (int) $event->params->get('event_show_online_meeting', 1) !== 1) {
            throw new RuntimeException(Text::_('COM_JEM_ONLINE_MEETING_ACCESS_DENIED'), 403);
        }
        $linkId = $app->input->getInt('link_id');
        $url = $linkId > 0
            ? $db->setQuery("SELECT url FROM #__jem_links WHERE type='online' AND state=1 AND event_id=" . $id . ' AND id=' . $linkId)->loadResult()
            : $db->setQuery('SELECT online_meeting_url FROM #__jem_events WHERE id=' . $id)->loadResult();
        if (!$linkId && trim((string) $url) === '') {
            $url = $db->setQuery("SELECT url FROM #__jem_links WHERE type='online' AND state=1 AND event_id="
                . $id . ' ORDER BY ordering,id', 0, 1)->loadResult();
        }
        $url = JemHelper::getOnlineMeetingUrl((object) ['online_meeting_url' => (string) $url]);
        if ($url === '') {
            throw new RuntimeException(Text::_('COM_JEM_ONLINE_MEETING_ACCESS_DENIED'), 403);
        }
        $this->setRedirect($url);
    }
}
