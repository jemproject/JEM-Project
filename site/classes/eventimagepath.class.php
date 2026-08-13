<?php
/**
 * @version    5.1.0-alpha1
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

/**
 * Normalises and resolves event image subfolder paths.
 *
 * image_path is always relative to images/jem/events and may never point outside
 * that root. An empty image_path keeps the legacy flat folder behaviour.
 */
class JemEventImagePath
{
    public const BASE = 'images/jem/events';
    public const THUMB = 'small';

    public static function normaliseRelativeFolder($folder, $maxDepth = 0)
    {
        $folder = trim(str_replace('\\', '/', (string) $folder));
        $folder = trim($folder, '/');

        if ($folder === '') {
            return '';
        }

        $segments = array();

        foreach (explode('/', $folder) as $segment) {
            $segment = self::cleanFolderSegment($segment);

            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        $maxDepth = (int) $maxDepth;
        if ($maxDepth > 0 && count($segments) > $maxDepth) {
            $segments = array_slice($segments, 0, $maxDepth);
        }

        return implode('/', $segments);
    }

    public static function cleanFolderSegment($segment)
    {
        $segment = trim((string) $segment);
        $segment = str_replace(array('\\', '/', "\0"), '', $segment);

        if ($segment === '' || $segment === '.' || $segment === '..') {
            return '';
        }

        $segment = OutputFilter::stringURLSafe($segment);
        $segment = File::makeSafe($segment);
        $segment = trim($segment, '.-_ ');

        if ($segment === '' || strtolower($segment) === self::THUMB) {
            return '';
        }

        return $segment;
    }

    public static function imagePath($folder, $filename)
    {
        return self::buildPath(self::BASE, $folder, $filename);
    }

    public static function thumbPath($folder, $filename)
    {
        return self::buildPath(self::BASE . '/' . self::THUMB, $folder, $filename);
    }

    public static function imagePathFromEvent($event, $field = 'datimage')
    {
        $filename = is_object($event) && isset($event->$field) ? $event->$field : '';
        $folder   = is_object($event) && isset($event->image_path) ? $event->image_path : '';

        return self::imagePath($folder, $filename);
    }

    public static function thumbPathFromEvent($event, $field = 'datimage')
    {
        $filename = is_object($event) && isset($event->$field) ? $event->$field : '';
        $folder   = is_object($event) && isset($event->image_path) ? $event->image_path : '';

        return self::thumbPath($folder, $filename);
    }

    public static function isSubfoldersEnabled($attribs = null)
    {
        $attribs = $attribs ?: JemHelper::globalattribs();

        return (int) $attribs->get('event_image_subfolder_enabled', 0) === 1;
    }

    public static function configuredFolderFromEvent($event, $attribs = null)
    {
        $attribs = $attribs ?: JemHelper::globalattribs();

        if (!self::isSubfoldersEnabled($attribs)) {
            return self::normaliseRelativeFolder(self::eventValue($event, 'image_path', ''));
        }

        $preset  = (string) $attribs->get('event_image_subfolder_preset', 'root');
        $pattern = self::patternFromPreset($preset, (string) $attribs->get('event_image_subfolder_pattern', ''));
        $maxDepth = max(1, min(8, (int) $attribs->get('event_image_subfolder_max_depth', 3)));

        if ($pattern === '') {
            return '';
        }

        return self::normaliseRelativeFolder(self::replaceTokens($pattern, $event), $maxDepth);
    }

    public static function ensureEventFolders($folder)
    {
        $folder = self::normaliseRelativeFolder($folder);
        $paths = array(
            JPATH_SITE . '/' . self::BASE . ($folder !== '' ? '/' . $folder : ''),
            JPATH_SITE . '/' . self::BASE . '/' . self::THUMB . ($folder !== '' ? '/' . $folder : '')
        );

        foreach ($paths as $path) {
            $path = Path::clean($path);

            if (!Folder::exists($path) && !Folder::create($path)) {
                return false;
            }
        }

        return true;
    }

    public static function absoluteImageFolder($folder)
    {
        $relative = self::BASE . ($folder !== '' ? '/' . self::normaliseRelativeFolder($folder) : '');

        return Path::clean(JPATH_SITE . '/' . $relative) . DIRECTORY_SEPARATOR;
    }

    public static function absoluteThumbFolder($folder)
    {
        $relative = self::BASE . '/' . self::THUMB . ($folder !== '' ? '/' . self::normaliseRelativeFolder($folder) : '');

        return Path::clean(JPATH_SITE . '/' . $relative) . DIRECTORY_SEPARATOR;
    }

    public static function createThumbnail($folder, $filename, $sourcePath, $settings)
    {
        $filename = File::makeSafe((string) $filename);

        if ($filename === '' || (int) ($settings->gddisabled ?? 0) !== 1) {
            return;
        }

        if (!self::ensureEventFolders($folder)) {
            return;
        }

        $target = Path::clean(JPATH_SITE . '/' . self::thumbPath($folder, $filename));

        if (!File::exists($target)) {
            JemImage::thumb($sourcePath, $target, (int) $settings->imagewidth, (int) $settings->imagehight);
        }
    }

    public static function relocateEventImages($fromFolder, $toFolder, array $filenames, $settings, $move = true)
    {
        $fromFolder = self::normaliseRelativeFolder($fromFolder);
        $toFolder   = self::normaliseRelativeFolder($toFolder);

        if ($fromFolder === $toFolder) {
            return true;
        }

        if (!self::ensureEventFolders($toFolder)) {
            return false;
        }

        $basePath      = Path::clean(JPATH_SITE . '/' . self::BASE);
        $baseThumbPath = Path::clean(JPATH_SITE . '/' . self::BASE . '/' . self::THUMB);
        $filenames     = array_unique(array_filter(array_map(static function ($filename) {
            return File::makeSafe((string) $filename);
        }, $filenames)));

        foreach ($filenames as $filename) {
            if ($filename === '') {
                continue;
            }

            $source = Path::clean(JPATH_SITE . '/' . self::imagePath($fromFolder, $filename));
            $target = Path::clean(JPATH_SITE . '/' . self::imagePath($toFolder, $filename));

            if (!self::isInsideBase($source, $basePath) || !self::isInsideBase($target, $basePath)) {
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

            if (!self::isInsideBase($sourceThumb, $baseThumbPath) || !self::isInsideBase($targetThumb, $baseThumbPath)) {
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
        $basePath     = rtrim(Path::clean((string) $basePath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return strpos($absolutePath . DIRECTORY_SEPARATOR, $basePath) === 0;
    }

    private static function patternFromPreset($preset, $customPattern)
    {
        switch ($preset) {
            case 'year':
                return '{year}';
            case 'year_category':
                return '{year}/{category_alias}';
            case 'year_category_venue':
                return '{year}/{category_alias}/{venue_alias}';
            case 'category_venue':
                return '{category_alias}/{venue_alias}';
            case 'venue_year':
                return '{venue_alias}/{year}';
            case 'custom':
                return trim((string) $customPattern);
            case 'root':
            default:
                return '';
        }
    }

    private static function replaceTokens($pattern, $event)
    {
        $context = self::buildContext($event);

        return preg_replace_callback('/\{([a-z0-9_]+)\}/i', static function ($matches) use ($context) {
            $key = strtolower($matches[1]);

            return $context[$key] ?? '';
        }, $pattern);
    }

    private static function buildContext($event)
    {
        $date = self::eventValue($event, 'dates', '') ?: self::eventValue($event, 'created', '');
        $timestamp = $date ? strtotime((string) $date) : false;

        if (!$timestamp) {
            $timestamp = time();
        }

        $context = array(
            'year'          => date('Y', $timestamp),
            'month'         => date('m', $timestamp),
            'month_name'    => date('F', $timestamp),
            'quarter'       => 'q' . ceil((int) date('n', $timestamp) / 3),
            'week'          => date('W', $timestamp),
            'event_id'      => (string) (int) self::eventValue($event, 'id', 0),
            'event_alias'   => self::eventValue($event, 'alias', ''),
            'import_source' => self::eventValue($event, 'import_source', ''),
            'import_profile'=> self::eventValue($event, 'import_profile', ''),
            'category_alias'=> '',
            'venue_alias'   => '',
            'city'          => '',
            'region'        => '',
            'country'       => '',
            'type_alias'    => ''
        );

        $categoryId = self::firstCategoryId($event);
        if ($categoryId > 0) {
            $context['category_alias'] = self::lookupValue('#__jem_categories', 'alias', $categoryId);
        }

        $venueId = (int) self::eventValue($event, 'locid', 0);
        if ($venueId > 0) {
            $venue = self::lookupRow('#__jem_venues', array('alias', 'city', 'state', 'country'), $venueId);
            $context['venue_alias'] = $venue['alias'] ?? '';
            $context['city']        = $venue['city'] ?? '';
            $context['region']      = $venue['state'] ?? '';
            $context['country']     = $venue['country'] ?? '';
        }

        $typeId = (int) self::eventValue($event, 'type_id', 0);
        if ($typeId > 0) {
            $context['type_alias'] = self::lookupValue('#__jem_types', 'alias', $typeId);
        }

        foreach ($context as $key => $value) {
            $context[$key] = self::cleanFolderSegment($value);
        }

        return $context;
    }

    private static function firstCategoryId($event)
    {
        $cats = self::eventValue($event, 'cats', array());

        if (is_string($cats)) {
            $cats = explode(',', $cats);
        }

        if (is_array($cats)) {
            foreach ($cats as $cat) {
                if (is_array($cat)) {
                    $cat = $cat['id'] ?? $cat['catid'] ?? 0;
                } elseif (is_object($cat)) {
                    $cat = $cat->id ?? $cat->catid ?? 0;
                }

                $cat = (int) $cat;
                if ($cat > 0) {
                    return $cat;
                }
            }
        }

        return (int) self::eventValue($event, 'catid', 0);
    }

    private static function lookupValue($table, $field, $id)
    {
        $row = self::lookupRow($table, array($field), $id);

        return (string) ($row[$field] ?? '');
    }

    private static function lookupRow($table, array $fields, $id)
    {
        $id = (int) $id;

        if ($id <= 0) {
            return array();
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName($fields))
                ->from($db->quoteName($table))
                ->where($db->quoteName('id') . ' = ' . $id);
            $db->setQuery($query);

            return (array) $db->loadAssoc();
        } catch (Throwable $e) {
            return array();
        }
    }

    private static function eventValue($event, $field, $default = '')
    {
        if (is_array($event) && array_key_exists($field, $event)) {
            return $event[$field];
        }

        if (is_object($event) && isset($event->$field)) {
            return $event->$field;
        }

        return $default;
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
