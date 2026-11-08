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
use Joomla\CMS\Router\Route;
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

    /** @var int[] Normalised administrator selection already checked in this request. */
    private array $checkedAdminSelection = array();

    public static function getSubscribedEvents(): array
    {
        return array(
            'onUserBeforeDelete' => 'onUserBeforeDelete',
        );
    }

    /**
     * Check native administrator selections before their first deletion, then guard each account.
     *
     * @param BeforeDeleteEvent $event Account Joomla is about to delete.
     * @return void
     * @throws RuntimeException When deletion is vetoed outside the native administrator flow.
     */
    public function onUserBeforeDelete(BeforeDeleteEvent $event): void
    {
        $user = $event->getUser();
        $userId = (int) ($user['id'] ?? 0);

        if ($userId <= 0) {
            return;
        }

        $app = $this->getApplication();
        $input = $app->getInput();
        $isAdminDelete = $app->isClient('administrator')
            && $input->getCmd('option') === 'com_users'
            && in_array($input->getCmd('task'), array('delete', 'users.delete'), true);
        $selectedIds = array();

        if ($isAdminDelete) {
            $selectedIds = array_values(array_unique(array_filter(
                (array) $input->get('cid', array(), 'int'),
                static fn ($id): bool => is_int($id) && $id > 0
            )));
            sort($selectedIds, SORT_NUMERIC);
            $isAdminDelete = in_array($userId, $selectedIds, true);
        }

        $checkSelection = $isAdminDelete && count($selectedIds) > 1
            && $selectedIds !== $this->checkedAdminSelection;

        try {
            $db = $this->getDatabase();
            $tables = $db->getTableList();
            if (!in_array($db->replacePrefix('#__jem_register'), $tables, true)
                || !in_array($db->replacePrefix('#__jem_events'), $tables, true)) {
                // Keep com_users usable if JEM was removed without uninstalling this plugin.
                return;
            }

            $count = $this->countActiveFutureRegistrations($checkSelection ? $selectedIds : array($userId));
        } catch (Throwable $e) {
            $key = $checkSelection ? 'PLG_USER_JEM_DELETE_BATCH_CHECK_FAILED' : 'PLG_USER_JEM_DELETE_CHECK_FAILED';
            $this->rejectDeletion(Text::_($key), $isAdminDelete, $e);
        }

        if ($count > 0) {
            $message = $checkSelection
                ? Text::_('PLG_USER_JEM_DELETE_BATCH_BLOCKED')
                : Text::plural('PLG_USER_JEM_DELETE_BLOCKED_ACTIVE_REGISTRATIONS', $count, $count);
            $this->rejectDeletion($message, $isAdminDelete);
        }

        if ($checkSelection) {
            $this->checkedAdminSelection = $selectedIds;
        }
    }

    /**
     * Stop deletion with an administrator message or an exception for other callers.
     * Joomla ignores boolean results from onUserBeforeDelete, so this must never return.
     *
     * @param string $message Translated reason for rejecting the operation.
     * @param bool $isAdminDelete Whether this is the native administrator users-delete request.
     * @param Throwable|null $previous Original database failure, when applicable.
     * @return never
     * @throws RuntimeException When not redirecting, or if a redirect unexpectedly returns.
     */
    private function rejectDeletion(string $message, bool $isAdminDelete, ?Throwable $previous = null): never
    {
        if ($isAdminDelete) {
            $app = $this->getApplication();
            $app->enqueueMessage($message, 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=users', false), 303);
        }

        throw new RuntimeException($message, 0, $previous);
    }

    /**
     * Count active registrations for selected accounts using the existing event-end policy.
     *
     * @param int[] $userIds Non-empty list of positive Joomla user IDs.
     * @return int Number of active registrations whose events have not ended.
     */
    private function countActiveFutureRegistrations(array $userIds): int
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
            ->whereIn($db->quoteName('r.uid'), $userIds, ParameterType::INTEGER)
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
            ->bind(':nowUtc', $nowUtc)
            ->bind(':today', $today);

        return (int) $db->setQuery($query)->loadResult();
    }
}
