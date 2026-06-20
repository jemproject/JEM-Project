<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$layoutSuffix = JemHelper::getLayoutStyleSuffix();

if (!empty($layoutSuffix)) {
    $this->addTemplatePath(JPATH_SITE . '/components/com_jem/views/venueslist/tmpl/' . $layoutSuffix);
}

$this->addTemplatePath(JPATH_SITE . '/components/com_jem/views/venueslist/tmpl');
?>

<div id="jem" class="jem_typevenues jem_venueslist<?php echo $this->pageclass_sfx; ?>">

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
        <h1 class="componentheading">
            <?php echo $this->escape($this->params->get('page_heading')); ?>
        </h1>
    <?php endif; ?>

    <?php if (!$this->type) : ?>
        <div class="alert alert-info">
            <?php echo !empty($this->missingTypeId) ? Text::sprintf('COM_JEM_TYPEVENUES_TYPE_NOT_FOUND', (int) $this->missingTypeId) : Text::_('COM_JEM_TYPEVENUES_NO_TYPES'); ?>
        </div>
    <?php elseif ($this->type) : ?>
        <div class="jem-type-header mb-3">
            <h2 class="jem-type-title">
                <?php if ($this->type->icon) : ?>
                    <span class="<?php echo htmlspecialchars($this->type->icon, ENT_QUOTES, 'UTF-8'); ?>"></span>
                <?php endif; ?>
                <?php echo htmlspecialchars($this->type->name, ENT_QUOTES, 'UTF-8'); ?>
            </h2>
            <?php if ($this->type->description) : ?>
                <div class="jem-type-description"><?php echo htmlspecialchars($this->type->description, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($this->type && $layoutSuffix === 'responsive') : ?>
        <form action="<?php echo htmlspecialchars($this->action); ?>" method="post" name="adminForm" id="adminForm">
            <?php echo $this->loadTemplate('venues'); ?>

            <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
            <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
            <input type="hidden" name="boxchecked" value="0" />
            <input type="hidden" name="task" value="" />
            <input type="hidden" name="option" value="com_jem" />
            <input type="hidden" name="view" value="typevenues" />
            <input type="hidden" name="id" value="<?php echo (int) $this->type->id; ?>" />
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php elseif ($this->type) : ?>
        <?php echo $this->loadTemplate('venues'); ?>
    <?php endif; ?>

        <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>
    <div class="copyright">
        <?php echo JemOutput::footer(); ?>
    </div>
</div>
