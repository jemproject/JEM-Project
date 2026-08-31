<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImageCameraCaptureTest extends TestCase
{
    public function testFrontendImageFieldsUseTheProfileAwareCameraField(): void
    {
        $event = $this->read('/site/models/forms/event.xml');
        $venue = $this->read('/site/models/forms/venue.xml');

        self::assertStringContainsString('name="userfile" type="jemimagefile"', $event);
        self::assertStringContainsString('imageprofile="event_intro"', $event);
        self::assertStringContainsString('name="fulluserfile" type="jemimagefile"', $event);
        self::assertStringContainsString('imageprofile="event_full"', $event);
        self::assertStringContainsString('name="userfile" type="jemimagefile"', $venue);
        self::assertStringContainsString('imageprofile="venue"', $venue);
    }

    public function testBackendUploaderUsesTheSameCameraIntegration(): void
    {
        $template = $this->read('/admin/views/imagehandler/tmpl/uploadimage.php');

        self::assertStringContainsString("JemImageCamera::button(", $template);
        self::assertStringContainsString("'adminForm'", $template);
        self::assertStringContainsString('id="userfile" type="file" accept="image/*"', $template);
    }

    public function testCameraAssetUsesLiveVideoAndUploadsOnlyTheProcessedFile(): void
    {
        $script = $this->read('/media/js/image-camera.js');
        $helper = $this->read('/site/classes/imagecamera.class.php');

        self::assertStringContainsString('navigator.mediaDevices.getUserMedia', $script);
        self::assertStringContainsString('navigator.mediaDevices.enumerateDevices', $script);
        self::assertStringContainsString('<video autoplay muted playsinline>', $script);
        self::assertStringContainsString('id="jem-camera-device-select"', $script);
        self::assertStringContainsString('id="jem-camera-format-select"', $script);
        self::assertStringContainsString("device.kind === 'videoinput'", $script);
        self::assertStringContainsString('video.videoWidth > 0 && video.videoHeight > 0', $script);
        self::assertStringContainsString('videoConstraints.deviceId = {exact: requestedDeviceId};', $script);
        self::assertStringContainsString('data-jem-image-resolution-control', $script);
        self::assertStringContainsString('data-jem-camera-resolution-field', $helper);
        self::assertStringContainsString('JemImageProfilePolicy::requestedMaxDimension', $this->read('/site/classes/image.class.php'));
        self::assertStringContainsString('sourceAndOutputGeometry', $script);
        self::assertStringContainsString('compressCanvas', $script);
        self::assertStringContainsString('compressCanvas(source, activeConfig, cameraFormatSelect.value)', $script);
        self::assertStringContainsString("var order = ['image/jpeg', 'image/png', 'image/webp'];", $script);
        self::assertStringContainsString('preferred && mimeTypes.indexOf(preferred) !== -1', $script);
        self::assertStringContainsString('new File([capturedBlob]', $script);
        self::assertStringContainsString('activeInput.files = transfer.files', $script);
        self::assertStringContainsString('track.stop()', $script);
        self::assertStringNotContainsString('fetch(', $script);
        self::assertStringNotContainsString('XMLHttpRequest', $script);
    }

    public function testCameraButtonUsesAnAccessibleIconBesideTheFileInput(): void
    {
        $helper = $this->read('/site/classes/imagecamera.class.php');
        $field = $this->read('/site/models/fields/jemimagefile.php');
        $stylesheet = $this->read('/media/css/image-camera.css');

        self::assertStringContainsString('jem-camera-button__icon', $helper);
        self::assertStringContainsString('class="btn jem-image-action-button jem-camera-button"', $helper);
        self::assertStringNotContainsString('btn-secondary jem-image-action-button jem-camera-button', $helper);
        self::assertStringContainsString("Text::_('COM_JEM_CAMERA_TAKE_PHOTO')", $helper);
        self::assertStringContainsString('<span class="visually-hidden">', $helper);
        self::assertStringContainsString("'<div class=\"jem-image-source-controls\">'", $field);
        self::assertStringContainsString("'<div class=\"jem-image-file-input\">'", $field);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr) auto;', $stylesheet);
        self::assertStringContainsString('width: 2.5rem;', $stylesheet);
        self::assertStringContainsString('justify-content: center;', $stylesheet);
        self::assertStringContainsString('align-self: stretch;', $stylesheet);
        self::assertStringContainsString('height: 100%;', $stylesheet);
        self::assertStringContainsString('width: 1.5rem;', $stylesheet);
        self::assertStringContainsString('height: 1.5rem;', $stylesheet);
        self::assertStringContainsString('top: 50%;', $stylesheet);
        self::assertStringContainsString('left: 50%;', $stylesheet);
        self::assertStringContainsString('transform: translate(-50%, -50%);', $stylesheet);
        self::assertStringContainsString(
            '--bs-btn-color: var(--bs-body-color, var(--body-color, #212529));',
            $stylesheet
        );
        self::assertStringContainsString(
            '--bs-btn-hover-color: var(--bs-body-color, var(--body-color, #212529));',
            $stylesheet
        );
        self::assertStringContainsString(
            '--bs-btn-active-color: var(--bs-body-color, var(--body-color, #212529));',
            $stylesheet
        );
        self::assertStringContainsString('color: var(--bs-btn-color) !important;', $stylesheet);
        self::assertStringContainsString('fill: currentColor;', $stylesheet);
        self::assertStringContainsString('data-jem-camera-input', $helper);
        self::assertStringContainsString('$profile === JemImageProfilePolicy::CATEGORY', $helper);
        self::assertStringContainsString('array(128, 300, 600, 800, 1080, 1200, 1440, 1920, 2560, 3840)', $helper);
        self::assertStringContainsString('1440', $helper);
        self::assertStringContainsString('$maximum = JemImageProfilePolicy::maxDimension($settings);', $helper);
        self::assertStringNotContainsString('array(128, 512, 800', $helper);
        self::assertStringNotContainsString('array(128, 512, 800, 1280, 1920, 2560, 3840)', $helper);
        self::assertStringContainsString('$value > $minimum && $value < $maximum', $helper);
        self::assertStringNotContainsString('$marks[] = $maximum;', $helper);
        self::assertStringContainsString('class="jem-image-resolution-range"', $helper);
        self::assertStringContainsString('data-jem-image-resolution-label=', $helper);
        self::assertStringContainsString('data-jem-image-ratio-select', $helper);
        self::assertStringContainsString("Text::_('COM_JEM_IMAGE_UPLOAD_RATIO_LABEL')", $helper);
        self::assertStringContainsString('JemImageProfilePolicy::defaultUploadMaxDimension', $helper);
    }

    public function testCameraActionsUseNeutralButtonsWithReadableSpacing(): void
    {
        $helper = $this->read('/site/classes/imagecamera.class.php');
        $script = $this->read('/media/js/image-camera.js');
        $stylesheet = $this->read('/media/css/image-camera.css');

        self::assertStringContainsString('jem-image-action-button jem-camera-button', $helper);
        self::assertStringContainsString('jem-camera-action-button', $script);
        self::assertStringNotContainsString('btn btn-primary', $script);
        self::assertStringContainsString('gap: 0.65rem;', $stylesheet);
        self::assertStringContainsString('background-color: var(--bs-tertiary-bg, #f1f3f5);', $stylesheet);
        self::assertStringContainsString('.jem-camera-device,', $stylesheet);
        self::assertStringContainsString('.jem-camera-format {', $stylesheet);
        self::assertStringContainsString('.jem-camera-options {', $stylesheet);
        self::assertStringContainsString('grid-template-columns: auto minmax(14rem, 28rem);', $stylesheet);
        self::assertStringContainsString('.jem-image-resolution {', $stylesheet);
        self::assertStringContainsString('.jem-image-resolution-mark::before {', $stylesheet);
        self::assertStringContainsString('.jem-image-resolution-mark:nth-child(even)::after {', $stylesheet);
        self::assertStringContainsString('bottom: calc(100% + 0.05rem);', $stylesheet);
        self::assertStringContainsString('--jem-resolution-progress', $stylesheet);
        self::assertStringContainsString('::-webkit-slider-runnable-track', $stylesheet);
        self::assertStringContainsString('::-moz-range-track', $stylesheet);
        self::assertStringContainsString("range.style.setProperty('--jem-resolution-progress'", $script);
        self::assertStringContainsString('pointer-events: auto;', $stylesheet);
    }

    public function testSelectedUploadRatioIsAppliedByTheClientAndServer(): void
    {
        $script = $this->read('/media/js/image-camera.js');
        $image = $this->read('/site/classes/image.class.php');
        $event = $this->read('/admin/tables/event.php');
        $venue = $this->read('/admin/tables/venue.php');
        $imageHandler = $this->read('/admin/controllers/imagehandler.php');

        self::assertStringContainsString('applyRatioSelection', $script);
        self::assertStringContainsString('data-jem-image-ratio-mode', $this->read('/site/classes/imagecamera.class.php'));
        self::assertStringContainsString('JemImageProfilePolicy::resolveUpload', $image);
        self::assertStringContainsString("preg_replace('/\\bkB\\b/u', 'KB'", $image);
        self::assertStringContainsString("getCmd('image_ratio', '')", $event);
        self::assertStringContainsString("getCmd('fullimage_ratio', '')", $event);
        self::assertStringContainsString("getCmd('image_ratio', '')", $venue);
        self::assertStringContainsString("getCmd('image_ratio', '')", $imageHandler);
    }

    public function testMandatoryProfileSettingsHideControlsAndRemainServerAuthoritative(): void
    {
        $policy = $this->read('/site/classes/imageprofilepolicy.class.php');
        $helper = $this->read('/site/classes/imagecamera.class.php');
        $script = $this->read('/media/js/image-camera.js');

        self::assertStringContainsString('function isDimensionMandatory(', $policy);
        self::assertStringContainsString('function isRatioMandatory(', $policy);
        self::assertStringContainsString('self::isDimensionMandatory($settings, $profile)', $policy);
        self::assertStringContainsString('self::isRatioMandatory($settings, $profile)', $policy);
        self::assertStringContainsString('$showResolution = !JemImageProfilePolicy::isDimensionMandatory', $helper);
        self::assertStringContainsString('$showRatio = !JemImageProfilePolicy::isRatioMandatory', $helper);
        self::assertStringContainsString("if (!\$showResolution && !\$showRatio)", $helper);
        self::assertStringContainsString('type="hidden" id="', $helper);
        self::assertStringContainsString('mandatoryPolicySummary(', $helper);
        self::assertStringContainsString('jem-image-resolution--policy-only', $helper);
        self::assertStringContainsString('jem-image-resolution--ratio-only', $helper);
        self::assertStringContainsString('jem-image-resolution--resolution-only', $helper);
        self::assertStringContainsString('COM_JEM_IMAGE_POLICY_RESOLUTION', $helper);
        self::assertStringContainsString('COM_JEM_IMAGE_POLICY_RATIO', $helper);
        self::assertStringContainsString('COM_JEM_IMAGE_POLICY_MAX_SIZE', $helper);
        self::assertStringContainsString('JemImage::formattedMaxUploadSize($settings)', $helper);
        self::assertStringNotContainsString("return \$wrapper . ' hidden>'", $helper);
        self::assertStringContainsString('.jem-image-resolution--policy-only', $this->read('/media/css/image-camera.css'));
        self::assertStringContainsString('.jem-image-resolution--ratio-only', $this->read('/media/css/image-camera.css'));
        self::assertStringContainsString('if (!range || !number)', $script);
        self::assertStringContainsString('if (range && number)', $script);

        foreach (array(
            '/site/language/en-GB/com_jem.ini',
            '/admin/language/en-GB/com_jem.ini',
        ) as $path) {
            $language = $this->read($path);
            self::assertStringContainsString('COM_JEM_IMAGE_MANDATORY_POLICY=', $language);
            self::assertStringContainsString('COM_JEM_IMAGE_POLICY_RESOLUTION=', $language);
            self::assertStringContainsString('COM_JEM_IMAGE_POLICY_RATIO=', $language);
            self::assertStringContainsString('COM_JEM_IMAGE_POLICY_MAX_SIZE=', $language);
        }
    }

    public function testMismatchedFilesUseTheInteractiveCropOrCentredPadEditor(): void
    {
        $script = $this->read('/media/js/image-camera.js');
        $stylesheet = $this->read('/media/css/image-camera.css');

        self::assertStringContainsString('openImageEditor', $script);
        self::assertStringContainsString('editorCanvas.addEventListener(\'pointermove\'', $script);
        self::assertStringContainsString('(canvas.width - width) / 2', $script);
        self::assertStringContainsString('(canvas.height - height) / 2', $script);
        self::assertStringContainsString('compressCanvas(editedImageCanvas(), editorConfig)', $script);
        self::assertStringContainsString("editorStage.style.aspectRatio = String(ratio);", $script);
        self::assertStringContainsString("editorStage.style.width = 'min(100%, '", $script);
        self::assertStringContainsString('.jem-image-editor__canvas--draggable', $stylesheet);
        self::assertStringContainsString('overflow: hidden;', $stylesheet);
        self::assertStringNotContainsString('fetch(', $script);
    }

    public function testProcessedUploadPreviewCanReopenTheCropEditorFromItsOriginalSource(): void
    {
        $script = $this->read('/media/js/image-camera.js');
        $previewScript = $this->read('/media/js/other.js');
        $stylesheet = $this->read('/media/css/image-camera.css');

        self::assertStringContainsString('function reopenImageEditor(preview)', $script);
        self::assertStringContainsString('editorSourceFiles.get(input)', $script);
        self::assertStringContainsString(
            'openImageEditor(input, sourceFile, config, image, true)',
            $script
        );
        self::assertStringContainsString("config.mode === 'crop'", $script);
        self::assertStringContainsString("preview.setAttribute('role', 'button')", $script);
        self::assertStringContainsString("preview.setAttribute('tabindex', '0')", $script);
        self::assertStringContainsString("event.key !== 'Enter' && event.key !== ' '", $script);
        self::assertStringContainsString('editorKeepSelectionOnCancel', $script);
        self::assertStringContainsString('createCameraSourceCanvas()', $script);
        self::assertStringContainsString('editorSourceFiles.set(activeInput, sourceFile)', $script);
        self::assertStringContainsString(
            "fileInput.dispatchEvent(new Event('change', {bubbles: true}))",
            $previewScript
        );
        self::assertStringContainsString('img.jem-image-preview-adjustable', $stylesheet);
        self::assertStringContainsString('cursor: zoom-in;', $stylesheet);
        self::assertStringContainsString(':focus-visible', $stylesheet);
    }

    public function testVenueImageLayoutsUseTheSameStructuredFullWidthComposition(): void
    {
        foreach (array(
            '/site/views/editvenue/tmpl/edit_extended.php',
            '/site/views/editvenue/tmpl/responsive/edit_extended.php',
        ) as $path) {
            $template = $this->read($path);

            self::assertStringContainsString('class="jem-image-upload-label"', $template);
            self::assertStringContainsString('jem-image-upload-row--alt', $template);
            self::assertStringContainsString('jem-image-action-button', $template);
            self::assertStringContainsString('JemImageCamera::resolutionControl(', $template);
            self::assertStringContainsString('jem-image-actions--last', $template);
            self::assertLessThan(
                strpos($template, 'jem-image-actions--last'),
                strpos($template, 'jem-image-upload-row--alt'),
                $path . ' must place Clear immediately after the alternative-text row'
            );
            self::assertLessThan(
                strpos($template, 'jem-image-preview-stage'),
                strpos($template, 'jem-image-actions--last'),
                $path . ' must keep Clear in the field column before the preview'
            );
        }

        foreach (array(
            '/site/views/editvenue/tmpl/edit.php',
            '/site/views/editvenue/tmpl/responsive/edit.php',
        ) as $path) {
            self::assertStringContainsString(
                "'editvenue-extendedtab', Text::_('COM_JEM_IMAGE')",
                $this->read($path)
            );
        }

        foreach (array('/media/css/jem.css', '/media/css/jem-responsive.css') as $path) {
            $stylesheet = $this->read($path);

            self::assertStringContainsString('.jem-image-upload-row--alt input[type="text"]', $stylesheet);
            self::assertStringContainsString(
                'grid-template-columns: minmax(11rem, 14rem) minmax(0, 1fr);',
                $stylesheet
            );
        }
    }

    public function testEventImageProfilesExposeFileCameraAndIndependentPreviews(): void
    {
        foreach (array(
            '/site/views/editevent/tmpl/edit.php',
            '/site/views/editevent/tmpl/responsive/edit.php',
        ) as $path) {
            $template = $this->read($path);

            self::assertSame(2, substr_count($template, 'jem-editevent-image-field jem-image-upload-panel'), $path);
            self::assertSame(2, substr_count($template, 'jem-image-selected-preview'), $path);
            self::assertGreaterThanOrEqual(2, substr_count($template, 'jem-image-preview-stage'), $path);
            self::assertStringContainsString("getInput('userfile')", $template);
            self::assertStringContainsString("getInput('fulluserfile')", $template);
            self::assertStringNotContainsString('showImageConflictMessage', $template);
            self::assertStringNotContainsString('COM_JEM_IMAGE_UPLOAD_CONFLICT', $template);
            self::assertStringNotContainsString(
                '.jem-editevent-image-upload > div:not(:first-child)',
                $template
            );
        }

        $previewScript = $this->read('/media/js/other.js');
        $legacyStylesheet = $this->read('/media/css/jem.css');
        $responsiveStylesheet = $this->read('/media/css/jem-responsive.css');

        self::assertStringContainsString('.jem-image-upload-panel input[type="file"]', $previewScript);
        self::assertStringContainsString("previewWrap.querySelector('img')", $previewScript);
        self::assertStringContainsString("setServerImageValue(imageSelect, '');", $previewScript);
        self::assertStringContainsString('fancySelect.choicesInstance.removeActiveItems();', $previewScript);
        self::assertStringContainsString('fancySelect.choicesInstance.setChoiceByValue(value);', $previewScript);
        self::assertStringContainsString("fileInput.dispatchEvent(new Event('change', {bubbles: true}));", $previewScript);
        self::assertStringContainsString("removeImage.value = '0';", $previewScript);
        self::assertStringNotContainsString("$('#jform_userfile').on('change'", $previewScript);
        self::assertStringNotContainsString('#jem-selected-venue-image-preview', $previewScript);
        self::assertStringNotContainsString('stage.appendChild(cameraButton);', $previewScript);
        self::assertStringNotContainsString('jem-camera-button--overlay', $previewScript);

        foreach (array($legacyStylesheet, $responsiveStylesheet) as $stylesheet) {
            self::assertStringContainsString('object-position: center center;', $stylesheet);
            self::assertStringNotContainsString('object-position: center top;', $stylesheet);
            self::assertStringNotContainsString('> .jem-camera-button', $stylesheet);
        }

        $cameraScript = $this->read('/media/js/image-camera.js');
        self::assertStringContainsString('editorSourceFiles.delete(input);', $cameraScript);
    }

    public function testServerImageSelectorProvidesAVisibleSearchField(): void
    {
        $field = $this->read('/site/models/fields/imageselectevent.php');
        $categoryField = $this->read('/site/models/fields/imageselectcategory.php');

        self::assertStringContainsString('search-placeholder=', $field);
        self::assertStringContainsString('min-term-length="1"', $field);
        self::assertStringContainsString('min-width: 100% !important;', $field);
        self::assertStringContainsString('width: 100% !important;', $field);
        self::assertStringContainsString("getValue('image_path', null, '')", $field);
        self::assertStringContainsString('JemVenueImagePath::normaliseRelativeFolder', $field);
        self::assertStringContainsString('JemVenueImagePath::absoluteImageFolder', $field);
        self::assertStringContainsString('JemEventImagePath::normaliseRelativeFolder', $field);
        self::assertStringContainsString('JemEventImagePath::absoluteImageFolder', $field);
        self::assertStringContainsString("Folder::files(\$path, '\\.(jpg|jpeg|png|gif|webp|svg)\$'", $field);
        self::assertStringContainsString('data-jem-image-base-url=', $field);
        self::assertStringContainsString('protected $imageRelativePath', $field);
        self::assertStringContainsString('class="jem-server-image-path"', $field);
        self::assertStringContainsString("Text::_('COM_JEM_SERVER_IMAGE_PATH')", $field);
        self::assertStringContainsString("'images/jem/categories'", $categoryField);
        self::assertStringContainsString(
            'COM_JEM_SERVER_IMAGE_PATH="Server folder"',
            $this->read('/site/language/en-GB/com_jem.ini')
        );
        self::assertStringContainsString(
            'COM_JEM_SERVER_IMAGE_PATH="Server folder"',
            $this->read('/admin/language/en-GB/com_jem.ini')
        );
        self::assertStringContainsString(
            '.jem-server-image-path {',
            $this->read('/media/css/image-camera.css')
        );

        $previewScript = $this->read('/media/js/other.js');
        self::assertStringContainsString(
            'joomla-field-fancy-select[data-jem-image-base-url] select',
            $previewScript
        );
        self::assertStringContainsString('encodeURIComponent(filename)', $previewScript);
    }

    public function testVenueImageTabOffersFolderScopedServerAndFileSources(): void
    {
        foreach (array(
            '/site/views/editvenue/tmpl/edit_extended.php',
            '/site/views/editvenue/tmpl/responsive/edit_extended.php',
        ) as $path) {
            $template = $this->read($path);
            $serverImage = strpos($template, "getInput('locimage')");
            $newImage = strpos($template, "getInput('userfile')");

            self::assertNotFalse($serverImage, $path);
            self::assertNotFalse($newImage, $path);
            self::assertTrue($serverImage < $newImage, $path);
            self::assertStringContainsString('data-jem-image-select="jform_locimage"', $template);
            self::assertStringContainsString('data-jem-image-file="jform_userfile"', $template);
        }
    }

    public function testVenueImageStorageUsesVariablesForJoomlaDatabaseUpdates(): void
    {
        $model = $this->read('/admin/models/venue.php');
        $methodStart = strpos($model, 'private function syncVenueImageStorage');

        self::assertNotFalse($methodStart);
        $method = substr($model, (int) $methodStart, 2600);
        self::assertStringContainsString('$clearImagePath = (object) array(', $method);
        self::assertStringContainsString(
            "updateObject('#__jem_venues', \$clearImagePath, 'id')",
            $method
        );
        self::assertStringContainsString('$updateImagePath = (object) array(', $method);
        self::assertStringContainsString(
            "updateObject('#__jem_venues', \$updateImagePath, 'id')",
            $method
        );
        self::assertDoesNotMatchRegularExpression(
            "/updateObject\\(\\s*'#__jem_venues'\\s*,\\s*\\(object\\)/",
            $method
        );
    }

    public function testPendingEventUploadWinsOverCategoryDefaultImage(): void
    {
        $model = $this->read('/admin/models/event.php');
        $methodStart = strpos($model, 'protected function applyCategoryDefaultEventImage');

        self::assertNotFalse($methodStart);
        $method = substr($model, (int) $methodStart, 2600);
        self::assertStringContainsString("files->get('userfile'", $method);
        self::assertStringContainsString("\$formFiles['userfile']", $method);
        self::assertStringContainsString("!empty(\$pendingImage['name'])", $method);
    }

    public function testTypesRemainOutsideTheCameraImageScope(): void
    {
        $typeForm = $this->read('/admin/models/forms/type.xml');

        self::assertStringNotContainsString('jemimagefile', $typeForm);
        self::assertStringNotContainsString('imageselect', $typeForm);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
