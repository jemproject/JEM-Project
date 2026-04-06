<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */


defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
?>

<div class="row">
    <div class="col-md-12">
       
        <div class="adminform-subform">
            <?php echo $this->form->renderField('event_links'); ?>
        </div>
    </div>
</div>