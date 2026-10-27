<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

/**
 * Inventory standard Joomla template and language overrides affecting JEM.
 */
final class JemHousekeepingOverrides
{
    private DatabaseDriver $db;

    public function __construct(?DatabaseDriver $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
    }

    public function scan(): array
    {
        return array(
            'templates' => $this->templateOverrides(),
            'languages' => $this->languageOverrides(),
            'css' => $this->cssOverrides(),
        );
    }

    /** Discover JEM CSS Manager files and template CSS substitutions. */
    private function cssOverrides(): array
    {
        $items = array();
        $customRoot = JPATH_SITE . '/media/com_jem/css/custom';
        $configured = array();
        $settings = JemHelper::retrieveCss()->toArray();
        foreach ($settings as $key => $filename) {
            if (!is_string($key) || !preg_match('/^css_([a-z0-9_]+)_customfile$/D', $key, $matches)
                || !is_string($filename) || $filename === '') {
                continue;
            }
            $useKey = 'css_' . $matches[1] . '_usecustom';
            $baseUseKey = str_replace('_responsive_usecustom', '_usecustom', $useKey);
            if ((int) ($settings[$useKey] ?? 0) === 1
                || ($baseUseKey !== $useKey && (int) ($settings[$baseUseKey] ?? 0) === 1)) {
                $configured[basename($filename)][] = str_replace('_', '-', $matches[1]) . '.css';
            }
        }

        $root = realpath($customRoot);
        if ($root !== false && !is_link($customRoot)) {
            foreach ((array) glob($customRoot . '/*.css') as $file) {
                $resolved = realpath($file);
                if ($resolved === false || !is_file($file) || is_link($file)
                    || !str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $root) . '/')) {
                    continue;
                }
                $name = basename($file);
                $userCss = in_array($name, array('jem-user-front.css', 'jem-user-module.css'), true);
                $items[] = array(
                    'name' => $name,
                    'kind' => $userCss ? 'user' : 'manager',
                    'path' => 'media/com_jem/css/custom/' . $name,
                    'used_by' => $userCss ? array('JEM frontend/module') : array_values(array_unique($configured[$name] ?? array())),
                );
            }
        }

        $query = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('template') . ', ' . $this->db->quoteName('client_id'))
            ->from($this->db->quoteName('#__template_styles'));
        $this->db->setQuery($query);
        foreach ((array) $this->db->loadObjectList() as $style) {
            $client = (int) $style->client_id === 1 ? 'administrator' : 'site';
            $base = $client === 'administrator' ? JPATH_ADMINISTRATOR : JPATH_SITE;
            $template = (string) $style->template;
            if (!preg_match('/^[a-z0-9_-]+$/iD', $template)) {
                continue;
            }
            $directory = $base . '/templates/' . $template . '/css/com_jem';
            $resolvedRoot = realpath($directory);
            if ($resolvedRoot === false || is_link($directory)) {
                continue;
            }
            foreach ((array) glob($directory . '/*.css') as $file) {
                $resolved = realpath($file);
                if ($resolved === false || !is_file($file) || is_link($file)
                    || !str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $resolvedRoot) . '/')) {
                    continue;
                }
                $name = basename($file);
                $items[] = array(
                    'name' => $name,
                    'kind' => 'template',
                    'path' => ($client === 'administrator' ? 'administrator/' : '')
                        . 'templates/' . $template . '/css/com_jem/' . $name,
                    'used_by' => array($client . ' / ' . $template),
                );
            }
        }

        usort($items, static fn($first, $second): int => strnatcasecmp($first['path'], $second['path']));

        return $items;
    }

    public static function selectionId(string $kind, string $path, array $keys = array()): string
    {
        sort($keys, SORT_STRING);

        return ($kind === 'template' ? 't_' : 'l_')
            . hash('sha256', $kind . ':' . $path . ':' . implode("\n", $keys));
    }

    /** Resolve every selection against a fresh scan before changing any file. */
    public function deleteSelected(array $selected): array
    {
        foreach ($selected as $id) {
            if (!is_string($id)) {
                throw new \InvalidArgumentException('The override selection is invalid.');
            }
        }
        $selected = array_values(array_unique(array_map('strval', $selected)));
        if (!$selected || count($selected) > 100) {
            throw new \InvalidArgumentException('Select between 1 and 100 overrides.');
        }

        $current = $this->scan();
        $available = array();
        foreach ($current['templates'] as $item) {
            if ($item['winner'] !== '') {
                $available[self::selectionId('template', $item['winner'])] = array(
                    'kind' => 'template',
                    'path' => $item['winner'],
                );
            }
        }
        foreach ($current['languages'] as $item) {
            $available[self::selectionId('language', $item['path'], $item['keys'])] = array(
                'kind' => 'language',
                'path' => $item['path'],
                'keys' => $item['keys'],
            );
        }

        $targets = array();
        foreach ($selected as $id) {
            if (!preg_match('/^[tl]_[a-f0-9]{64}$/D', $id) || !isset($available[$id])) {
                throw new \InvalidArgumentException('The override selection is no longer valid. Run diagnostics again.');
            }
            $item = $available[$id];
            $item['absolute'] = $this->validatedPath($item['kind'], $item['path']);
            $targets[] = $item;
        }

        $result = array('template_files' => 0, 'language_keys' => 0);
        foreach ($targets as $target) {
            if ($target['kind'] === 'template') {
                if (!unlink($target['absolute'])) {
                    throw new \RuntimeException('The selected template override could not be deleted.');
                }
                $result['template_files']++;
            } else {
                $result['language_keys'] += $this->removeLanguageKeys($target['absolute'], $target['keys']);
            }
        }

        return $result;
    }

    /** Never accept a native JEM file, a path outside the override area or a symlink. */
    private function validatedPath(string $kind, string $relative): string
    {
        $administrator = str_starts_with($relative, 'administrator/');
        $base = $administrator ? JPATH_ADMINISTRATOR : JPATH_SITE;
        $local = $administrator ? substr($relative, strlen('administrator/')) : $relative;
        if ($kind === 'template' && str_starts_with($local, 'site/')) {
            $local = substr($local, strlen('site/'));
        }
        $pattern = $kind === 'template'
            ? '#^templates/[a-z0-9_-]+/html/(?:com_jem|mod_jem(?:_[a-z0-9_]+)?|layouts/com_jem|layouts/joomla/searchtools)/[a-z0-9_./-]+\.(?:php|xml)$#iD'
            : '#^language/overrides/[a-z]{2,3}-[A-Z]{2}\.override\.ini$#D';

        if (!preg_match($pattern, $local) || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $local)) {
            throw new \InvalidArgumentException('The selected path is not a JEM override.');
        }

        $root = realpath($base . '/' . ($kind === 'template' ? 'templates' : 'language/overrides'));
        $candidate = $base . '/' . $local;
        $resolved = realpath($candidate);
        if ($root === false || $resolved === false || !is_file($candidate)
            || !str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $root) . '/')) {
            throw new \InvalidArgumentException('The selected override is outside the expected directory.');
        }

        $part = $base;
        foreach (explode('/', $local) as $segment) {
            $part .= '/' . $segment;
            if (is_link($part)) {
                throw new \InvalidArgumentException('A linked override path cannot be deleted.');
            }
        }

        return $resolved;
    }

    /** Language override INIs are shared with Joomla: remove JEM entries, not the file. */
    private function removeLanguageKeys(string $path, array $keys): int
    {
        $stream = @fopen($path, 'r+');
        if ($stream === false) {
            throw new \RuntimeException('The language override could not be locked.');
        }

        if (!flock($stream, LOCK_EX)) {
            fclose($stream);
            throw new \RuntimeException('The language override could not be locked.');
        }

        try {
            $size = fstat($stream)['size'] ?? 0;
            if ($size > 1048576) {
                throw new \RuntimeException('The language override is too large to edit safely.');
            }
            rewind($stream);
            $original = stream_get_contents($stream);
            if ($original === false || strlen($original) > 1048576) {
                throw new \RuntimeException('The language override could not be read.');
            }

            $allowed = array_fill_keys($keys, true);
            $parts = preg_split('/(\r\n|\r|\n)/', $original, -1, PREG_SPLIT_DELIM_CAPTURE);
            $updated = '';
            $removed = 0;
            for ($index = 0; $index < count($parts); $index += 2) {
                $line = $parts[$index];
                if (preg_match('/^(?:\xEF\xBB\xBF)?\s*([A-Z][A-Z0-9_]*)\s*=/', $line, $matches)
                    && isset($allowed[$matches[1]])) {
                    $removed++;
                    continue;
                }
                $updated .= $line . ($parts[$index + 1] ?? '');
            }
            if ($removed === 0) {
                throw new \RuntimeException('The selected language override changed. Run diagnostics again.');
            }

            try {
                $this->writeLockedFile($stream, $updated);
            } catch (\Throwable $error) {
                try {
                    $this->writeLockedFile($stream, $original);
                } catch (\Throwable $restoreError) {
                    throw new \RuntimeException('The language override update and rollback both failed.', 0, $restoreError);
                }
                throw $error;
            }

            return $removed;
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
    }

    /** Write under the existing exclusive lock; the caller handles rollback. */
    private function writeLockedFile($stream, string $content): void
    {
        rewind($stream);
        $written = 0;
        while ($written < strlen($content)) {
            $count = fwrite($stream, substr($content, $written));
            if ($count === false || $count === 0) {
                throw new \RuntimeException('The language override could not be written completely.');
            }
            $written += $count;
        }
        if (!ftruncate($stream, strlen($content)) || !fflush($stream)) {
            throw new \RuntimeException('The language override could not be updated.');
        }
    }

    private function templateOverrides(): array
    {
        $query = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('template') . ', ' . $this->db->quoteName('client_id'))
            ->from($this->db->quoteName('#__template_styles'))
            ->order($this->db->quoteName('client_id') . ', ' . $this->db->quoteName('template'));
        $this->db->setQuery($query);
        $items = array();

        foreach ((array) $this->db->loadObjectList() as $style) {
            $client = (int) $style->client_id === 1 ? 'administrator' : 'site';
            $base = $client === 'administrator' ? JPATH_ADMINISTRATOR : JPATH_SITE;
            $template = (string) $style->template;
            if (!preg_match('/^[a-z0-9_-]+$/iD', $template)) {
                continue;
            }
            $parent = $this->templateParent($base, $template);
            $layers = array($template);
            if ($parent !== '') {
                $layers[] = $parent;
            }
            $found = array();
            foreach ($layers as $layer) {
                $html = $base . '/templates/' . $layer . '/html';
                foreach (array('com_jem', 'layouts/com_jem') as $root) {
                    $this->collectFiles($html . '/' . $root, $root, $layer, $found);
                }
                foreach (array(
                    'mod_jem', 'mod_jem_banner', 'mod_jem_cal', 'mod_jem_jubilee',
                    'mod_jem_map', 'mod_jem_teaser', 'mod_jem_types', 'mod_jem_wide',
                ) as $module) {
                    $this->collectFiles($html . '/' . $module, $module, $layer, $found);
                }
                // This shared Joomla layout is explicitly rendered by JEM list views.
                $this->collectFiles(
                    $html . '/layouts/joomla/searchtools',
                    'layouts/joomla/searchtools',
                    $layer,
                    $found,
                    array('default.php')
                );
            }

            foreach ($found as $target => $present) {
                $order = array();
                foreach ($layers as $layer) {
                    if (isset($present[$layer])) {
                        $order[] = $client . '/templates/' . $layer . '/html/' . $target;
                    }
                }
                $native = $this->nativePath($client, $target);
                if ($native !== '' && is_file(JPATH_SITE . '/' . $native)) {
                    $order[] = $native;
                }
                $items[] = array(
                    'client' => $client,
                    'template' => $template,
                    'kind' => str_starts_with($target, 'com_jem/') ? 'view'
                        : (str_starts_with($target, 'layouts/') ? 'layout' : 'module'),
                    'target' => $target,
                    'winner' => $order[0] ?? '',
                    'load_order' => $order,
                );
            }
        }

        return $items;
    }

    private function templateParent(string $base, string $template): string
    {
        $manifest = $base . '/templates/' . $template . '/templateDetails.xml';
        if (!is_file($manifest)) {
            return '';
        }
        $xml = simplexml_load_file($manifest, 'SimpleXMLElement', LIBXML_NONET);
        $parent = $xml === false ? '' : (string) ($xml->parent ?? '');

        return preg_match('/^[a-z0-9_-]+$/iD', $parent) && $parent !== $template ? $parent : '';
    }

    private function collectFiles(
        string $directory,
        string $root,
        string $layer,
        array &$found,
        array $allowed = array()
    ): void {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        $count = 0;
        foreach ($iterator as $file) {
            if (++$count > 1000) {
                break;
            }
            if (!$file->isFile() || $file->isLink()
                || !in_array(strtolower($file->getExtension()), array('php', 'xml'), true)) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            if ($allowed && !in_array($relative, $allowed, true)) {
                continue;
            }
            $found[$root . '/' . $relative][$layer] = true;
        }
    }

    private function nativePath(string $client, string $target): string
    {
        $prefix = $client === 'administrator' ? 'administrator/' : '';
        if (preg_match('#^com_jem/([a-z0-9_]+)/(.+)$#iD', $target, $matches)) {
            return $prefix . 'components/com_jem/views/' . $matches[1] . '/tmpl/' . $matches[2];
        }
        if (preg_match('#^(mod_jem(?:_[a-z0-9_]+)?)/(.+)$#iD', $target, $matches)) {
            return $prefix . 'modules/' . $matches[1] . '/tmpl/' . $matches[2];
        }
        if (str_starts_with($target, 'layouts/com_jem/')) {
            return $prefix . 'components/com_jem/layouts/' . substr($target, strlen('layouts/com_jem/'));
        }
        if (str_starts_with($target, 'layouts/joomla/')) {
            return 'layouts/joomla/' . substr($target, strlen('layouts/joomla/'));
        }

        return '';
    }

    private function languageOverrides(): array
    {
        $items = array();
        foreach (array('administrator' => JPATH_ADMINISTRATOR, 'site' => JPATH_SITE) as $client => $base) {
            foreach ((array) glob($base . '/language/overrides/*.override.ini') as $path) {
                $resolved = realpath($path);
                $directory = realpath($base . '/language/overrides');
                if ($resolved === false || $directory === false || is_link($path)
                    || !str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $directory) . '/')
                    || filesize($path) > 1048576) {
                    continue;
                }
                $filename = basename($path);
                if (!preg_match('/^([a-z]{2,3}-[A-Z]{2})\.override\.ini$/D', $filename, $matches)) {
                    continue;
                }
                $keys = array();
                $file = new SplFileObject($path);
                while (!$file->eof() && count($keys) < 2000) {
                    $line = $file->fgets();
                    if (preg_match('/^(?:\xEF\xBB\xBF)?\s*((?:COM_JEM|MOD_JEM|PKG_JEM|PLG_[A-Z0-9_]*JEM)[A-Z0-9_]*)\s*=/', $line, $match)) {
                        $keys[] = $match[1];
                    }
                }
                if (!$keys) {
                    continue;
                }
                $items[] = array(
                    'client' => $client,
                    'language' => $matches[1],
                    'path' => ($client === 'administrator' ? 'administrator/' : '')
                        . 'language/overrides/' . $filename,
                    'keys' => array_values(array_unique($keys)),
                    'load_order' => array('Joomla language override', 'JEM language INI'),
                );
            }
        }

        return $items;
    }
}
