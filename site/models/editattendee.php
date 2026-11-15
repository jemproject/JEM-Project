<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/attendee.php';
require_once dirname(__DIR__) . '/classes/attendeeeditoraccess.class.php';
require_once dirname(__DIR__) . '/classes/registrationcomment.class.php';

/** Frontend adapter to the existing attendee editor's catalogue and validated writer. */
class JemModelEditattendee extends JemModelAttendee
{
    /** @return object Existing reservation, after checking its event scope. */
    public function getData()
    {
        try {
            JemAttendeeEditorAccess::assertReservation($this->getDatabase(), JemFactory::getUser(), (int) $this->_id);
        } catch (DomainException $error) {
            throw new RuntimeException(Text::_($error->getMessage()), 403, $error);
        }
        return parent::getData();
    }

    /** @param string $action Audit action suffix. @return string Frontend audit source. */
    protected function editorSource(string $action): string
    {
        return 'site.attendee.' . $action;
    }

    /** @return bool Protect admission history under the registration writer's lock. */
    protected function protectAdmission(): bool
    {
        return true;
    }

    /**
     * Edit only permitted fields of an existing, authorised reservation.
     * @param array $input Form values; holder, event, totals and payment data are ignored.
     * @return object|false Saved registration or a validation failure.
     */
    public function store($input)
    {
        $before = $this->getData();
        $actor = JemFactory::getUser();
        try {
            $revision = JemRegistrationQuantity::parseOptional($input['revision'] ?? 0);
            if ($revision < 1 || $revision !== (int) $before->revision) {
                throw new DomainException('COM_JEM_FE_RESERVATION_STALE');
            }
            $rawStatus = $input['status'] ?? '';
            if (!is_scalar($rawStatus) || !in_array((string) $rawStatus, ['-1', '0', '1', '2'], true)) {
                throw new DomainException('COM_JEM_ATTENDEES_STATUS_UNKNOWN');
            }
            $status = (int) $rawStatus;
            $data = ['id' => (int) $before->id, 'event' => (int) $before->event, 'uid' => (int) $before->uid,
                'status' => $status, '_expected_revision' => $revision, 'seriesbooking' => 0,
                'comment' => $input['comment'] ?? $before->comment];
            foreach (['admissions', 'capacity_areas'] as $field) {
                $data[$field] = [];
                foreach ((array) ($input[$field] ?? []) as $key => $value) {
                    if (($field === 'admissions' && !preg_match('/^[1-9][0-9]*$/D', (string) $key))
                        || ($field === 'capacity_areas' && !preg_match('/^(?:area|layout):[1-9][0-9]*$/D', (string) $key))) {
                        throw new DomainException('COM_JEM_RELOCATION_SELECTION_INVALID');
                    }
                    $data[$field][$key] = JemRegistrationQuantity::parseOptional($value);
                }
            }
            $data['places'] = isset($input['places']) ? JemRegistrationQuantity::parseOptional($input['places']) : (int) $before->places;
            $priced = in_array((string) $before->pricing_mode, ['single', 'multiple', 'priced'], true);
            $capacity = $this->getCapacityData((int) $before->event, (int) $before->id);
            $oldSelection = $newSelection = [];
            if ($priced) {
                foreach ((new JemRegistrationService())->commercialItems((int) $before->id, $revision) as $item) {
                    if ($item->line_kind === 'admission') {
                        $oldSelection[(int) $item->event_price_id] = (int) $item->quantity;
                    }
                }
                $newSelection = array_filter($data['admissions']);
            } elseif ($capacity->enabled) {
                $oldSelection = array_map('intval', $capacity->current);
                $newSelection = array_filter($data['capacity_areas']);
            }
            ksort($oldSelection);
            ksort($newSelection);
            $changedPlaces = ($priced || $capacity->enabled) ? $oldSelection !== $newSelection : $data['places'] !== (int) $before->places;
            $structural = $changedPlaces || $status !== (int) $before->status;
            if ($structural) {
                $entered = (int) $this->getDatabase()->setQuery('SELECT COALESCE(SUM(quantity),0) FROM #__jem_ticket_operations WHERE registration_reference='
                    . $this->getDatabase()->quote($before->reference) . " AND action='admission'")->loadResult();
                if ($entered > 0) {
                    throw new DomainException('COM_JEM_RELOCATION_ALREADY_ENTERED');
                }
            }
            if ($priced && $changedPlaces && !$actor->authorise('jem.tickets.pay', 'com_jem')) {
                throw new DomainException('COM_JEM_RELOCATION_FINANCE_PERMISSION');
            }
            if (!empty($before->invitation_key) && $changedPlaces) {
                throw new DomainException('COM_JEM_RELOCATION_INVITATION_TERMS');
            }
            // Guest invitations retain their identity and agreed quantities; notes/status use the shared writer.
            if (empty($before->uid)) {
                if ($changedPlaces) {
                    throw new DomainException('COM_JEM_RELOCATION_INVITATION_TERMS');
                }
                $row = clone $before;
                $row->comment = JemRegistrationComment::normalise($data['comment']);
                JemRegistrationTransition::applyLogicalStatus($row, $status);
                return (new JemRegistrationService())->save($row, ['requireExisting' => true,
                    'expectedRevision' => $revision, 'respectPlaces' => true, 'protectAdmission' => true,
                    'actorId' => (int) $actor->id, 'source' => $this->editorSource('edit')])->after;
            }
            return parent::store($data);
        } catch (Throwable $error) {
            $this->setError(Text::_($error->getMessage()));
            return false;
        }
    }
}
