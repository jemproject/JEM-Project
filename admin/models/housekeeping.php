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

/**
 * Housekeeping-Model
 */
class JemModelHousekeeping extends BaseDatabaseModel
{
    const EVENTS = 1;
    const VENUES = 2;
    const CATEGORIES = 3;

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
        // Set FTP credentials, if given
        ClientHelper::setCredentialsFromRequest('ftp');

        // Get some data from the request
        $images    = $this->getImages($type);
        $folder = $this->map[$type]['folder'];

        $count = count($images);
        $fail = 0;

        foreach ($images as $image)
        {
            if ($image !== InputFilter::getInstance()->clean($image, 'path')) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UNABLE_TO_DELETE').' '.htmlspecialchars($image, ENT_COMPAT, 'UTF-8'), 'warning');
                $fail++;
                continue;
            }

            $fullPath = Path::clean(JPATH_SITE.'/images/jem/'.$folder.'/'.$image);
            $fullPaththumb = Path::clean(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$image);

            if (is_file($fullPath)) {
                File::delete($fullPath);
                if (is_file($fullPaththumb)) {
                    File::delete($fullPaththumb);
                }
            }
        }

        $deleted = $count - $fail;

        return $deleted;
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
        $images = array_diff($this->getAvailable($type), $this->getAssigned($type));

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
        $query = 'SELECT '.$this->map[$type]['field'].' FROM #__jem_'.$this->map[$type]['table'];

        $this->_db->setQuery($query);
        $assigned = $this->_db->loadColumn();

        return $assigned;
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
                if (is_file($basePath.'/'.$file) && substr($file, 0, 1) != '.') {
                    $images[] = $file;
                }
            }
        }

        return $images;
    }
}
?>
