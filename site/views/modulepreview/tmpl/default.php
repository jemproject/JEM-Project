<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

?>
<div id="jem" class="jem_module_preview">
    <?php if ($this->params->get('show_page_heading', 1)) : ?>
        <h1 class="componentheading"><?php echo $this->escape($this->params->get('page_heading') ?: $this->pageTitle); ?></h1>
    <?php endif; ?>
    <?php echo ModuleHelper::renderModule($this->module, array('style' => 'none')); ?>
</div>
