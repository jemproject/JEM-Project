<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/** Meeting credentials have their own view level, independent of event publication. */
final class JemOnlineMeeting
{
    public const DEFAULTS = ['online_meeting_access' => 1, 'online_meeting_registered' => 0];

    /** @param object|array $event Event with persisted attributes. @return array Access and confirmation requirements. */
    public static function settings($event): array
    {
        $event = (object) $event;
        $attributes = new Registry($event->attribs ?? []);
        $result = [];
        foreach (self::DEFAULTS as $key => $default) {
            $value = $attributes->get($key, $default);
            // Malformed imported settings must not silently become public.
            $result[$key] = is_scalar($value) && preg_match('/^[0-9]+$/D', (string) $value)
                ? (int) $value : -1;
        }
        return $result;
    }

    /** @param object|array $event Event. @return bool Whether public outputs must omit its joining credentials. */
    public static function restricted($event): bool
    {
        return self::settings($event) !== self::DEFAULTS;
    }

    /** @param object|null $row Current booking. @param int $eventId Exact occurrence. @return bool At least one confirmed place remains. */
    public static function confirmed(?object $row, int $eventId): bool
    {
        return $row && (int) ($row->event ?? 0) === $eventId
            && (int) ($row->status ?? 0) === 1 && (int) ($row->waiting ?? 0) === 0
            && (int) ($row->places ?? 0) > 0 && empty($row->invitation_revoked);
    }

    /**
     * Apply both requirements after the caller has established current event visibility.
     * Payment is deliberately independent of the confirmed-registration checkbox.
     * @param object $event Current event. @param object $user Joomla identity (guest for external capabilities).
     * @param object|null $row Verified owner's booking. @param bool $visible Current event visibility.
     * @param bool $external Whether the caller verified an external booking capability.
     * @return bool Access to the joining credential is allowed.
     */
    public static function allowed(object $event, object $user, ?object $row, bool $visible, bool $external = false): bool
    {
        $settings = self::settings($event);
        if (!$visible || !in_array($settings['online_meeting_registered'], [0, 1], true) || !empty($user->block)
            || !in_array((string) ($user->activation ?? ''), ['', '0'], true)
            || !in_array($settings['online_meeting_access'], array_map('intval', $user->getAuthorisedViewLevels()), true)) {
            return false;
        }
        if (self::restricted($event) && JemRegistrationAccessPolicy::invitationEventMessage($event) !== '') {
            return false;
        }
        if ($external || $settings['online_meeting_registered'] !== 0) {
            return self::confirmed($row, (int) $event->id)
                && ($external ? (int) $row->uid === 0
                    : (int) ($user->id ?? 0) > 0 && (int) $row->uid === (int) $user->id);
        }
        return true;
    }

    /** @param int $eventId Event. @param int $userId Logged-in owner. @return object|null Current confirmed booking. */
    public static function booking(int $eventId, int $userId): ?object
    {
        if ($userId < 1) {
            return null;
        }
        return Factory::getContainer()->get('DatabaseDriver')->setQuery(
            'SELECT * FROM #__jem_register WHERE event=' . $eventId . ' AND uid=' . $userId
            . ' AND status=1 AND waiting=0 AND places>0 ORDER BY id', 0, 1
        )->loadObject() ?: null;
    }

    /** @param int $eventId Event. @param int $linkId Optional additional online link. @return string Non-secret site route. */
    public static function route(int $eventId, int $linkId = 0): string
    {
        return 'index.php?option=com_jem&task=onlinemeeting.join&id=' . $eventId
            . ($linkId > 0 ? '&link_id=' . $linkId : '');
    }

    /**
     * Remove raw private credentials before frontend layouts or plugins receive the event.
     * An eligible visitor receives a non-secret route that rechecks access when followed.
     * @param object $event Frontend model item, already decorated with access-view.
     * @return void
     */
    public static function prepare(object $event): void
    {
        if (!self::restricted($event)) {
            return;
        }
        $app = Factory::getApplication();
        $user = $app->getIdentity();
        $settings = self::settings($event);
        $row = $settings['online_meeting_registered'] !== 0
            ? self::booking((int) $event->id, (int) $user->id) : null;
        $allowed = self::allowed($event, $user, $row, (bool) $event->params->get('access-view', false));
        $hasMeeting = trim((string) ($event->online_meeting_url ?? '')) !== '' || !empty($event->jem_private_meeting);
        $event->online_meeting_url = '';
        $links = [];
        foreach ($event->event_links ?? [] as $link) {
            if (strtolower(trim((string) ($link->type ?? ''))) === 'online') {
                $hasMeeting = true;
                if (!$allowed || (int) ($link->state ?? 0) !== 1) {
                    continue;
                }
                $link = clone $link;
                $link->url = Route::_(self::route((int) $event->id, (int) $link->id), false);
            }
            $links[] = $link;
        }
        $event->event_links = $links;
        $event->jem_private_meeting = $hasMeeting;
        $event->jem_meeting_join_url = $allowed && $hasMeeting ? Route::_(self::route((int) $event->id), false) : '';
    }

    /** @param object $event Event. @param array $links Additional links. @return array Links safe for public exports. */
    public static function publicLinks(object $event, array $links): array
    {
        return self::restricted($event) ? array_values(array_filter($links,
            static fn ($link) => strtolower(trim((string) (((object) $link)->type ?? ''))) !== 'online')) : $links;
    }

    /**
     * Bind an external join capability to one booking and its current revision.
     * A locator alone is never proof of ownership; no new token table is required.
     * @param object $row Authoritative guest booking. @param string $secret Joomla site secret.
     * @return string HMAC, or empty when this booking cannot receive a capability.
     */
    public static function guestKey(object $row, string $secret): string
    {
        if ($secret === '' || (int) ($row->uid ?? -1) !== 0
            || empty($row->invitation_key) || empty($row->reference)
            || !self::confirmed($row, (int) ($row->event ?? 0))) {
            return '';
        }
        return hash_hmac('sha256', implode('|', ['jem-meeting-v1', (int) $row->id, (int) $row->event,
            (int) ($row->revision ?? 0), $row->invitation_key, $row->reference]), $secret);
    }

    /** @param object $row Current booking. @param string $key Supplied capability. @param string $secret Site secret. @return bool Valid external proof. */
    public static function verifyGuest(object $row, string $key, string $secret): bool
    {
        $expected = self::guestKey($row, $secret);
        return $expected !== '' && preg_match('/^[a-f0-9]{64}$/D', $key) && hash_equals($expected, $key);
    }

    /**
     * Create an external recipient's scoped link, never the provider URL.
     * @param object $event Event with policy. @param object $row Confirmed external booking.
     * @return string Absolute capability link, or empty if the link requires a Joomla account.
     */
    public static function guestUrl(object $event, object $row): string
    {
        if ((int) self::settings($event)['online_meeting_access'] !== 1) {
            return '';
        }
        $key = self::guestKey($row, (string) Factory::getApplication()->get('secret', ''));
        return $key === '' ? '' : JemHelperRoute::getAbsoluteSiteRoute(self::route((int) $row->event)
            . '&registration_id=' . (int) $row->id . '&meeting_key=' . $key);
    }

    /** @return void Prevent the private gateway, including errors, entering page/proxy caches. */
    public static function privateResponse(): void
    {
        $app = Factory::getApplication();
        $app->getDispatcher()->addListener('onPageCacheIsExcluded', static function ($event): void {
            $event->addResult(true);
        });
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->setHeader('Referrer-Policy', 'no-referrer', true);
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
    }
}
