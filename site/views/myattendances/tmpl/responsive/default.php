<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
?>
<div id="jem" class="jem_myattendances<?php echo $this->pageclass_sfx; ?>">
    <?php if ($this->needLoginFirst) {
        $uri = Uri::getInstance();
        $returnUrl = $uri->toString();
        $urlLogin = 'index.php?option=com_users&view=login&return=' . base64_encode($returnUrl); ?>
        <button class="btn btn-warning" onclick="location.href='<?php echo $uri->root() . $urlLogin; ?>'"
                type="button"><?php echo Text::_('COM_JEM_LOGIN_TO_ACCESS'); ?></button>

    <?php } else { ?>
        <div class="buttons">
            <?php
            $btn_params = array('task' => $this->task, 'print_link' => $this->print_link);
            echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
            ?>
        </div>

        <?php if ($this->params->get('show_page_heading', 1)) : ?>
            <h1 class="componentheading">
                <?php echo $this->escape($this->params->get('page_heading')); ?>
            </h1>
        <?php endif; ?>

        <!--table-->
        <?php echo $this->loadTemplate('attendances'); ?>

        <!--footer-->
        <div class="copyright">
            <?php echo JemOutput::footer(); ?>
        </div>

    <?php } ?>
</div>
