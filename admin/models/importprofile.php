<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;

require_once __DIR__ . '/admin.php';

class JemModelImportprofile extends JemModelAdmin
{
    protected function canDelete($record)
    {
        return !empty($record->id) && JemFactory::getUser()->authorise('core.delete', 'com_jem');
    }

    public function getTable($name = 'jem_import_profiles', $prefix = '', $options = array())
    {
        return Table::getInstance($name, $prefix, $options);
    }

    public function getForm($data = array(), $loadData = true)
    {
        return false;
    }
}
