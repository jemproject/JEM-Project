<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Detect and explicitly migrate legacy JEM module CSS overrides.
 */
final class JemModuleCssOverride
{
    /**
     * Return the legacy-to-current stylesheet names for each JEM module.
     *
     * @return array
     */
    public static function getMappings()
    {
        return array(
            'mod_jem' => array(
                'mod_jem.css'                => 'default.css',
                'mod_jem_responsive.css'     => 'responsive.css',
                'mod_jem_table.css'          => 'table.css',
                'mod_jem_table-advanced.css' => 'table-advanced.css',
                'mod_jem_table-style.css'    => 'table-style.css',
            ),
            'mod_jem_banner' => array(
                'mod_jem_banner.css'                => 'default.css',
                'mod_jem_banner_cards.css'          => 'cards.css',
                'mod_jem_banner_iconfont.css'       => 'iconfont.css',
                'mod_jem_banner_iconimg.css'        => 'iconimg.css',
                'mod_jem_banner_responsive.css'     => 'responsive.css',
                'mod_jem_banner_table-advanced.css' => 'table-advanced.css',
            ),
            'mod_jem_cal' => array(
                'mod_jem_cal.css'          => 'default.css',
                'mod_jem_cal_darkblue.css' => 'darkblue.css',
                'mod_jem_cal_grid.css'     => 'grid.css',
            ),
            'mod_jem_jubilee' => array(
                'mod_jem_jubilee.css'            => 'default.css',
                'mod_jem_jubilee_iconfont.css'   => 'iconfont.css',
                'mod_jem_jubilee_iconimg.css'    => 'iconimg.css',
                'mod_jem_jubilee_responsive.css' => 'responsive.css',
            ),
            'mod_jem_teaser' => array(
                'mod_jem_teaser.css'            => 'default.css',
                'mod_jem_teaser_iconfont.css'   => 'iconfont.css',
                'mod_jem_teaser_iconimg.css'    => 'iconimg.css',
                'mod_jem_teaser_responsive.css' => 'responsive.css',
            ),
            'mod_jem_wide' => array(
                'mod_jem_wide_default.css'    => 'default.css',
                'mod_jem_wide_iconfont.css'   => 'iconfont.css',
                'mod_jem_wide_iconimg.css'    => 'iconimg.css',
                'mod_jem_wide_responsive.css' => 'responsive.css',
            ),
        );
    }

    /**
     * Return the legacy filename for a requested current module stylesheet.
     *
     * @param   string  $module       Module name.
     * @param   string  $currentFile  Current CSS filename or basename.
     *
     * @return string
     */
    public static function getLegacyFileName($module, $currentFile)
    {
        $currentFile = trim((string) $currentFile);

        if ($currentFile === '') {
            return '';
        }

        $currentFile .= str_ends_with($currentFile, '.css') ? '' : '.css';

        foreach (self::getMappings()[(string) $module] ?? array() as $legacyFile => $mappedFile) {
            if ($mappedFile === $currentFile) {
                return $legacyFile;
            }
        }

        return '';
    }

    /**
     * Find obsolete module CSS overrides in site templates and module media folders.
     *
     * @param   string  $siteRoot   Joomla site root.
     * @param   array   $templates  Installed site-template names.
     *
     * @return array
     */
    public static function discover($siteRoot, array $templates)
    {
        $root = realpath((string) $siteRoot);

        if ($root === false || !is_dir($root)) {
            return array();
        }

        $templates = array_values(array_unique(array_filter(array_map('strval', $templates), function ($template) {
            return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $template);
        })));
        $items = array();

        foreach (self::getMappings() as $module => $mappings) {
            $directories = array();

            foreach ($templates as $template) {
                $directories[] = $root . '/templates/' . $template . '/css/' . $module;
                $directories[] = $root . '/templates/' . $template . '/html/' . $module;
            }

            $directories[] = $root . '/media/' . $module . '/css';

            foreach ($directories as $directory) {
                $directoryReal = realpath($directory);

                if ($directoryReal === false || !is_dir($directoryReal) || !self::isWithinRoot($directoryReal, $root)) {
                    continue;
                }

                foreach ($mappings as $legacyFile => $currentFile) {
                    $source = $directoryReal . DIRECTORY_SEPARATOR . $legacyFile;

                    if (!is_file($source)) {
                        continue;
                    }

                    $target = $directoryReal . DIRECTORY_SEPARATOR . $currentFile;
                    $status = 'pending';

                    if (is_link($source) || !self::isWithinRoot((string) realpath($source), $root)) {
                        $status = 'unsafe';
                    } elseif (file_exists($target) || is_link($target)) {
                        $status = 'conflict';
                    } elseif (!is_writable($directoryReal)) {
                        $status = 'not_writable';
                    }

                    $items[] = array(
                        'module'          => $module,
                        'legacyFile'      => $legacyFile,
                        'currentFile'     => $currentFile,
                        'source'          => $source,
                        'target'          => $target,
                        'sourceRelative'  => self::relativePath($source, $root),
                        'targetRelative'  => self::relativePath($target, $root),
                        'status'          => $status,
                    );
                }
            }
        }

