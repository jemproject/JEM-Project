<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';

class JFormFieldSpecialdaytype extends ListField
{
    protected $type = 'Specialdaytype';

    protected function getOptions()
    {
        $options = array(
            HTMLHelper::_('select.option', '', Text::_('COM_JEM_SPECIAL_DAY_SELECT_TYPE')),
        );
        $types = JemHelper::calendarSpecialDayTypes();

        foreach ($types as $type) {
            $options[] = HTMLHelper::_('select.option', $type['name'], $type['name']);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
