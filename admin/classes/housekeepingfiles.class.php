<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseDriver;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

/**
 * Unused JEM image and attachment files: a read-only preview, and deletion of
 * explicitly selected files only. Every selection is re-validated against the
 * current files and database at delete time.
 *
 * The one-click cleanup tasks of Housekeeping are kept unchanged; this class
 * only adds the reviewed alternative.
 */
final class JemHousekeepingFiles
{
    /** Image areas: folders below images/jem. */
    public const IMAGE_AREAS = array('events', 'venues', 'categories');

    /** Maximum number of files deleted in one request. */
    public const BATCH_LIMIT = 200;

    /** Placeholder files that are never offered for deletion. */
    private const KEEP = array('index.html', 'index.htm', 'web.config', '.htaccess');

    private DatabaseDriver $db;

    private ?JemModelHousekeeping $model;

    public function __construct(?DatabaseDriver $db = null, ?JemModelHousekeeping $model = null)
    {
        $this->db = $db ?? Factory::getContainer()->get('DatabaseDriver');
        $this->model = $model;
    }

    /** All areas offered by the preview. */
    public static function areas(): array
    {
        return array_merge(self::IMAGE_AREAS, array('attachments'));
    }

    /**
     * Unused originals and orphaned thumbnails in images/jem/<area>.
     *
     * @return array List of candidates: id, kind (original|thumbnail), relative, url, size,
     *               modified, thumbnail (relative or ''), elsewhere (list of uses).
     */
    public function imageCandidates(string $area): array
    {
        $base = $this->imageBase($area);
        if (!is_dir($base)) {
            return array();
        }

        // The model decides what is unused, as for the one-click cleanup
        // (image files only, contained paths, event link images).
        $model = $this->model();
        $used = array_fill_keys(array_map('strtolower', $model->getUsedImagePaths($area)), true);
        $thumbs = is_dir($base . '/small') ? $this->listFiles($base . '/small') : array();
        $thumbs = array_fill_keys($thumbs, true);

        $candidates = array();
        foreach ($model->getUnusedImagePaths($area) as $relative) {
            $candidates[] = $this->candidate($area, 'original', $relative, isset($thumbs[$relative]) ? 'small/' . $relative : '');
        }

        // A thumbnail whose original is gone and that nothing uses.
        foreach (array_keys($thumbs) as $relative) {
            if (is_file($base . '/' . $relative) || isset($used[strtolower($relative)])) {
                continue;
            }
            $candidates[] = $this->candidate($area, 'thumbnail', 'small/' . $relative, '');
        }

        $this->markElsewhere($candidates, 'images/jem/' . $area . '/');
        usort($candidates, static fn(array $a, array $b): int => strnatcasecmp($a['relative'], $b['relative']));

        return $candidates;
    }

