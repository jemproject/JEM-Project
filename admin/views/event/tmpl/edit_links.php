<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_jem.jem-links', 'media/com_jem/css/jem-links.css');
$wa->registerAndUseScript('com_jem.jem-links', 'media/com_jem/js/jem-links.js');
?>

<div class="row jem-links-tab">
    <div class="col-12">
        <div class="adminform-subform">
            <div class="jem-links-global-options">
                <?php echo $this->form->renderField('links_layout', 'attribs'); ?>
                <?php echo $this->form->renderField('links_order', 'attribs'); ?>
            </div>

            <?php echo $this->form->renderField('event_links'); ?>

        </div>
    </div>
</div>

