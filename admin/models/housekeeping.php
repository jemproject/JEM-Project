<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Filesystem\File;
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Client\ClientHelper;
use Joomla\Filesystem\Path;
use Joomla\CMS\Filter\InputFilter;

/** Cleanup was refused because image references could not be verified. */
class JemHousekeepingImageReferenceException extends \RuntimeException
{
}

/**
 * Housekeeping-Model
 */
class JemModelHousekeeping extends BaseDatabaseModel
{
    const EVENTS = 1;
    const VENUES = 2;
    const CATEGORIES = 3;

    /** Maximum number of explicitly reviewed originals deleted per request. */
    public const IMAGE_CLEANUP_BATCH_LIMIT = 200;

    /**
     * Map logical name to folder and db names
     * @var stdClass
     */
    private $map = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $map = array();
        $map[JemModelHousekeeping::EVENTS] = array("folder" => "events", "table" => "events", "field" => "datimage");
        $map[JemModelHousekeeping::VENUES] = array("folder" => "venues", "table" => "venues", "field" => "locimage");
        $map[JemModelHousekeeping::CATEGORIES] = array("folder" => "categories", "table" => "categories", "field" => "image");
        $this->map = $map;
    }

    /**
     * Method to delete the images
     *
     * @access public
     * @return int
     */
    public function delete($type)
    {
        ClientHelper::setCredentialsFromRequest('ftp');
        $deleted = 0;
        foreach ($this->getImages($type) as $image) {
            if ($this->deleteImageFile($type, $image)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * List unused originals for review, with identifiers bound to their current metadata.
     *
     * @param int $type Event, venue or category image area.
     * @return array Candidate identifier, relative name, size and modification time.
     */
    public function getImageCleanupCandidates(int $type): array
    {
        if (!isset($this->map[$type])) {
            throw new InvalidArgumentException('Unknown image area.');
        }
        $base = JPATH_SITE . '/images/jem/' . $this->map[$type]['folder'];
        $secret = (string) Factory::getApplication()->get('secret', '');
        $candidates = array();
        foreach ($this->getImages($type) as $image) {
            $path = self::containedImageFile($base, $image);
            if ($path === null) {
                continue;
            }
            clearstatcache(true, $path);
            $size = (int) filesize($path);
            $modified = (int) filemtime($path);
            $candidates[] = array(
                'id' => hash_hmac('sha256', implode('|', array($type, $image, $size, $modified)), $secret),
                'relative' => $image,
                'size' => $size,
                'modified' => $modified,
            );
        }
        usort($candidates, static fn(array $a, array $b): int => strnatcasecmp($a['relative'], $b['relative']));

        return $candidates;
    }

    /**
     * Delete only selected originals that are still unused and unchanged since review.
     *
     * @param int $type Image area.
     * @param array $selected Opaque candidate identifiers from the preview.
     * @return array Counts of deleted, skipped and failed originals.
     */
    public function deleteSelectedImages(int $type, array $selected): array
    {
        if (!$selected || count($selected) > self::IMAGE_CLEANUP_BATCH_LIMIT) {
            throw new InvalidArgumentException('Invalid image selection size.');
        }
        foreach ($selected as $id) {
            if (!is_string($id) || !preg_match('/^[a-f0-9]{64}$/D', $id)) {
                throw new InvalidArgumentException('Invalid image selection.');
            }
        }
        $candidates = array_column($this->getImageCleanupCandidates($type), null, 'id');
        $result = array('deleted' => 0, 'skipped' => 0, 'failed' => 0);
        foreach (array_unique($selected) as $id) {
            if (!isset($candidates[$id])) {
                $result['skipped']++;
                continue;
            }
            $result[$this->deleteImageFile($type, $candidates[$id]['relative']) ? 'deleted' : 'failed']++;
        }

        return $result;
    }

    /**
     * Remove a verified unused original and its contained thumbnail.
     *
     * @param int $type Image area.
     * @param string $image Folder-relative filename.
     * @return bool Whether all existing files were removed successfully.
     */
    private function deleteImageFile(int $type, string $image): bool
    {
        $base = JPATH_SITE . '/images/jem/' . $this->map[$type]['folder'];
        $path = self::containedImageFile($base, $image);
        if ($path === null) {
            return false;
        }
        try {
            if (!File::delete($path)) {
                return false;
            }
            $thumb = self::containedImageFile($base . '/small', $image);
            return $thumb === null || File::delete($thumb);
        } catch (\RuntimeException $error) {
            return false;
        }
    }

    /**
     * Resolve regular images within their managed directory, excluding links and control files.
     *
     * @param string $base Managed directory.
     * @param string $relative Filename inside that directory.
     * @return string|null Verified absolute path, or null for an unsafe/non-image entry.
     */
    private static function containedImageFile(string $base, string $relative): ?string
    {
        if (
            $relative === '' || $relative[0] === '.' || str_contains($relative, "\0")
            || !preg_match('/\.(?:bmp|gif|jpe|jpeg|jfif|jpg|png|svg|webp)$/iD', $relative)
        ) {
            return null;
        }
        $root = realpath($base);
        $expected = Path::clean($base . '/' . $relative);
        $path = realpath($expected);
        if ($root === false || $path === false || !is_file($path) || is_link($expected)) {
            return null;
        }
        $compare = static fn(string $value): string => PHP_OS_FAMILY === 'Windows' ? strtolower($value) : $value;
        return str_starts_with($compare($path), $compare($root . DIRECTORY_SEPARATOR))
            && $compare($path) === $compare($expected) ? $path : null;
    }

    /**
     * Deletes zombie cats_event_relations with no existing event or category
     * @return boolean
     */
    public function cleanupCatsEventRelations()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $db->setQuery('DELETE cat FROM #__jem_cats_event_relations as cat'
                .' LEFT OUTER JOIN #__jem_events as e ON cat.itemid = e.id'
                .' WHERE e.id IS NULL');
        $db->execute();

        $db->setQuery('DELETE cat FROM #__jem_cats_event_relations as cat'
                .' LEFT OUTER JOIN #__jem_categories as c ON cat.catid = c.id'
                .' WHERE c.id IS NULL');
        $db->execute();

        return true;
    }

    /**
     * Deletes physical attachment files that are no longer referenced by attachment records.
     *
     * @param string|false $type Optional object type prefix, e.g. event, venue, category.
     *
     * @return object Cleanup counters.
     */
    public function cleanupUnusedAttachmentFiles($type = false)
    {
        $result = null;

        JemHelper::delete_unused_attachment_files($type, $result);

        return $result ?: (object) array(
            'files'   => 0,
            'folders' => 0,
            'failed'  => 0,
        );
    }

    /**
     * Regenerates thumbnails for assigned event, venue, category and event link images.
     *
     * @return int Number of regenerated thumbnails.
     */
    public function resizeThumbnails()
    {
        $jemsettings = JemHelper::config();
        $width = max(1, (int) $jemsettings->imagewidth);
        $height = max(1, (int) $jemsettings->imagehight);
        $count = 0;

        foreach (array(JemModelHousekeeping::EVENTS, JemModelHousekeeping::VENUES, JemModelHousekeeping::CATEGORIES) as $type) {
            $folder = $this->map[$type]['folder'];
            $images = array_unique(array_filter((array) $this->getAssigned($type)));
            $sourceBase = Path::clean(JPATH_SITE . '/images/jem/' . $folder);
            $thumbBase = Path::clean($sourceBase . '/small');

            if (!Folder::exists($thumbBase)) {
                Folder::create($thumbBase);
            }

            foreach ($images as $image) {
                if ($image !== InputFilter::getInstance()->clean($image, 'path')) {
                    JemHelper::addLogEntry('Skipping unsafe image path while regenerating thumbnails: ' . $image, __METHOD__, Log::WARNING);
                    continue;
                }

                $source = Path::clean($sourceBase . '/' . $image);
                $thumb = Path::clean($thumbBase . '/' . $image);

                if (!is_file($source)) {
                    continue;
                }

                if (File::exists($thumb) && !File::delete($thumb)) {
                    JemHelper::addLogEntry('Unable to remove old thumbnail: ' . $thumb, __METHOD__, Log::WARNING);
                    continue;
                }

                JemImage::thumb($source, $thumb, $width, $height);

                if (File::exists($thumb)) {
                    $count++;
                }
            }
        }

        return $count + $this->resizeLinkThumbnails();
    }

    /**
     * Regenerates thumbnails for event link images.
     *
     * @return int Number of regenerated thumbnails.
     */
    private function resizeLinkThumbnails()
    {
        $thumbBase = Path::clean(JPATH_SITE . '/images/jem/links/small');
        $count = 0;
        $seen = array();

        if (Folder::exists($thumbBase)) {
            $files = Folder::files($thumbBase, '.', false, true, array('index.html'), array());

            foreach ($files as $file) {
                if (is_file($file)) {
                    File::delete($file);
                }
            }
        } else {
            Folder::create($thumbBase);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__jem_links'))
            ->where($db->quoteName('params') . ' IS NOT NULL')
            ->where($db->quoteName('params') . ' <> ' . $db->quote(''));

        $db->setQuery($query);
        $linkParams = $db->loadColumn() ?: array();

        foreach ($linkParams as $paramsJson) {
            $params = json_decode($paramsJson, true);

            if (!is_array($params) || empty($params['image'])) {
                continue;
            }

            $image = trim((string) $params['image']);
            $maxWidth = isset($params['max_width']) ? (int) $params['max_width'] : 120;
            $maxHeight = isset($params['max_height']) ? (int) $params['max_height'] : 60;
            $key = $image . '|' . $maxWidth . '|' . $maxHeight;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $thumb = JemImage::linkThumbnail($image, $maxWidth, $maxHeight, true);

            if (strpos($thumb, 'images/jem/links/small/') === 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Truncates JEM tables with exception of settings table
     */
    public function truncateAllData($deleteAttachmentFiles = false, $deleteImageFiles = false)
    {
        $result = true;
        $tables = array('attachments', 'categories', 'cats_event_relations', 'events', 'groupmembers', 'groups', 'links', 'register', 'types', 'venues');
        $db = Factory::getContainer()->get('DatabaseDriver');

        if ($deleteImageFiles && !$this->deleteAllImageFiles()) {
            JemHelper::addLogEntry('Error deleting image files while truncating JEM data', __METHOD__, Log::ERROR);
            $result = false;
        }

        if ($deleteAttachmentFiles && !$this->deleteAllAttachmentFiles()) {
            JemHelper::addLogEntry('Error deleting attachment files while truncating JEM data', __METHOD__, Log::ERROR);
            $result = false;
        }

        foreach ($tables as $table) {
            $db->setQuery('TRUNCATE #__jem_'.$table);

            if ($db->execute() === false) {
                // report but continue
                JemHelper::addLogEntry('Error truncating #__jem_'.$table, __METHOD__, Log::ERROR);
                $result = false;
            }
        }

        $categoryTable = $this->getTable('category', 'JemTable');
        $categoryTable->addRoot();

        return $result;
    }

    /**
     * Deletes event, venue, category and event link image files from the JEM image folders.
     *
     * @return boolean
     */
    private function deleteAllImageFiles()
    {
        $basePath = Path::clean(JPATH_SITE . '/images/jem');
        $folders = array('events', 'venues', 'categories', 'links');
        $result = true;

        foreach ($folders as $folder) {
            $path = Path::clean($basePath . '/' . $folder);

            if (!Folder::exists($path)) {
                continue;
            }

            $files = Folder::files($path, '.', true, true, array('index.html'), array());

            foreach ($files as $file) {
                if (is_file($file) && !File::delete($file)) {
                    $result = false;
                }
            }
        }

        return $result;
    }

    /**
     * Deletes attachment object folders from the configured attachments path.
     *
     * @return boolean
     */
    private function deleteAllAttachmentFiles()
    {
        $jemsettings = JemHelper::config();
        $relativePath = trim((string) $jemsettings->attachments_path);

        if ($relativePath === '') {
            return true;
        }

        $basePath = Path::clean(JPATH_SITE . '/' . $relativePath);
        $sitePath = rtrim(Path::clean(JPATH_SITE), '\\/');

        if ($basePath === $sitePath || !Folder::exists($basePath)) {
            return true;
        }

        $folders = Folder::folders($basePath, '.', false, true, array('.', '..'));
        $result = true;

        foreach ($folders as $folder) {
            $object = basename($folder);

            if (!preg_match('/^[a-z]+[0-9]+$/i', $object)) {
                continue;
            }

            if (!Folder::delete($folder)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Method to count the cat_relations table
     *
     * @access public
     * @return int
     */
    public function getCountcats()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select(array('*'));
        $query->from('#__jem_cats_event_relations');
        $db->setQuery($query);
        $db->execute();

        $total = $db->loadObjectList();

        return is_array($total) ? count($total) : 0;
    }

    /**
     * Method to determine the images to delete
     *
     * @access private
     * @return array
     */
    private function getImages($type)
    {
        $assigned = $this->getAssigned($type);
        $key = static fn($name): string => PHP_OS_FAMILY === 'Windows' ? mb_strtolower($name, 'UTF-8') : $name;
        $used = array_fill_keys(array_map($key, $assigned), true);
        $images = array_values(array_filter($this->getAvailable($type), static fn($name): bool => !isset($used[$key($name)])));

        return $images;
    }

    /**
     * Method to determine the assigned images
     *
     * @access private
     * @return array
     */
    private function getAssigned($type)
    {
        try {
            if ($type === JemModelHousekeeping::EVENTS) {
                $query = 'SELECT datimage FROM #__jem_events WHERE datimage <> ' . $this->_db->quote('')
                    . ' UNION SELECT fullimage FROM #__jem_events WHERE fullimage <> ' . $this->_db->quote('');
            } else {
                $query = 'SELECT '.$this->map[$type]['field'].' FROM #__jem_'.$this->map[$type]['table'];
            }

            $this->_db->setQuery($query);
            $assigned = $this->_db->loadColumn();

            return array_merge((array) $assigned, $this->getLinkImages($type));
        } catch (JemHousekeepingImageReferenceException $error) {
            throw $error;
        } catch (\Exception $error) {
            throw new JemHousekeepingImageReferenceException(
                Text::_('COM_JEM_HOUSEKEEPING_IMAGE_REFERENCES_FAILED'),
                0,
                $error
            );
        }
    }

    /**
     * Read link references regardless of publication or the event's current state.
     *
     * @param int $type Image area identifier.
     * @return string[] Paths relative to the selected image folder.
     */
    private function getLinkImages($type): array
    {
        $query = $this->_db->getQuery(true)
            ->select($this->_db->quoteName('params'))
            ->from($this->_db->quoteName('#__jem_links'))
            ->where($this->_db->quoteName('params') . ' IS NOT NULL')
            ->where($this->_db->quoteName('params') . ' <> ' . $this->_db->quote(''));
        $this->_db->setQuery($query);

        return self::extractLinkImages((array) $this->_db->loadColumn(), $this->map[$type]['folder']);
    }

    /**
     * Resolve local link images without treating unreadable references as unused.
     *
     * @param array $rows Stored JSON parameter values.
     * @param string $folder Managed image folder name.
     * @return string[] Unique local names, including literal and URL-decoded variants.
     * @throws UnexpectedValueException If stored image references cannot be interpreted.
     * @throws JsonException If a nonempty parameter value is malformed.
     */
    private static function extractLinkImages(array $rows, string $folder): array
    {
        $prefix = 'images/jem/' . $folder . '/';
        $images = array();

        foreach ($rows as $json) {
            if (trim((string) $json) === '') {
                continue;
            }
            $params = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($params)) {
                throw new UnexpectedValueException('Invalid event link parameters.');
            }
            $image = $params['image'] ?? '';
            if (!is_string($image)) {
                throw new UnexpectedValueException('Invalid event link image.');
            }
            $image = trim($image);
            $fragment = strpos($image, '#joomlaImage://');
            if ($fragment !== false) {
                $image = substr($image, 0, $fragment);
            }

            foreach (array_unique(array(rawurldecode($image), $image)) as $variant) {
                $variant = str_replace('\\', '/', $variant);
                // External URLs never protect an unrelated file in a JEM folder.
                if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $variant)) {
                    continue;
                }
                $variant = ltrim($variant, '/');
                if (str_contains($variant, "\0") || preg_match('#(?:^|/)\.\.(?:/|$)#', $variant)) {
                    throw new UnexpectedValueException('Invalid local event link image path.');
                }
                $variant = implode('/', array_filter(explode('/', $variant), static fn($part): bool => $part !== '' && $part !== '.'));
                $matches = PHP_OS_FAMILY === 'Windows'
                    ? strncasecmp($variant, $prefix, strlen($prefix)) === 0
                    : str_starts_with($variant, $prefix);
                if (!$matches || strlen($variant) <= strlen($prefix)) {
                    continue;
                }
                $relative = substr($variant, strlen($prefix));
                $images[] = $relative;
                // Removing an original also removes its thumbnail. Protect both
                // when a link explicitly selects a file from the small folder.
                $thumbnail = PHP_OS_FAMILY === 'Windows'
                    ? strncasecmp($relative, 'small/', 6) === 0 : str_starts_with($relative, 'small/');
                if ($thumbnail) {
                    $images[] = substr($relative, strlen('small/'));
                }
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * Method to determine the unassigned images
     *
     * @access private
     * @return array
     */
    private function getAvailable($type)
    {
        // Initialize variables
        $basePath = JPATH_SITE.'/images/jem/'.$this->map[$type]['folder'];

        $images = array ();

        // Get the list of files and folders from the given folder
        $fileList = Folder::files($basePath);

        // Iterate over the files if they exist
        if ($fileList !== false) {
            foreach ($fileList as $file)
            {
                if (self::containedImageFile($basePath, $file) !== null) {
                    $images[] = $file;
                }
            }
        }

        return $images;
    }
}
?>
