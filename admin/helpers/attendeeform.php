<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserFactoryInterface;

/** Retain only editable attendee input after a rejected administrator save. */
final class JemAttendeeFormHelper
{
    private const STATE = 'com_jem.attendee.failed_form';

    /**
     * Store a single session draft, without request tokens or calculated totals.
     *
     * @param array $input Submitted editable values.
     * @param int $eventId Event being edited.
     * @param int $id Registration identifier, or zero for a new registration.
     * @return void
     */
    public static function remember(array $input, int $eventId, int $id): void
    {
        $data = [];
        foreach (['uid', 'status', 'places', 'sendemail', 'seriesbooking'] as $field) {
            $data[$field] = isset($input[$field]) && is_scalar($input[$field]) ? (int) $input[$field] : 0;
        }
        if (isset($input['comment']) && is_string($input['comment'])) {
            $data['comment'] = $input['comment'];
        }
        foreach (['admissions', 'capacity_areas'] as $field) {
            $data[$field] = [];
            foreach ((array) ($input[$field] ?? []) as $key => $quantity) {
                if (is_scalar($quantity)) {
                    $data[$field][$key] = (int) $quantity;
                }
            }
        }
        $app = Factory::getApplication();
        $app->setUserState(self::STATE, [
            'actor' => (int) $app->getIdentity()->id,
            'event' => $eventId,
            'id' => $id,
            'data' => $data,
        ]);
    }

    /**
     * Read a draft only for its original administrator, event and registration.
     *
     * @param int $eventId Event identifier.
     * @param int $id Registration identifier.
     * @return array Editable input, or an empty array when no matching draft exists.
     */
    public static function getDraft(int $eventId, int $id): array
    {
        $app = Factory::getApplication();
        $draft = $app->getUserState(self::STATE);
        if (!is_array($draft) || ($draft['actor'] ?? 0) !== (int) $app->getIdentity()->id
            || ($draft['event'] ?? 0) !== $eventId || ($draft['id'] ?? -1) !== $id) {
            return [];
        }

        return (array) $draft['data'];
    }

    /**
     * Discard this form's draft after successful saving or cancellation.
     *
     * @param int $eventId Event identifier.
     * @param int $id Registration identifier.
     * @return void
     */
    public static function clear(int $eventId, int $id): void
    {
        if (self::getDraft($eventId, $id)) {
            Factory::getApplication()->setUserState(self::STATE, null);
        }
    }

    /**
     * Restore display values on a clone, leaving the persisted model row intact.
     *
     * @param object $row Current database/default form row.
     * @param array $draft Allowed editable values.
     * @return object Form row; event metadata and identity are never taken from POST.
     */
    public static function restoreRow(object $row, array $draft): object
    {
        $formRow = clone $row;
        foreach (['uid', 'status', 'places', 'comment'] as $field) {
            if (array_key_exists($field, $draft)) {
                $formRow->$field = $draft[$field];
            }
        }
        if ((int) ($row->uid ?? 0) !== (int) ($formRow->uid ?? 0)) {
            $formRow->username = Factory::getContainer()->get(UserFactoryInterface::class)
                ->loadUserById((int) $formRow->uid)->name;
        }

        return $formRow;
    }

    /**
     * Display a model error once, or a fallback when this save supplied no warning.
     *
     * @param mixed $error Model error string or exception.
     * @param array $previousMessages Message queue immediately before storing.
     * @return void
     */
    public static function reportError($error, array $previousMessages): void
    {
        $app = Factory::getApplication();
        $messages = $app->getMessageQueue();
        $message = $error instanceof Throwable ? $error->getMessage() : (is_scalar($error) ? trim((string) $error) : '');
        if ($message !== '') {
            foreach ($messages as $queued) {
                if (($queued['message'] ?? '') === $message && in_array($queued['type'] ?? '', ['warning', 'error'], true)) {
                    return;
                }
            }
        } else {
            foreach (array_slice($messages, count($previousMessages)) as $queued) {
                if (in_array($queued['type'] ?? '', ['warning', 'error'], true) && !empty($queued['message'])) {
                    return;
                }
            }
            $message = Text::_('COM_JEM_ATTENDEE_SAVE_FAILED');
        }
        $app->enqueueMessage($message, 'error');
    }
}
