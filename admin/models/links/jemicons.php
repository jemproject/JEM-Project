<?php

/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

class JFormFieldJemicons extends FormField
{
    protected $type = 'Jemicons';

    protected function getInput()
    {
        $icons = $this->getIcons();
        $options = [];
        $options[] = HTMLHelper::_('select.option', '', Text::_('JNONE'));

        foreach ($icons as $iconClass) {
            $options[] = HTMLHelper::_('select.option', $iconClass, $iconClass);
        }

        $attributes = [
            'id' => $this->id,
            'class' => 'form-select jem-icon-select',
        ];

        $html = [];
        $html[] = '<div class="jem-icon-field">';
        $html[] = HTMLHelper::_(
            'select.genericlist',
            $options,
            $this->name,
            $attributes,
            'value',
            'text',
            $this->value
        );

        $html[] = '<span class="jem-icon-preview" aria-hidden="true">';
        $html[] = $this->value ? '<span class="' . htmlspecialchars($this->value, ENT_QUOTES, 'UTF-8') . '"></span>' : '';
        $html[] = '</span>';
        $html[] = '</div>';

        $this->loadAssets();

        return implode('', $html);
    }

    protected function getIcons()
    {
        return array(
            'fa fa-address-book',
            'fa fa-address-card',
            'fa fa-adjust',
            'fa fa-align-center',
            'fa fa-align-justify',
            'fa fa-align-left',
            'fa fa-align-right',
            'fa fa-anchor',
            'fa fa-archive',
            'fa fa-area-chart',
            'fa fa-arrow-circle-down',
            'fa fa-arrow-circle-left',
            'fa fa-arrow-circle-o-down',
            'fa fa-arrow-circle-o-left',
            'fa fa-arrow-circle-o-right',
            'fa fa-arrow-circle-o-up',
            'fa fa-arrow-circle-right',
            'fa fa-arrow-circle-up',
            'fa fa-arrow-down',
            'fa fa-arrow-left',
            'fa fa-arrow-right',
            'fa fa-arrow-up',
            'fa fa-arrows',
            'fa fa-arrows-alt',
            'fa fa-arrows-h',
            'fa fa-arrows-v',
            'fa fa-asterisk',
            'fa fa-at',
            'fa fa-automobile',
            'fa fa-backward',
            'fa fa-balance-scale',
            'fa fa-ban',
            'fa fa-bank',
            'fa fa-bar-chart',
            'fa fa-bar-chart-o',
            'fa fa-barcode',
            'fa fa-bars',
            'fa fa-battery',
            'fa fa-battery-0',
            'fa fa-battery-1',
            'fa fa-battery-2',
            'fa fa-battery-3',
            'fa fa-battery-4',
            'fa fa-battery-empty',
            'fa fa-battery-full',
            'fa fa-battery-half',
            'fa fa-battery-quarter',
            'fa fa-battery-three-quarters',
            'fa fa-bed',
            'fa fa-beer',
            'fa fa-bell',
            'fa fa-bell-o',
            'fa fa-bell-slash',
            'fa fa-bell-slash-o',
            'fa fa-bicycle',
            'fa fa-binoculars',
            'fa fa-birthday-cake',
            'fa fa-bluetooth',
            'fa fa-bluetooth-b',
            'fa fa-bolt',
            'fa fa-book',
            'fa fa-bookmark',
            'fa fa-bookmark-o',
            'fa fa-briefcase',
            'fa fa-bug',
            'fa fa-building',
            'fa fa-building-o',
            'fa fa-bullhorn',
            'fa fa-bullseye',
            'fa fa-bus',
            'fa fa-cab',
            'fa fa-calculator',
            'fa fa-calendar',
            'fa fa-calendar-check-o',
            'fa fa-calendar-minus-o',
            'fa fa-calendar-o',
            'fa fa-calendar-plus-o',
            'fa fa-calendar-times-o',
            'fa fa-camera',
            'fa fa-camera-retro',
            'fa fa-car',
            'fa fa-caret-down',
            'fa fa-caret-left',
            'fa fa-caret-right',
            'fa fa-caret-square-o-down',
            'fa fa-caret-square-o-left',
            'fa fa-caret-square-o-right',
            'fa fa-caret-square-o-up',
            'fa fa-caret-up',
            'fa fa-cart-arrow-down',
            'fa fa-cart-plus',
            'fa fa-certificate',
            'fa fa-check',
            'fa fa-check-circle',
            'fa fa-check-circle-o',
            'fa fa-check-square',
            'fa fa-check-square-o',
            'fa fa-chevron-circle-down',
            'fa fa-chevron-circle-left',
            'fa fa-chevron-circle-right',
            'fa fa-chevron-circle-up',
            'fa fa-chevron-down',
            'fa fa-chevron-left',
            'fa fa-chevron-right',
            'fa fa-chevron-up',
            'fa fa-child',
            'fa fa-circle',
            'fa fa-circle-o',
            'fa fa-circle-o-notch',
            'fa fa-circle-thin',
            'fa fa-clipboard',
            'fa fa-clock-o',
            'fa fa-clone',
            'fa fa-close',
            'fa fa-cloud',
            'fa fa-cloud-download',
            'fa fa-cloud-upload',
            'fa fa-code',
            'fa fa-code-fork',
            'fa fa-coffee',
            'fa fa-cog',
            'fa fa-cogs',
            'fa fa-comment',
            'fa fa-comment-o',
            'fa fa-commenting',
            'fa fa-commenting-o',
            'fa fa-comments',
            'fa fa-comments-o',
            'fa fa-compass',
            'fa fa-compress',
            'fa fa-copy',
            'fa fa-copyright',
            'fa fa-credit-card',
            'fa fa-credit-card-alt',
            'fa fa-crop',
            'fa fa-crosshairs',
            'fa fa-cube',
            'fa fa-cubes',
            'fa fa-cut',
            'fa fa-cutlery',
            'fa fa-dashboard',
            'fa fa-database',
            'fa fa-desktop',
            'fa fa-diamond',
            'fa fa-dot-circle-o',
            'fa fa-download',
            'fa fa-edit',
            'fa fa-ellipsis-h',
            'fa fa-ellipsis-v',
            'fa fa-envelope',
            'fa fa-envelope-o',
            'fa fa-envelope-open',
            'fa fa-envelope-open-o',
            'fa fa-envelope-square',
            'fa fa-eraser',
            'fa fa-exchange',
            'fa fa-exclamation',
            'fa fa-exclamation-circle',
            'fa fa-exclamation-triangle',
            'fa fa-expand',
            'fa fa-external-link',
            'fa fa-external-link-square',
            'fa fa-eye',
            'fa fa-eye-slash',
            'fa fa-eyedropper',
            'fa fa-fax',
            'fa fa-feed',
            'fa fa-female',
            'fa fa-file',
            'fa fa-file-archive-o',
            'fa fa-file-audio-o',
            'fa fa-file-code-o',
            'fa fa-file-excel-o',
            'fa fa-file-image-o',
            'fa fa-file-movie-o',
            'fa fa-file-o',
            'fa fa-file-pdf-o',
            'fa fa-file-photo-o',
            'fa fa-file-picture-o',
            'fa fa-file-powerpoint-o',
            'fa fa-file-sound-o',
            'fa fa-file-text',
            'fa fa-file-text-o',
            'fa fa-file-video-o',
            'fa fa-file-word-o',
            'fa fa-file-zip-o',
            'fa fa-files-o',
            'fa fa-film',
            'fa fa-filter',
            'fa fa-fire',
            'fa fa-flag',
            'fa fa-flag-checkered',
            'fa fa-flag-o',
            'fa fa-flash',
            'fa fa-flask',
            'fa fa-folder',
            'fa fa-folder-o',
            'fa fa-folder-open',
            'fa fa-folder-open-o',
            'fa fa-frown-o',
            'fa fa-gamepad',
            'fa fa-gavel',
            'fa fa-gear',
            'fa fa-gears',
            'fa fa-gift',
            'fa fa-glass',
            'fa fa-globe',
            'fa fa-graduation-cap',
            'fa fa-group',
            'fa fa-handshake-o',
            'fa fa-hashtag',
            'fa fa-hdd-o',
            'fa fa-headphones',
            'fa fa-heart',
            'fa fa-heart-o',
            'fa fa-history',
            'fa fa-home',
            'fa fa-hotel',
            'fa fa-hourglass',
            'fa fa-hourglass-end',
            'fa fa-hourglass-half',
            'fa fa-hourglass-o',
            'fa fa-hourglass-start',
            'fa fa-id-badge',
            'fa fa-id-card',
            'fa fa-id-card-o',
            'fa fa-image',
            'fa fa-inbox',
            'fa fa-industry',
            'fa fa-info',
            'fa fa-info-circle',
            'fa fa-institution',
            'fa fa-key',
            'fa fa-keyboard-o',
            'fa fa-language',
            'fa fa-laptop',
            'fa fa-leaf',
            'fa fa-legal',
            'fa fa-level-down',
            'fa fa-level-up',
            'fa fa-life-ring',
            'fa fa-lightbulb-o',
            'fa fa-line-chart',
            'fa fa-link',
            'fa fa-list',
            'fa fa-location-arrow',
            'fa fa-lock',
            'fa fa-magic',
            'fa fa-map',
            'fa fa-map-marker',
            'fa fa-map-o',
            'fa fa-map-pin',
            'fa fa-map-signs',
            'fa fa-meh-o',
            'fa fa-microchip',
            'fa fa-microphone',
            'fa fa-microphone-slash',
            'fa fa-minus',
            'fa fa-minus-circle',
            'fa fa-mobile',
            'fa fa-money',
            'fa fa-moon-o',
            'fa fa-mortar-board',
            'fa fa-motorcycle',
            'fa fa-mouse-pointer',
            'fa fa-music',
            'fa fa-navicon',
            'fa fa-newspaper-o',
            'fa fa-paint-brush',
            'fa fa-paper-plane',
            'fa fa-paper-plane-o',
            'fa fa-paperclip',
            'fa fa-paste',
            'fa fa-pause',
            'fa fa-pause-circle',
            'fa fa-paw',
            'fa fa-pencil',
            'fa fa-pencil-square',
            'fa fa-pencil-square-o',
            'fa fa-phone',
            'fa fa-phone-square',
            'fa fa-photo',
            'fa fa-picture-o',
            'fa fa-pie-chart',
            'fa fa-plane',
            'fa fa-plug',
            'fa fa-plus',
            'fa fa-plus-circle',
            'fa fa-plus-square',
            'fa fa-plus-square-o',
            'fa fa-podcast',
            'fa fa-power-off',
            'fa fa-print',
            'fa fa-puzzle-piece',
            'fa fa-qrcode',
            'fa fa-question',
            'fa fa-question-circle',
            'fa fa-question-circle-o',
            'fa fa-quote-left',
            'fa fa-quote-right',
            'fa fa-random',
            'fa fa-refresh',
            'fa fa-registered',
            'fa fa-remove',
            'fa fa-reorder',
            'fa fa-reply',
            'fa fa-reply-all',
            'fa fa-road',
            'fa fa-rocket',
            'fa fa-rss',
            'fa fa-rss-square',
            'fa fa-search',
            'fa fa-search-minus',
            'fa fa-search-plus',
            'fa fa-send',
            'fa fa-server',
            'fa fa-share',
            'fa fa-share-alt',
            'fa fa-share-square',
            'fa fa-shield',
            'fa fa-shopping-bag',
            'fa fa-shopping-basket',
            'fa fa-shopping-cart',
            'fa fa-sign-in',
            'fa fa-sign-out',
            'fa fa-signal',
            'fa fa-sitemap',
            'fa fa-sliders',
            'fa fa-smile-o',
            'fa fa-sort',
            'fa fa-spinner',
            'fa fa-square',
            'fa fa-star',
            'fa fa-star-half',
            'fa fa-star-o',
            'fa fa-sticky-note',
            'fa fa-street-view',
            'fa fa-suitcase',
            'fa fa-sun-o',
            'fa fa-tablet',
            'fa fa-tag',
            'fa fa-tags',
            'fa fa-tasks',
            'fa fa-taxi',
            'fa fa-television',
            'fa fa-terminal',
            'fa fa-thumbs-down',
            'fa fa-thumbs-o-down',
            'fa fa-thumbs-o-up',
            'fa fa-thumbs-up',
            'fa fa-ticket',
            'fa fa-times',
            'fa fa-times-circle',
            'fa fa-times-circle-o',
            'fa fa-trash',
            'fa fa-trash-o',
            'fa fa-tree',
            'fa fa-trophy',
            'fa fa-truck',
            'fa fa-tv',
            'fa fa-university',
            'fa fa-unlock',
            'fa fa-unlock-alt',
            'fa fa-upload',
            'fa fa-user',
            'fa fa-user-circle',
            'fa fa-user-o',
            'fa fa-user-plus',
            'fa fa-user-secret',
            'fa fa-user-times',
            'fa fa-users',
            'fa fa-video-camera',
            'fa fa-volume-down',
            'fa fa-volume-off',
            'fa fa-volume-up',
            'fa fa-warning',
            'fa fa-wheelchair',
            'fa fa-wifi',
            'fa fa-window-close',
            'fa fa-wrench'
        );
    }

    private function loadAssets(): void
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        $wa->addInlineStyle('
            .jem-icon-field {
                display: flex;
                align-items: center;
                gap: .5rem;
                width: 100%;
            }

            .jem-icon-field .jem-icon-select {
                flex: 1 1 auto;
                min-width: 0;
            }

            .jem-icon-preview {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.25rem;
                min-width: 2.25rem;
                height: 2.25rem;
                border: 1px solid #d7dce1;
                border-radius: .25rem;
                background: #f8f9fa;
                font-size: 1rem;
            }
        ');

        $wa->addInlineScript('
            document.addEventListener("change", function(event) {
                if (!event.target.classList.contains("jem-icon-select")) {
                    return;
                }

                const wrapper = event.target.closest(".jem-icon-field");

                if (!wrapper) {
                    return;
                }

                const preview = wrapper.querySelector(".jem-icon-preview");

                if (!preview) {
                    return;
                }

                preview.innerHTML = "";

                if (event.target.value) {
                    const icon = document.createElement("span");
                    icon.className = event.target.value;
                    preview.appendChild(icon);
                }
            });
        ');
    }
}