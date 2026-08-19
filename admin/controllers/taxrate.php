<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

require_once JPATH_COMPONENT_SITE . '/classes/controller.form.class.php';

class JemControllerTaxrate extends JemControllerForm
{
    protected $text_prefix = 'COM_JEM_TAX_RATE';

    public function execute($task)
    {
        require_once JPATH_SITE . '/components/com_jem/classes/featurepolicy.class.php';
        if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_PRICING)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::execute($task);
    }

    protected function allowAdd($data = array())
    {
        return JemHelperBackend::canManage('core.options');
    }

    protected function allowEdit($data = array(), $key = 'id')
    {
        return JemHelperBackend::canManage('core.options');
    }
}
