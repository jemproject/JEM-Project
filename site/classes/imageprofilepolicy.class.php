<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/imageresourcepolicy.class.php';

/**
 * Resolves the configurable image policy for each public JEM image role.
 */
final class JemImageProfilePolicy
{
    public const DEFAULT_MIN_DIMENSION = 64;
    public const DEFAULT_CONFIGURED_MAX_DIMENSION = 2560;
    public const DEFAULT_MAX_FILE_SIZE_KB = 400;
    public const MIN_CONFIGURED_MIN_DIMENSION = 64;

    public const EVENT_INTRO = 'event_intro';
    public const EVENT_FULL = 'event_full';
    public const VENUE = 'venue';
    public const CATEGORY = 'category';

    public const MODE_NONE = 'none';
    public const MODE_PAD = 'pad';
    public const MODE_CROP = 'crop';
    public const UPLOAD_RATIO_ORIGINAL = 'original';

    /** @var array<string, string> */
    private const PREFIXES = array(
        self::EVENT_INTRO => 'image_event_intro',
        self::EVENT_FULL  => 'image_event_full',
        self::VENUE       => 'image_venue',
        self::CATEGORY    => 'image_category',
    );

    /** @var array<string, string> */
    private const DEFAULT_RATIOS = array(
        self::EVENT_INTRO => '16_9',
        self::EVENT_FULL  => '16_9',
        self::VENUE       => '4_3',
        self::CATEGORY    => '1_1',
    );

    /** @var array<string, int> */
    private const DEFAULT_UPLOAD_DIMENSIONS = array(
        self::EVENT_INTRO => 1200,
        self::EVENT_FULL  => 1920,
        self::VENUE       => 1280,
        self::CATEGORY    => 800,
    );

    /** @var array<string, array{0: int, 1: int}> */
    private const PRESET_RATIOS = array(
        '1_1'  => array(1, 1),
        '4_3'  => array(4, 3),
        '3_4'  => array(3, 4),
        '3_2'  => array(3, 2),
        '2_3'  => array(2, 3),
        '8_5'  => array(8, 5),
        '5_8'  => array(5, 8),
        '16_9' => array(16, 9),
        '9_16' => array(9, 16),
        '21_9' => array(21, 9),
        '9_21' => array(9, 21),
    );

    /**
     * @return array<int, string>
     */
    public static function profiles(): array
    {
        return array_keys(self::PREFIXES);
    }

    public static function isProfile(string $profile): bool
    {
        return isset(self::PREFIXES[$profile]);
    }

    public static function prefix(string $profile): string
    {
        return self::PREFIXES[$profile] ?? self::PREFIXES[self::EVENT_INTRO];
    }

    public static function maxDimension($settings): int
    {
        return JemImageResourcePolicy::normaliseConfiguredMaxDimension(
            (int) self::setting($settings, 'image_max_dimension', self::DEFAULT_CONFIGURED_MAX_DIMENSION)
        );
    }

    public static function minDimension($settings): int
    {
        $value = (int) self::setting($settings, 'image_min_dimension', self::DEFAULT_MIN_DIMENSION);

        return max(
            self::MIN_CONFIGURED_MIN_DIMENSION,
            min($value, self::maxDimension($settings))
        );
    }

    public static function defaultUploadMaxDimension($settings, string $profile): int
    {
        $profile = self::isProfile($profile) ? $profile : self::EVENT_INTRO;
        $configured = (int) self::setting(
            $settings,
            self::prefix($profile) . '_default_dimension',
            self::DEFAULT_UPLOAD_DIMENSIONS[$profile]
        );

        return self::requestedMaxDimension($settings, $profile, $configured);
    }

