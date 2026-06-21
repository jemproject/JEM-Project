<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/admin.php';
Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_jem/tables');

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
            return new CMSObject(array(
                'id' => 0,
                'title' => '',
                'alias' => '',
                'day_type' => '',
                'start_date' => null,
                'end_date' => null,
                'weekdays' => array(),
                'country' => array(),
                'region' => '',
                'city' => '',
                'description' => '',
                'published' => 1,
                'ordering' => 0,
            ));
        }

        return parent::getItem($pk);
    }

    protected function preprocessForm(Form $form, $data, $group = 'content')
    {
        // Special Days do not need content plugin form preprocessing.
    }

    protected function populateState()
    {
        $app = Factory::getApplication();

        $pk = $app->input->getInt('id', 0);
        $this->setState($this->getName() . '.id', $pk);

        $return = $app->input->get('return', '', 'base64');
        $decodedReturn = $return ? base64_decode($return, true) : false;
        $this->setState('return_page', ($decodedReturn && Uri::isInternal($decodedReturn)) ? $decodedReturn : '');
        $this->setState('params', $app->getParams());
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

    public function getReturnPage()
    {
        return $this->getState('return_page');
    }
}
