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

require_once JPATH_SITE . '/components/com_jem/classes/imageprofilepolicy.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/eventimagepath.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/venueimagepath.class.php';

/**
 * Housekeeping-Model
 */
class JemModelHousekeeping extends BaseDatabaseModel
{
    const EVENTS = 1;
    const VENUES = 2;
    const CATEGORIES = 3;
    const IMAGE_NORMALISE_BATCH_LIMIT = 25;
    const IMAGE_CANDIDATE_PAGE_LIMIT = 50;

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

                $thumbFolder = dirname($thumb);
                if (!Folder::exists($thumbFolder) && !Folder::create($thumbFolder)) {
                    JemHelper::addLogEntry('Unable to create thumbnail folder: ' . $thumbFolder, __METHOD__, Log::WARNING);
                    continue;
                }

                JemImage::thumb(
                    $source,
                    $thumb,
                    $width,
                    $height,
                    JemImageProfilePolicy::displayMaxDimension($jemsettings)
                );

                if (File::exists($thumb)) {
                    $count++;
                }
            }
        }

        return $count + $this->resizeLinkThumbnails();
    }

    /**
     * Read-only audit of all assigned event, venue and category originals.
     * Counters represent physical files, not database references.
     */
    public function auditImageProfiles($ordering = 'file', $direction = 'asc', $limitstart = 0, $limit = self::IMAGE_CANDIDATE_PAGE_LIMIT)
    {
        $settings = JemHelper::config();
        $report = array(
            'total' => 0,
            'valid' => 0,
            'pending' => 0,
            'blocked' => 0,
            'below_minimum' => 0,
            'over_maximum' => 0,
            'ratio_mismatch' => 0,
            'invalid' => 0,
            'animated' => 0,
            'animated_blocked' => 0,
            'conflicts' => 0,
            'details' => array(),
            'candidates' => array(),
            'candidate_total' => 0,
            'ordering' => 'file',
            'direction' => 'asc',
            'limitstart' => 0,
            'limit' => self::IMAGE_CANDIDATE_PAGE_LIMIT,
        );

        foreach ($this->getImageProfileAssignments($settings) as $assignment) {
            $report['total']++;
            if ($assignment['conflict']) {
                $report['conflicts']++;
                $report['blocked']++;
                $report['details'][] = $this->imageAuditDetail($assignment, 'conflict');
                continue;
            }

            $analysis = JemImage::analyseStoredImage($assignment['source'], $settings, $assignment['profile'], true);
            if (!$analysis['accepted']) {
                $report['invalid']++;
                $report['blocked']++;
                $report['details'][] = $this->imageAuditDetail($assignment, 'invalid');
                continue;
            }

            if ($analysis['minimum_not_met']) {
                $report['below_minimum']++;
            }
            if ($analysis['max_exceeded']) {
                $report['over_maximum']++;
            }
            if ($analysis['ratio_mismatch']) {
                $report['ratio_mismatch']++;
            }
            if ((int) $analysis['frames'] > 1) {
                $report['animated']++;
            }

            if ($analysis['minimum_not_met']) {
                $report['blocked']++;
                $report['details'][] = $this->imageAuditDetail($assignment, 'below_minimum');
            } elseif ($analysis['needs_normalisation'] && (int) $analysis['frames'] > 1) {
                $report['animated_blocked']++;
                $report['blocked']++;
                $report['details'][] = $this->imageAuditDetail($assignment, 'animated_skip');
            } elseif ($analysis['needs_normalisation']
                && !$this->isImageNormalisationCandidate($assignment, $analysis, $settings)) {
                $report['blocked']++;
                $report['details'][] = $this->imageAuditDetail($assignment, 'target_below_minimum');
            } elseif ($analysis['needs_normalisation']) {
                $report['pending']++;
                $report['candidates'][] = $this->imageNormalisationCandidate($assignment, $analysis, $settings);
            } else {
                $report['valid']++;
            }
        }

        $report['candidate_total'] = count($report['candidates']);
        $report['pending'] = $report['candidate_total'];
        $report['ordering'] = $this->normaliseImageCandidateOrdering($ordering);
        $report['direction'] = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';
        $this->sortImageCandidates($report['candidates'], $report['ordering'], $report['direction']);

        $limit = max(1, min(100, (int) $limit));
        $limitstart = max(0, (int) $limitstart);
        if ($report['candidate_total'] > 0 && $limitstart >= $report['candidate_total']) {
            $limitstart = (int) (floor(($report['candidate_total'] - 1) / $limit) * $limit);
        }

        $report['limit'] = $limit;
        $report['limitstart'] = $limitstart;
        $report['candidates'] = array_slice($report['candidates'], $limitstart, $limit);
        $report['details'] = array_slice($report['details'], 0, 100);

        return $report;
    }

    /**
     * Normalise one explicitly selected batch. Existing references and filenames stay unchanged.
     */
    public function normaliseImageProfiles(array $selectedIdentifiers)
    {
        $settings = JemHelper::config();
        $selectedIdentifiers = array_values(array_unique($selectedIdentifiers));

        if (count($selectedIdentifiers) < 1 || count($selectedIdentifiers) > self::IMAGE_NORMALISE_BATCH_LIMIT) {
            throw new InvalidArgumentException('Invalid image normalisation batch size.');
        }

        foreach ($selectedIdentifiers as $identifier) {
            if (!is_string($identifier) || !preg_match('/^[a-f0-9]{64}$/D', $identifier)) {
                throw new InvalidArgumentException('Invalid image normalisation identifier.');
            }
        }

        $selected = array_fill_keys($selectedIdentifiers, true);
        $result = array(
            'selected' => count($selectedIdentifiers),
            'attempted' => 0,
            'completed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'failed_files' => array(),
        );

        foreach ($this->getImageProfileAssignments($settings) as $assignment) {
            if (!$selected) {
                break;
            }

            if ($assignment['conflict']) {
                continue;
            }

            $analysis = JemImage::analyseStoredImage($assignment['source'], $settings, $assignment['profile'], true);
            if (!$this->isImageNormalisationCandidate($assignment, $analysis, $settings)) {
                continue;
            }

            $identifier = $this->imageCandidateIdentifier($assignment);
            if (!isset($selected[$identifier])) {
                continue;
            }

            unset($selected[$identifier]);
            $analysis = JemImage::analyseStoredImage($assignment['source'], $settings, $assignment['profile'], true);
            if (!$this->isImageNormalisationCandidate($assignment, $analysis, $settings)) {
                $result['skipped']++;
                continue;
            }

            $result['attempted']++;
            if (JemImage::normaliseStoredImage(
                $assignment['source'],
                $assignment['thumbnail'],
                $settings,
                $assignment['profile']
            )) {
                $result['completed']++;
            } else {
                $result['failed']++;
                $result['failed_files'][] = $this->imageRelativePath($assignment['source']);
                JemHelper::addLogEntry(
                    'Unable to normalise selected image: ' . $assignment['source'],
                    __METHOD__,
                    Log::WARNING
                );
            }
        }

        $result['skipped'] += count($selected);

        return $result;
    }

    /**
     * Resolve and deduplicate all database image references by physical source path.
     */
    private function getImageProfileAssignments($settings)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $assignments = array();

        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(array('id', 'title', 'image_path', 'datimage', 'fullimage')))
                ->from($db->quoteName('#__jem_events'))
        );
        foreach ((array) $db->loadObjectList() as $event) {
            $folder = JemEventImagePath::normaliseRelativeFolder($event->image_path ?? '');
            $this->addImageProfileAssignment(
                $assignments,
                'event',
                (int) $event->id,
                (string) $event->title,
                (string) $event->datimage,
                JemEventImagePath::absoluteImageFolder($folder),
                JemEventImagePath::absoluteThumbFolder($folder),
                JemImageProfilePolicy::EVENT_INTRO,
                $settings
            );
            $this->addImageProfileAssignment(
                $assignments,
                'event',
                (int) $event->id,
                (string) $event->title,
                (string) $event->fullimage,
                JemEventImagePath::absoluteImageFolder($folder),
                JemEventImagePath::absoluteThumbFolder($folder),
                JemImageProfilePolicy::EVENT_FULL,
                $settings
            );
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(array('id', 'venue', 'image_path', 'locimage')))
                ->from($db->quoteName('#__jem_venues'))
        );
        foreach ((array) $db->loadObjectList() as $venue) {
            $folder = JemVenueImagePath::normaliseRelativeFolder($venue->image_path ?? '');
            $this->addImageProfileAssignment(
                $assignments,
                'venue',
                (int) $venue->id,
                (string) $venue->venue,
                (string) $venue->locimage,
                JemVenueImagePath::absoluteImageFolder($folder),
                JemVenueImagePath::absoluteThumbFolder($folder),
                JemImageProfilePolicy::VENUE,
                $settings
            );
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(array('id', 'catname', 'image')))
                ->from($db->quoteName('#__jem_categories'))
                ->where($db->quoteName('id') . ' > 1')
        );
        foreach ((array) $db->loadObjectList() as $category) {
            $this->addImageProfileAssignment(
                $assignments,
                'category',
                (int) $category->id,
                (string) $category->catname,
                (string) $category->image,
                Path::clean(JPATH_SITE . '/images/jem/categories'),
                Path::clean(JPATH_SITE . '/images/jem/categories/small'),
                JemImageProfilePolicy::CATEGORY,
                $settings
            );
        }

        return array_values($assignments);
    }

    private function addImageProfileAssignment(
        array &$assignments,
        $entity,
        $id,
        $title,
        $filename,
        $sourceFolder,
        $thumbnailFolder,
        $profile,
        $settings
    ) {
        $filename = trim((string) $filename);
        if ($filename === '' || File::makeSafe($filename) !== $filename) {
            return;
        }

        $source = Path::clean(rtrim((string) $sourceFolder, '\\/') . '/' . $filename);
        if (!is_file($source)) {
            return;
        }

        $key = strtolower(str_replace('\\', '/', $source));
        $signature = JemImageProfilePolicy::signature($settings, (string) $profile);
        if (!isset($assignments[$key])) {
            $assignments[$key] = array(
                'source' => $source,
                'thumbnail' => Path::clean(rtrim((string) $thumbnailFolder, '\\/') . '/' . $filename),
                'profile' => (string) $profile,
                'signature' => $signature,
                'conflict' => false,
                'uses' => array(),
            );
        } elseif ($assignments[$key]['signature'] !== $signature) {
            $assignments[$key]['conflict'] = true;
        }

        $assignments[$key]['uses'][] = array(
            'entity' => (string) $entity,
            'id' => (int) $id,
            'title' => (string) $title,
            'profile' => (string) $profile,
        );
    }

    private function imageAuditDetail(array $assignment, $status)
    {
        $use = reset($assignment['uses']);

        return array(
            'status' => (string) $status,
            'entity' => (string) ($use['entity'] ?? ''),
            'id' => (int) ($use['id'] ?? 0),
            'title' => (string) ($use['title'] ?? ''),
            'profile' => (string) ($use['profile'] ?? $assignment['profile']),
            'file' => basename((string) $assignment['source']),
        );
    }

    /**
     * Build the display-only information for one eligible image.
     */
    private function imageNormalisationCandidate(array $assignment, array $analysis, $settings)
    {
        $use = reset($assignment['uses']);
        $config = JemImageProfilePolicy::resolve($settings, (string) $assignment['profile']);
        $geometry = JemImageProfilePolicy::geometry(
            (int) $analysis['width'],
            (int) $analysis['height'],
            JemImageProfilePolicy::maxDimension($settings),
            $config['mode'],
            $config['ratio_width'],
            $config['ratio_height']
        );
        $profiles = array_values(array_unique(array_map(static function ($assignedUse) {
            return (string) ($assignedUse['profile'] ?? '');
        }, $assignment['uses'])));
        sort($profiles, SORT_STRING);

        return array(
            'identifier' => $this->imageCandidateIdentifier($assignment),
            'file' => basename((string) $assignment['source']),
            'path' => dirname($this->imageRelativePath($assignment['source'])),
            'extension' => strtolower(File::getExt((string) $assignment['source'])),
            'size' => max(0, (int) @filesize((string) $assignment['source'])),
            'width' => (int) $analysis['width'],
            'height' => (int) $analysis['height'],
            'resolution' => (int) $analysis['width'] * (int) $analysis['height'],
            'ratio' => $this->imageRatio((int) $analysis['width'], (int) $analysis['height']),
            'target_width' => (int) $geometry['width'],
            'target_height' => (int) $geometry['height'],
            'target_ratio' => $this->imageRatio((int) $geometry['width'], (int) $geometry['height']),
            'adjustment' => (string) $geometry['method'],
            'profile' => implode(',', $profiles),
            'profiles' => $profiles,
            'entity' => (string) ($use['entity'] ?? ''),
            'id' => (int) ($use['id'] ?? 0),
            'title' => (string) ($use['title'] ?? ''),
            'uses' => count($assignment['uses']),
        );
    }

    private function isImageNormalisationCandidate(array $assignment, array $analysis, $settings)
    {
        if (empty($analysis['accepted'])
            || !empty($analysis['minimum_not_met'])
            || empty($analysis['needs_normalisation'])
            || (int) ($analysis['frames'] ?? 1) !== 1) {
            return false;
        }

        $config = JemImageProfilePolicy::resolve($settings, (string) $assignment['profile']);
        $geometry = JemImageProfilePolicy::geometry(
            (int) $analysis['width'],
            (int) $analysis['height'],
            JemImageProfilePolicy::maxDimension($settings),
            $config['mode'],
            $config['ratio_width'],
            $config['ratio_height']
        );
        $minimum = JemImageProfilePolicy::minDimension($settings);

        return (int) $geometry['width'] >= $minimum
            && (int) $geometry['height'] >= $minimum;
    }

    /**
     * Bind a submitted selection to the current file and profile without exposing a server path.
     */
    private function imageCandidateIdentifier(array $assignment)
    {
        $source = (string) $assignment['source'];
        $payload = implode('|', array(
            $this->imageRelativePath($source),
            (string) $assignment['signature'],
            (string) max(0, (int) @filesize($source)),
            (string) max(0, (int) @filemtime($source)),
        ));
        $secret = (string) Factory::getApplication()->get('secret', '');

        return hash_hmac('sha256', $payload, $secret !== '' ? $secret : __CLASS__);
    }

    private function imageRelativePath($source)
    {
        $source = str_replace('\\', '/', Path::clean((string) $source));
        $root = rtrim(str_replace('\\', '/', Path::clean(JPATH_SITE)), '/');

        if (strncasecmp($source, $root . '/', strlen($root) + 1) === 0) {
            return ltrim(substr($source, strlen($root)), '/');
        }

        return basename($source);
    }

    private function imageRatio($width, $height)
    {
        $width = max(1, (int) $width);
        $height = max(1, (int) $height);
        $left = $width;
        $right = $height;

        while ($right !== 0) {
            $remainder = $left % $right;
            $left = $right;
            $right = $remainder;
        }

        $divisor = max(1, $left);

        return (int) ($width / $divisor) . ':' . (int) ($height / $divisor);
    }

    private function normaliseImageCandidateOrdering($ordering)
    {
        $allowed = array('file', 'path', 'profile', 'resolution', 'ratio', 'size', 'extension', 'adjustment');

        return in_array((string) $ordering, $allowed, true) ? (string) $ordering : 'file';
    }

    private function sortImageCandidates(array &$candidates, $ordering, $direction)
    {
        $multiplier = $direction === 'desc' ? -1 : 1;

        usort($candidates, static function ($left, $right) use ($ordering, $multiplier) {
            if (in_array($ordering, array('resolution', 'size'), true)) {
                $comparison = ((int) $left[$ordering]) <=> ((int) $right[$ordering]);
            } else {
                $comparison = strnatcasecmp((string) $left[$ordering], (string) $right[$ordering]);
            }

            if ($comparison === 0) {
                $comparison = strnatcasecmp(
                    (string) $left['path'] . '/' . (string) $left['file'],
                    (string) $right['path'] . '/' . (string) $right['file']
                );
            }

            return $comparison * $multiplier;
        });
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
        // Child and history tables must be reset as well as their legacy
        // parents. Otherwise stale explicit IDs can block a later Sample Data
        // import even though events, venues and categories appear empty.
        $tables = array(
            'notifications_attempts',
            'notifications',
            'register_items',
            'register_capacity_allocations',
            'register_history',
            'register',
            'event_prices',
            'capacity_pools',
            'event_space_layouts',
            'space_conflict_overrides',
            'venue_capacity_areas',
            'venue_profile_spaces',
            'venue_layouts',
            'venue_spaces',
            'venue_capacity_profiles',
            'cats_event_relations',
            'event_series',
            'attachments',
            'links',
            'events',
            'groupmembers',
            'groups',
            'special_days',
            'categories',
            'venues',
            'types',
        );
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

        // Keep the reusable global reminder definitions, but remove definitions
        // tied to events that were just deleted.
        $db->setQuery('DELETE FROM #__jem_reminders WHERE event_id <> 0');
        if ($db->execute() === false) {
            JemHelper::addLogEntry('Error deleting event reminder definitions', __METHOD__, Log::ERROR);
            $result = false;
        }

        // This marker describes installed Sample Data rather than a setting.
        $db->setQuery(
            'DELETE FROM #__jem_config WHERE keyname = ' . $db->quote('sample_showcase_catalog')
        );
        if ($db->execute() === false) {
            JemHelper::addLogEntry('Error deleting the Sample Data marker', __METHOD__, Log::ERROR);
            $result = false;
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
        if ($type === JemModelHousekeeping::EVENTS) {
            $query = 'SELECT CONCAT_WS(' . $this->_db->quote('/') . ', NULLIF(image_path, ' . $this->_db->quote('') . '), datimage)'
                . ' FROM #__jem_events WHERE datimage <> ' . $this->_db->quote('')
                . ' UNION SELECT CONCAT_WS(' . $this->_db->quote('/') . ', NULLIF(image_path, ' . $this->_db->quote('') . '), fullimage)'
                . ' FROM #__jem_events WHERE fullimage <> ' . $this->_db->quote('');
        } elseif ($type === JemModelHousekeeping::VENUES) {
            $query = 'SELECT CONCAT_WS(' . $this->_db->quote('/') . ', NULLIF(image_path, ' . $this->_db->quote('') . '), locimage)'
                . ' FROM #__jem_venues WHERE locimage <> ' . $this->_db->quote('');
        } else {
            $query = 'SELECT '.$this->map[$type]['field'].' FROM #__jem_'.$this->map[$type]['table'];
        }

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
        $basePath = rtrim(Path::clean(JPATH_SITE.'/images/jem/'.$this->map[$type]['folder']), DIRECTORY_SEPARATOR);
        $thumbBase = rtrim(Path::clean($basePath . '/small'), DIRECTORY_SEPARATOR);

        $images = array ();

        // Get the list of files and folders from the given folder
        $fileList = Folder::files($basePath, '.', true, true);

        // Iterate over the files if they exist
        if ($fileList !== false) {
            foreach ($fileList as $file) {
                $file = Path::clean($file);
                if (strpos($file . DIRECTORY_SEPARATOR, $thumbBase . DIRECTORY_SEPARATOR) === 0) {
                    continue;
                }
                $relative = ltrim(str_replace('\\', '/', substr($file, strlen($basePath))), '/');
                if (is_file($file) && $relative !== '' && substr(basename($relative), 0, 1) != '.') {
                    $images[] = $relative;
                }
            }
        }

        return $images;
    }
}
?>
