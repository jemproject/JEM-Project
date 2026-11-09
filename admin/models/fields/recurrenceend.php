<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

if (!class_exists('JFormFieldCalendarJem')) {
    require_once __DIR__ . '/calendarjem.php';
}
require_once JPATH_SITE . '/components/com_jem/classes/recurrencevalidator.class.php';

/** Localised recurrence cutoff with recoverable, conditional form validation. */
class JFormFieldRecurrenceend extends JFormFieldCalendarJem
{
    protected $type = 'Recurrenceend';

    /**
     * Normalise valid dates and leave rejected input for the normal validation/retry flow.
     * @param mixed $value Submitted date in the editor's display format.
     * @param string|null $group Optional form field group.
     * @param Registry|null $input Complete submitted form data.
     * @return mixed Normalised date or unchanged invalid input.
     */
    public function filter($value, $group = null, ?Registry $input = null)
    {
        if ($value !== null && (!is_string($value) || str_contains($value, "\0"))) {
            return $value;
        }

        try {
            $filtered = parent::filter($value, $group, $input);

            // Joomla's localised date filter adds a time; the cutoff is a DATE column.
            return is_string($filtered) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $filtered)
                ? substr($filtered, 0, 10) : $filtered;
        } catch (\Exception $error) {
            return $value;
        }
    }

    /**
     * Require a real cutoff for periodic recurrences, leaving other event modes alone.
     * @param mixed $value Filtered cutoff date.
     * @param string|null $group Optional form field group.
     * @param Registry|null $input Filtered form data, including recurrence type and start date.
     * @return bool|\Exception Validation result for Joomla's normal error handling.
     */
    public function validate($value, $group = null, ?Registry $input = null)
    {
        $data = $input ? $input->toArray() : [];
        $data['recurrence_limit_date'] = $value;
        if (isset($data['dates']) && is_string($data['dates'])
            && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $data['dates'])) {
            $data['dates'] = substr($data['dates'], 0, 10);
        }
        $error = JemRecurrenceValidator::limitDateError($data);

        return $error === null ? true : new \RuntimeException(Text::_($error));
    }

    /** @return array Calendar layout data with a translated required-field message. */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $data['dataAttribute'] .= ' data-recurrence-required-text="'
            . htmlspecialchars(Text::_('COM_JEM_RECURRENCE_END_REQUIRED'), ENT_QUOTES, 'UTF-8') . '"';

        return $data;
    }

    /** @return string Calendar markup retaining invalid submitted text after a failed save. */
    protected function getInput()
    {
        $original = $this->value;
        $value = is_string($original) ? trim($original) : '';
        // Stored ISO dates already have the correct representation for CalendarField.
        if ($value !== '' && JemRecurrenceValidator::limitDateError([
            'recurrence_type' => 1, 'recurrence_limit_date' => $value,
        ]) !== null) {
            $value = $this->filter($value);
            if (JemRecurrenceValidator::limitDateError([
                'recurrence_type' => 1, 'recurrence_limit_date' => $value,
            ]) !== null) {
                $data = $this->collectLayoutData();
                $data['value'] = '';
                $data['dataAttribute'] .= ' data-recurrence-invalid-date="'
                    . htmlspecialchars((string) $original, ENT_QUOTES, 'UTF-8') . '"';

                return $this->getRenderer($this->layout)->render($data);
            }
        }

        $this->value = $value;
        try {
            return parent::getInput();
        } finally {
            $this->value = $original;
        }
    }
}