        usort($items, function ($left, $right) {
            return strnatcasecmp($left['sourceRelative'], $right['sourceRelative']);
        });

        return $items;
    }

    /**
     * Rename every currently safe legacy override after explicit administrator approval.
     *
     * @param   string  $siteRoot   Joomla site root.
     * @param   array   $templates  Installed site-template names.
     *
     * @return array
     */
    public static function migrate($siteRoot, array $templates)
    {
        $result = array(
            'migrated' => array(),
            'failed'   => array(),
            'skipped'  => array(),
        );

        foreach (self::discover($siteRoot, $templates) as $item) {
            if ($item['status'] !== 'pending') {
                $result['skipped'][] = $item;
                continue;
            }

            if (!is_file($item['source']) || is_link($item['source'])
                || file_exists($item['target']) || is_link($item['target'])) {
                $item['status'] = 'changed';
                $result['failed'][] = $item;
                continue;
            }

            $moved = self::moveWithoutOverwrite($item['source'], $item['target']);

            if ($moved && is_file($item['target']) && !file_exists($item['source'])) {
                $item['status'] = 'migrated';
                $result['migrated'][] = $item;
            } else {
                $item['status'] = 'failed';
                $result['failed'][] = $item;
            }
        }

        return $result;
    }

    /**
     * Copy a file to a newly created target and remove the source only after verification.
     *
     * Opening the target with mode "x" guarantees that an existing file is never
     * overwritten, including if it appears after discovery but before migration.
     *
     * @param   string  $source  Existing source file.
     * @param   string  $target  New target file.
     *
     * @return bool
     */
    protected static function moveWithoutOverwrite($source, $target)
    {
        $sourceHandle = @fopen($source, 'rb');

        if ($sourceHandle === false) {
            return false;
        }

        $sourceStat = fstat($sourceHandle);
        $targetHandle = @fopen($target, 'x+b');

        if ($targetHandle === false) {
            fclose($sourceHandle);

            return false;
        }

        $copied = @stream_copy_to_stream($sourceHandle, $targetHandle);
        $flushed = @fflush($targetHandle);
        $sourceHash = false;
        $targetHash = false;

        if (@rewind($sourceHandle) && @rewind($targetHandle)) {
            $sourceHashContext = hash_init('sha256');
            $targetHashContext = hash_init('sha256');
            hash_update_stream($sourceHashContext, $sourceHandle);
            hash_update_stream($targetHashContext, $targetHandle);
            $sourceHash = hash_final($sourceHashContext);
            $targetHash = hash_final($targetHashContext);
        }

        fclose($targetHandle);
        fclose($sourceHandle);

        $expectedSize = is_array($sourceStat) ? (int) ($sourceStat['size'] ?? -1) : -1;
        $verified = $copied !== false
            && (int) $copied === $expectedSize
            && $flushed
            && is_file($target)
            && is_string($sourceHash)
            && is_string($targetHash)
            && hash_equals($sourceHash, $targetHash);

        if (!$verified) {
            @unlink($target);

            return false;
        }

        if (is_array($sourceStat)) {
            @chmod($target, (int) ($sourceStat['mode'] ?? 0644) & 0x1FF);
            @touch($target, (int) ($sourceStat['mtime'] ?? time()));
        }

        if (!@unlink($source)) {
            @unlink($target);

            return false;
        }

        return is_file($target) && !file_exists($source);
    }

    /**
     * Confirm that a resolved path remains below the Joomla site root.
     *
     * @param   string  $path  Resolved path.
     * @param   string  $root  Resolved site root.
     *
     * @return bool
     */
    protected static function isWithinRoot($path, $root)
    {
        $path = rtrim(str_replace('\\', '/', (string) $path), '/');
        $root = rtrim(str_replace('\\', '/', (string) $root), '/');

        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return $path === $root || str_starts_with($path, $root . '/');
    }

    /**
     * Return a root-relative display path using URL-style separators.
     *
     * @param   string  $path  Absolute path.
     * @param   string  $root  Joomla site root.
     *
     * @return string
     */
    protected static function relativePath($path, $root)
    {
        $path = str_replace('\\', '/', (string) $path);
        $root = rtrim(str_replace('\\', '/', (string) $root), '/');

        return '/' . ltrim(substr($path, strlen($root)), '/');
    }
}
