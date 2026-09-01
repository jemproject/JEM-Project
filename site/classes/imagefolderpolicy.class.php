<?php
/**
 * @version    5.1.0
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Shared limits for configured image folder structures.
 */
class JemImageFolderPolicy
{
    public const MAX_DEPTH = 8;

    /**
     * Count non-empty folder levels in a configured pattern or resolved path.
     *
     * @param   mixed  $path  Folder pattern or relative path.
     *
     * @return  int
     */
    public static function depth($path): int
    {
        $path = trim(str_replace('\\', '/', (string) $path), '/');

        if ($path === '') {
            return 0;
        }

        return count(array_filter(explode('/', $path), static function ($segment) {
            return trim($segment) !== '';
        }));
    }

    /**
     * Check whether a pattern or resolved path stays within the technical limit.
     *
     * @param   mixed  $path  Folder pattern or relative path.
     *
     * @return  bool
     */
    public static function isWithinMaximumDepth($path): bool
    {
        return self::depth($path) <= self::MAX_DEPTH;
    }

    /**
     * Keep a valid resolved path intact or fall back to the object's safe root.
     *
     * @param   mixed  $folder  Normalised relative folder.
     *
     * @return  string
     */
    public static function resolvedFolderOrRoot($folder): string
    {
        $folder = (string) $folder;

        return self::isWithinMaximumDepth($folder) ? $folder : '';
    }
}