    /**
     * Minimum longest-side target that can still satisfy the configured
     * minimum dimensions for a fixed-ratio profile.
     */
    public static function minimumOutputMaxDimension(
        $settings,
        string $profile,
        ?string $requestedRatio = null
    ): int
    {
        $minimum = self::minDimension($settings);
        $config = self::resolveUpload($settings, $profile, $requestedRatio);

        if ($config['mode'] === self::MODE_NONE) {
            return $minimum;
        }

        $shortRatio = min($config['ratio_width'], $config['ratio_height']);
        $longRatio = max($config['ratio_width'], $config['ratio_height']);
        $ratioUnit = (int) ceil($minimum / max(1, $shortRatio));

        return min(self::maxDimension($settings), $longRatio * max(1, $ratioUnit));
    }

    /**
     * Clamp an untrusted per-upload target to the active profile policy.
     */
    public static function requestedMaxDimension(
        $settings,
        string $profile,
        $requested,
        int $sourceWidth = 0,
        int $sourceHeight = 0,
        ?string $requestedRatio = null
    ): int
    {
        $maximum = self::maxDimension($settings);
        $minimum = self::minimumOutputMaxDimension($settings, $profile, $requestedRatio);
        $config = self::resolveUpload($settings, $profile, $requestedRatio);

        if (self::isDimensionMandatory($settings, $profile)) {
            $requested = self::setting(
                $settings,
                self::prefix($profile) . '_default_dimension',
                self::DEFAULT_UPLOAD_DIMENSIONS[$profile]
            );
        }

        if ($config['mode'] === self::MODE_NONE && $sourceWidth > 0 && $sourceHeight > 0) {
            $shortSide = min($sourceWidth, $sourceHeight);
            $longSide = max($sourceWidth, $sourceHeight);
            $minimum = min(
                $maximum,
                (int) ceil(self::minDimension($settings) * $longSide / max(1, $shortSide))
            );
        }

        $value = filter_var($requested, FILTER_VALIDATE_INT);

        if ($value === false) {
            return $maximum;
        }

        return max($minimum, min($maximum, (int) $value));
    }

    /**
     * Existing images within the original security boundary remain displayable
     * when an administrator lowers the upload limit.
     */
    public static function displayMaxDimension($settings): int
    {
        return max(JemImageResourcePolicy::DEFAULT_MAX_DIMENSION, self::maxDimension($settings));
    }

    /**
     * @return array{profile: string, mode: string, preset: string, custom: string, ratio_width: int, ratio_height: int}
     */
    public static function resolve($settings, string $profile): array
    {
        $profile = self::isProfile($profile) ? $profile : self::EVENT_INTRO;
        $prefix = self::prefix($profile);
        $mode = self::normaliseMode((string) self::setting($settings, $prefix . '_mode', self::MODE_NONE));
        $preset = self::normalisePreset(
            (string) self::setting($settings, $prefix . '_ratio', self::DEFAULT_RATIOS[$profile]),
            self::DEFAULT_RATIOS[$profile]
        );
        $custom = self::normaliseCustomRatio((string) self::setting($settings, $prefix . '_custom_ratio', ''));
        $ratio = $preset === 'custom' ? self::parseRatio($custom) : (self::PRESET_RATIOS[$preset] ?? null);

        if ($ratio === null) {
            $preset = self::DEFAULT_RATIOS[$profile];
            $ratio = self::PRESET_RATIOS[$preset];
        }

        return array(
            'profile' => $profile,
            'mode' => $mode,
            'preset' => $preset,
            'custom' => $custom,
            'ratio_width' => $ratio[0],
            'ratio_height' => $ratio[1],
        );
    }

