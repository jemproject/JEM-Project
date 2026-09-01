<?php
/**
 * @version    5.1.0
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

require_once __DIR__ . '/imageprofilepolicy.class.php';
require_once __DIR__ . '/imagefolderpolicy.class.php';

/**
 * Resolves category image paths below the fixed JEM category image root.
 */
class JemCategoryImagePath
{
    public const BASE = 'images/jem/categories';
    public const THUMB = 'small';

    public static function normaliseRelativeFolder($folder)
    {
        $folder = trim(str_replace('\\', '/', (string) $folder), '/');

        if ($folder === '') {
            return '';
        }

        $segments = array();

        foreach (explode('/', $folder) as $segment) {
            $segment = self::cleanSegment($segment);

            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }

    public static function imagePath($folder, $filename)
    {
        return self::buildPath(self::BASE, $folder, $filename);
    }

    public static function thumbPath($folder, $filename)
    {
        return self::buildPath(self::BASE . '/' . self::THUMB, $folder, $filename);
    }

    public static function absoluteImageFolder($folder)
    {
        $folder = self::normaliseRelativeFolder($folder);

        return Path::clean(JPATH_SITE . '/' . self::BASE . ($folder !== '' ? '/' . $folder : ''))
            . DIRECTORY_SEPARATOR;
    }

    public static function absoluteThumbFolder($folder)
    {
        $folder = self::normaliseRelativeFolder($folder);

        return Path::clean(JPATH_SITE . '/' . self::BASE . '/' . self::THUMB . ($folder !== '' ? '/' . $folder : ''))
            . DIRECTORY_SEPARATOR;
    }

    public static function isSubfoldersEnabled($attribs = null)
    {
        $attribs = $attribs ?: JemHelper::globalattribs();

        return (int) $attribs->get('category_image_subfolder_enabled', 0) === 1;
    }

    public static function configuredFolderFromCategory($category, $attribs = null)
    {
        $attribs = $attribs ?: JemHelper::globalattribs();
        $current = self::normaliseRelativeFolder(self::value($category, 'image_path', ''));

        if (!self::isSubfoldersEnabled($attribs)) {
            return $current;
        }

        $preset = (string) $attribs->get('category_image_subfolder_preset', 'root');
        $pattern = self::patternFromPreset(
            $preset,
            (string) $attribs->get('category_image_subfolder_pattern', '')
        );
        if ($pattern === '') {
            return '';
        }

        $folder = self::normaliseRelativeFolder(self::replaceTokens($pattern, $category));

        return JemImageFolderPolicy::resolvedFolderOrRoot($folder);
    }

    public static function ensureFolders($folder)
    {
        foreach (array(self::absoluteImageFolder($folder), self::absoluteThumbFolder($folder)) as $path) {
            if (!Folder::exists($path) && !Folder::create($path)) {
                return false;
            }
        }

        return true;
    }

    public static function createThumbnail($folder, $filename, $sourcePath, $settings)
    {
        $filename = File::makeSafe((string) $filename);

        if ($filename === '' || (int) ($settings->gddisabled ?? 0) !== 1 || !self::ensureFolders($folder)) {
            return false;
        }

        $target = Path::clean(JPATH_SITE . '/' . self::thumbPath($folder, $filename));

        if (!File::exists($target)) {
            JemImage::thumb(
                $sourcePath,
                $target,
                (int) $settings->imagewidth,
                (int) $settings->imagehight,
                JemImageProfilePolicy::displayMaxDimension($settings)
            );
        }

        return File::exists($target);
    }

    public static function relocateImages($fromFolder, $toFolder, array $filenames, $settings, $move = false)
    {
        $fromFolder = self::normaliseRelativeFolder($fromFolder);
        $toFolder = self::normaliseRelativeFolder($toFolder);

        if ($fromFolder === $toFolder) {
            return true;
        }

        if (!self::ensureFolders($toFolder)) {
            return false;
        }

        $base = Path::clean(JPATH_SITE . '/' . self::BASE);
        $thumbBase = Path::clean(JPATH_SITE . '/' . self::BASE . '/' . self::THUMB);
        $filenames = array_unique(array_filter(array_map(static function ($filename) {
            return File::makeSafe((string) $filename);
        }, $filenames)));

        foreach ($filenames as $filename) {
            $source = Path::clean(JPATH_SITE . '/' . self::imagePath($fromFolder, $filename));
            $target = Path::clean(JPATH_SITE . '/' . self::imagePath($toFolder, $filename));

            if (!self::isInsideBase($source, $base) || !self::isInsideBase($target, $base)) {
                return false;
            }

            if (!File::exists($source)) {
                continue;
            }

            if (!File::exists($target)) {
                $ok = $move ? File::move($source, $target) : File::copy($source, $target);

                if (!$ok) {
                    return false;
                }
            }

            $sourceThumb = Path::clean(JPATH_SITE . '/' . self::thumbPath($fromFolder, $filename));
            $targetThumb = Path::clean(JPATH_SITE . '/' . self::thumbPath($toFolder, $filename));

            if (!self::isInsideBase($sourceThumb, $thumbBase) || !self::isInsideBase($targetThumb, $thumbBase)) {
                return false;
            }

            if (File::exists($sourceThumb) && !File::exists($targetThumb)) {
                $ok = $move ? File::move($sourceThumb, $targetThumb) : File::copy($sourceThumb, $targetThumb);

                if (!$ok) {
                    return false;
                }
            } elseif (!File::exists($targetThumb)) {
                self::createThumbnail($toFolder, $filename, $target, $settings);
            }
        }

        return true;
    }

    public static function isInsideBase($absolutePath, $basePath)
    {
        $absolutePath = Path::clean((string) $absolutePath);
        $basePath = rtrim(Path::clean((string) $basePath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return strpos($absolutePath . DIRECTORY_SEPARATOR, $basePath) === 0;
    }

    private static function patternFromPreset($preset, $customPattern)
    {
        switch ($preset) {
            case 'category':
                return '{category_alias}';
            case 'parent_category':
                return '{parent_category_alias}/{category_alias}';
            case 'type_category':
                return '{type_alias}/{category_alias}';
            case 'custom':
                return trim((string) $customPattern);
            case 'root':
            default:
                return '';
        }
    }

    private static function replaceTokens($pattern, $category)
    {
        $context = self::buildContext($category);

        return preg_replace_callback('/\{([a-z0-9_]+)\}/i', static function ($matches) use ($context) {
            return $context[strtolower($matches[1])] ?? '';
        }, $pattern);
    }

    private static function buildContext($category)
    {
        $created = self::value($category, 'created_time', '');
        $timestamp = $created ? strtotime((string) $created) : false;
        $timestamp = $timestamp ?: time();
        $parentId = (int) self::value($category, 'parent_id', 0);
        $typeId = (int) self::value($category, 'type_id', 0);
        $context = array(
            'year' => date('Y', $timestamp),
            'category_id' => (string) (int) self::value($category, 'id', 0),
            'category_alias' => self::value($category, 'alias', ''),
            'parent_category_alias' => $parentId > 1
                ? self::lookupValue('#__jem_categories', 'alias', $parentId)
                : '',
            'type_alias' => $typeId > 0 ? self::lookupValue('#__jem_types', 'alias', $typeId) : '',
        );

        foreach ($context as $key => $value) {
            $context[$key] = self::cleanSegment($value);
        }

        return $context;
    }

    private static function lookupValue($table, $field, $id)
    {
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName($field))
                ->from($db->quoteName($table))
                ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($query, 0, 1);

            return (string) $db->loadResult();
        } catch (Throwable $exception) {
            return '';
        }
    }

    private static function value($category, $field, $default = '')
    {
        if (is_array($category) && array_key_exists($field, $category)) {
            return $category[$field];
        }

        if (is_object($category) && isset($category->$field)) {
            return $category->$field;
        }

        return $default;
    }

    private static function cleanSegment($segment)
    {
        $segment = trim(str_replace(array('\\', '/', "\0"), '', (string) $segment));

        if ($segment === '' || $segment === '.' || $segment === '..') {
            return '';
        }

        $segment = File::makeSafe(OutputFilter::stringURLSafe($segment));
        $segment = trim($segment, '.-_ ');

        return $segment !== '' && strtolower($segment) !== self::THUMB ? $segment : '';
    }

    private static function buildPath($base, $folder, $filename)
    {
        $filename = File::makeSafe((string) $filename);

        if ($filename === '') {
            return '';
        }

        $folder = self::normaliseRelativeFolder($folder);

        return $base . '/' . ($folder !== '' ? $folder . '/' : '') . $filename;
    }
}
