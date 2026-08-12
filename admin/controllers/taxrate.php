<?php
/** @package JEM */
defined('_JEXEC') or die;

require_once JPATH_COMPONENT_SITE . '/classes/controller.form.class.php';

class JemControllerTaxrate extends JemControllerForm
{
    protected $text_prefix = 'COM_JEM_TAX_RATE';

    protected function allowAdd($data = array())
    {
        return JemHelperBackend::canManage('core.options');
    }

    protected function allowEdit($data = array(), $key = 'id')
    {
        return JemHelperBackend::canManage('core.options');
    }
}
