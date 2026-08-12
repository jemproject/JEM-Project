<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

class JemModelReminder extends AdminModel
{
    public function getTable($name = 'jem_reminders', $prefix = '', $options = array())
    {
        return Table::getInstance($name, $prefix, $options);
    }

    public function getForm($data = array(), $loadData = true)
    {
        return $this->loadForm('com_jem.reminder', 'reminder', array('control' => 'jform', 'load_data' => $loadData));
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jem.edit.reminder.data', array());
        if (empty($data)) {
            $data = $this->getItem();
            $interval = self::minutesToInterval((int) ($data->minutes ?? 1440), (string) ($data->code ?? ''));
            $data->amount = $interval['amount'];
            $data->unit = $interval['unit'];
        }

        return $data;
    }

    public function save($data)
    {
        $unit = strtolower(trim((string) ($data['unit'] ?? 'minute')));
        if (!in_array($unit, array('minute', 'hour', 'day', 'week'), true)) {
            $this->setError(Text::_('COM_JEM_REMINDER_ERROR_UNIT_INVALID'));

            return false;
        }
        try {
            $minutes = self::intervalToMinutes($data['amount'] ?? 1, $unit);
        } catch (InvalidArgumentException $e) {
            $this->setError(Text::_('COM_JEM_REMINDER_ERROR_MINUTES_INVALID'));

            return false;
        }
        $data['minutes'] = $minutes;
        $data['event_id'] = 0;
        $data['source_id'] = null;
        unset($data['amount'], $data['unit']);

        if (!parent::save($data)) {
            return false;
        }

        return true;
    }

    public function delete(&$pks)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $pks)));
        if (!$ids) {
            return false;
        }
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jem_reminders'))
            ->where($db->quoteName('event_id') . ' = 0')
            ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
        $db->setQuery($query);
        $pks = array_map('intval', (array) $db->loadColumn());

        return $pks ? parent::delete($pks) : false;
    }

    public static function intervalToMinutes($amount, $unit)
    {
        $amount = (int) $amount;
        $unit = strtolower(trim((string) $unit));
        $factors = array('minute' => 1, 'hour' => 60, 'day' => 1440, 'week' => 10080);
        if ($amount < 1 || !isset($factors[$unit])) {
            throw new InvalidArgumentException('Invalid reminder interval unit.');
        }
        $minutes = $amount * $factors[$unit];
        if ($minutes < 1 || $minutes > 4294967295) {
            throw new InvalidArgumentException('Reminder interval is outside the supported range.');
        }

        return $minutes;
    }

    public static function minutesToInterval($minutes, $code = '')
    {
        $minutes = max(1, (int) $minutes);
        if ($code === 'default_7_days') {
            return array('amount' => 7, 'unit' => 'day');
        }
        if ($code === 'default_24_hours') {
            return array('amount' => 24, 'unit' => 'hour');
        }
        if ($code === 'default_2_hours') {
            return array('amount' => 2, 'unit' => 'hour');
        }
        foreach (array('week' => 10080, 'day' => 1440, 'hour' => 60) as $unit => $factor) {
            if ($minutes % $factor === 0) {
                return array('amount' => (int) ($minutes / $factor), 'unit' => $unit);
            }
        }

        return array('amount' => $minutes, 'unit' => 'minute');
    }
}
