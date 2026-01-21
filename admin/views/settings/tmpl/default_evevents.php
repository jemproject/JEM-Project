<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

// Create shortcut to parameters.
$params = $this->state->get('params');
$params = $params->toArray();

// This checks if the config options have ever been saved. If they haven't they will fall back to the original settings.
$editoroptions = isset($params['show_publishing_options']);

if (!$editoroptions):
$params['show_publishing_options'] = '1';
$params['show_article_options'] = '1';
$params['show_urls_images_backend'] = '0';
$params['show_urls_images_frontend'] = '0';
endif;

$group = 'globalattribs';
?>

<div class="width-100" style="padding: 10px 1vw;">
    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_JEM_SETTINGS_EVENT_PART'); ?></legend>
        <ul class="adminformlist">
            <?php foreach ($this->form->getFieldset('evevents') as $field): ?>
                <li><div class="label-form"><?php echo $this->form->renderfield($field->fieldname,$group); ?></div></li>
            <?php endforeach; ?>
        </ul>
        <ul class="adminformlist">
            <?php foreach ($this->form->getFieldset('basic') as $field): ?>
                <li><div class="label-form"><?php echo $this->form->renderfield($field->fieldname,$group); ?></div></li>
            <?php endforeach; ?>
        </ul>
    </fieldset>
</div>
