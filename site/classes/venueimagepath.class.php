<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

/**
 * Resolves local Venue media paths using stable database identifiers.
 *
 * Original files and thumbnails use mirrored trees:
 * images/jem/venues/{object path}/{file}
 * images/jem/venues/small/{object path}/{file}
 *
 * An empty Venue image path preserves the JEM 5.0 flat-folder behaviour.
 */
class JemVenueImagePath
{
    public const BASE = 'images/jem/venues';
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

    public static function venueFolder($venueId)
    {
        $venueId = (int) $venueId;

        return $venueId > 0 ? $venueId . '/venue' : '';
    }

    public static function profileFolder($venueId, $profileId)
    {
        return self::idFolder($venueId, 'profiles', $profileId);
    }

    public static function spaceFolder($venueId, $spaceId)
    {
        return self::idFolder($venueId, 'spaces', $spaceId) . '/space';
    }

    public static function layoutFolder($venueId, $spaceId, $layoutId)
    {
        $base = self::idFolder($venueId, 'spaces', $spaceId);
        $layoutId = (int) $layoutId;

        return $base !== '' && $layoutId > 0 ? $base . '/layouts/' . $layoutId . '/layout' : '';
    }

    public static function areaFolder($venueId, $spaceId, $layoutId, $areaId)
    {
        $layout = self::layoutFolder($venueId, $spaceId, $layoutId);
        $areaId = (int) $areaId;

        return $layout !== '' && $areaId > 0
            ? dirname(str_replace('\\', '/', $layout)) . '/areas/' . $areaId
            : '';
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
        $relative = self::BASE . ($folder !== '' ? '/' . $folder : '');

        return Path::clean(JPATH_SITE . '/' . $relative) . DIRECTORY_SEPARATOR;
    }

    public static function absoluteThumbFolder($folder)
    {
        $folder = self::normaliseRelativeFolder($folder);
        $relative = self::BASE . '/' . self::THUMB . ($folder !== '' ? '/' . $folder : '');

        return Path::clean(JPATH_SITE . '/' . $relative) . DIRECTORY_SEPARATOR;
    }

    public static function ensureFolders($folder)
    {
        $folder = self::normaliseRelativeFolder($folder);
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
            JemImage::thumb($sourcePath, $target, (int) $settings->imagewidth, (int) $settings->imagehight);
        }

        return File::exists($target);
    }

    public static function relocateImages($fromFolder, $toFolder, array $filenames, $settings, $move = true)
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

    private static function idFolder($venueId, $collection, $objectId)
    {
        $venueId = (int) $venueId;
        $objectId = (int) $objectId;

        return $venueId > 0 && $objectId > 0
            ? $venueId . '/' . self::cleanSegment($collection) . '/' . $objectId
            : '';
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
