<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;

/**
 * Frontend event/venue image selector.
 */
class JFormFieldImageselectevent extends ListField
{
    protected $type = 'Imageselectevent';

    protected function getInput()
    {
        $this->size = 1;
        $this->element['size'] = 1;

        $attr  = ' class="' . trim((string) ($this->class ?: 'form-select')) . '"';
        $attr .= $this->required ? ' required aria-required="true"' : '';
        $attr .= $this->disabled ? ' disabled="disabled"' : '';
        $attr .= $this->onchange ? ' onchange="' . $this->onchange . '"' : '';

        $html = HTMLHelper::_(
            'select.genericlist',
            $this->getOptions(),
            $this->name,
            trim($attr),
            'value',
            'text',
            $this->value,
            $this->id
        );

        Factory::getApplication()->getDocument()->getWebAssetManager()
            ->usePreset('choicesjs')
            ->useScript('webcomponent.field-fancy-select');
        $this->addFancySelectStyle();

        $fancyAttr  = ' class="jem-image-fancy-select"';
        $fancyAttr .= ' style="width: min(100%, 24rem); max-width: 100%;"';
        $fancyAttr .= $this->required ? ' required aria-required="true"' : '';
        $fancyAttr .= $this->disabled ? ' disabled="disabled"' : '';
        $fancyAttr .= ' placeholder="' . Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS') . '"';

        return '<joomla-field-fancy-select ' . $fancyAttr . '>' . $html . '</joomla-field-fancy-select>';
    }

    protected function getOptions()
    {
        $options = array(
            HTMLHelper::_('select.option', '', Text::_('COM_JEM_NO_IMAGE')),
        );

        $folder = in_array((string) $this->fieldname, array('locimage'), true) ? 'venues' : 'events';
        $path = JPATH_SITE . '/images/jem/' . $folder;

        if (!is_dir($path)) {
            return array_merge(parent::getOptions(), $options);
        }

        $images = Folder::files($path, '\.(jpg|jpeg|png|gif|webp|svg)$', false, false, array('index.html'));
        natcasesort($images);

        foreach ($images as $image) {
            $options[] = HTMLHelper::_('select.option', $image, $image);
        }

        return array_merge(parent::getOptions(), $options);
    }

    protected function addFancySelectStyle()
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        Factory::getApplication()->getDocument()->addStyleDeclaration('
joomla-field-fancy-select.jem-image-fancy-select,
joomla-field-fancy-select.jem-image-fancy-select .choices,
joomla-field-fancy-select.jem-image-fancy-select .choices__inner {
    width: min(100%, 24rem);
    max-width: 100%;
    box-sizing: border-box;
}
joomla-field-fancy-select.jem-image-fancy-select .choices__list--dropdown,
joomla-field-fancy-select.jem-image-fancy-select .choices__list[aria-expanded] {
    width: 100%;
    min-width: 100%;
    max-width: 100%;
    overflow-x: hidden;
    box-sizing: border-box;
}
joomla-field-fancy-select.jem-image-fancy-select > select {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    opacity: 0 !important;
    pointer-events: none !important;
}
joomla-field-fancy-select.jem-image-fancy-select .choices__list--single,
joomla-field-fancy-select.jem-image-fancy-select .choices__item {
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}
joomla-field-fancy-select.jem-image-fancy-select .choices__list--dropdown .choices__item,
joomla-field-fancy-select.jem-image-fancy-select .choices__list[aria-expanded] .choices__item {
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
joomla-field-fancy-select.jem-image-fancy-select .choices__input--cloned {
    min-width: 1ch !important;
    width: 1ch !important;
    max-width: 100% !important;
}
joomla-field-fancy-select.jem-image-fancy-select .choices__list,
joomla-field-fancy-select.jem-image-fancy-select .choices__list--dropdown .choices__list,
joomla-field-fancy-select.jem-image-fancy-select .choices__list[aria-expanded] .choices__list {
    overflow-x: hidden !important;
}
@media (max-width: 767.98px) {
    joomla-field-fancy-select.jem-image-fancy-select,
    joomla-field-fancy-select.jem-image-fancy-select .choices,
    joomla-field-fancy-select.jem-image-fancy-select .choices__inner {
        width: 100%;
        max-width: 100%;
    }
}
');
    }
}