    /**
     * Resolve the policy for one new upload without modifying global Settings.
     * Unknown values fall back to the configured profile policy.
     *
     * @return array{profile: string, mode: string, preset: string, custom: string, ratio_width: int, ratio_height: int}
     */
    public static function resolveUpload($settings, string $profile, ?string $requestedRatio = null): array
    {
        $configured = self::resolve($settings, $profile);
        $requestedRatio = trim((string) $requestedRatio);

        if ($requestedRatio === '' || self::isRatioMandatory($settings, $profile)) {
            return $configured;
        }

        if ($requestedRatio === self::UPLOAD_RATIO_ORIGINAL) {
            $configured['mode'] = self::MODE_NONE;
            $configured['preset'] = self::UPLOAD_RATIO_ORIGINAL;
            $configured['custom'] = '';
            $configured['ratio_width'] = 1;
            $configured['ratio_height'] = 1;

            return $configured;
        }

        if ($requestedRatio === 'custom' && $configured['preset'] === 'custom') {
            return $configured;
        }

        if (!isset(self::PRESET_RATIOS[$requestedRatio])) {
            return $configured;
        }

        $configured['mode'] = $configured['mode'] === self::MODE_NONE
            ? self::MODE_CROP
            : $configured['mode'];
        $configured['preset'] = $requestedRatio;
        $configured['custom'] = '';
        $configured['ratio_width'] = self::PRESET_RATIOS[$requestedRatio][0];
        $configured['ratio_height'] = self::PRESET_RATIOS[$requestedRatio][1];

        return $configured;
    }

    /**
     * Return the ratios that may be selected for one upload.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function uploadRatioOptions($settings, string $profile): array
    {
        $configured = self::resolve($settings, $profile);
        $options = array_merge(
            array(self::UPLOAD_RATIO_ORIGINAL => array(0, 0)),
            self::PRESET_RATIOS
        );

        if ($configured['preset'] === 'custom') {
            $options['custom'] = array(
                $configured['ratio_width'],
                $configured['ratio_height'],
            );
        }

        return $options;
    }

    public static function defaultUploadRatio($settings, string $profile): string
    {
        $configured = self::resolve($settings, $profile);

        return $configured['mode'] === self::MODE_NONE
            ? self::UPLOAD_RATIO_ORIGINAL
            : $configured['preset'];
    }

    public static function normaliseMode(string $mode): string
    {
        return in_array($mode, array(self::MODE_NONE, self::MODE_PAD, self::MODE_CROP), true)
            ? $mode
            : self::MODE_NONE;
    }

    public static function normalisePreset(string $preset, string $default = '1_1'): string
    {
        if ($preset === 'custom' || isset(self::PRESET_RATIOS[$preset])) {
            return $preset;
        }

        return isset(self::PRESET_RATIOS[$default]) ? $default : '1_1';
    }

    public static function normaliseCustomRatio(string $ratio): string
    {
        $parsed = self::parseRatio($ratio);

        return $parsed === null ? '' : $parsed[0] . ':' . $parsed[1];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public static function parseRatio(string $ratio): ?array
    {
        $ratio = preg_replace('/\s+/', '', trim($ratio));

        if (!is_string($ratio) || !preg_match('/^([1-9][0-9]{0,3}):([1-9][0-9]{0,3})$/', $ratio, $match)) {
            return null;
        }

        $width = (int) $match[1];
        $height = (int) $match[2];
        $divisor = self::greatestCommonDivisor($width, $height);

        return array((int) ($width / $divisor), (int) ($height / $divisor));
    }

    public static function isExactRatio(int $width, int $height, int $ratioWidth, int $ratioHeight): bool
    {
        return $width > 0 && $height > 0 && $ratioWidth > 0 && $ratioHeight > 0
            && ($width * $ratioHeight) === ($height * $ratioWidth);
    }

    public static function ratioFitsBounds(int $minimum, int $maximum, int $ratioWidth, int $ratioHeight): bool
    {
        if ($minimum < 1 || $maximum < $minimum || $ratioWidth < 1 || $ratioHeight < 1) {
            return false;
        }

        $minimumUnit = max(
            (int) ceil($minimum / $ratioWidth),
            (int) ceil($minimum / $ratioHeight)
        );
        $maximumUnit = min(
            (int) floor($maximum / $ratioWidth),
            (int) floor($maximum / $ratioHeight)
        );

        return $minimumUnit <= $maximumUnit;
    }

    /**
     * Calculate a bounded output canvas without stretching or unnecessary upscaling.
     *
     * @return array{width: int, height: int, method: string, changed: bool}
     */
    public static function geometry(
        int $width,
        int $height,
        int $maxDimension,
        string $mode,
        int $ratioWidth,
        int $ratioHeight
    ): array {
        $width = max(1, $width);
        $height = max(1, $height);
        $maxDimension = JemImageResourcePolicy::normaliseConfiguredMaxDimension($maxDimension);
        $scale = min(1.0, $maxDimension / $width, $maxDimension / $height);
        $baseWidth = max(1, (int) floor($width * $scale));
        $baseHeight = max(1, (int) floor($height * $scale));
        $mode = self::normaliseMode($mode);

        if ($mode === self::MODE_NONE || self::isExactRatio($baseWidth, $baseHeight, $ratioWidth, $ratioHeight)) {
            return array(
                'width' => $baseWidth,
                'height' => $baseHeight,
                'method' => 'contain',
                'changed' => $baseWidth !== $width || $baseHeight !== $height,
            );
        }

        if ($mode === self::MODE_CROP) {
            $unit = max(1, min(
                (int) floor($baseWidth / $ratioWidth),
                (int) floor($baseHeight / $ratioHeight)
            ));
            $targetWidth = $ratioWidth * $unit;
            $targetHeight = $ratioHeight * $unit;

            return array(
                'width' => $targetWidth,
                'height' => $targetHeight,
                'method' => 'crop',
                'changed' => true,
            );
        }

        // Use the largest exact canvas that still forces at least one source
        // axis to scale down. This lets boxed resize add padding without ever
        // enlarging the source pixels merely to satisfy ratio rounding.
        $unit = max(1, (int) floor(max(
            $baseWidth / $ratioWidth,
            $baseHeight / $ratioHeight
        )));
        $maximumUnit = max(1, min(
            (int) floor($maxDimension / $ratioWidth),
            (int) floor($maxDimension / $ratioHeight)
        ));
        $unit = min($unit, $maximumUnit);

        return array(
            'width' => $ratioWidth * $unit,
            'height' => $ratioHeight * $unit,
            'method' => 'pad',
            'changed' => true,
        );
    }

