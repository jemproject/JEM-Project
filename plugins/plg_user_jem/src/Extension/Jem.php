<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace Joomla\Plugin\User\Jem\Extension;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Event\User\BeforeDeleteEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use RuntimeException;
use Throwable;

defined('_JEXEC') or die;

/**
 * Protects Joomla accounts which own active future JEM registrations.
 */
final class Jem extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return array(
            'onUserBeforeDelete' => 'onUserBeforeDelete',
        );
    }

    /**
     * Refuse physical deletion while the account owns an active future booking.
     *
     * Joomla 5 and 6 do not consume a boolean result from this event, therefore
     * an exception is required to stop the delete operation reliably.
     */
    public function onUserBeforeDelete(BeforeDeleteEvent $event): void
    {
        $user = $event->getUser();
        $userId = (int) ($user['id'] ?? 0);

        if ($userId <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $tables = $db->getTableList();
        if (!in_array($db->replacePrefix('#__jem_register'), $tables, true)
            || !in_array($db->replacePrefix('#__jem_events'), $tables, true)) {
            // Keep com_users usable if JEM was removed without uninstalling this plugin.
            return;
        }

        try {
            $count = $this->countActiveFutureRegistrations($userId);
        } catch (Throwable $e) {
            throw new RuntimeException(Text::_('PLG_USER_JEM_DELETE_CHECK_FAILED'), 0, $e);
        }

        if ($count > 0) {
            throw new RuntimeException(
                Text::plural('PLG_USER_JEM_DELETE_BLOCKED_ACTIVE_REGISTRATIONS', $count, $count)
            );
        }
    }

    /**
     * Counts registrations that are active and whose event has not ended.
     */
    private function countActiveFutureRegistrations(int $userId): int
    {
        $db = $this->getDatabase();
        $nowUtc = (new Date('now', 'UTC'))->format('Y-m-d H:i:s');
        $today = (new Date('now', $this->getApplication()->get('offset', 'UTC')))->format('Y-m-d');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jem_register', 'r'))
            ->innerJoin(
                $db->quoteName('#__jem_events', 'e')
                . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('r.event')
            )
            ->where($db->quoteName('r.uid') . ' = :userId')
            ->where($db->quoteName('r.status') . ' = 1')
            ->where(
                '('
                . $db->quoteName('e.end_utc') . ' >= :nowUtc'
                . ' OR (' . $db->quoteName('e.end_utc') . ' IS NULL'
                . ' AND ' . $db->quoteName('e.start_utc') . ' >= :nowUtc)'
                . ' OR (' . $db->quoteName('e.end_utc') . ' IS NULL'
                . ' AND ' . $db->quoteName('e.start_utc') . ' IS NULL'
                . ' AND COALESCE(' . $db->quoteName('e.enddates') . ', ' . $db->quoteName('e.dates') . ') >= :today)'
                . ')'
            )
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->bind(':nowUtc', $nowUtc)
            ->bind(':today', $today);

        return (int) $db->setQuery($query)->loadResult();
    }
}
