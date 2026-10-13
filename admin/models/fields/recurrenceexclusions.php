<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

require_once JPATH_SITE . '/components/com_jem/classes/recurrenceexclusions.class.php';

class JFormFieldRecurrenceexclusions extends ListField
{
    protected $type = 'Recurrenceexclusions';

    protected function getOptions()
    {
        $rows = JemRecurrenceExclusions::options(
            Factory::getContainer()->get('DatabaseDriver'),
            Factory::getApplication()->getIdentity()->getAuthorisedViewLevels()
        );
        $options = [];
        foreach ($rows as $token => $row) {
            $prefix = $row['kind'] === 'type' ? 'COM_JEM_RECURRENCE_EXCLUDE_TYPE' : 'COM_JEM_RECURRENCE_EXCLUDE_DAY';
            $options[] = HTMLHelper::_('select.option', $token, Text::sprintf($prefix, $row['title']));
        }
        foreach ((array) $this->value as $token) {
            if (is_string($token) && $token !== '' && !isset($rows[$token])) {
                $options[] = HTMLHelper::_('select.option', $token, Text::sprintf('COM_JEM_RECURRENCE_EXCLUDE_UNAVAILABLE', $token));
            }
        }

        return array_merge(parent::getOptions(), $options);
    }

    protected function getInput()
    {
        $id = (int) $this->form->getValue('id');
        if ($id > 0) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)->select('recurrence_first_id')
                ->from($db->quoteName('#__jem_events'))->where('id = ' . $id);
            if ((int) $db->setQuery($query)->loadResult() > 0) {
                return '<p class="form-text">' . Text::_('COM_JEM_RECURRENCE_EXCLUDE_EDIT_ROOT') . '</p>';
            }
        }
        // A cleared multiselect must be submitted; absence means preserve on legacy forms.
        return '<input type="hidden" name="' . htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8')
            . '" value="">' . parent::getInput();
    }
}
