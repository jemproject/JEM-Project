<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
?>
<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <div class="card"><div class="card-body">
        <?php echo $this->form->renderFieldset('details'); ?>
    </div></div>
    <input type="hidden" name="task" value=""><?php echo HTMLHelper::_('form.token'); ?>
</form>
