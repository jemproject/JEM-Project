<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldEventschedule extends FormField
{
    protected $type = 'Eventschedule';

    protected function getInput()
    {
        HTMLHelper::_('script', 'com_jem/event-schedule.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);
        HTMLHelper::_('stylesheet', 'com_jem/event-schedule.css', ['version' => 'auto', 'relative' => true]);
        $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $labels = [];
        foreach (['daily', 'continuous', 'per_day', 'date', 'closed', 'all_day', 'start', 'end', 'add', 'remove',
            'initialise', 'confirm', 'invalid_dates', 'help', 'legacy', 'day', 'range_changed', 'label', 'unconfigured'] as $key) {
            $labels[$key] = Text::_('COM_JEM_SCHEDULE_' . strtoupper($key));
        }
        $value = (string) $this->value;
        $legacy = $value === '' && (int) $this->form->getValue('id') > 0;
        if ($value === '' && !$legacy) {
            $value = '{"version":1,"mode":"daily","days":[]}';
        }
        return '<div class="jem-event-schedule" data-jem-event-schedule data-labels="'
            . $escape(json_encode($labels)) . '"><input type="hidden" id="' . $escape($this->id)
            . '" name="' . $escape($this->name) . '" value="' . $escape($value) . '">'
            . '<p class="form-text">' . $escape($labels['help']) . '</p>'
            . ($legacy ? '<p class="alert alert-info">' . $escape($labels['legacy']) . '</p>' : '')
            . '<div data-schedule-editor></div><noscript>' . $escape(Text::_('COM_JEM_SCHEDULE_JAVASCRIPT'))
            . '</noscript></div>';
    }
}
