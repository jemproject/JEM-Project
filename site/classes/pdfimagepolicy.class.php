<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/imageresourcepolicy.class.php';

/**
 * Resolves local raster images that may be passed to the PDF renderer.
 */
final class JemPdfImagePolicy
{
    /**
     * @var array<string, string>
     */
    private const TYPE_FOLDERS = array(
        'event'    => 'events',
        'venue'    => 'venues',
        'category' => 'categories',
    );

    /**
     * @var array<int, int>
     */
    private const ALLOWED_IMAGE_TYPES = array(
        IMAGETYPE_GIF,
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_WEBP,
    );

    /**
     * @var array<int, string>
     */
    private const ALLOWED_ROOTS = array(
        'images',
        'media/com_jem',
    );

    /**
     * Resolve a stored source to an existing image contained by Joomla media roots.
     * Absolute HTTP(S) values are treated as path hints and are never downloaded.
     */
    public static function resolveLocalImage(
        string $source,
        string $type,
        string $siteRoot,
        string $basePath = '',
        int $maxDimension = JemImageResourcePolicy::DEFAULT_MAX_DIMENSION
    ): string
    {
        $source = trim(html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($source === '' || self::containsControlCharacter($source)) {
            return '';
        }

        $source = str_replace('\\', '/', $source);

        if (strpos($source, '//') === 0) {
            return '';
        }

        $parts = parse_url($source);

        if ($parts === false) {
            return '';
        }

        if (!empty($parts['scheme'])) {
            if (!in_array(strtolower((string) $parts['scheme']), array('http', 'https'), true)
                || isset($parts['user'])
                || isset($parts['pass'])) {
                return '';
            }

            $path = (string) ($parts['path'] ?? '');
        } elseif (!empty($parts['host'])) {
            return '';
        } else {
            $path = (string) ($parts['path'] ?? '');
        }

        $path = rawurldecode(str_replace('\\', '/', $path));

        if ($path === '' || self::containsControlCharacter($path) || strpos($path, ':') !== false) {
            return '';
        }

        $path = ltrim($path, '/');
        $basePath = trim(rawurldecode(str_replace('\\', '/', $basePath)), '/');

        if ($basePath !== '' && $path === $basePath) {
            return '';
        }

        if ($basePath !== '' && strpos($path, $basePath . '/') === 0) {
            $path = substr($path, strlen($basePath) + 1);
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                return '';
            }
        }

        $segments = array_values(array_filter($segments, static function ($segment) {
            return $segment !== '';
        }));

        if (!$segments) {
            return '';
        }

        $relativeCandidates = array(implode('/', $segments));

        if (count($segments) === 1 && isset(self::TYPE_FOLDERS[$type])) {
            array_unshift($relativeCandidates, 'images/jem/' . self::TYPE_FOLDERS[$type] . '/' . $segments[0]);
        }

        $siteRoot = realpath($siteRoot);

        if ($siteRoot === false || !is_dir($siteRoot)) {
            return '';
        }

        foreach (array_unique($relativeCandidates) as $relative) {
            $allowedRootName = self::getAllowedRoot($relative);

            if ($allowedRootName === '') {
                continue;
            }

            $allowedRoot = realpath(
                $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $allowedRootName)
            );
            $candidate = realpath($siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));

            if ($allowedRoot === false || $candidate === false || !is_file($candidate)) {
                continue;
            }

            if (!self::isContainedPath($candidate, $allowedRoot) || !self::isSupportedImage($candidate, $maxDimension)) {
                continue;
            }

            return $candidate;
        }

        return '';
    }

    private static function getAllowedRoot(string $relative): string
    {
        foreach (self::ALLOWED_ROOTS as $allowedRoot) {
            if ($relative === $allowedRoot || strpos($relative, $allowedRoot . '/') === 0) {
                return $allowedRoot;
            }
        }

        return '';
    }

    private static function containsControlCharacter(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    private static function isContainedPath(string $candidate, string $allowedRoot): bool
    {
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        $allowedRoot = rtrim(str_replace('\\', '/', $allowedRoot), '/');

        if (DIRECTORY_SEPARATOR === '\\') {
            $candidate = strtolower($candidate);
            $allowedRoot = strtolower($allowedRoot);
        }

        return strpos($candidate, $allowedRoot . '/') === 0;
    }

    private static function isSupportedImage(string $path, int $maxDimension): bool
    {
        $info = @getimagesize($path);

        if (!is_array($info) || !isset($info[2])
            || !in_array((int) $info[2], self::ALLOWED_IMAGE_TYPES, true)) {
            return false;
        }

        $resource = JemImageResourcePolicy::inspect(
            $path,
            strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            JemImageResourcePolicy::normaliseConfiguredMaxDimension($maxDimension)
        );

        return $resource['accepted'];
    }
}
