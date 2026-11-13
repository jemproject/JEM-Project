<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/invitationservice.class.php';

/** Shared, non-cached FE/BE composer presentation. */
trait JemInvitationView
{
    public array $invitationData = [];

    /** @return void Load authorised events and the last server-side preview. */
    protected function prepareInvitations(): void
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $actor = $app->getIdentity();
        $service = new JemInvitationService($db);
        $events = $service->events($actor);
        if (!$events) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $key = 'com_jem.invitation.' . $app->getName();
        $form = $app->getSession()->get($key . '.form', []);
        $id = $app->input->getInt('event_id', (int) ($form['event_id'] ?? 0));
        $event = null;
        foreach ($events as $candidate) {
            if ((int) $candidate->id === $id) {
                $event = $candidate;
            }
        }
        if ($id && !$event) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        if ((int) ($form['event_id'] ?? 0) !== $id) {
            $form = ['event_id' => $id, 'quantity' => 1, 'kind' => 'reserved', 'free' => 1];
            $app->getSession()->set($key . '.fingerprint', '');
            $app->getSession()->set($key . '.report', null);
        }
        $rows = $id ? $db->setQuery('SELECT r.*,COALESCE(u.name,r.guest_name) AS holder_name,
            COALESCE(u.email,r.guest_email) AS holder_email FROM #__jem_register r LEFT JOIN #__users u ON u.id=r.uid
            WHERE r.event=' . $id . ' AND r.invitation_key IS NOT NULL ORDER BY r.id DESC')->loadObjectList() : [];
        $recipientAccess = new JemInvitationAccess($db);
        $candidates = $id ? $db->setQuery("SELECT id,name FROM #__users WHERE block=0 AND (activation IS NULL OR activation IN ('','0')) ORDER BY name")->loadObjectList() : [];
        $users = $recipientAccess->filterUsers($candidates, $id ? [$id] : []);
        $seriesUsers = $id ? $recipientAccess->filterUsers($users, array_keys($service->dates($id, 'series'))) : [];
        $assigned = array_sum(array_map(static fn ($row) => (int) $row->reserved_assigned, $rows));
        $confirmed = array_sum(array_map(static fn ($row) => (int) $row->reserved_confirmed, $rows));
        $this->invitationData = [
            'events' => $events, 'event' => $event, 'form' => $form, 'rows' => $rows,
            'unavailable' => $event ? $service->availabilityMessage($event) : '',
            'users' => $users,
            'series_users' => array_map(static fn ($user) => (int) $user->id, $seriesUsers),
            'groups' => $actor->authorise('jem.invitations.bulk', 'com_jem') ? $db->setQuery('SELECT id,title FROM #__usergroups ORDER BY lft')->loadObjectList() : [],
            'prices' => $id ? $db->setQuery('SELECT code,name,amount FROM #__jem_event_prices WHERE published=1 AND event_id=' . $id . ' ORDER BY ordering,id')->loadObjectList() : [],
            'areas' => $id ? (new JemRegistrationService($db))->capacityOptions($id)->options : [],
            'canFree' => $id && $service->can($actor, $id, 'free'),
            'report' => $app->getSession()->get($key . '.report'),
            'reviewed' => (bool) $app->getSession()->get($key . '.fingerprint'),
            'pool' => ['total' => (int) ($event->reservedplaces ?? 0) + $confirmed,
                'available' => (int) ($event->reservedplaces ?? 0) - $assigned,
                'assigned' => $assigned, 'confirmed' => $confirmed],
        ];
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->getDocument()->setMetadata('robots', 'noindex, nofollow');
        $app->getDocument()->getWebAssetManager()->registerAndUseStyle('com_jem.invitations', 'com_jem/invitations.css');
        $app->getDocument()->getWebAssetManager()->registerAndUseScript('com_jem.invitations', 'com_jem/invitations.js', [], ['defer' => true]);
    }
}
