<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;


abstract class JemModelAdmin extends AdminModel
{
    protected function _prepareTable($table)
    {
        // Derived class will provide its own implementation if required.
    }
    protected function prepareTable($table)
    {
        $this->_prepareTable($table);
    }
}
