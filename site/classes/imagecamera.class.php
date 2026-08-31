<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/imageprofilepolicy.class.php';
require_once __DIR__ . '/image.class.php';

/**
 * Shared camera-capture integration for profile-backed JEM image fields.
 */
final class JemImageCamera
{
    /**
     * Register the shared camera assets and translated JavaScript messages.
     */
    public static function registerAssets(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;
        $document = Factory::getApplication()->getDocument();
        $wa = $document->getWebAssetManager();
        JemHelper::loadCss('image-camera');
        $wa->registerAndUseScript(
            'com_jem.image-camera',
            'media/com_jem/js/image-camera.js',
            array(),
            array('defer' => true)
        );

        foreach (array(
            'COM_JEM_CAMERA_DIALOG_TITLE',
            'COM_JEM_CAMERA_LIVE_PREVIEW',
            'COM_JEM_CAMERA_CAPTURE',
            'COM_JEM_CAMERA_RETAKE',
            'COM_JEM_CAMERA_USE_PHOTO',
            'COM_JEM_CAMERA_CANCEL',
            'COM_JEM_CAMERA_CLOSE',
            'COM_JEM_CAMERA_DEVICE_LABEL',
            'COM_JEM_CAMERA_DEVICE_FALLBACK',
            'COM_JEM_CAMERA_FORMAT_LABEL',
            'COM_JEM_CAMERA_STARTING',
            'COM_JEM_CAMERA_SWITCHING',
            'COM_JEM_CAMERA_PROCESSING',
            'COM_JEM_CAMERA_READY',
            'COM_JEM_CAMERA_RESULT',
            'COM_JEM_CAMERA_UNAVAILABLE',
            'COM_JEM_CAMERA_SECURE_CONTEXT_REQUIRED',
            'COM_JEM_CAMERA_PERMISSION_DENIED',
            'COM_JEM_CAMERA_PROCESSING_FAILED',
            'COM_JEM_CAMERA_OUTPUT_TOO_LARGE',
            'COM_JEM_CAMERA_FILE_ASSIGN_FAILED',
            'COM_JEM_CAMERA_RATIO_LABEL',
            'COM_JEM_CAMERA_MODE_CROP',
            'COM_JEM_CAMERA_MODE_PAD',
            'COM_JEM_CAMERA_MODE_NONE',
            'COM_JEM_IMAGE_EDITOR_TITLE',
            'COM_JEM_IMAGE_EDITOR_CROP_HELP',
            'COM_JEM_IMAGE_EDITOR_PAD_HELP',
            'COM_JEM_IMAGE_EDITOR_ZOOM',
            'COM_JEM_IMAGE_EDITOR_USE_IMAGE',
            'COM_JEM_IMAGE_EDITOR_CLOSE',
        ) as $key) {
            Text::script($key);
        }
    }

    /**
     * Return the trusted client hints for a server-side image profile.
     * The upload validator remains authoritative.
     *
     * @return array<string, mixed>
     */
    public static function configuration($settings, string $profile): array
    {
        $profile = JemImageProfilePolicy::isProfile($profile)
            ? $profile
            : JemImageProfilePolicy::EVENT_INTRO;
        $resolved = JemImageProfilePolicy::resolve($settings, $profile);
        $dimensionMandatory = JemImageProfilePolicy::isDimensionMandatory($settings, $profile);
        $allowed = array_filter(array_map(
            'trim',
            explode(',', strtolower((string) ($settings->image_filetypes ?? 'jpg,gif,png,webp')))
        ));
        $mimeTypes = array();
        $extensions = array();

        if (in_array('jpg', $allowed, true) || in_array('jpeg', $allowed, true)) {
            $mimeTypes[] = 'image/jpeg';
            $extensions['image/jpeg'] = in_array('jpg', $allowed, true) ? 'jpg' : 'jpeg';
        }
        if (in_array('webp', $allowed, true)) {
            $mimeTypes[] = 'image/webp';
            $extensions['image/webp'] = 'webp';
        }
        if (in_array('png', $allowed, true)) {
            $mimeTypes[] = 'image/png';
            $extensions['image/png'] = 'png';
        }

        return array(
            'profile' => $profile,
            'mode' => $resolved['mode'],
            'ratioWidth' => (int) $resolved['ratio_width'],
            'ratioHeight' => (int) $resolved['ratio_height'],
            'maxDimension' => $dimensionMandatory
                ? JemImageProfilePolicy::defaultUploadMaxDimension($settings, $profile)
                : JemImageProfilePolicy::maxDimension($settings),
            'minDimension' => JemImageProfilePolicy::minDimension($settings),
            'dimensionMandatory' => $dimensionMandatory,
            'ratioMandatory' => JemImageProfilePolicy::isRatioMandatory($settings, $profile),
            'maxBytes' => max(
                1,
                (int) ($settings->sizelimit ?? JemImageProfilePolicy::DEFAULT_MAX_FILE_SIZE_KB)
            ) * 1024,
            'mimeTypes' => $mimeTypes,
            'extensions' => $extensions,
            'paddingColor' => '#000000',
        );
    }

