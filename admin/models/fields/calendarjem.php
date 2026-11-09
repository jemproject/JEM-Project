<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
 
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Field\CalendarField;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;

/**
 * Form Field class for JEM needs.
 *
 * Advances CalendarField for better country-specific date format support.
 *
 * @since  2.2.3
 */

class JFormFieldCalendarJem extends CalendarField
{
    /**
     * The form field type.
     *
     * @var    string
     */
    protected $type = 'CalendarJem';

    /**
     * Method to get the data to be passed to the layout for rendering.
     *
     * @return  array
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $data['firstday'] = $this->getJemFirstWeekday((int) ($data['firstday'] ?? 0));
        $data['class'] = trim(($data['class'] ?? '') . ' validate-jemdate');
        $data['dataAttribute'] = ($data['dataAttribute'] ?? '')
            . ' data-validation-text="'
            . htmlspecialchars(Text::sprintf('COM_JEM_EVENT_ERROR_INVALID_DATE', $this->title), ENT_COMPAT, 'UTF-8')
            . '"';

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        if (!$wa->assetExists('script', 'jem.datevalidation')) {
            $wa->registerScript(
                'jem.datevalidation',
                'com_jem/date-validation.js',
                array(),
                array('defer' => true),
                array('form.validate')
            );
        }
        $wa->useScript('jem.datevalidation');

        if (!empty($this->hint)) {
            return $data;
        }

        // add hint regarding date/time format accepted in edit field
        $exampleTimestamp = strtotime("NOW");
        $date_format = str_replace("%","",$this->format);
        $hint = Text::sprintf('COM_JEM_DATEFIELD_HINT', date($date_format, $exampleTimestamp));

        return array_merge($data, ['hint' => $hint]);
    }

    /**
     * Normalise valid display-format dates without throwing for rejected input.
     *
     * @param   mixed          $value  Submitted field value.
     * @param   string|null    $group  Optional field group.
     * @param   Registry|null  $input  Complete form data.
     *
     * @return  mixed  Normalised date, empty value, or false for validation to reject.
     */
    public function filter($value, $group = null, ?Registry $input = null)
    {
        if ($value !== null && (!is_string($value) || str_contains($value, "\0"))) {
            return false;
        }

        $value = is_string($value) ? trim($value) : $value;
        $format = $this->filterFormat ?: HTMLHelper::strftimeFormatToDateFormat($this->format);
        if ($value !== '' && $value !== null && !$this->isCalendarDate($value, $format)) {
            return false;
        }

        return parent::filter($value, $group, $input);
    }

    /**
     * Report rejected dates through Joomla's normal form-validation flow.
     *
     * @param   mixed          $value  Filtered date or rejection marker.
     * @param   string|null    $group  Optional field group.
     * @param   Registry|null  $input  Filtered form data.
     *
     * @return  bool|\Exception  True or an error for Joomla to display on the form.
     */
    public function validate($value, $group = null, ?Registry $input = null)
    {
        $valid = parent::validate($value, $group, $input);
        if ($valid !== true || $value === '' || $value === null) {
            return $valid;
        }

        // The native filter produces SQL dates; the unfiltered display format is not enough.
        $format = $this->filterFormat ? 'Y-m-d H:i:s' : HTMLHelper::strftimeFormatToDateFormat($this->format);

        return $this->isCalendarDate($value, $format)
            ? true : new \RuntimeException(Text::sprintf('COM_JEM_EVENT_ERROR_INVALID_DATE', $this->title));
    }

    /**
     * Render stored or retried dates without rolling invalid input into another date.
     *
     * @return  string  Calendar markup, retaining rejected text for correction.
     */
    protected function getInput()
    {
        $original = $this->value;
        $value = is_string($original) ? trim($original, " \t\n\r\v") : $original;
        // Legacy stored zero dates represent open-date events; submissions still reject them.
        if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            $value = '';
        }
        if ($value !== '' && $value !== null
            && !$this->isCalendarDate($value, 'Y-m-d')
            && !$this->isCalendarDate($value, 'Y-m-d H:i:s')) {
            $value = $this->filter($original);
            if ($value === false) {
                // Initialise the native picker empty, then restore the escaped text in JS.
                $data = $this->collectLayoutData();
                $data['value'] = '';
                $data['dataAttribute'] .= ' data-jem-invalid-date="'
                    . htmlspecialchars(is_scalar($original) ? (string) $original : '', ENT_QUOTES, 'UTF-8') . '"';

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

    /**
     * Check a date against one exact format without PHP's automatic date rollover.
     *
     * @param   mixed   $value   Date to check.
     * @param   string  $format  Expected PHP date format.
     *
     * @return  bool  Whether parsing preserves the complete input without warnings.
     */
    private function isCalendarDate($value, string $format): bool
    {
        if (!is_string($value) || str_contains($value, "\0")) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))
            && $date->format($format) === $value;
    }

    /**
     * Return JEM's configured first day of week for Joomla's calendar field.
     *
     * @param   integer  $fallback  Joomla language fallback value.
     *
     * @return  integer  0 for Sunday, 1 for Monday.
     */
    private function getJemFirstWeekday($fallback = 0)
    {
        try {
            if (!class_exists('JemHelper')) {
                require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
            }

            $settings = JemHelper::config();

            return ((int) ($settings->weekdaystart ?? $fallback) === 1) ? 1 : 0;
        } catch (Throwable $e) {
            return ((int) $fallback === 1) ? 1 : 0;
        }
    }

}