    public static function signature($settings, string $profile): string
    {
        $config = self::resolve($settings, $profile);

        if ($config['mode'] === self::MODE_NONE) {
            return self::MODE_NONE;
        }

        return $config['mode'] . ':' . $config['ratio_width'] . ':' . $config['ratio_height'];
    }

    public static function isRequired($settings, string $profile): bool
    {
        if (!self::isProfile($profile)) {
            return false;
        }

        return (int) self::setting($settings, self::prefix($profile) . '_required', 0) === 1;
    }

    public static function isDimensionMandatory($settings, string $profile): bool
    {
        if (!self::isProfile($profile)) {
            return false;
        }

        return (int) self::setting($settings, self::prefix($profile) . '_dimension_mandatory', 0) === 1;
    }

    public static function isRatioMandatory($settings, string $profile): bool
    {
        if (!self::isProfile($profile)) {
            return false;
        }

        return (int) self::setting($settings, self::prefix($profile) . '_ratio_mandatory', 0) === 1;
    }

    private static function setting($settings, string $key, $default)
    {
        if (is_object($settings) && method_exists($settings, 'get')) {
            return $settings->get($key, $default);
        }

        if (is_object($settings) && isset($settings->$key)) {
            return $settings->$key;
        }

        if (is_array($settings) && array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return $default;
    }

    private static function greatestCommonDivisor(int $left, int $right): int
    {
        while ($right !== 0) {
            $remainder = $left % $right;
            $left = $right;
            $right = $remainder;
        }

        return max(1, $left);
    }
}
