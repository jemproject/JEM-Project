<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
$group = 'globalattribs';
?>

<div class="width-50 fltlft">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_SETTINGS_VENUE_DETAIL'); ?></legend>
            <ul class="adminformlist">
                <?php foreach ($this->form->getFieldset('venues') as $field): ?>
                    <li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
                <?php endforeach; ?>
            </ul>
        </fieldset>
    </div>
</div>
<div class="width-50 fltrt">
    <div class="width-100" style="padding: 10px 1vw;">
    </div>
</div>
