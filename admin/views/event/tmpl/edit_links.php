<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */


defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
?>

<!-- LINKS TAB -->
<div class="row">
    <div class="col-md-7">

        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'info', 'recall' => true, 'breakpoint' => 768]); ?>
        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'links', Text::_('COM_JEM_EVENT_LINKS_TAB')); ?>


        <fieldset class="adminform">
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('link_info'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('link_online'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('link_request'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('link_pay'); ?></div></li>
            </ul>
        </fieldset>
    </div>
</div>
