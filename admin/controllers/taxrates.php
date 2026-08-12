<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;

class JemControllerTaxrates extends AdminController
{
    protected $text_prefix = 'COM_JEM_TAX_RATES';

    public function execute($task)
    {
        if (!JemHelperBackend::canManage('core.options')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::execute($task);
    }

    public function getModel($name = 'Taxrate', $prefix = 'JemModel', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }
}
