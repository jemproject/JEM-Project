/** Compact caption and credit editors shared by frontend and administrator forms. */
(function () {
    'use strict';
    const editorSelector = '[data-jem-credit-editor], [data-jem-caption-editor]';

    function textColour(style) {
        const match = style.match(/^\s*color\s*:\s*(#[a-f0-9]{6}|#[a-f0-9]{3}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))\s*;?\s*$/i);
        if (!match) return '';
        const colour = match[1].toLowerCase();
        if (colour[0] === '#') {
            return colour.length === 4 ? '#' + colour.slice(1).split('').map(c => c + c).join('') : colour;
        }
        const channels = colour.match(/\d+/g).map(Number);
        return channels.every(c => c <= 255) ? '#' + channels.map(c => c.toString(16).padStart(2, '0')).join('') : '';
    }

    // Editor serialization uses fresh, allowlisted nodes, never pasted HTML.
    // The PHP normaliser remains authoritative when saving and displaying metadata.
    function safeMarkup(html) {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const output = document.createElement('div');
        function copy(parent, destination) {
            Array.from(parent.childNodes).forEach(function (node) {
                if (node.nodeType === 3) {
                    destination.appendChild(document.createTextNode(node.nodeValue));
                    return;
                }
                if (node.nodeType !== 1 || /^(SCRIPT|STYLE|SVG|MATH|IFRAME|OBJECT|EMBED|TEMPLATE)$/.test(node.tagName)) {
                    return;
                }
                let tag = node.tagName.toLowerCase();
                if (tag === 'b') tag = 'strong';
                if (tag === 'i') tag = 'em';
                let href = '';
                if (tag === 'a') {
                    href = (node.getAttribute('href') || '').trim();
                    try {
                        if (!/^https?:\/\//i.test(href) || /[\x00-\x20\x7f]/.test(href) || href.length > 2048) {
                            throw new Error('Invalid credit URL');
                        }
                        new URL(href);
                    } catch (_) {
                        tag = '';
                    }
                }
                const colour = tag === 'span' ? textColour(node.getAttribute('style') || '') : '';
                if (['a', 'strong', 'em', 'br'].includes(tag) || colour) {
                    const element = document.createElement(tag);
                    if (tag === 'a') {
                        element.setAttribute('href', href);
                        const target = node.getAttribute('target');
                        if (['_blank', '_self', '_parent', '_top'].includes(target)) {
                            element.setAttribute('target', target);
                            if (target === '_blank') element.setAttribute('rel', 'noopener noreferrer');
                        }
                    }
                    if (colour) element.setAttribute('style', 'color: ' + colour + ';');
                    copy(node, element);
                    destination.appendChild(element);
                } else {
                    // TinyMCE uses blocks internally. Persist line breaks, not paragraphs
                    // that the server would flatten into adjacent words.
                    const block = /^(P|DIV|LI|H[1-6])$/.test(node.tagName);
                    if (block && destination.lastChild && destination.lastChild.nodeName !== 'BR') {
                        destination.appendChild(document.createElement('br'));
                    }
                    copy(node, destination);
                    if (block && node.nextSibling && destination.lastChild && destination.lastChild.nodeName !== 'BR') {
                        destination.appendChild(document.createElement('br'));
                    }
                }
            });
        }
        copy(parsed.body, output);
        return output.textContent.trim() ? output.innerHTML : '';
    }

    function editor(field) {
        const details = field.closest('.jem-image-metadata__details');
        if (details && !details.open) return;
        if (!window.tinymce || field.dataset.jemEditorStarted) return;
        field.dataset.jemEditorStarted = '1';
        const language = field.dataset.jemEditorLanguage;
        const caption = field.hasAttribute('data-jem-caption-editor');
        window.tinymce.init({
            target: field,
            base_url: field.dataset.jemEditorBase,
            suffix: '.min',
            license_key: 'gpl',
            ...(language ? {language, language_url: field.dataset.jemEditorBase + '/langs/' + language + '.js'} : {}),
            plugins: caption ? '' : 'link',
            toolbar: caption ? 'bold italic forecolor' : 'link unlink forecolor',
            menubar: false,
            statusbar: false,
            branding: false,
            promotion: false,
            height: 160,
            iframe_aria_text: field.labels.length ? field.labels[0].textContent : '',
            min_height: 120,
            resize: false,
            contextmenu: false,
            link_title: false,
            ...(caption ? {} : {
                link_target_list: [
                    {title: field.dataset.jemLinkNewTab, value: '_blank'},
                    {title: field.dataset.jemLinkSameTab, value: '_self'},
                    {title: field.dataset.jemLinkParent, value: '_parent'},
                    {title: field.dataset.jemLinkTop, value: '_top'}
                ],
                link_default_target: '_blank'
            }),
            link_default_protocol: 'https',
            convert_urls: false,
            paste_as_text: true,
            newline_behavior: 'linebreak',
            indent: false,
            valid_elements: 'p,br,strong/b,em/i,a[href|target|rel],span[style]',
            valid_styles: {span: 'color'},
            content_style: 'body {font-family:system-ui,sans-serif;font-size:14px;margin:8px;} p {margin:0;} a {overflow-wrap:anywhere;}',
            setup: function (instance) {
                instance.on('GetContent', function (event) {
                    if (event.save) event.content = safeMarkup(event.content);
                });
                instance.on('input change undo redo SetContent', function () {
                    instance.save();
                });
                instance.on('init', function () {
                    instance.save();
                });
            }
        }).catch(function () {
            // Keep the original textarea usable if a site's editor assets fail to load.
            const instance = window.tinymce.get(field.id);
            if (instance) instance.remove();
            field.style.display = '';
            delete field.dataset.jemEditorStarted;
        });
    }

    function sync(select) {
        const card = select.closest('.jem-image-metadata__card');
        const custom = card && card.querySelector('[data-jem-credit-custom]');
        if (custom) {
            custom.hidden = select.value !== 'custom';
        }
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-jem-credit-label]')) {
            sync(event.target);
        }
        if (event.target.matches(editorSelector)) {
            const instance = window.tinymce && window.tinymce.get(event.target.id);
            if (instance) instance.setContent(event.target.value);
        }
    });

    function initialise() {
        document.querySelectorAll('[data-jem-credit-label]').forEach(sync);
        document.querySelectorAll(editorSelector).forEach(editor);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
    document.addEventListener('joomla:updated', initialise);
    document.addEventListener('toggle', function (event) {
        if (event.target.matches('.jem-image-metadata__details') && event.target.open) {
            event.target.querySelectorAll(editorSelector).forEach(editor);
        }
    }, true);
    document.addEventListener('reset', function (event) {
        window.setTimeout(function () {
            event.target.querySelectorAll(editorSelector).forEach(function (field) {
                const instance = window.tinymce && window.tinymce.get(field.id);
                if (instance) instance.setContent(field.value);
            });
            event.target.querySelectorAll('.jem-image-metadata__details').forEach(function (details) {
                details.open = Array.from(details.querySelectorAll('textarea')).some(field => safeMarkup(field.value) !== '');
            });
            initialise();
        }, 0);
    });
}());
