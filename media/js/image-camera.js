/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

(function () {
    'use strict';

    var modal = null;
    var video = null;
    var resultCanvas = null;
    var statusNode = null;
    var ratioNode = null;
    var cameraDeviceControl = null;
    var cameraDeviceSelect = null;
    var cameraFormatControl = null;
    var cameraFormatSelect = null;
    var livePanel = null;
    var resultPanel = null;
    var captureButton = null;
    var retakeButton = null;
    var useButton = null;
    var activeButton = null;
    var activeInput = null;
    var activeConfig = null;
    var activeStream = null;
    var activeDeviceId = '';
    var cameraRequestSequence = 0;
    var capturedBlob = null;
    var capturedMime = '';
    var capturedSourceBlob = null;
    var capturedSourceMime = '';
    var capturedWidth = 0;
    var capturedHeight = 0;
    var editorModal = null;
    var editorCanvas = null;
    var editorZoom = null;
    var editorHelp = null;
    var editorInput = null;
    var editorFile = null;
    var editorImage = null;
    var editorConfig = null;
    var editorScale = 1;
    var editorMinimumScale = 1;
    var editorOffsetX = 0;
    var editorOffsetY = 0;
    var editorPointer = null;
    var editorKeepSelectionOnCancel = false;
    var editorSourceFiles = new WeakMap();

    var fallbacks = {
        COM_JEM_CAMERA_DIALOG_TITLE: 'Take photo',
        COM_JEM_CAMERA_LIVE_PREVIEW: 'Live camera preview',
        COM_JEM_CAMERA_CAPTURE: 'Capture',
        COM_JEM_CAMERA_RETAKE: 'Retake',
        COM_JEM_CAMERA_USE_PHOTO: 'Use photo',
        COM_JEM_CAMERA_CANCEL: 'Cancel',
        COM_JEM_CAMERA_CLOSE: 'Close camera',
        COM_JEM_CAMERA_DEVICE_LABEL: 'Camera',
        COM_JEM_CAMERA_DEVICE_FALLBACK: 'Camera %s',
        COM_JEM_CAMERA_FORMAT_LABEL: 'Photo format',
        COM_JEM_CAMERA_SWITCHING: 'Switching camera...',
        COM_JEM_CAMERA_STARTING: 'Starting camera…',
        COM_JEM_CAMERA_PROCESSING: 'Preparing the image…',
        COM_JEM_CAMERA_READY: 'Frame the image and capture the photo.',
        COM_JEM_CAMERA_RESULT: 'Processed photo preview',
        COM_JEM_CAMERA_UNAVAILABLE: 'No camera is available in this browser or device.',
        COM_JEM_CAMERA_SECURE_CONTEXT_REQUIRED: 'Camera access requires HTTPS or localhost.',
        COM_JEM_CAMERA_PERMISSION_DENIED: 'Camera permission was not granted.',
        COM_JEM_CAMERA_PROCESSING_FAILED: 'The captured image could not be processed.',
        COM_JEM_CAMERA_OUTPUT_TOO_LARGE: 'The image cannot meet the configured size and resolution limits.',
        COM_JEM_CAMERA_FILE_ASSIGN_FAILED: 'The processed photo could not be attached to the form.',
        COM_JEM_CAMERA_RATIO_LABEL: 'Ratio',
        COM_JEM_CAMERA_MODE_CROP: 'Crop',
        COM_JEM_CAMERA_MODE_PAD: 'Pad',
        COM_JEM_CAMERA_MODE_NONE: 'Original ratio',
        COM_JEM_IMAGE_EDITOR_TITLE: 'Adjust image',
        COM_JEM_IMAGE_EDITOR_CROP_HELP: 'Drag and zoom the image to choose the visible area.',
        COM_JEM_IMAGE_EDITOR_PAD_HELP: 'The complete image will be centred horizontally and vertically inside the selected ratio.',
        COM_JEM_IMAGE_EDITOR_ZOOM: 'Zoom',
        COM_JEM_IMAGE_EDITOR_USE_IMAGE: 'Use image',
        COM_JEM_IMAGE_EDITOR_CLOSE: 'Close image editor'
    };

    function text(key) {
        if (window.Joomla && Joomla.Text && typeof Joomla.Text._ === 'function') {
            return Joomla.Text._(key, fallbacks[key] || key);
        }

        return fallbacks[key] || key;
    }

    function clampResolutionValue(input, value) {
        var minimum = Number(input.min) || 1;
        var maximum = Number(input.max) || minimum;
        var parsed = Number.parseInt(value, 10);

        if (!Number.isFinite(parsed)) {
            parsed = maximum;
        }

        return Math.max(minimum, Math.min(maximum, parsed));
    }

    function setResolutionControlValue(control, value) {
        var range = control.querySelector('[data-jem-image-resolution-range]');
        var number = control.querySelector('[data-jem-image-resolution-number]');

        if (!range || !number) {
            return;
        }

        var resolved = clampResolutionValue(range, value);
        var minimum = Number(range.min) || 1;
        var maximum = Number(range.max) || minimum;
        var progress = ((resolved - minimum) / Math.max(1, maximum - minimum)) * 100;
        range.value = String(resolved);
        number.value = String(resolved);
        range.style.setProperty('--jem-resolution-progress', progress.toFixed(3) + '%');
        control.querySelectorAll('[data-jem-image-resolution-value]').forEach(function (mark) {
            var active = Number(mark.dataset.jemImageResolutionValue) === resolved;
            mark.classList.toggle('jem-image-resolution-mark--active', active);
            mark.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function refreshResolutionMarks(control) {
        var range = control.querySelector('[data-jem-image-resolution-range]');

        if (!range) {
            return;
        }

        var minimum = Number(range.min) || 1;
        var maximum = Number(range.max) || minimum;

        control.querySelectorAll('[data-jem-image-resolution-value]').forEach(function (mark) {
            var value = Number(mark.dataset.jemImageResolutionValue);
            var visible = Number.isFinite(value) && value > minimum && value < maximum;

            mark.hidden = !visible;
            if (!visible) {
                return;
            }

            var position = ((value - minimum) / Math.max(1, maximum - minimum)) * 100;
            var labelOffset = position < 5 ? '0%' : (position > 95 ? '-100%' : '-50%');
            mark.style.setProperty('--jem-resolution-position', position.toFixed(3) + '%');
            mark.style.setProperty('--jem-resolution-label-offset', labelOffset);
        });
    }

    function applyRatioSelection(control, config) {
        var range = control.querySelector('[data-jem-image-resolution-range]');
        var number = control.querySelector('[data-jem-image-resolution-number]');
        var select = control.querySelector('[data-jem-image-ratio-select]');
        var option = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;

        if (!option) {
            return config;
        }

        if (range && number) {
            var minimum = Math.max(1, Number(option.dataset.jemImageResolutionMin) || Number(range.min) || 1);
            range.min = String(minimum);
            number.min = String(minimum);
            setResolutionControlValue(control, range.value);
            refreshResolutionMarks(control);
        }

        if (config) {
            config.mode = option.dataset.jemImageRatioMode || 'none';
            config.ratioWidth = Math.max(1, Number(option.dataset.jemImageRatioWidth) || 1);
            config.ratioHeight = Math.max(1, Number(option.dataset.jemImageRatioHeight) || 1);
        }

        return config;
    }

    function initialiseResolutionControls() {
        document.querySelectorAll('[data-jem-image-resolution-control]').forEach(function (control) {
            var range = control.querySelector('[data-jem-image-resolution-range]');
            var number = control.querySelector('[data-jem-image-resolution-number]');
            var ratio = control.querySelector('[data-jem-image-ratio-select]');

            if (ratio) {
                ratio.addEventListener('change', function () {
                    applyRatioSelection(control, null);
                    control.dispatchEvent(new CustomEvent('jem:image-ratio-change', {bubbles: true}));
                });
            }
            control.addEventListener('jem:image-resolution-reset', function () {
                if (ratio && control.dataset.jemImageRatioDefault) {
                    ratio.value = control.dataset.jemImageRatioDefault;
                    applyRatioSelection(control, null);
                }
                if (range && number) {
                    setResolutionControlValue(control, control.dataset.jemImageResolutionDefault);
                }
            });

            if (!range || !number) {
                return;
            }

            applyRatioSelection(control, null);
            setResolutionControlValue(control, range.value);
            range.addEventListener('input', function () {
                setResolutionControlValue(control, range.value);
            });
            number.addEventListener('change', function () {
                setResolutionControlValue(control, number.value);
            });
            control.addEventListener('click', function (event) {
                var mark = event.target.closest('[data-jem-image-resolution-value]');

                if (mark) {
                    setResolutionControlValue(control, mark.dataset.jemImageResolutionValue);
                }
            });
        });
    }

    function ensureModal() {
        if (modal) {
            return;
        }

        modal = document.createElement('div');
        modal.className = 'jem-camera-modal';
        modal.hidden = true;
        modal.innerHTML = [
            '<div class="jem-camera-modal__backdrop" data-jem-camera-close></div>',
            '<section class="jem-camera-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="jem-camera-title">',
            '  <header class="jem-camera-modal__header">',
            '    <h2 id="jem-camera-title"></h2>',
            '    <button type="button" class="jem-camera-modal__close" data-jem-camera-close aria-label=""></button>',
            '  </header>',
            '  <div class="jem-camera-modal__body">',
            '    <div class="jem-camera-options">',
            '      <div class="jem-camera-device" hidden>',
            '        <label for="jem-camera-device-select"></label>',
            '        <select id="jem-camera-device-select" class="form-select"></select>',
            '      </div>',
            '      <div class="jem-camera-format">',
            '        <label for="jem-camera-format-select"></label>',
            '        <select id="jem-camera-format-select" class="form-select"></select>',
            '      </div>',
            '    </div>',
            '    <div class="jem-camera-live">',
            '      <div class="jem-camera-frame">',
            '        <video autoplay muted playsinline></video>',
            '        <span class="jem-camera-live-label"></span>',
            '      </div>',
            '    </div>',
            '    <div class="jem-camera-result" hidden>',
            '      <canvas></canvas>',
            '      <p class="jem-camera-result__summary"></p>',
            '    </div>',
            '    <p class="jem-camera-status" role="status" aria-live="polite"></p>',
            '  </div>',
            '  <footer class="jem-camera-modal__footer">',
            '    <button type="button" class="btn btn-secondary jem-camera-action-button" data-jem-camera-capture></button>',
            '    <button type="button" class="btn btn-secondary jem-camera-action-button" data-jem-camera-retake hidden></button>',
            '    <button type="button" class="btn btn-secondary jem-camera-action-button" data-jem-camera-use hidden></button>',
            '    <button type="button" class="btn btn-secondary jem-camera-action-button" data-jem-camera-close></button>',
            '  </footer>',
            '</section>'
        ].join('');
        document.body.appendChild(modal);

        video = modal.querySelector('video');
        resultCanvas = modal.querySelector('canvas');
        statusNode = modal.querySelector('.jem-camera-status');
        ratioNode = modal.querySelector('.jem-camera-live-label');
        cameraDeviceControl = modal.querySelector('.jem-camera-device');
        cameraDeviceSelect = modal.querySelector('#jem-camera-device-select');
        cameraFormatControl = modal.querySelector('.jem-camera-format');
        cameraFormatSelect = modal.querySelector('#jem-camera-format-select');
        livePanel = modal.querySelector('.jem-camera-live');
        resultPanel = modal.querySelector('.jem-camera-result');
        captureButton = modal.querySelector('[data-jem-camera-capture]');
        retakeButton = modal.querySelector('[data-jem-camera-retake]');
        useButton = modal.querySelector('[data-jem-camera-use]');

        modal.querySelector('#jem-camera-title').textContent = text('COM_JEM_CAMERA_DIALOG_TITLE');
        modal.querySelector('.jem-camera-modal__close').setAttribute('aria-label', text('COM_JEM_CAMERA_CLOSE'));
        modal.querySelector('.jem-camera-modal__close').innerHTML = '<span aria-hidden="true">&times;</span>';
        cameraDeviceControl.querySelector('label').textContent = text('COM_JEM_CAMERA_DEVICE_LABEL');
        cameraFormatControl.querySelector('label').textContent = text('COM_JEM_CAMERA_FORMAT_LABEL');
        captureButton.textContent = text('COM_JEM_CAMERA_CAPTURE');
        retakeButton.textContent = text('COM_JEM_CAMERA_RETAKE');
        useButton.textContent = text('COM_JEM_CAMERA_USE_PHOTO');
        Array.prototype.forEach.call(modal.querySelectorAll('.jem-camera-modal__footer [data-jem-camera-close]'), function (button) {
            button.textContent = text('COM_JEM_CAMERA_CANCEL');
        });

        captureButton.addEventListener('click', capturePhoto);
        retakeButton.addEventListener('click', function () {
            startCamera(activeDeviceId);
        });
        useButton.addEventListener('click', usePhoto);
        cameraDeviceSelect.addEventListener('change', function () {
            startCamera(cameraDeviceSelect.value, true);
        });
        Array.prototype.forEach.call(modal.querySelectorAll('[data-jem-camera-close]'), function (node) {
            node.addEventListener('click', closeCamera);
        });
        document.addEventListener('keydown', function (event) {
            if (!modal.hidden && event.key === 'Escape') {
                closeCamera();
            }
        });
    }

    function setStatus(message, isError) {
        statusNode.textContent = message;
        statusNode.classList.toggle('jem-camera-status--error', Boolean(isError));
    }

    function profileModeLabel(config) {
        if (config.mode === 'crop') {
            return text('COM_JEM_CAMERA_MODE_CROP');
        }
        if (config.mode === 'pad') {
            return text('COM_JEM_CAMERA_MODE_PAD');
        }

        return text('COM_JEM_CAMERA_MODE_NONE');
    }

    function updateFrame() {
        var frame = modal.querySelector('.jem-camera-frame');
        var ratioWidth = Number(activeConfig.ratioWidth) || 4;
        var ratioHeight = Number(activeConfig.ratioHeight) || 3;

        if (activeConfig.mode === 'none' && video.videoWidth > 0 && video.videoHeight > 0) {
            ratioWidth = video.videoWidth;
            ratioHeight = video.videoHeight;
        }

        frame.style.aspectRatio = ratioWidth + ' / ' + ratioHeight;
        frame.style.setProperty('--jem-camera-ratio', String(ratioWidth / ratioHeight));
        frame.dataset.mode = activeConfig.mode;
        video.style.objectFit = activeConfig.mode === 'pad' ? 'contain' : 'cover';
        frame.style.backgroundColor = activeConfig.paddingColor || '#000000';
        ratioNode.textContent = activeConfig.mode === 'none'
            ? profileModeLabel(activeConfig)
            : text('COM_JEM_CAMERA_RATIO_LABEL') + ' ' + activeConfig.ratioWidth + ':' + activeConfig.ratioHeight + ' · ' + profileModeLabel(activeConfig);
    }

    function stopStream() {
        if (activeStream) {
            activeStream.getTracks().forEach(function (track) {
                track.stop();
            });
        }
        activeStream = null;
        if (video) {
            video.srcObject = null;
        }
    }

    function cameraFallbackLabel(index) {
        return text('COM_JEM_CAMERA_DEVICE_FALLBACK').replace('%s', String(index + 1));
    }

    async function updateCameraDevices(selectedDeviceId) {
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') {
            cameraDeviceControl.hidden = true;
            return;
        }

        var cameras;

        try {
            var devices = await navigator.mediaDevices.enumerateDevices();
            cameras = devices.filter(function (device) {
                return device.kind === 'videoinput';
            });
        } catch (error) {
            cameraDeviceControl.hidden = true;
            return;
        }

        cameraDeviceSelect.replaceChildren();
        cameras.forEach(function (device, index) {
            var option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = device.label || cameraFallbackLabel(index);
            option.selected = device.deviceId === selectedDeviceId;
            cameraDeviceSelect.appendChild(option);
        });

        cameraDeviceControl.hidden = cameras.length === 0;
        cameraDeviceSelect.disabled = cameras.length < 2;

        if (selectedDeviceId && cameraDeviceSelect.value !== selectedDeviceId) {
            cameraDeviceSelect.value = selectedDeviceId;
        }
    }

    function waitForVideoFrame() {
        return new Promise(function (resolve, reject) {
            var timeoutId = window.setTimeout(function () {
                cleanup();
                reject(new Error('Camera did not provide video dimensions'));
            }, 8000);

            function cleanup() {
                window.clearTimeout(timeoutId);
                video.removeEventListener('loadedmetadata', checkFrame);
                video.removeEventListener('canplay', checkFrame);
                video.removeEventListener('error', fail);
            }

            function checkFrame() {
                if (video.videoWidth > 0 && video.videoHeight > 0) {
                    cleanup();
                    resolve();
                }
            }

            function fail() {
                cleanup();
                reject(new Error('Camera video failed'));
            }

            video.addEventListener('loadedmetadata', checkFrame);
            video.addEventListener('canplay', checkFrame);
            video.addEventListener('error', fail);
            checkFrame();
        });
    }

    function enforceSourceMinimumOutput() {
        if (!activeConfig || activeConfig.mode !== 'none' || video.videoWidth < 1 || video.videoHeight < 1) {
            return;
        }

        var minimumSide = Math.max(1, Number(activeConfig.minDimension) || 1);
        var sourceShortSide = Math.min(video.videoWidth, video.videoHeight);
        var sourceLongSide = Math.max(video.videoWidth, video.videoHeight);
        var sourceMinimum = Math.ceil(minimumSide * sourceLongSide / sourceShortSide);
        var configuredMaximum = Number(activeConfig.configuredMaxDimension) || Number(activeConfig.maxDimension) || sourceMinimum;

        activeConfig.maxDimension = Math.min(
            configuredMaximum,
            Math.max(Number(activeConfig.maxDimension) || sourceMinimum, sourceMinimum)
        );

        var resolutionFieldId = activeButton ? activeButton.dataset.jemCameraResolutionField || '' : '';
        var resolutionField = resolutionFieldId ? document.getElementById(resolutionFieldId) : null;
        var resolutionControl = resolutionField
            ? resolutionField.closest('[data-jem-image-resolution-control]')
            : null;

        if (resolutionControl && Number(resolutionField.value) < activeConfig.maxDimension) {
            setResolutionControlValue(resolutionControl, activeConfig.maxDimension);
        }
    }

    async function startCamera(deviceId, switching) {
        var requestedDeviceId = typeof deviceId === 'string' ? deviceId : '';
        var requestSequence = ++cameraRequestSequence;

        stopStream();
        capturedBlob = null;
        capturedMime = '';
        capturedSourceBlob = null;
        capturedSourceMime = '';
        livePanel.hidden = false;
        resultPanel.hidden = true;
        captureButton.hidden = false;
        captureButton.disabled = true;
        cameraFormatSelect.disabled = false;
        retakeButton.hidden = true;
        useButton.hidden = true;
        cameraDeviceSelect.disabled = true;
        setStatus(text(switching ? 'COM_JEM_CAMERA_SWITCHING' : 'COM_JEM_CAMERA_STARTING'), false);

        if (!window.isSecureContext) {
            setStatus(text('COM_JEM_CAMERA_SECURE_CONTEXT_REQUIRED'), true);
            return;
        }
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            setStatus(text('COM_JEM_CAMERA_UNAVAILABLE'), true);
            return;
        }

        try {
            var videoConstraints = {
                width: {ideal: Number(activeConfig.maxDimension) || 1920},
                height: {ideal: Number(activeConfig.maxDimension) || 1080}
            };

            if (requestedDeviceId) {
                videoConstraints.deviceId = {exact: requestedDeviceId};
            } else {
                videoConstraints.facingMode = {ideal: 'environment'};
            }

            var stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: videoConstraints
            });

            if (requestSequence !== cameraRequestSequence) {
                stream.getTracks().forEach(function (track) {
                    track.stop();
                });
                return;
            }

            activeStream = stream;
            video.srcObject = activeStream;
            await video.play();
            await waitForVideoFrame();

            if (requestSequence !== cameraRequestSequence) {
                return;
            }

            var videoTrack = activeStream.getVideoTracks()[0];
            var settings = videoTrack && typeof videoTrack.getSettings === 'function'
                ? videoTrack.getSettings()
                : {};
            activeDeviceId = settings.deviceId || requestedDeviceId;
            await updateCameraDevices(activeDeviceId);
            enforceSourceMinimumOutput();
            updateFrame();
            captureButton.disabled = false;
            setStatus(text('COM_JEM_CAMERA_READY'), false);
        } catch (error) {
            if (requestSequence !== cameraRequestSequence) {
                return;
            }

            var denied = error && (error.name === 'NotAllowedError' || error.name === 'SecurityError');
            setStatus(text(denied ? 'COM_JEM_CAMERA_PERMISSION_DENIED' : 'COM_JEM_CAMERA_UNAVAILABLE'), true);
            cameraDeviceSelect.disabled = cameraDeviceSelect.options.length < 2;
            stopStream();
        }
    }

    function sourceAndOutputGeometry(width, height, config) {
        var ratioWidth = Math.max(1, Number(config.ratioWidth) || 1);
        var ratioHeight = Math.max(1, Number(config.ratioHeight) || 1);
        var maxDimension = Math.max(1, Number(config.maxDimension) || 3840);
        var sourceX = 0;
        var sourceY = 0;
        var sourceWidth = width;
        var sourceHeight = height;
        var outputWidth;
        var outputHeight;

        if (config.mode === 'crop') {
            if (width / height > ratioWidth / ratioHeight) {
                sourceWidth = height * ratioWidth / ratioHeight;
                sourceX = (width - sourceWidth) / 2;
            } else {
                sourceHeight = width * ratioHeight / ratioWidth;
                sourceY = (height - sourceHeight) / 2;
            }

            var cropUnit = Math.max(1, Math.floor(Math.min(
                maxDimension / ratioWidth,
                maxDimension / ratioHeight,
                sourceWidth / ratioWidth,
                sourceHeight / ratioHeight
            )));
            outputWidth = ratioWidth * cropUnit;
            outputHeight = ratioHeight * cropUnit;
        } else if (config.mode === 'pad') {
            var sourceScale = Math.min(1, maxDimension / width, maxDimension / height);
            var scaledWidth = width * sourceScale;
            var scaledHeight = height * sourceScale;
            var padUnit = Math.max(1, Math.floor(Math.min(
                Math.max(scaledWidth / ratioWidth, scaledHeight / ratioHeight),
                maxDimension / ratioWidth,
                maxDimension / ratioHeight
            )));
            outputWidth = ratioWidth * padUnit;
            outputHeight = ratioHeight * padUnit;
        } else {
            var scale = Math.min(1, maxDimension / width, maxDimension / height);
            outputWidth = Math.max(1, Math.floor(width * scale));
            outputHeight = Math.max(1, Math.floor(height * scale));
        }

        return {
            sourceX: sourceX,
            sourceY: sourceY,
            sourceWidth: sourceWidth,
            sourceHeight: sourceHeight,
            outputWidth: outputWidth,
            outputHeight: outputHeight
        };
    }

    function createCameraSourceCanvas() {
        var canvas = document.createElement('canvas');
        var context = canvas.getContext('2d');

        if (!context || video.videoWidth < 1 || video.videoHeight < 1) {
            throw new Error('Invalid camera source');
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        return canvas;
    }

    function createCapturedCanvas(source) {
        var geometry = sourceAndOutputGeometry(source.width, source.height, activeConfig);
        var canvas = document.createElement('canvas');
        var context = canvas.getContext('2d', {alpha: activeConfig.mode === 'pad'});

        if (!context || geometry.outputWidth < 1 || geometry.outputHeight < 1) {
            throw new Error('Invalid camera canvas');
        }

        canvas.width = geometry.outputWidth;
        canvas.height = geometry.outputHeight;

        if (activeConfig.mode === 'pad') {
            context.fillStyle = activeConfig.paddingColor || '#000000';
            context.fillRect(0, 0, canvas.width, canvas.height);
            var containScale = Math.min(canvas.width / source.width, canvas.height / source.height);
            var drawWidth = source.width * containScale;
            var drawHeight = source.height * containScale;
            context.drawImage(
                source,
                (canvas.width - drawWidth) / 2,
                (canvas.height - drawHeight) / 2,
                drawWidth,
                drawHeight
            );
        } else {
            context.drawImage(
                source,
                geometry.sourceX,
                geometry.sourceY,
                geometry.sourceWidth,
                geometry.sourceHeight,
                0,
                0,
                canvas.width,
                canvas.height
            );
        }

        return canvas;
    }

    function canvasSupportsMime(canvas, mime) {
        if (mime === 'image/png') {
            return true;
        }

        try {
            return canvas.toDataURL(mime, 0.8).indexOf('data:' + mime) === 0;
        } catch (error) {
            return false;
        }
    }

    function chooseMime(canvas, configured, preferred) {
        var mimeTypes = Array.isArray(configured) ? configured : [];

        if (preferred && mimeTypes.indexOf(preferred) !== -1 && canvasSupportsMime(canvas, preferred)) {
            return preferred;
        }

        for (var i = 0; i < mimeTypes.length; i += 1) {
            if (canvasSupportsMime(canvas, mimeTypes[i])) {
                return mimeTypes[i];
            }
        }

        return '';
    }

    function populateCameraFormats(config) {
        var configured = Array.isArray(config.mimeTypes) ? config.mimeTypes : [];
        var canvas = document.createElement('canvas');
        var labels = {
            'image/jpeg': 'JPEG',
            'image/png': 'PNG',
            'image/webp': 'WebP'
        };
        var order = ['image/jpeg', 'image/png', 'image/webp'];

        cameraFormatSelect.replaceChildren();
        order.forEach(function (mime) {
            if (configured.indexOf(mime) === -1 || !canvasSupportsMime(canvas, mime)) {
                return;
            }

            var option = document.createElement('option');
            option.value = mime;
            option.textContent = labels[mime];
            cameraFormatSelect.appendChild(option);
        });

        cameraFormatControl.hidden = cameraFormatSelect.options.length === 0;
        cameraFormatSelect.disabled = false;
        if (configured.indexOf('image/jpeg') !== -1) {
            cameraFormatSelect.value = 'image/jpeg';
        }
    }

    function canvasToBlob(canvas, mime, quality) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (!blob || blob.type !== mime) {
                    reject(new Error('Canvas encoding failed'));
                    return;
                }
                resolve(blob);
            }, mime, quality);
        });
    }

    function resizedCanvas(source, scale, config) {
        var width = Math.max(1, Math.floor(source.width * scale));
        var height = Math.max(1, Math.floor(source.height * scale));

        if (config.mode !== 'none') {
            var unit = Math.max(1, Math.floor(Math.min(
                width / Math.max(1, Number(config.ratioWidth)),
                height / Math.max(1, Number(config.ratioHeight))
            )));
            width = Math.max(1, Number(config.ratioWidth)) * unit;
            height = Math.max(1, Number(config.ratioHeight)) * unit;
        }

        var canvas = document.createElement('canvas');
        var context = canvas.getContext('2d');
        canvas.width = width;
        canvas.height = height;
        context.drawImage(source, 0, 0, width, height);

        return canvas;
    }

    async function compressCanvas(source, config, preferredMime) {
        var working = source;
        var mime = chooseMime(working, config.mimeTypes, preferredMime);
        var quality = 0.92;
        var maxBytes = Math.max(1, Number(config.maxBytes) || 1);
        var minDimension = Math.max(1, Number(config.minDimension) || 1);

        if (!mime) {
            throw new Error('No configured camera output format');
        }

        var blob = await canvasToBlob(working, mime, quality);
        var attempts = 0;

        while (blob.size > maxBytes && attempts < 16) {
            attempts += 1;

            if (mime !== 'image/png' && quality > 0.46) {
                quality = Math.max(0.46, quality - 0.08);
            } else {
                var scale = Math.min(0.9, Math.max(0.55, Math.sqrt(maxBytes / blob.size) * 0.94));
                var next = resizedCanvas(working, scale, config);

                if (next.width < minDimension || next.height < minDimension
                    || (next.width === working.width && next.height === working.height)) {
                    break;
                }
                working = next;
                quality = mime === 'image/png' ? 1 : 0.86;
            }

            blob = await canvasToBlob(working, mime, quality);
        }

        if (blob.size > maxBytes || working.width < minDimension || working.height < minDimension) {
            throw new Error('Camera output exceeds configured limits');
        }

        return {canvas: working, blob: blob, mime: mime};
    }

    function dispatchAcceptedFile(input) {
        input.dataset.jemImageEditorAccepted = '1';
        input.dispatchEvent(new Event('change', {bubbles: true}));
        syncPreviewEditorAvailability(input);
    }

    function assignSelectedFile(input, file) {
        var transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
    }

    function cameraButtonForInput(input) {
        return Array.prototype.find.call(document.querySelectorAll('.jem-camera-button'), function (button) {
            return button.dataset.jemCameraInput === input.id;
        }) || null;
    }

    function previewImageForInput(input) {
        var panel = input ? input.closest('.jem-image-upload-panel') : null;
        var preview = panel ? panel.querySelector('.jem-image-selected-preview img') : null;

        return preview || null;
    }

    function syncPreviewEditorAvailability(input) {
        var preview = previewImageForInput(input);
        var config = uploadConfigForInput(input);
        var available = Boolean(
            preview
            && input.files
            && input.files[0]
            && editorSourceFiles.get(input)
            && config
            && config.mode === 'crop'
        );

        if (!preview) {
            return;
        }

        preview.classList.toggle('jem-image-preview-adjustable', available);

        if (available) {
            preview.setAttribute('role', 'button');
            preview.setAttribute('tabindex', '0');
            preview.setAttribute('title', text('COM_JEM_IMAGE_EDITOR_TITLE'));
            preview.setAttribute('aria-label', text('COM_JEM_IMAGE_EDITOR_TITLE'));
            return;
        }

        preview.removeAttribute('role');
        preview.removeAttribute('tabindex');
        preview.removeAttribute('title');
        preview.removeAttribute('aria-label');
    }

    function uploadConfigForInput(input) {
        var button = cameraButtonForInput(input);
        var config;

        if (!button) {
            return null;
        }

        try {
            config = JSON.parse(button.dataset.jemCameraConfig || '{}');
        } catch (error) {
            return null;
        }

        var resolutionFieldId = button.dataset.jemCameraResolutionField || '';
        var resolutionField = resolutionFieldId ? document.getElementById(resolutionFieldId) : null;
        var resolutionControl = resolutionField
            ? resolutionField.closest('[data-jem-image-resolution-control]')
            : null;

        if (resolutionControl) {
            applyRatioSelection(resolutionControl, config);
        }
        if (resolutionField) {
            config.maxDimension = clampResolutionValue(resolutionField, resolutionField.value);
        }

        return config;
    }

    function loadSelectedImage(file) {
        return new Promise(function (resolve, reject) {
            var image = document.createElement('img');
            var source = URL.createObjectURL(file);

            image.onload = function () {
                URL.revokeObjectURL(source);
                resolve(image);
            };
            image.onerror = function () {
                URL.revokeObjectURL(source);
                reject(new Error('Selected image could not be decoded'));
            };
            image.src = source;
        });
    }

    function imageMatchesRatio(image, config) {
        return image.naturalWidth * Number(config.ratioHeight)
            === image.naturalHeight * Number(config.ratioWidth);
    }

    function constrainEditorOffset() {
        if (!editorCanvas || !editorImage || !editorConfig || editorConfig.mode !== 'crop') {
            return;
        }

        var drawnWidth = editorImage.naturalWidth * editorScale;
        var drawnHeight = editorImage.naturalHeight * editorScale;
        editorOffsetX = Math.min(0, Math.max(editorCanvas.width - drawnWidth, editorOffsetX));
        editorOffsetY = Math.min(0, Math.max(editorCanvas.height - drawnHeight, editorOffsetY));
    }

    function drawImageEditor() {
        if (!editorCanvas || !editorImage || !editorConfig) {
            return;
        }

        var context = editorCanvas.getContext('2d');
        context.fillStyle = editorConfig.paddingColor || '#000000';
        context.fillRect(0, 0, editorCanvas.width, editorCanvas.height);

        if (editorConfig.mode === 'pad') {
            var containScale = Math.min(
                editorCanvas.width / editorImage.naturalWidth,
                editorCanvas.height / editorImage.naturalHeight
            );
            var width = editorImage.naturalWidth * containScale;
            var height = editorImage.naturalHeight * containScale;
            context.drawImage(
                editorImage,
                (editorCanvas.width - width) / 2,
                (editorCanvas.height - height) / 2,
                width,
                height
            );
            return;
        }

        constrainEditorOffset();
        context.drawImage(
            editorImage,
            editorOffsetX,
            editorOffsetY,
            editorImage.naturalWidth * editorScale,
            editorImage.naturalHeight * editorScale
        );
    }

    function ensureImageEditor() {
        if (editorModal) {
            return;
        }

        editorModal = document.createElement('div');
        editorModal.className = 'jem-camera-modal jem-image-editor';
        editorModal.hidden = true;
        editorModal.innerHTML = [
            '<div class="jem-camera-modal__backdrop" data-jem-image-editor-close></div>',
            '<section class="jem-camera-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="jem-image-editor-title">',
            '  <header class="jem-camera-modal__header">',
            '    <h2 id="jem-image-editor-title"></h2>',
            '    <button type="button" class="jem-camera-modal__close" data-jem-image-editor-close></button>',
            '  </header>',
            '  <div class="jem-camera-modal__body">',
            '    <div class="jem-image-editor__stage"><canvas tabindex="0" aria-describedby="jem-image-editor-help"></canvas></div>',
            '    <div class="jem-image-editor__zoom">',
            '      <label for="jem-image-editor-zoom"></label>',
            '      <input type="range" id="jem-image-editor-zoom" min="100" max="300" value="100" step="1">',
            '    </div>',
            '    <p id="jem-image-editor-help" class="jem-camera-status" role="status"></p>',
            '  </div>',
            '  <footer class="jem-camera-modal__footer">',
            '    <button type="button" class="btn btn-secondary jem-camera-action-button" data-jem-image-editor-use></button>',
            '    <button type="button" class="btn btn-secondary jem-camera-action-button" data-jem-image-editor-close></button>',
            '  </footer>',
            '</section>'
        ].join('');
        document.body.appendChild(editorModal);

        editorCanvas = editorModal.querySelector('canvas');
        editorZoom = editorModal.querySelector('#jem-image-editor-zoom');
        editorHelp = editorModal.querySelector('.jem-camera-status');
        editorModal.querySelector('#jem-image-editor-title').textContent = text('COM_JEM_IMAGE_EDITOR_TITLE');
        editorModal.querySelector('.jem-image-editor__zoom label').textContent = text('COM_JEM_IMAGE_EDITOR_ZOOM');
        editorModal.querySelector('[data-jem-image-editor-use]').textContent = text('COM_JEM_IMAGE_EDITOR_USE_IMAGE');
        editorModal.querySelector('.jem-camera-modal__close').innerHTML = '<span aria-hidden="true">&times;</span>';
        editorModal.querySelector('.jem-camera-modal__close').setAttribute(
            'aria-label',
            text('COM_JEM_IMAGE_EDITOR_CLOSE')
        );
        Array.prototype.forEach.call(
            editorModal.querySelectorAll('.jem-camera-modal__footer [data-jem-image-editor-close]'),
            function (button) {
                button.textContent = text('COM_JEM_CAMERA_CANCEL');
            }
        );

        editorZoom.addEventListener('input', function () {
            if (!editorImage || !editorCanvas) {
                return;
            }

            var centreX = (editorCanvas.width / 2 - editorOffsetX) / editorScale;
            var centreY = (editorCanvas.height / 2 - editorOffsetY) / editorScale;
            editorScale = editorMinimumScale * (Number(editorZoom.value) / 100);
            editorOffsetX = editorCanvas.width / 2 - centreX * editorScale;
            editorOffsetY = editorCanvas.height / 2 - centreY * editorScale;
            drawImageEditor();
        });
        editorCanvas.addEventListener('pointerdown', function (event) {
            if (!editorConfig || editorConfig.mode !== 'crop') {
                return;
            }

            editorPointer = {id: event.pointerId, x: event.clientX, y: event.clientY};
            editorCanvas.setPointerCapture(event.pointerId);
        });
        editorCanvas.addEventListener('pointermove', function (event) {
            if (!editorPointer || editorPointer.id !== event.pointerId) {
                return;
            }

            var bounds = editorCanvas.getBoundingClientRect();
            editorOffsetX += (event.clientX - editorPointer.x) * editorCanvas.width / bounds.width;
            editorOffsetY += (event.clientY - editorPointer.y) * editorCanvas.height / bounds.height;
            editorPointer.x = event.clientX;
            editorPointer.y = event.clientY;
            drawImageEditor();
        });
        editorCanvas.addEventListener('pointerup', function (event) {
            if (editorPointer && editorPointer.id === event.pointerId) {
                editorPointer = null;
            }
        });
        editorCanvas.addEventListener('keydown', function (event) {
            if (!editorConfig || editorConfig.mode !== 'crop'
                || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
                return;
            }

            var movement = event.shiftKey ? 30 : 10;
            editorOffsetX += event.key === 'ArrowLeft' ? -movement : (event.key === 'ArrowRight' ? movement : 0);
            editorOffsetY += event.key === 'ArrowUp' ? -movement : (event.key === 'ArrowDown' ? movement : 0);
            event.preventDefault();
            drawImageEditor();
        });
        editorModal.querySelector('[data-jem-image-editor-use]').addEventListener('click', useEditedImage);
        Array.prototype.forEach.call(editorModal.querySelectorAll('[data-jem-image-editor-close]'), function (node) {
            node.addEventListener('click', function () {
                closeImageEditor(true);
            });
        });
        document.addEventListener('keydown', function (event) {
            if (!editorModal.hidden && event.key === 'Escape') {
                closeImageEditor(true);
            }
        });
    }

    function openImageEditor(input, file, config, image, keepSelectionOnCancel) {
        ensureImageEditor();
        editorInput = input;
        editorFile = file;
        editorConfig = config;
        editorImage = image;
        editorPointer = null;
        editorKeepSelectionOnCancel = Boolean(keepSelectionOnCancel);

        var ratio = Math.max(0.01, Number(config.ratioWidth) / Number(config.ratioHeight));
        var maximumWidth = 900;
        var maximumHeight = 540;
        editorCanvas.width = ratio >= maximumWidth / maximumHeight
            ? maximumWidth
            : Math.max(1, Math.round(maximumHeight * ratio));
        editorCanvas.height = ratio >= maximumWidth / maximumHeight
            ? Math.max(1, Math.round(maximumWidth / ratio))
            : maximumHeight;

        var editorStage = editorModal.querySelector('.jem-image-editor__stage');
        editorStage.style.aspectRatio = String(ratio);
        editorStage.style.width = 'min(100%, ' + (62 * ratio).toFixed(3) + 'vh)';

        editorMinimumScale = config.mode === 'crop'
            ? Math.max(
                editorCanvas.width / image.naturalWidth,
                editorCanvas.height / image.naturalHeight
            )
            : Math.min(
                editorCanvas.width / image.naturalWidth,
                editorCanvas.height / image.naturalHeight
            );
        editorScale = editorMinimumScale;
        editorOffsetX = (editorCanvas.width - image.naturalWidth * editorScale) / 2;
        editorOffsetY = (editorCanvas.height - image.naturalHeight * editorScale) / 2;
        editorZoom.value = '100';
        editorModal.querySelector('.jem-image-editor__zoom').hidden = config.mode !== 'crop';
        editorCanvas.classList.toggle('jem-image-editor__canvas--draggable', config.mode === 'crop');
        editorHelp.classList.remove('jem-camera-status--error');
        editorHelp.textContent = text(
            config.mode === 'crop' ? 'COM_JEM_IMAGE_EDITOR_CROP_HELP' : 'COM_JEM_IMAGE_EDITOR_PAD_HELP'
        );
        drawImageEditor();
        editorModal.hidden = false;
        document.body.classList.add('jem-camera-open');
        editorModal.querySelector('[data-jem-image-editor-use]').focus();
    }

    function editedImageCanvas() {
        var ratioWidth = Math.max(1, Number(editorConfig.ratioWidth) || 1);
        var ratioHeight = Math.max(1, Number(editorConfig.ratioHeight) || 1);
        var maxDimension = Math.max(1, Number(editorConfig.maxDimension) || 3840);
        var canvas = document.createElement('canvas');
        var context = canvas.getContext('2d');

        if (editorConfig.mode === 'crop') {
            var sourceX = Math.max(0, -editorOffsetX / editorScale);
            var sourceY = Math.max(0, -editorOffsetY / editorScale);
            var sourceWidth = Math.min(editorImage.naturalWidth - sourceX, editorCanvas.width / editorScale);
            var sourceHeight = Math.min(editorImage.naturalHeight - sourceY, editorCanvas.height / editorScale);
            var cropUnit = Math.max(1, Math.floor(Math.min(
                maxDimension / ratioWidth,
                maxDimension / ratioHeight,
                sourceWidth / ratioWidth,
                sourceHeight / ratioHeight
            )));
            canvas.width = ratioWidth * cropUnit;
            canvas.height = ratioHeight * cropUnit;
            context.drawImage(
                editorImage,
                sourceX,
                sourceY,
                sourceWidth,
                sourceHeight,
                0,
                0,
                canvas.width,
                canvas.height
            );
            return canvas;
        }

        var geometry = sourceAndOutputGeometry(
            editorImage.naturalWidth,
            editorImage.naturalHeight,
            editorConfig
        );
        canvas.width = geometry.outputWidth;
        canvas.height = geometry.outputHeight;
        context.fillStyle = editorConfig.paddingColor || '#000000';
        context.fillRect(0, 0, canvas.width, canvas.height);
        var containScale = Math.min(
            canvas.width / editorImage.naturalWidth,
            canvas.height / editorImage.naturalHeight
        );
        var width = editorImage.naturalWidth * containScale;
        var height = editorImage.naturalHeight * containScale;
        context.drawImage(
            editorImage,
            (canvas.width - width) / 2,
            (canvas.height - height) / 2,
            width,
            height
        );

        return canvas;
    }

    async function useEditedImage() {
        if (!editorInput || !editorFile || !editorConfig || !editorImage) {
            return;
        }

        var useButton = editorModal.querySelector('[data-jem-image-editor-use]');
        useButton.disabled = true;

        try {
            var processed = await compressCanvas(editedImageCanvas(), editorConfig);
            var extension = (editorConfig.extensions || {})[processed.mime]
                || (processed.mime === 'image/jpeg' ? 'jpg' : processed.mime.replace('image/', ''));
            var baseName = editorFile.name.replace(/\.[^.]+$/, '') || 'jem-image';
            var file = new File(
                [processed.blob],
                baseName + '-adjusted.' + extension,
                {type: processed.mime, lastModified: Date.now()}
            );
            var transfer = new DataTransfer();
            transfer.items.add(file);
            editorInput.files = transfer.files;
            var acceptedInput = editorInput;
            closeImageEditor(false);
            dispatchAcceptedFile(acceptedInput);
        } catch (error) {
            editorHelp.textContent = text('COM_JEM_CAMERA_PROCESSING_FAILED');
            editorHelp.classList.add('jem-camera-status--error');
        } finally {
            useButton.disabled = false;
        }
    }

    function closeImageEditor(clearSelection) {
        var input = editorInput;

        if (clearSelection && input && !editorKeepSelectionOnCancel) {
            input.value = '';
            editorSourceFiles.delete(input);
        }
        editorModal.hidden = true;
        document.body.classList.remove('jem-camera-open');
        editorInput = null;
        editorFile = null;
        editorImage = null;
        editorConfig = null;
        editorPointer = null;
        editorKeepSelectionOnCancel = false;
        if (clearSelection && input && !input.files.length) {
            dispatchAcceptedFile(input);
        }
    }

    async function inspectSelectedFile(input, file, keepSelectionOnCancel) {
        var config = uploadConfigForInput(input);

        if (!config || config.mode === 'none'
            || !/^image\/(?:jpeg|png|webp)$/i.test(file.type || '')) {
            assignSelectedFile(input, file);
            dispatchAcceptedFile(input);
            return;
        }

        try {
            var image = await loadSelectedImage(file);

            if (imageMatchesRatio(image, config)) {
                assignSelectedFile(input, file);
                dispatchAcceptedFile(input);
                return;
            }

            openImageEditor(input, file, config, image, keepSelectionOnCancel);
        } catch (error) {
            assignSelectedFile(input, file);
            dispatchAcceptedFile(input);
        }
    }

    async function capturePhoto() {
        if (!activeStream || video.videoWidth < 1 || video.videoHeight < 1) {
            return;
        }

        captureButton.disabled = true;
        cameraFormatSelect.disabled = true;
        setStatus(text('COM_JEM_CAMERA_PROCESSING'), false);

        try {
            var originalSource = createCameraSourceCanvas();
            var source = createCapturedCanvas(originalSource);
            stopStream();
            var result = await compressCanvas(source, activeConfig, cameraFormatSelect.value);
            capturedSourceMime = chooseMime(
                originalSource,
                activeConfig.mimeTypes,
                cameraFormatSelect.value
            ) || result.mime;
            try {
                capturedSourceBlob = await canvasToBlob(originalSource, capturedSourceMime, 0.95);
            } catch (error) {
                capturedSourceBlob = result.blob;
                capturedSourceMime = result.mime;
            }
            var resultContext = resultCanvas.getContext('2d');
            resultCanvas.width = result.canvas.width;
            resultCanvas.height = result.canvas.height;
            resultContext.drawImage(result.canvas, 0, 0);
            capturedBlob = result.blob;
            capturedMime = result.mime;
            capturedWidth = result.canvas.width;
            capturedHeight = result.canvas.height;
            livePanel.hidden = true;
            resultPanel.hidden = false;
            resultPanel.querySelector('.jem-camera-result__summary').textContent = outputSummary();
            captureButton.hidden = true;
            retakeButton.hidden = false;
            useButton.hidden = false;
            setStatus(text('COM_JEM_CAMERA_RESULT'), false);
        } catch (error) {
            stopStream();
            captureButton.hidden = true;
            retakeButton.hidden = false;
            setStatus(
                text(error && error.message === 'Camera output exceeds configured limits'
                    ? 'COM_JEM_CAMERA_OUTPUT_TOO_LARGE'
                    : 'COM_JEM_CAMERA_PROCESSING_FAILED'),
                true
            );
        }
    }

    function outputSummary() {
        var type = capturedMime.replace('image/', '').toUpperCase();
        var kilobytes = Math.max(1, Math.ceil(capturedBlob.size / 1024));
        var ratio = activeConfig.mode === 'none'
            ? ''
            : activeConfig.ratioWidth + ':' + activeConfig.ratioHeight + ' · ';

        return ratio + capturedWidth + ' × ' + capturedHeight + ' · ' + kilobytes + ' KB · ' + type;
    }

    function fileExtension(mime) {
        var extensions = activeConfig && activeConfig.extensions ? activeConfig.extensions : {};

        return extensions[mime] || (mime === 'image/jpeg' ? 'jpg' : mime.replace('image/', ''));
    }

    function usePhoto() {
        if (!capturedBlob || !activeInput) {
            return;
        }

        try {
            var filename = 'jem-camera-' + activeConfig.profile + '-' + Date.now() + '.' + fileExtension(capturedMime);
            var file = new File([capturedBlob], filename, {type: capturedMime, lastModified: Date.now()});
            var sourceFilename = 'jem-camera-source-' + activeConfig.profile + '-' + Date.now()
                + '.' + fileExtension(capturedSourceMime || capturedMime);
            var sourceFile = capturedSourceBlob
                ? new File(
                    [capturedSourceBlob],
                    sourceFilename,
                    {type: capturedSourceMime || capturedMime, lastModified: Date.now()}
                )
                : file;
            var transfer = new DataTransfer();
            transfer.items.add(file);

            var clearSelectId = activeButton.dataset.jemCameraClearSelect || '';
            var removeFieldId = activeButton.dataset.jemCameraRemoveField || '';
            var clearSelect = clearSelectId ? document.getElementById(clearSelectId) : null;
            var removeField = removeFieldId ? document.getElementById(removeFieldId) : null;

            if (clearSelect) {
                clearSelect.value = '';
                clearSelect.dispatchEvent(new Event('change', {bubbles: true}));
            }
            if (removeField) {
                removeField.value = '0';
            }

            activeInput.files = transfer.files;
            activeInput.dispatchEvent(new Event('change', {bubbles: true}));
            editorSourceFiles.set(activeInput, sourceFile);
            syncPreviewEditorAvailability(activeInput);

            if (!activeInput.files || activeInput.files.length !== 1) {
                throw new Error('File assignment failed');
            }

            var submitFormId = activeButton.dataset.jemCameraSubmitForm || '';
            var submitForm = submitFormId ? document.getElementById(submitFormId) : null;
            closeCamera();

            if (submitForm) {
                if (typeof submitForm.requestSubmit === 'function') {
                    submitForm.requestSubmit();
                } else {
                    submitForm.submit();
                }
            }
        } catch (error) {
            setStatus(text('COM_JEM_CAMERA_FILE_ASSIGN_FAILED'), true);
        }
    }

    function closeCamera() {
        var returnButton = activeButton;

        cameraRequestSequence += 1;
        stopStream();
        capturedBlob = null;
        capturedMime = '';
        capturedSourceBlob = null;
        capturedSourceMime = '';
        capturedWidth = 0;
        capturedHeight = 0;
        if (resultCanvas) {
            resultCanvas.width = 1;
            resultCanvas.height = 1;
        }
        modal.hidden = true;
        document.body.classList.remove('jem-camera-open');
        activeButton = null;
        activeInput = null;
        activeConfig = null;
        activeDeviceId = '';
        cameraDeviceControl.hidden = true;
        cameraDeviceSelect.replaceChildren();
        cameraFormatControl.hidden = true;
        cameraFormatSelect.replaceChildren();

        if (returnButton) {
            returnButton.focus();
        }
    }

    async function openCamera(button) {
        ensureModal();

        var inputId = button.dataset.jemCameraInput || '';
        var input = document.getElementById(inputId);
        var config;

        try {
            config = JSON.parse(button.dataset.jemCameraConfig || '{}');
        } catch (error) {
            return;
        }

        if (!input || input.type !== 'file') {
            return;
        }

        activeButton = button;
        activeInput = input;
        activeConfig = config;
        activeConfig.configuredMaxDimension = Number(config.maxDimension) || 0;
        populateCameraFormats(activeConfig);

        var resolutionFieldId = button.dataset.jemCameraResolutionField || '';
        var resolutionField = resolutionFieldId ? document.getElementById(resolutionFieldId) : null;
        var resolutionControl = resolutionField
            ? resolutionField.closest('[data-jem-image-resolution-control]')
            : null;
        if (resolutionControl) {
            applyRatioSelection(resolutionControl, activeConfig);
        }
        if (resolutionField) {
            activeConfig.maxDimension = clampResolutionValue(resolutionField, resolutionField.value);
        }

        modal.hidden = false;
        document.body.classList.add('jem-camera-open');
        updateFrame();
        modal.querySelector('.jem-camera-modal__close').focus();
        await startCamera();
    }

    initialiseResolutionControls();

    document.addEventListener('change', function (event) {
        var input = event.target;

        if (!input || input.type !== 'file' || !cameraButtonForInput(input)) {
            return;
        }
        if (input.dataset.jemImageEditorAccepted === '1') {
            delete input.dataset.jemImageEditorAccepted;
            return;
        }
        if (!input.files || !input.files[0]) {
            editorSourceFiles.delete(input);
            return;
        }

        event.stopImmediatePropagation();
        editorSourceFiles.set(input, input.files[0]);
        inspectSelectedFile(input, input.files[0], false);
    }, true);

    document.addEventListener('jem:image-ratio-change', function (event) {
        var control = event.target.closest('[data-jem-image-resolution-control]');

        if (!control) {
            return;
        }

        Array.prototype.forEach.call(document.querySelectorAll('.jem-camera-button'), function (button) {
            var resolutionFieldId = button.dataset.jemCameraResolutionField || '';
            var resolutionField = resolutionFieldId ? document.getElementById(resolutionFieldId) : null;
            var input = document.getElementById(button.dataset.jemCameraInput || '');

            var sourceFile = input ? editorSourceFiles.get(input) : null;

            if (resolutionField && resolutionField.closest('[data-jem-image-resolution-control]') === control
                && input && sourceFile) {
                inspectSelectedFile(input, sourceFile, true);
            }
        });
    });

    async function reopenImageEditor(preview) {
        var panel = preview.closest('.jem-image-upload-panel');
        var input = panel ? panel.querySelector('input[type="file"]') : null;
        var sourceFile = input ? editorSourceFiles.get(input) : null;
        var config = input ? uploadConfigForInput(input) : null;

        if (!input || !input.files || !input.files[0] || !sourceFile
            || !config || config.mode !== 'crop') {
            if (input) {
                syncPreviewEditorAvailability(input);
            }
            return;
        }

        try {
            var image = await loadSelectedImage(sourceFile);
            openImageEditor(input, sourceFile, config, image, true);
        } catch (error) {
            syncPreviewEditorAvailability(input);
        }
    }

    document.addEventListener('keydown', function (event) {
        var preview = event.target.closest('.jem-image-preview-adjustable');

        if (!preview || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }

        event.preventDefault();
        reopenImageEditor(preview);
    });

    document.addEventListener('click', function (event) {
        var preview = event.target.closest('.jem-image-preview-adjustable');
        var button = event.target.closest('.jem-camera-button');

        if (preview) {
            event.preventDefault();
            reopenImageEditor(preview);
            return;
        }

        if (!button) {
            return;
        }

        event.preventDefault();
        openCamera(button);
    });
}());