    /**
     * Files in attachment object folders (event12, venue3, ...) without an attachment record.
     *
     * @return array List of candidates: id, kind (attachment), relative, size, modified,
     *               object, object_exists.
     */
    public function attachmentCandidates(): array
    {
        $base = $this->attachmentBase();
        if ($base === '') {
            return array();
        }

        $objects = array();
        foreach (Folder::folders($base, '.', false, false) as $object) {
            if (preg_match('/^[a-z]+[0-9]+$/iD', $object)) {
                $objects[] = $object;
            }
        }
        if (!$objects) {
            return array();
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('object', 'file')))
            ->from($this->db->quoteName('#__jem_attachments'))
            ->where($this->db->quoteName('object') . ' IN (' . implode(',', array_map(array($this->db, 'quote'), $objects)) . ')');
        $used = array();
        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            $used[strtolower($row->object . '/' . $row->file)] = true;
        }

        $existing = $this->existingObjects($objects);
        $candidates = array();
        foreach ($objects as $object) {
            foreach (Folder::files($base . '/' . $object, '.', false, false, array(), array()) as $file) {
                if (in_array(strtolower($file), self::KEEP, true) || $file[0] === '.') {
                    continue;
                }
                $relative = $object . '/' . $file;
                if (isset($used[strtolower($relative)])) {
                    continue;
                }
                $path = $base . '/' . $relative;
                $candidates[] = array(
                    'id' => $this->identifier('attachments', $relative, $path),
                    'kind' => 'attachment',
                    'relative' => $relative,
                    'size' => (int) @filesize($path),
                    'modified' => (int) @filemtime($path),
                    'object' => $object,
                    'object_exists' => !empty($existing[strtolower($object)]),
                    'thumbnail' => '',
                    'elsewhere' => array(),
                    'url' => '',
                );
            }
        }
        usort($candidates, static fn(array $a, array $b): int => strnatcasecmp($a['relative'], $b['relative']));

        return $candidates;
    }

    /** Candidates of one area. */
    public function candidates(string $area): array
    {
        return $area === 'attachments' ? $this->attachmentCandidates() : $this->imageCandidates($area);
    }

    /**
     * Delete the selected candidates of one area. Unknown or changed selections are skipped.
     *
     * @return array deleted, skipped, failed, folders, bytes, lines
     */
    public function delete(string $area, array $selected): array
    {
        if (!in_array($area, self::areas(), true)) {
            throw new InvalidArgumentException('Unknown file area.');
        }

        $selected = array_values(array_unique(array_filter($selected, static fn($id): bool => is_string($id))));
        if (!$selected || count($selected) > self::BATCH_LIMIT) {
            throw new InvalidArgumentException('Select between 1 and ' . self::BATCH_LIMIT . ' files.');
        }
        foreach ($selected as $id) {
            if (!preg_match('/^[a-f0-9]{64}$/D', $id)) {
                throw new InvalidArgumentException('Invalid file selection.');
            }
        }

        $isAttachments = $area === 'attachments';
        $base = $isAttachments ? $this->attachmentBase() : $this->imageBase($area);
        $byId = array();
        foreach ($this->candidates($area) as $candidate) {
            $byId[$candidate['id']] = $candidate;
        }

        $result = array('deleted' => 0, 'skipped' => 0, 'failed' => 0, 'folders' => 0, 'bytes' => 0, 'lines' => array());
        $touchedFolders = array();
        foreach ($selected as $id) {
            if (!isset($byId[$id])) {
                $result['skipped']++;
                continue;
            }
            $candidate = $byId[$id];
            $paths = array($candidate['relative']);
            if ($candidate['thumbnail'] !== '') {
                $paths[] = $candidate['thumbnail'];
            }
            $ok = true;
            foreach ($paths as $relative) {
                $path = $this->insideBase($base, $relative);
                if ($path === '' || !is_file($path) || !@unlink($path)) {
                    $ok = false;
                    JemHelper::addLogEntry('Unable to delete unused file: ' . $base . '/' . $relative, __METHOD__, Log::WARNING);
                }
            }
            if ($ok) {
                $result['deleted']++;
                $result['bytes'] += (int) $candidate['size'];
                $result['lines'][] = array('status' => 'notice', 'message' => $candidate['relative']);
                if ($isAttachments) {
                    $touchedFolders[$candidate['object']] = true;
                }
            } else {
                $result['failed']++;
                $result['lines'][] = array('status' => 'error', 'message' => $candidate['relative']);
            }
        }

        // Same rule as the one-click cleanup: an attachment object folder without files is removed.
        foreach (array_keys($touchedFolders) as $object) {
            $folder = $this->insideBase($base, $object);
            if ($folder !== '' && is_dir($folder) && !Folder::files($folder, '.', true, true, array('index.html'), array())) {
                if (Folder::delete($folder)) {
                    $result['folders']++;
                } else {
                    $result['failed']++;
                }
            }
        }

        JemHelper::addLogEntry(sprintf(
            'Housekeeping deleted %d unused %s file(s), %d skipped, %d failed.',
            $result['deleted'],
            $area,
            $result['skipped'],
            $result['failed']
        ), __METHOD__, Log::INFO);

        return $result;
    }

    private function model(): JemModelHousekeeping
    {
        if ($this->model === null) {
            if (!class_exists('JemModelHousekeeping')) {
                require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/housekeeping.php';
            }
            $this->model = new JemModelHousekeeping();
        }

        return $this->model;
    }

    public function imageBase(string $area): string
    {
        if (!in_array($area, self::IMAGE_AREAS, true)) {
            throw new InvalidArgumentException('Unknown image area.');
        }

        return rtrim(Path::clean(JPATH_SITE . '/images/jem/' . $area), '\\/');
    }

    /** Configured attachment folder, or '' when it is unset, outside the site or missing. */
    public function attachmentBase(): string
    {
        $relative = trim((string) JemHelper::config()->attachments_path);
        $site = rtrim(Path::clean(JPATH_SITE), '\\/');
        $base = rtrim(Path::clean(JPATH_SITE . '/' . $relative), '\\/');
        if ($relative === '' || $base === $site || strpos($base . DIRECTORY_SEPARATOR, $site . DIRECTORY_SEPARATOR) !== 0 || !is_dir($base)) {
            return '';
        }

        return $base;
    }

    private function candidate(string $area, string $kind, string $relative, string $thumbnail): array
    {
        $path = $this->imageBase($area) . '/' . $relative;

        return array(
            'id' => $this->identifier($area, $relative, $path),
            'kind' => $kind,
            'relative' => $relative,
            'url' => 'images/jem/' . $area . '/' . implode('/', array_map('rawurlencode', explode('/', $relative))),
            'size' => (int) @filesize($path),
            'modified' => (int) @filemtime($path),
            'thumbnail' => $thumbnail,
            'elsewhere' => array(),
            'object' => '',
            'object_exists' => true,
        );
    }

    /** Bind a selection to path, size and modification time without exposing server paths. */
    private function identifier(string $area, string $relative, string $path): string
    {
        $secret = (string) Factory::getApplication()->get('secret', '');

        return hash_hmac(
            'sha256',
            implode('|', array($area, $relative, (string) (int) @filesize($path), (string) (int) @filemtime($path))),
            $secret !== '' ? $secret : __CLASS__
        );
    }

    /** All regular, non-hidden files below $base as relative paths with forward slashes. */
    private function listFiles(string $base): array
    {
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $name = $file->getFilename();
            if ($name[0] === '.' || in_array(strtolower($name), self::KEEP, true)) {
                continue;
            }
            $files[] = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/');
        }

        return $files;
    }

    /** Absolute path of $relative when it stays inside $base, otherwise ''. */
    private function insideBase(string $base, string $relative): string
    {
        if ($relative === '' || strpos($relative, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', str_replace('\\', '/', $relative))) {
            return '';
        }
        $path = Path::clean($base . '/' . $relative);
        $real = realpath($path);
        $realBase = realpath($base);
        if ($real === false || $realBase === false) {
            return $path;
        }

        return strpos($real, $realBase . DIRECTORY_SEPARATOR) === 0 ? $real : '';
    }

    /** Mark candidates whose path appears in articles, modules, menu items or JEM descriptions. */
    private function markElsewhere(array &$candidates, string $prefix): void
    {
        if (!$candidates) {
            return;
        }
        $sources = array(
            array('#__content', array('introtext', 'fulltext', 'images', 'urls'), 'COM_JEM_HOUSEKEEPING_FILES_USED_ARTICLE'),
            array('#__modules', array('content', 'params'), 'COM_JEM_HOUSEKEEPING_FILES_USED_MODULE'),
            array('#__menu', array('params'), 'COM_JEM_HOUSEKEEPING_FILES_USED_MENU'),
            array('#__jem_events', array('introtext', 'fulltext'), 'COM_JEM_HOUSEKEEPING_FILES_USED_EVENT'),
            array('#__jem_venues', array('locdescription'), 'COM_JEM_HOUSEKEEPING_FILES_USED_VENUE'),
            array('#__jem_categories', array('description'), 'COM_JEM_HOUSEKEEPING_FILES_USED_CATEGORY'),
        );
        // Loose on purpose: JSON columns store "images\/jem\/...". The exact match is done below.
        $pattern = '%images%jem%' . trim(substr($prefix, strlen('images/jem/')), '/') . '%';
        foreach ($sources as list($table, $columns, $label)) {
            $where = array();
            foreach ($columns as $column) {
                $where[] = $this->db->quoteName($column) . ' LIKE ' . $this->db->quote($pattern);
            }
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(array_merge(array('id'), $columns)))
                ->from($this->db->quoteName($table))
                ->where('(' . implode(' OR ', $where) . ')');
            try {
                $rows = (array) $this->db->setQuery($query, 0, 5000)->loadAssocList();
            } catch (Throwable $error) {
                continue;
            }
            foreach ($rows as $row) {
                $text = '';
                foreach ($columns as $column) {
                    $text .= "\n" . (string) $row[$column];
                }
                $text = rawurldecode(str_replace('\\/', '/', $text));
                foreach ($candidates as &$candidate) {
                    if (stripos($text, $prefix . $candidate['relative']) !== false && count($candidate['elsewhere']) < 5) {
                        $candidate['elsewhere'][] = array('label' => $label, 'id' => (int) $row['id']);
                    }
                }
                unset($candidate);
            }
        }
    }

    /** Which attachment objects (event12, venue3, category4) still exist. */
    private function existingObjects(array $objects): array
    {
        $tables = array('event' => '#__jem_events', 'venue' => '#__jem_venues', 'category' => '#__jem_categories');
        $ids = array();
        foreach ($objects as $object) {
            if (preg_match('/^([a-z]+)([0-9]+)$/iD', $object, $match) && isset($tables[strtolower($match[1])])) {
                $ids[strtolower($match[1])][] = (int) $match[2];
            }
        }
        $existing = array();
        foreach ($ids as $type => $list) {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName($tables[$type]))
                ->where($this->db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $list)) . ')');
            foreach ((array) $this->db->setQuery($query)->loadColumn() as $id) {
                $existing[$type . (int) $id] = true;
            }
        }

        return $existing;
    }
}
