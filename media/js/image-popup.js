/** JEM image window: build DOM nodes, never execute caption markup as script. */
(function () {
    'use strict';

    const scriptUrl = document.currentScript.src;
    const stylesheet = new URL('../css/image-metadata.css', scriptUrl);
    stylesheet.search = new URL(scriptUrl).search;

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-jem-image-popup]');
        if (!link || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        const imageUrl = new URL(link.href, document.baseURI);
        if (!['http:', 'https:'].includes(imageUrl.protocol) || imageUrl.origin !== location.origin) {
            return;
        }

        const width = Math.min(Math.max(Number(link.dataset.jemImageWidth) || 640, 320), screen.availWidth);
        const height = Math.min(Math.max((Number(link.dataset.jemImageHeight) || 480) + 130, 320), screen.availHeight);
        const popup = window.open('', '_blank', 'popup,width=' + width + ',height=' + height + ',resizable=yes,scrollbars=yes');
        if (!popup) {
            return; // A blocked popup still leaves the normal image link usable.
        }

        popup.opener = null;
        const doc = popup.document;
        const head = doc.createElement('head');
        const charset = doc.createElement('meta');
        charset.charset = 'utf-8';
        const viewport = doc.createElement('meta');
        viewport.name = 'viewport';
        viewport.content = 'width=device-width, initial-scale=1';
        const title = doc.createElement('title');
        title.textContent = link.dataset.jemImageTitle;
        const css = doc.createElement('link');
        css.rel = 'stylesheet';
        css.href = stylesheet.href;
        head.append(charset, viewport, title, css);

        const body = doc.createElement('body');
        body.className = 'jem-image-popup';
        const figure = doc.createElement('figure');
        const image = doc.createElement('img');
        image.src = imageUrl.href;
        image.alt = link.dataset.jemImageAlt || '';
        const description = doc.createElement('figcaption');
        const heading = doc.createElement('div');
        heading.className = 'jem-image-popup__title';
        heading.textContent = link.dataset.jemImageTitle;
        description.append(heading);
        ['caption', 'credit'].forEach(function (field) {
            const template = link.querySelector('template[data-jem-image-' + field + ']');
            if (template && template.content.textContent.trim() !== '') {
                const line = doc.createElement('div');
                line.className = 'jem-image-popup__' + field;
                line.append(doc.importNode(template.content, true));
                description.append(line);
            }
        });
        figure.append(image, description);
        body.append(figure);
        doc.documentElement.lang = document.documentElement.lang;
        doc.documentElement.dir = document.documentElement.dir;
        doc.documentElement.replaceChildren(head, body);
        event.preventDefault();
    });
}());
