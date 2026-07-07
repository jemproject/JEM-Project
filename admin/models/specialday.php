<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Table\Table;

require_once __DIR__ . '/admin.php';
require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';

class JemModelSpecialday extends JemModelAdmin
{
    protected $event_before_save = 'onJemSpecialdayBeforeSave';
    protected $event_after_save = 'onJemSpecialdayAfterSave';
    protected $event_change_state = 'onJemSpecialdayChangeState';

    public function __construct($config = array())
    {
        $config['events_map'] = array_merge(
            array(
                'save' => 'jem',
                'delete' => 'jem',
                'change_state' => 'jem',
            ),
            $config['events_map'] ?? array()
        );

        parent::__construct($config);
    }

    protected function canDelete($record)
    {
        return !empty($record->id) && JemFactory::getUser()->authorise('core.delete', 'com_jem');
    }

    public function getTable($name = 'jem_special_days', $prefix = '', $options = array())
    {
        return Table::getInstance($name, '', $options);
    }

    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm('com_jem.specialday', 'specialday', array('control' => 'jform', 'load_data' => $loadData));

        return empty($form) ? false : $form;
    }

    public function getItem($pk = null)
    {
        $pk = $pk ?: (int) $this->getState($this->getName() . '.id');

        if ($pk <= 0) {
            return (object) array(
                'id' => 0,
                'title' => '',
                'alias' => '',
                'day_type_id' => 0,
                'day_type' => '',
                'start_date' => null,
                'end_date' => null,
                'weekdays' => array(),
                'country' => array(),
                'region' => '',
                'city' => '',
                'description' => '',
                'article_id' => 0,
                'url' => '',
                'link' => '',
                'show_dates' => 1,
                'published' => 1,
                'access' => 1,
                'ordering' => 0,
            );
        }

        $item = parent::getItem($pk);

        if ($item) {
            $item->link = $this->buildLinkValue($item);
        }

        return $item;
    }

    protected function preprocessForm(Form $form, $data, $group = 'content')
    {
        // Special Days do not need content plugin form preprocessing.
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jem.edit.specialday.data', array());

        if (empty($data)) {
            $data = $this->getItem();
        }

        if (is_object($data) && isset($data->weekdays) && !is_array($data->weekdays)) {
            $data->weekdays = trim((string) $data->weekdays) === '' ? array() : explode(',', (string) $data->weekdays);
        }

        if (is_object($data) && isset($data->country) && !is_array($data->country)) {
            $data->country = trim((string) $data->country) === '' ? array() : explode(',', (string) $data->country);
        }

        if (is_object($data) && empty($data->day_type_id) && !empty($data->day_type)) {
            $type = JemHelper::resolveCalendarSpecialDayType($data->day_type);
            $data->day_type_id = (int) ($type['id'] ?? 0);
        }

        if (is_object($data) && !isset($data->link)) {
            $data->link = $this->buildLinkValue($data);
        }

        return $data;
    }

    protected function prepareTable($table)
    {
        $date = Factory::getDate();
        $user = JemFactory::getUser();

        if (empty($table->id)) {
            $table->created = $date->toSql();
            $table->created_by = $user->get('id');
        } else {
            $table->modified = $date->toSql();
            $table->modified_by = $user->get('id');
        }
    }

    protected function buildLinkValue($item)
    {
        $articleId = (int) ($item->article_id ?? 0);

        if ($articleId > 0) {
            return 'index.php?option=com_content&view=article&id=' . $articleId;
        }

        return trim((string) ($item->url ?? ''));
    }
}
