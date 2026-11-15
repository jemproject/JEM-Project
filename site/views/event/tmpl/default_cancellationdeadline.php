<?php
/**
 * @package JEM
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

if (empty($this->item->unregistra) || empty($this->dateUnregistationUntil)) {
    return;
}
$this->cancellationDeadlineRendered = true;
?>
<p class="jem-cancellation-deadline mt-3 mb-0">
    <strong><?php echo Text::_($this->allowAnnulation ? 'COM_JEM_EVENT_ANNULATION_NOTWILLBE_FROM' : 'COM_JEM_EVENT_ANNULATION_ISNOT_FROM'); ?></strong>
    <span><?php echo HTMLHelper::_('date', $this->dateUnregistationUntil, Text::_('DATE_FORMAT_LC2')); ?></span>
</p>
