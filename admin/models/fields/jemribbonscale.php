<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\NumberField;

/**
 * Module ribbon scale field shown only while the global ribbon policy is active.
 */
class JFormFieldJemRibbonScale extends NumberField
{
    protected $type = 'JemRibbonScale';

    /**
     * Configure the field and suppress it when status ribbons are globally disabled.
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        $result = parent::setup($element, $value, $group);

        if ($result) {
            $this->hidden = !$this->statusRibbonsEnabled();
        }

        return $result;
    }

    /**
     * Check the component-wide master switch without making module forms fragile.
     */
    protected function statusRibbonsEnabled()
    {
        try {
            if (!class_exists('JemHelper')) {
                $helper = JPATH_SITE . '/components/com_jem/helpers/helper.php';
                if (!is_file($helper)) {
                    return true;
                }

                require_once $helper;
            }

            $settings = JemHelper::config();

            return (int) ($settings->module_status_ribbons ?? 1) === 1;
        } catch (\Throwable $e) {
            return true;
        }
    }
}
