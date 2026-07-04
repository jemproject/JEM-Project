<?php
/**
 * @version    5.1.0-alpha1
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

/**
 * Normalises event image subfolder paths for original images and thumbnails.
 */
class JemEventImagePath
{
    public const BASE = 'images/jem/events';
    public const THUMB = 'small';

    public static function normaliseRelativeFolder($folder)
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

        return implode('/', $segments);
    }

    public static function cleanFolderSegment($segment)
    {
        $segment = trim((string) $segment);
        $segment = str_replace(array('\\', '/', "\0"), '', $segment);

        if ($segment === '' || $segment === '.' || $segment === '..') {
            return '';
        }

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

    public static function isInsideBase($absolutePath, $basePath)
    {
        $absolutePath = Path::clean((string) $absolutePath);
        $basePath     = rtrim(Path::clean((string) $basePath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return strpos($absolutePath . DIRECTORY_SEPARATOR, $basePath) === 0;
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