    /**
     * Render a camera button targeting an existing file input.
     */
    public static function button(
        string $inputId,
        string $profile,
        $settings,
        string $clearSelectId = '',
        string $removeFieldId = '',
        string $submitFormId = '',
        string $resolutionFieldId = ''
    ): string {
        self::registerAssets();

        $config = self::configuration($settings, $profile);
        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $icon = '<svg class="jem-camera-button__icon" viewBox="0 0 24 24" focusable="false" aria-hidden="true">'
            . '<path fill="currentColor"'
            . ' d="M9 3 7.2 5H4a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h16a3 3 0 0 0 3-3V8a3 3 0 0 0-3-3h-3.2L15 3H9Z'
            . 'm3 5a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/>'
            . '</svg>';
        $attributes = array(
            'data-jem-camera-input' => $inputId,
            'data-jem-camera-config' => (string) $configJson,
        );

        if ($clearSelectId !== '') {
            $attributes['data-jem-camera-clear-select'] = $clearSelectId;
        }
        if ($removeFieldId !== '') {
            $attributes['data-jem-camera-remove-field'] = $removeFieldId;
        }
        if ($submitFormId !== '') {
            $attributes['data-jem-camera-submit-form'] = $submitFormId;
        }
        if ($resolutionFieldId !== '') {
            $attributes['data-jem-camera-resolution-field'] = $resolutionFieldId;
        }

        $attributeHtml = '';
        foreach ($attributes as $name => $value) {
            $attributeHtml .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<button type="button" class="btn jem-image-action-button jem-camera-button"'
            . ' title="' . htmlspecialchars(Text::_('COM_JEM_CAMERA_TAKE_PHOTO'), ENT_QUOTES, 'UTF-8') . '"'
            . ' aria-label="' . htmlspecialchars(Text::_('COM_JEM_CAMERA_TAKE_PHOTO'), ENT_QUOTES, 'UTF-8') . '"'
            . $attributeHtml
            . '>' . $icon . '<span class="visually-hidden">'
            . Text::_('COM_JEM_CAMERA_TAKE_PHOTO') . '</span></button>';
    }

    /**
     * Render the per-upload output resolution control shared by file and camera sources.
     */
    public static function resolutionControl(
        string $fieldName,
        string $fieldId,
        string $profile,
        $settings
    ): string
    {
        self::registerAssets();

        $minimum = JemImageProfilePolicy::minimumOutputMaxDimension($settings, $profile);
        $maximum = JemImageProfilePolicy::maxDimension($settings);
        $defaultDimension = JemImageProfilePolicy::defaultUploadMaxDimension($settings, $profile);
        $showResolution = !JemImageProfilePolicy::isDimensionMandatory($settings, $profile);
        $showRatio = !JemImageProfilePolicy::isRatioMandatory($settings, $profile);
        $mandatoryPolicy = self::mandatoryPolicySummary(
            $settings,
            $profile,
            !$showResolution,
            !$showRatio
        );
        $marks = $profile === JemImageProfilePolicy::CATEGORY
            ? array(128, 300, 600, 800, 1080, 1200, 1440, 1920, 2560, 3840)
            : array(300, 600, 800, 1080, 1200, 1440, 1920, 2560, 3840);
        $marks = array_values(array_unique(array_filter(
            $marks,
            static fn(int $value): bool => $value > $minimum && $value < $maximum
        )));
        sort($marks, SORT_NUMERIC);

        $safeName = htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8');
        $safeId = htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8');
        $ratioFieldName = preg_replace('/_max_dimension$/', '_ratio', $fieldName);
        $ratioFieldName = $ratioFieldName !== $fieldName ? $ratioFieldName : $fieldName . '_ratio';
        $ratioFieldId = $fieldId . '-ratio';
        $safeRatioName = htmlspecialchars($ratioFieldName, ENT_QUOTES, 'UTF-8');
        $safeRatioId = htmlspecialchars($ratioFieldId, ENT_QUOTES, 'UTF-8');
        $numberId = $safeId . '-number';
        $buttons = '';
        $ratioOptions = '';
        $defaultRatio = JemImageProfilePolicy::defaultUploadRatio($settings, $profile);

        foreach ($marks as $mark) {
            $naturalPosition = $maximum > $minimum
                ? (($mark - $minimum) / ($maximum - $minimum)) * 100
                : 50.0;
            $labelOffset = $naturalPosition < 5.0
                ? '0%'
                : ($naturalPosition > 95.0 ? '-100%' : '-50%');
            $buttons .= '<button type="button" class="jem-image-resolution-mark"'
                . ' data-jem-image-resolution-value="' . $mark . '"'
                . ' data-jem-image-resolution-label="' . $mark . '"'
                . ' aria-label="' . $mark . ' px"'
                . ' style="--jem-resolution-position: ' . number_format($naturalPosition, 3, '.', '') . '%;'
                . ' --jem-resolution-label-offset: ' . $labelOffset . '">'
                . '<span class="visually-hidden">' . $mark . ' px</span></button>';
        }

        if ($showRatio) {
            foreach (JemImageProfilePolicy::uploadRatioOptions($settings, $profile) as $preset => $ratio) {
                $uploadConfig = JemImageProfilePolicy::resolveUpload($settings, $profile, $preset);
                $label = $preset === JemImageProfilePolicy::UPLOAD_RATIO_ORIGINAL
                    ? Text::_('COM_JEM_IMAGE_PROFILE_ORIGINAL_RATIO')
                    : $ratio[0] . ':' . $ratio[1];
                $ratioOptions .= '<option value="' . htmlspecialchars($preset, ENT_QUOTES, 'UTF-8') . '"'
                    . ' data-jem-image-ratio-mode="' . $uploadConfig['mode'] . '"'
                    . ' data-jem-image-ratio-width="' . $uploadConfig['ratio_width'] . '"'
                    . ' data-jem-image-ratio-height="' . $uploadConfig['ratio_height'] . '"'
                    . ' data-jem-image-resolution-min="'
                    . JemImageProfilePolicy::minimumOutputMaxDimension($settings, $profile, $preset) . '"'
                    . ($preset === $defaultRatio ? ' selected' : '') . '>'
                    . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
            }
        }

        $wrapperClass = 'jem-image-resolution jem-image-resolution--leading';

        if (!$showResolution && !$showRatio) {
            $wrapperClass .= ' jem-image-resolution--policy-only';
        } elseif (!$showResolution) {
            $wrapperClass .= ' jem-image-resolution--ratio-only';
        } elseif (!$showRatio) {
            $wrapperClass .= ' jem-image-resolution--resolution-only';
        }

        $wrapper = '<div class="' . $wrapperClass . '"'
            . ' data-jem-image-resolution-control'
            . ' data-jem-image-resolution-default="' . $defaultDimension . '"'
            . ' data-jem-image-ratio-default="'
            . htmlspecialchars($defaultRatio, ENT_QUOTES, 'UTF-8') . '"';
        $fixedResolution = '<input type="hidden" id="' . $safeId . '" name="' . $safeName . '"'
            . ' min="' . $minimum . '" max="' . $maximum . '" value="' . $defaultDimension . '">';

        if (!$showResolution && !$showRatio) {
            return $wrapper . '>'
                . $fixedResolution
                . '<small class="jem-image-mandatory-policy">'
                . htmlspecialchars($mandatoryPolicy, ENT_QUOTES, 'UTF-8')
                . '</small></div>';
        }

        $headingFor = $showResolution ? $safeId : $safeRatioId;
        $headingText = $showResolution
            ? Text::_('COM_JEM_IMAGE_RESOLUTION_LABEL')
            : Text::_('COM_JEM_IMAGE_UPLOAD_RATIO_LABEL');
        $inputsClass = 'jem-image-resolution-inputs';

        if (!$showResolution) {
            $inputsClass .= ' jem-image-resolution-inputs--ratio-only';
        } elseif (!$showRatio) {
            $inputsClass .= ' jem-image-resolution-inputs--resolution-only';
        }

        $resolutionInput = $fixedResolution;

        if ($showResolution) {
            $resolutionInput = '<div class="jem-image-resolution-range">'
                . '<input type="range" id="' . $safeId . '" name="' . $safeName . '"'
                . ' min="' . $minimum . '" max="' . $maximum . '" value="' . $defaultDimension . '" step="1"'
                . ' data-jem-image-resolution-range>'
                . '<div class="jem-image-resolution-mark-track" aria-label="'
                . htmlspecialchars(Text::_('COM_JEM_IMAGE_RESOLUTION_LABEL'), ENT_QUOTES, 'UTF-8') . '">'
                . $buttons . '</div>'
                . '</div>'
                . '<label class="visually-hidden" for="' . $numberId . '">'
                . Text::_('COM_JEM_IMAGE_RESOLUTION_NUMBER_LABEL') . '</label>'
                . '<input type="number" class="form-control" id="' . $numberId . '"'
                . ' min="' . $minimum . '" max="' . $maximum . '" value="' . $defaultDimension . '" step="1"'
                . ' inputmode="numeric" data-jem-image-resolution-number>';
        }

        $ratioInput = '';

        if ($showRatio) {
            $ratioInput = ($showResolution
                    ? '<label class="jem-image-ratio-label" for="' . $safeRatioId . '">'
                        . Text::_('COM_JEM_IMAGE_UPLOAD_RATIO_LABEL') . '</label>'
                    : '')
                . '<select class="form-select jem-image-ratio-select" id="' . $safeRatioId . '"'
                . ' name="' . $safeRatioName . '" data-jem-image-ratio-select>'
                . $ratioOptions . '</select>';
        }

        return $wrapper . '>'
            . '<div class="jem-image-resolution-heading">'
            . '<label for="' . $headingFor . '">' . $headingText . '</label>'
            . '</div>'
            . '<div class="' . $inputsClass . '">'
            . $resolutionInput
            . $ratioInput
            . '</div>'
            . '<div class="jem-image-resolution-meta">'
            . ($showResolution
                ? '<small class="jem-image-resolution-help">' . Text::_('COM_JEM_IMAGE_RESOLUTION_HELP') . '</small>'
                : '')
            . '<small class="' . ($mandatoryPolicy !== ''
                ? 'jem-image-mandatory-policy'
                : 'jem-image-profile-summary') . '">'
            . htmlspecialchars(
                $mandatoryPolicy !== '' ? $mandatoryPolicy : JemImage::profileSummary($settings, $profile),
                ENT_QUOTES,
                'UTF-8'
            )
            . '</small>'
            . '</div>'
            . '</div>';
    }

    private static function mandatoryPolicySummary(
        $settings,
        string $profile,
        bool $dimensionMandatory,
        bool $ratioMandatory
    ): string {
        if (!$dimensionMandatory && !$ratioMandatory) {
            return '';
        }

        $parts = array();

        if ($dimensionMandatory) {
            $parts[] = Text::sprintf(
                'COM_JEM_IMAGE_POLICY_RESOLUTION',
                JemImageProfilePolicy::defaultUploadMaxDimension($settings, $profile)
            );
        }

        if ($ratioMandatory) {
            $resolved = JemImageProfilePolicy::resolve($settings, $profile);
            $ratio = $resolved['mode'] === JemImageProfilePolicy::MODE_NONE
                ? Text::_('COM_JEM_IMAGE_PROFILE_ORIGINAL_RATIO')
                : $resolved['ratio_width'] . ':' . $resolved['ratio_height'];
            $parts[] = Text::sprintf('COM_JEM_IMAGE_POLICY_RATIO', $ratio);
        }

        $parts[] = Text::sprintf(
            'COM_JEM_IMAGE_POLICY_MAX_SIZE',
            JemImage::formattedMaxUploadSize($settings)
        );

        return Text::sprintf('COM_JEM_IMAGE_MANDATORY_POLICY', implode(' · ', $parts));
    }
}
