<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Table\Table;

require_once __DIR__ . '/admin.php';

class JemModelTaxrate extends JemModelAdmin
{
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item && empty($item->id) && empty($item->country_code)) {
            $defaultCountry = strtoupper(trim((string) (JemAdmin::config()->defaultCountry ?? '')));
            $item->country_code = preg_match('/^[A-Z]{2}$/D', $defaultCountry) === 1
                ? $defaultCountry
                : '';
        }

        return $item;
    }

    protected function canDelete($record)
    {
        if (empty($record->id) || !JemHelperBackend::canManage('core.options')) {
            return false;
        }

        $db = $this->getDatabase();
        foreach (array(
            array('#__jem_events', 'default_tax_rate_id'),
            array('#__jem_events', 'management_fee_tax_rate_id'),
            array('#__jem_event_prices', 'tax_rate_id'),
        ) as $reference) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName($reference[0]))
                ->where($db->quoteName($reference[1]) . ' = ' . (int) $record->id);
            $db->setQuery($query);
            if ((int) $db->loadResult() > 0) {
                return false;
            }
        }

        return true;
    }

    public function getTable($name = 'jem_tax_rates', $prefix = '', $options = array())
    {
        return Table::getInstance($name, '', $options);
    }

    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm('com_jem.taxrate', 'taxrate', array('control' => 'jform', 'load_data' => $loadData));

        return empty($form) ? false : $form;
    }

    protected function preprocessForm(Form $form, $data, $group = 'content')
    {
        // Tax rates do not use content plugin fields.
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jem.edit.taxrate.data', array());

        return empty($data) ? $this->getItem() : $data;
    }

    protected function prepareTable($table)
    {
        $now = Factory::getDate()->toSql();
        $userId = (int) JemFactory::getUser()->get('id');

        if (empty($table->id)) {
            $table->created = $now;
            $table->created_by = $userId;
        } else {
            $table->modified = $now;
            $table->modified_by = $userId;
        }
    }
}
