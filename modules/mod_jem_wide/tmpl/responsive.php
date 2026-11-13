<?php
/**
 * @package    JEM
 * @subpackage JEM Wide Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Helper\ModuleHelper;

$jemsettings = JemHelper::config();

echo '<div class="jemmodulewide'.$params->get('moduleclass_sfx').'" id="jemmodulewide">';
if (count($list)) {
    if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-tablestyle')) {
        include ModuleHelper::getLayoutPath('mod_jem_wide', 'default_jem_eventslist_small'); // Similar to the old table-layout
    } else {
        include ModuleHelper::getLayoutPath('mod_jem_wide', 'default_jem_eventslist'); // The new layout
    }
} else {
    echo Text::_('MOD_JEM_WIDE_NO_EVENTS');
}
echo '</div>';

?>
