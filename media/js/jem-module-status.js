/**
 * Keep JEM module status ribbons proportional to the displayed image.
 */
(function () {
    'use strict';

    var containerSelector = '.jem-module-event-status-image';
    var ribbonSelector = '.jem-module-event-status-ribbon';
    var observedImages = new WeakSet();
    var resizeObserver = null;

    function clamp(value, minimum, maximum) {
        return Math.min(maximum, Math.max(minimum, value));
    }

    function setPixels(element, property, value) {
        element.style.setProperty(property, value.toFixed(2) + 'px');
    }

    function fitRibbonText(ribbon, targetFontSize) {
        var minimumFontSize = 9;
        var fontSize = targetFontSize;
        var attempts = 0;

        setPixels(ribbon, '--jem-module-status-font-size', fontSize);

        while (ribbon.scrollWidth > ribbon.clientWidth && fontSize > minimumFontSize && attempts < 4) {
            fontSize = Math.max(
                minimumFontSize,
                fontSize * (ribbon.clientWidth / ribbon.scrollWidth) * 0.97
            );
            setPixels(ribbon, '--jem-module-status-font-size', fontSize);
            attempts++;
        }
    }

    function resizeRibbon(container) {
        var image = container.querySelector('img');
        var ribbon = container.querySelector(ribbonSelector);

        if (!image || !ribbon) {
            return;
        }

        var imageWidth = image.getBoundingClientRect().width;
        if (!Number.isFinite(imageWidth) || imageWidth <= 0) {
            return;
        }

        var configuredScaleValue = parseInt(ribbon.dataset.jemModuleStatusScale || '100', 10);
        var baseFontSizeValue = parseFloat(ribbon.dataset.jemModuleStatusBaseFontSize || '0.75');
        var configuredScale = clamp(Number.isFinite(configuredScaleValue) ? configuredScaleValue : 100, 50, 200) / 100;
        var baseFontSize = clamp(Number.isFinite(baseFontSizeValue) ? baseFontSizeValue : 0.75, 0.58, 0.85);
        var rootFontSize = parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 16;
        var automaticFontScale = clamp(imageWidth / 200, 0.75, 4);
        var targetFontSize = clamp(
            baseFontSize * rootFontSize * automaticFontScale * configuredScale,
            9,
            40
        );
        var paddingBlock = clamp(targetFontSize * 0.42, 3, 18);
        var paddingInline = clamp(targetFontSize * 0.8, 6, 32);
        var horizontalOffset = clamp(imageWidth * 0.036 * configuredScale, 4, 32);

        setPixels(ribbon, '--jem-module-status-padding-block', paddingBlock);
        setPixels(ribbon, '--jem-module-status-padding-inline', paddingInline);
        setPixels(ribbon, '--jem-module-status-horizontal-offset', horizontalOffset);

        if (ribbon.classList.contains('jem-module-event-status-ribbon--diagonal-ascending')
            || ribbon.classList.contains('jem-module-event-status-ribbon--diagonal-descending')) {
            var minimumDiagonalWidth = Math.min(96, imageWidth * 1.25);
            var diagonalWidth = clamp(
                imageWidth * 0.768 * configuredScale,
                minimumDiagonalWidth,
                imageWidth * 1.25
            );

            setPixels(ribbon, '--jem-module-status-diagonal-width', diagonalWidth);
            setPixels(ribbon, '--jem-module-status-diagonal-top', diagonalWidth * 0.125);
            setPixels(ribbon, '--jem-module-status-diagonal-offset', diagonalWidth * -0.2708333333);
        }

        fitRibbonText(ribbon, targetFontSize);
    }

    function observeContainer(container) {
        var image = container.querySelector('img');

        if (!image || observedImages.has(image)) {
            return;
        }

        observedImages.add(image);
        image.addEventListener('load', function () {
            resizeRibbon(container);
        });

        if (resizeObserver) {
            resizeObserver.observe(image);
        }

        resizeRibbon(container);
    }

    function initialiseModuleStatusRibbons() {
        if ('ResizeObserver' in window) {
            resizeObserver = new ResizeObserver(function (entries) {
                entries.forEach(function (entry) {
                    var container = entry.target.closest(containerSelector);

                    if (container) {
                        resizeRibbon(container);
                    }
                });
            });
        } else {
            window.addEventListener('resize', function () {
                document.querySelectorAll(containerSelector).forEach(resizeRibbon);
            });
        }

        document.querySelectorAll(containerSelector).forEach(observeContainer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseModuleStatusRibbons);
    } else {
        initialiseModuleStatusRibbons();
    }
}());
