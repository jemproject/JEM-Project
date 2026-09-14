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
        $exampleTimestamp = strtotime("12/31/2017 23:59");
        $date_format = str_replace("%","",$this->format);
        $hint = Text::sprintf('COM_JEM_DATEFIELD_HINT', date($date_format, $exampleTimestamp));

        $extraData = array(
            'hint' => $hint,
        );

        return array_merge($data, $extraData);
    }

    /**
     * Validate the submitted localised date before Joomla normalises it.
     *
     * Empty values remain valid because JEM supports open-date events.
     *
     * @param   mixed     $value  Submitted field value.
     * @param   string    $group  Optional field group.
     * @param   Registry  $input  Complete form data.
     *
     * @return  mixed
     *
     * @throws  Exception  If a non-empty value is not a real calendar date.
     */
    public function filter($value, $group = null, ?Registry $input = null)
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value !== '' && $value !== null) {
            $format = $this->filterFormat ?: HTMLHelper::strftimeFormatToDateFormat($this->format);
            $date = \DateTimeImmutable::createFromFormat('!' . $format, (string) $value);
            $errors = \DateTimeImmutable::getLastErrors();
            $hasErrors = $errors !== false
                && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0);

            if ($date === false || $hasErrors || $date->format($format) !== (string) $value) {
                throw new \Exception(Text::sprintf('COM_JEM_EVENT_ERROR_INVALID_DATE', $this->title));
            }
        }

        return parent::filter($value, $group, $input);
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
