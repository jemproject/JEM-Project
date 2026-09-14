<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\TimezoneField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

FormHelper::loadFieldClass('timezone');

/**
 * Timezone selector that requires an explicit event timezone selection.
 */
class JFormFieldEventTimezone extends TimezoneField
{
    protected $type = 'EventTimezone';

    /**
     * Add a real empty option before Joomla's grouped timezone list.
     *
     * @return array[]
     */
    protected function getGroups()
    {
        $groups = parent::getGroups();
        $placeholder = HTMLHelper::_(
            'select.option',
            '',
            Text::_('COM_JEM_EVENT_TIMEZONE_SELECT'),
            'value',
            'text',
            false
        );

        return array(0 => array($placeholder)) + $groups;
    }
}
