<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

require_once JPATH_SITE . '/components/com_jem/classes/imagemetadata.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/featurepolicy.class.php';

class JFormFieldJemimagemetadata extends FormField
{
    protected $type = 'Jemimagemetadata';

    protected function getInput()
    {
        return $this->renderMetadata();
    }

    /** Render one image's controls inside its editor panel, without duplicate input IDs. */
    public function renderSlot(string $slot, string $part = 'all'): string
    {
        if (!in_array($part, ['all', 'alt', 'details'], true)) {
            return '';
        }

        return $this->renderMetadata($slot, $part);
    }

    private function renderMetadata(?string $selectedSlot = null, string $part = 'all'): string
    {
        // A beta package may be reinstalled without changing its version.
        $assetVersion = static function (string $file): string {
            $hash = is_file($file) ? hash_file('sha256', $file) : false;

            return $hash === false ? 'auto' : substr($hash, 0, 12);
        };
        $mediaRoot = JPATH_ROOT . '/media/com_jem/';
        HTMLHelper::_('stylesheet', 'com_jem/image-metadata.css', [
            'version' => $assetVersion($mediaRoot . 'css/image-metadata.css'), 'relative' => true,
        ]);
        HTMLHelper::_('script', 'com_jem/image-metadata.js', [
            'version' => $assetVersion($mediaRoot . 'js/image-metadata.js'), 'relative' => true,
        ], ['defer' => true]);
        $context = (string) $this->element['context'];
        $values = JemImageMetadata::normalise($this->value, $context);
        if ($selectedSlot !== null) {
            if (!array_key_exists($selectedSlot, $values)) {
                return '';
            }
            $values = [$selectedSlot => $values[$selectedSlot]];
        }
        $advanced = JemFeaturePolicy::current()->isAdvanced();
        $editorLanguage = '';
        if ($advanced && $part !== 'alt') {
            // Use Joomla's bundled editor, independently of the user's description editor.
            $app = Factory::getApplication();
            $assets = $app->getDocument()->getWebAssetManager();
            $assets->getRegistry()->addExtensionRegistryFile('plg_editors_tinymce');
            $assets->useScript('tinymce');
            $language = $app->getLanguage()->getTag();
            if (is_file(JPATH_ROOT . '/media/vendor/tinymce/langs/' . $language . '.js')) {
                $editorLanguage = $language;
            }
        }
        if ($part === 'details' && !$advanced) {
            $values = array_filter($values, static fn ($value) => $value['caption'] !== '' || !empty($value['credit']));
            if ($values === []) {
                return '';
            }
        }
        $inlineHelp = filter_var((string) $this->element['inlinehelp'], FILTER_VALIDATE_BOOLEAN);
        $helpClass = $inlineHelp ? 'form-text hide-aware-inline-help d-none' : 'form-text';
        $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $linkLabels = '';
        foreach (['new_tab', 'same_tab', 'parent', 'top'] as $targetLabel) {
            $linkLabels .= ' data-jem-link-' . str_replace('_', '-', $targetLabel) . '="'
                . $escape(Text::_('COM_JEM_IMAGE_METADATA_LINK_' . strtoupper($targetLabel))) . '"';
        }
        $wrapperId = $this->id . ($selectedSlot !== null ? '_' . $selectedSlot . '_controls' : '')
            . ($part !== 'all' ? '_' . $part : '');
        $html = '<div class="jem-image-metadata jem-image-metadata--' . $part . '" id="' . $escape($wrapperId) . '">';
        foreach ($values as $slot => $value) {
            $id = $this->id . '_' . $slot;
            $name = $this->name . '[' . $slot . ']';
            $title = $context === 'event'
                ? ($slot === 'fullimage' ? 'COM_JEM_EVENT_FULLIMAGE' : 'COM_JEM_EVENT_INTRO_IMAGE')
                : 'COM_JEM_IMAGE';
            $alt = $value['alt'];
            if ($context === 'venue' && $alt === '') {
                $alt = JemImageMetadata::alt($this->form->getValue('locimage_alt', null, ''));
            }
            $legendClass = $selectedSlot !== null ? ' class="visually-hidden"' : '';
            $html .= '<fieldset class="jem-image-metadata__slot"><legend' . $legendClass . '>' . $escape(Text::_($title)) . '</legend>';
            if ($part !== 'details') {
                $html .= '<div class="jem-image-upload-row"><div class="jem-image-upload-label">'
                    . '<label for="' . $escape($id . '_alt') . '">' . $escape(Text::_('COM_JEM_IMAGE_METADATA_ALT')) . '</label></div>';
                $html .= '<div class="jem-image-upload-control"><input type="text" class="form-control" maxlength="255" id="' . $escape($id . '_alt')
                    . '" name="' . $escape($name . '[alt]') . '" value="' . $escape($alt) . '"'
                    . ($inlineHelp ? '' : ' aria-describedby="' . $escape($id . '_alt-desc') . '"') . '>';
                $html .= '<div class="' . $helpClass . '" id="' . $escape($id . '_alt-desc') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_ALT_SHORT_DESC')) . '</div></div></div>';
            }
            if ($part !== 'alt' && $advanced) {
                $hasText = $value['caption'] !== '' || !empty($value['credit']);
                $html .= '<details class="jem-image-metadata__details"' . ($hasText ? ' open' : '') . '>'
                    . '<summary>' . $escape(Text::_('COM_JEM_IMAGE_METADATA_DETAILS')) . '</summary>'
                    . '<div class="jem-image-metadata__cards"><fieldset class="jem-image-metadata__card">'
                    . '<legend>' . $escape(Text::_('COM_JEM_IMAGE_METADATA_CAPTION')) . '</legend>'
                    . '<p class="jem-image-metadata__hint" id="' . $escape($id . '_caption-desc') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_CAPTION_SHORT_DESC')) . '</p>';
                $html .= '<label class="visually-hidden" for="' . $escape($id . '_caption') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_CAPTION')) . '</label>';
                $html .= '<textarea class="form-control" rows="2" maxlength="4096" id="' . $escape($id . '_caption')
                    . '" data-jem-caption-editor data-jem-editor-language="' . $escape($editorLanguage)
                    . '" data-jem-editor-base="' . $escape(Uri::root(true) . '/media/vendor/tinymce')
                    . '"'
                    . ' name="' . $escape($name . '[caption]') . '"'
                    . ' aria-describedby="' . $escape($id . '_caption-desc') . '"'
                    . '>' . $escape($value['caption']) . '</textarea>';
                $html .= '<div class="jem-image-metadata__alignment"><label for="' . $escape($id . '_align') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_ALIGNMENT')) . '</label>'
                    . '<select class="form-select" id="' . $escape($id . '_align') . '" name="' . $escape($name . '[align]') . '">';
                foreach (['left', 'center', 'right'] as $alignment) {
                    $html .= '<option value="' . $alignment . '"'
                        . (($value['align'] ?? 'left') === $alignment ? ' selected' : '') . '>'
                        . $escape(Text::_('COM_JEM_IMAGE_METADATA_ALIGN_' . strtoupper($alignment))) . '</option>';
                }
                $html .= '</select></div></fieldset>';
                $html .= '<fieldset class="jem-image-metadata__card">'
                    . '<legend>' . $escape(Text::_('COM_JEM_IMAGE_METADATA_CREDITS')) . '</legend>'
                    . '<p class="jem-image-metadata__hint" id="' . $escape($id . '_credit-desc') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_CREDITS_SHORT_DESC')) . '</p>'
                    . '<label class="visually-hidden" for="' . $escape($id . '_credit') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_CREDITS')) . '</label>'
                    . '<textarea class="form-control" rows="2" maxlength="4096" id="' . $escape($id . '_credit')
                    . '" data-jem-credit-editor data-jem-editor-language="' . $escape($editorLanguage)
                    . '" data-jem-editor-base="' . $escape(Uri::root(true) . '/media/vendor/tinymce')
                    . '"' . $linkLabels
                    . ' name="' . $escape($name . '[credit]') . '"'
                    . ' aria-describedby="' . $escape($id . '_credit-desc') . '"'
                    . '>' . $escape($value['credit'] ?? '') . '</textarea>';
                $labelMode = JemImageMetadata::creditLabelMode($value['credit_label'] ?? 'image_credit');
                $html .= '<div class="jem-image-metadata__alignment"><label for="' . $escape($id . '_credit_label') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_CREDIT_LABEL')) . '</label>'
                    . '<select class="form-select" data-jem-credit-label data-jem-reset-value="image_credit" id="'
                    . $escape($id . '_credit_label') . '" name="' . $escape($name . '[credit_label]') . '">';
                foreach (JemImageMetadata::CREDIT_LABELS as $label) {
                    $html .= '<option value="' . $label . '"' . ($labelMode === $label ? ' selected' : '') . '>'
                        . $escape(Text::_('COM_JEM_IMAGE_METADATA_LABEL_' . strtoupper($label))) . '</option>';
                }
                $html .= '</select></div><div class="jem-image-metadata__custom-label" data-jem-credit-custom>'
                    . '<label for="' . $escape($id . '_credit_label_custom') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_CUSTOM_LABEL')) . '</label>'
                    . '<input type="text" class="form-control" maxlength="80" id="' . $escape($id . '_credit_label_custom')
                    . '" name="' . $escape($name . '[credit_label_custom]') . '" value="'
                    . $escape($value['credit_label_custom'] ?? '') . '"></div>';
                $html .= '<div class="jem-image-metadata__alignment"><label for="' . $escape($id . '_footer') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_CREDIT_POSITION')) . '</label>'
                    . '<select class="form-select" data-jem-reset-value="0" id="' . $escape($id . '_footer')
                    . '" name="' . $escape($name . '[footer]') . '"'
                    . ' aria-describedby="' . $escape($id . '_footer-desc') . '"'
                    . '><option value="0"' . (empty($value['footer']) ? ' selected' : '') . '>'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_POSITION_BELOW')) . '</option>'
                    . '<option value="1"' . (!empty($value['footer']) ? ' selected' : '') . '>'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_POSITION_SECTION')) . '</option></select></div>'
                    . '<div class="form-text" id="' . $escape($id . '_footer-desc') . '">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_CREDIT_POSITION_DESC')) . '</div></fieldset></div></details>';
            } elseif ($part !== 'alt' && ($value['caption'] !== '' || !empty($value['credit']))) {
                $html .= '<p class="alert alert-info jem-image-metadata__notice">'
                    . $escape(Text::_('COM_JEM_IMAGE_METADATA_EXISTING')) . '</p>';
            }
            $html .= '</fieldset>';
        }

        return $html . '</div>';
    }
}
