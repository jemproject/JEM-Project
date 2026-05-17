<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

require_once __DIR__ . '/admin.php';

class JemModelType extends JemModelAdmin
{
    protected function canDelete($record)
    {
        if (!empty($record->id)) {
            return JemFactory::getUser()->authorise('core.delete', 'com_jem');
        }
        return false;
    }

    public function getTable($name = 'jem_types', $prefix = '', $options = array())
    {
        return Table::getInstance($name, '', $options);
    }

    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm('com_jem.type', 'type', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }
        return $form;
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jem.edit.type.data', array());
        if (empty($data)) {
            $data = $this->getItem();
        }
        return $data;
    }

    protected function prepareTable($table)
    {
        $date = Factory::getDate();
        $user = JemFactory::getUser();

        if (empty($table->id)) {
            $table->created    = $date->toSql();
            $table->created_by = $user->get('id');
        } else {
            $table->modified    = $date->toSql();
            $table->modified_by = $user->get('id');
        }
    }
}
