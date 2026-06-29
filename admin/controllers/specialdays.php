<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;

require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importencoding.php';

class JemControllerSpecialdays extends AdminController
{
    protected $text_prefix = 'COM_JEM_SPECIAL_DAYS';
    protected static $importLoggerAdded = false;

    public function getModel($name = 'Specialday', $prefix = 'JemModel', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function saveOrderAjax()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.edit.state', 'com_jem') && !$user->authorise('core.admin', 'com_jem')) {
            echo '0';
            $app->close();
        }

        $cid = $app->input->get('cid', array(), 'array');
        $order = $app->input->get('order', array(), 'array');
        ArrayHelper::toInteger($cid);
        ArrayHelper::toInteger($order);

        if (empty($cid) || count($cid) !== count($order)) {
            echo '0';
            $app->close();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        foreach ($cid as $index => $id) {
            if ($id <= 0) {
                continue;
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_special_days'))
                ->set($db->quoteName('ordering') . ' = ' . (int) ($order[$index] ?? ($index + 1)))
                ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($query);
            $db->execute();
        }

        echo '1';
        $app->close();
    }

    public function remove()
    {
        Session::checkToken() or jexit(Text::_('COM_JEM_GLOBAL_INVALID_TOKEN'));

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.delete', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $cid = $app->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);
        $cid = array_filter($cid);

        if (empty($cid)) {
            throw new \Exception(Text::_('COM_JEM_SELECT_AN_ITEM_TO_DELETE'), 500);
        }

        $model = $this->getModel('specialday');

        if ($model->delete($cid)) {
            $app->enqueueMessage(Text::plural('COM_JEM_SPECIAL_DAYS_N_ITEMS_DELETED', count($cid)));
        } else {
            $app->enqueueMessage($model->getError(), 'error');
        }

        $this->setRedirect('index.php?option=com_jem&view=specialdays');
    }

    public function importCsv()
    {
        Session::checkToken() or jexit(Text::_('COM_JEM_GLOBAL_INVALID_TOKEN'));

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.create', 'com_jem')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $file = $app->input->files->get('FileSpecialDays', array(), 'array');
        $replace = $app->input->post->getInt('replace_specialdays', 0);
        $redirect = $app->input->getInt('return_import', 0)
            ? 'index.php?option=com_jem&view=import#special-days'
            : 'index.php?option=com_jem&view=specialdays';

        if (empty($file['name'])) {
            $this->addSpecialDaysImportLog(Text::_('COM_JEM_IMPORT_SELECT_FILE'), Log::WARNING);
            $this->setRedirect($redirect, Text::_('COM_JEM_IMPORT_SELECT_FILE'), 'error');
            return;
        }

        if (!empty($file['error']) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv' || !is_uploaded_file($file['tmp_name'])) {
            $this->addSpecialDaysImportLog(Text::_('COM_JEM_IMPORT_PARSE_ERROR') . ' File: ' . ($file['name'] ?? ''), Log::WARNING);
            $this->setRedirect($redirect, Text::_('COM_JEM_IMPORT_PARSE_ERROR'), 'error');
            return;
        }

        $jemconfig = JemConfig::getInstance()->toRegistry();
        $separator = $jemconfig->get('csv_separator', ';');
        $delimiter = $jemconfig->get('csv_delimiter', '"');
        $handle = fopen($file['tmp_name'], 'r');

        if (!$handle) {
            $this->addSpecialDaysImportLog(Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR') . ' File: ' . $file['name'], Log::WARNING);
            $this->setRedirect($redirect, Text::_('COM_JEM_IMPORT_OPEN_FILE_ERROR'), 'error');
            return;
        }

        $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
        $firstChars = fread($handle, 3);
        if (strncmp($firstChars, $bom, 3) !== 0) {
            fseek($handle, 0);
        }

        $header = fgetcsv($handle, 10000, $separator, $delimiter);
        if (is_array($header) && count($header) === 1 && strpos((string) $header[0], ',') !== false && $separator !== ',') {
            $separator = ',';
            fseek($handle, (strncmp($firstChars, $bom, 3) === 0) ? 3 : 0);
            $header = fgetcsv($handle, 10000, $separator, $delimiter);
        } elseif (is_array($header) && count($header) === 1 && strpos((string) $header[0], ';') !== false && $separator !== ';') {
            $separator = ';';
            fseek($handle, (strncmp($firstChars, $bom, 3) === 0) ? 3 : 0);
            $header = fgetcsv($handle, 10000, $separator, $delimiter);
        }
        if ($header === false) {
            fclose($handle);
            $this->addSpecialDaysImportLog(Text::_('COM_JEM_IMPORT_PARSE_ERROR') . ' File: ' . $file['name'], Log::WARNING);
            $this->setRedirect($redirect, Text::_('COM_JEM_IMPORT_PARSE_ERROR'), 'error');
            return;
        }

        array_walk($header, 'jem_normalise_csv_utf8');
        $fields = $this->normaliseSpecialDayCsvHeader($header);

        if (!$fields) {
            fclose($handle);
            $this->addSpecialDaysImportLog(Text::_('COM_JEM_IMPORT_PARSE_ERROR') . ' File: ' . $file['name'], Log::WARNING);
            $this->setRedirect($redirect, Text::_('COM_JEM_IMPORT_PARSE_ERROR'), 'error');
            return;
        }

        $result = array('added' => 0, 'updated' => 0, 'ignored' => 0, 'error' => 0);
        $rowNumber = 1;
        $now = Factory::getDate()->toSql();
        $userId = (int) $user->id;

        while (($row = fgetcsv($handle, 10000, $separator, $delimiter)) !== false) {
            $rowNumber++;
            array_walk($row, 'jem_normalise_csv_utf8');

            if (count(array_filter($row, 'strlen')) === 0) {
                continue;
            }

            $data = array();
            foreach ($fields as $index => $field) {
                if ($field === null) {
                    continue;
                }
                $data[$field] = $row[$index] ?? '';
            }

            $data = $this->normaliseSpecialDayCsvRow($data);

            if (empty($data['title']) || empty($data['day_type'])) {
                $result['ignored']++;
                continue;
            }

            $table = Table::getInstance('jem_special_days', '');
            $id = (int) ($data['id'] ?? 0);
            $exists = false;

            if ($replace && $id > 0) {
                $exists = $table->load($id);
            }

            if (!$replace || !$exists) {
                $data['id'] = 0;
                $data['created'] = $now;
                $data['created_by'] = $userId;
            } else {
                if (empty($table->created_by)) {
                    $data['created_by'] = $userId;
                }

                $data['modified'] = $now;
                $data['modified_by'] = $userId;
            }

            if (!$table->bind($data) || !$table->check() || !$table->store()) {
                $result['error']++;
                continue;
            }

            if ($replace && $exists) {
                $result['updated']++;
            } else {
                $result['added']++;
            }
        }

        fclose($handle);

        $message = Text::sprintf('COM_JEM_SPECIAL_DAYS_IMPORT_RESULT', $result['added'], $result['updated'], $result['ignored'], $result['error']);
        $this->addSpecialDaysImportLog(
            'Special Days CSV import from file "' . $file['name'] . '" completed. '
            . 'Added: ' . $result['added'] . ', updated: ' . $result['updated']
            . ', ignored: ' . $result['ignored'] . ', errors: ' . $result['error'] . '.',
            $result['error'] ? Log::WARNING : Log::INFO
        );
        $this->setRedirect($redirect, $message, $result['error'] ? 'warning' : 'message');
    }

    /**
     * Add a message to the Special Days import log file.
     *
     * @param   string   $message   Log message.
     * @param   integer  $priority  Joomla log priority.
     *
     * @return void
     */
    protected function addSpecialDaysImportLog($message, $priority = Log::INFO)
    {
        if (!self::$importLoggerAdded) {
            Log::addLogger(
                array(
                    'text_file' => 'jem-import-specialdays.log.php',
                    'text_entry_format' => '{DATE} {TIME} | {PRIORITY} | {CATEGORY} | {MESSAGE}',
                ),
                Log::ALL,
                array('JEM_IMPORT_SPECIAL_DAYS')
            );
            self::$importLoggerAdded = true;
        }

        Log::add($message, $priority, 'JEM_IMPORT_SPECIAL_DAYS');
    }

    private function normaliseSpecialDayCsvHeader(array $header)
    {
        $aliases = array(
            'type' => 'day_type',
            'daytype' => 'day_type',
            'day_type' => 'day_type',
            'start' => 'start_date',
            'startdate' => 'start_date',
            'start_date' => 'start_date',
            'end' => 'end_date',
            'enddate' => 'end_date',
            'end_date' => 'end_date',
            'weekday' => 'weekdays',
            'weekdays' => 'weekdays',
            'showdays' => 'show_dates',
            'show_days' => 'show_dates',
            'showdates' => 'show_dates',
            'show_dates' => 'show_dates',
            'listdays' => 'show_dates',
            'list_days' => 'show_dates',
            'listdates' => 'show_dates',
            'list_dates' => 'show_dates',
            'accesslevel' => 'access',
            'access_level' => 'access',
            'viewlevel' => 'access',
            'view_level' => 'access',
        );
        $allowed = array('id', 'title', 'alias', 'day_type', 'start_date', 'end_date', 'weekdays', 'country', 'region', 'city', 'description', 'show_dates', 'published', 'access', 'ordering');
        $fields = array();

        foreach ($header as $column) {
            $key = strtolower(trim((string) $column));
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key);
            $key = trim($key, '_');
            $key = $aliases[$key] ?? $key;
            $fields[] = in_array($key, $allowed, true) ? $key : null;
        }

        return array_filter($fields, static function ($field) {
            return $field !== null;
        }) ? $fields : array();
    }

    private function normaliseSpecialDayCsvRow(array $data)
    {
        $data['id'] = isset($data['id']) ? (int) $data['id'] : 0;
        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['day_type'] = trim((string) ($data['day_type'] ?? ''));
        $data['start_date'] = $this->normaliseSpecialDayCsvDate($data['start_date'] ?? '');
        $data['end_date'] = $this->normaliseSpecialDayCsvDate($data['end_date'] ?? '');
        $data['weekdays'] = $this->normaliseSpecialDayCsvWeekdays($data['weekdays'] ?? '');
        $data['show_dates'] = $this->normaliseSpecialDayCsvBoolean($data['show_dates'] ?? 1, 1);
        $data['published'] = isset($data['published']) && trim((string) $data['published']) !== '' ? (int) $data['published'] : 1;
        $data['access'] = isset($data['access']) && trim((string) $data['access']) !== '' ? max(1, (int) $data['access']) : 1;
        $data['ordering'] = isset($data['ordering']) ? (int) $data['ordering'] : 0;

        return $data;
    }

    private function normaliseSpecialDayCsvDate($date)
    {
        $date = trim((string) $date);

        if ($date === '' || strtoupper($date) === 'NULL' || $date === '0000-00-00') {
            return null;
        }

        $timestamp = strtotime($date);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function normaliseSpecialDayCsvWeekdays($weekdays)
    {
        $map = array(
            'sun' => 0, 'sunday' => 0,
            'mon' => 1, 'monday' => 1,
            'tue' => 2, 'tues' => 2, 'tuesday' => 2,
            'wed' => 3, 'wednesday' => 3,
            'thu' => 4, 'thur' => 4, 'thurs' => 4, 'thursday' => 4,
            'fri' => 5, 'friday' => 5,
            'sat' => 6, 'saturday' => 6,
        );
        $values = preg_split('/[,\|; ]+/', strtolower((string) $weekdays));
        $result = array();

        foreach ($values as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            if (is_numeric($value)) {
                $weekday = (int) $value;
            } else {
                $weekday = $map[$value] ?? null;
            }
            if ($weekday !== null && $weekday >= 0 && $weekday <= 6) {
                $result[] = $weekday;
            }
        }

        return implode(',', array_values(array_unique($result)));
    }

    private function normaliseSpecialDayCsvBoolean($value, $default = 1)
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return (int) $default;
        }

        if (in_array($value, array('1', 'yes', 'y', 'true', 'on', 'si', 'sí'), true)) {
            return 1;
        }

        if (in_array($value, array('0', 'no', 'n', 'false', 'off'), true)) {
            return 0;
        }

        return (int) $default;
    }
}
