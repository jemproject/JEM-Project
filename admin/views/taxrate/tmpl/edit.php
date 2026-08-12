<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
?>
<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <div class="row">
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <?php foreach (array('code', 'name', 'tax_type', 'rate', 'country_code', 'region_code', 'valid_from', 'valid_until', 'description') as $field) : ?>
                    <div class="mb-3"><?php echo $this->form->renderField($field); ?></div>
                <?php endforeach; ?>
            </div></div>
        </div>
        <div class="col-lg-4"><div class="card"><div class="card-body"><?php echo $this->form->renderField('published'); ?></div></div></div>
    </div>
    <input type="hidden" name="task" value="" />
    <?php echo $this->form->getInput('id'); ?>
    <?php echo $this->form->getInput('ordering'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
