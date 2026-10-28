<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Filesystem\File;
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\Table\Asset;
use Joomla\Filesystem\Path;

require_once JPATH_SITE . '/components/com_jem/classes/imageprofilepolicy.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/eventimagepath.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/venueimagepath.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/categoryimagepath.class.php';

/**
 * A data reset was refused before deleting any JEM records or physical files.
 */
class JemHousekeepingResetPreflightException extends \RuntimeException
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
        $images = $this->getImages($type);
        $folder = $this->map[$type]['folder'];
        $basePath = JPATH_SITE . '/images/jem/' . $folder;
        $thumbBase = $basePath . '/small';
        $deleted = 0;

        foreach ($images as $image) {
            $fullPath = self::containedExistingFile($basePath, $image);
            if ($fullPath === null) {
                Factory::getApplication()->enqueueMessage(
                    Text::_('COM_JEM_UNABLE_TO_DELETE') . ' ' . htmlspecialchars($image, ENT_COMPAT, 'UTF-8'),
                    'warning'
                );
                continue;
            }

            try {
                if (!File::delete($fullPath)) {
                    throw new \RuntimeException('The image file could not be deleted.');
                }
                $deleted++;
            } catch (\Throwable $error) {
                Factory::getApplication()->enqueueMessage(
                    Text::_('COM_JEM_UNABLE_TO_DELETE') . ' ' . htmlspecialchars($image, ENT_COMPAT, 'UTF-8'),
                    'warning'
                );
                continue;
            }

            $thumb = self::containedExistingFile($thumbBase, $image);
            if ($thumb !== null) {
                try {
                    if (!File::delete($thumb)) {
                        throw new \RuntimeException('The image thumbnail could not be deleted.');
                    }
                } catch (\Throwable $error) {
                    Factory::getApplication()->enqueueMessage(
                        Text::_('COM_JEM_UNABLE_TO_DELETE') . ' ' . htmlspecialchars('small/' . $image, ENT_COMPAT, 'UTF-8'),
                        'warning'
                    );
                }
            }
        }

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

        // Stored image paths may use per-record subfolders. Resolve them through
        // the same assignments as the profile audit, not a fixed root folder.
        foreach ($this->getImageProfileAssignments($jemsettings) as $assignment) {
            if ($assignment['conflict'] || !is_file($assignment['source'])) {
                continue;
            }

            $source = $assignment['source'];
            $thumb = $assignment['thumbnail'];
            $thumbFolder = dirname($thumb);
            if (!Folder::exists($thumbFolder) && !Folder::create($thumbFolder)) {
                JemHelper::addLogEntry('Unable to create thumbnail folder: ' . $thumbFolder, __METHOD__, Log::WARNING);
                continue;
            }
            if (File::exists($thumb) && !File::delete($thumb)) {
                JemHelper::addLogEntry('Unable to remove old thumbnail: ' . $thumb, __METHOD__, Log::WARNING);
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

        return $count + $this->resizeLinkThumbnails();
    }

    /**
     * Read-only audit of all assigned event, venue and category originals.
     * Counters represent distinct assigned paths, including missing originals,
     * rather than database references.
     */
    public function auditImageProfiles($ordering = 'file', $direction = 'asc', $limitstart = 0, $limit = self::IMAGE_CANDIDATE_PAGE_LIMIT)
    {
        $settings = JemHelper::config();
        $report = array(
            'total' => 0,
            'valid' => 0,
            'pending' => 0,
            'blocked' => 0,
            'missing' => 0,
            'by_entity' => array(
                'event' => array('total' => 0, 'not_normalised' => 0, 'missing' => 0),
                'category' => array('total' => 0, 'not_normalised' => 0, 'missing' => 0),
                'venue' => array('total' => 0, 'not_normalised' => 0, 'missing' => 0),
            ),
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
            $entities = array_unique(array_column($assignment['uses'], 'entity'));
            foreach ($entities as $entity) {
                $report['by_entity'][$entity]['total']++;
            }

            if (!empty($assignment['invalid_reference'])) {
                $report['invalid']++;
                $report['blocked']++;
                foreach ($entities as $entity) {
                    $report['by_entity'][$entity]['not_normalised']++;
                }
                $report['details'][] = $this->imageAuditDetail($assignment, 'invalid');
                continue;
            }

            if (!is_file($assignment['source'])) {
                $report['missing']++;
                $report['blocked']++;
                foreach ($entities as $entity) {
                    $report['by_entity'][$entity]['missing']++;
                    $report['by_entity'][$entity]['not_normalised']++;
                }
                $report['details'][] = $this->imageAuditDetail($assignment, 'missing');
                continue;
            }

            if ($assignment['conflict']) {
                $report['conflicts']++;
                $report['blocked']++;
                foreach ($entities as $entity) {
                    $report['by_entity'][$entity]['not_normalised']++;
                }
                $report['details'][] = $this->imageAuditDetail($assignment, 'conflict');
                continue;
            }

            $analysis = JemImage::analyseStoredImage($assignment['source'], $settings, $assignment['profile'], true);
            if (!$analysis['accepted']) {
                $report['invalid']++;
                $report['blocked']++;
                foreach ($entities as $entity) {
                    $report['by_entity'][$entity]['not_normalised']++;
                }
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
                foreach ($entities as $entity) {
                    $report['by_entity'][$entity]['not_normalised']++;
                }
                $report['details'][] = $this->imageAuditDetail($assignment, 'below_minimum');
            } elseif ($analysis['needs_normalisation'] && (int) $analysis['frames'] > 1) {
                $report['animated_blocked']++;
                $report['blocked']++;
                foreach ($entities as $entity) {
                    $report['by_entity'][$entity]['not_normalised']++;
                }
                $report['details'][] = $this->imageAuditDetail($assignment, 'animated_skip');
            } elseif ($analysis['needs_normalisation']
                && !$this->isImageNormalisationCandidate($assignment, $analysis, $settings)) {
                $report['blocked']++;
                foreach ($entities as $entity) {
                    $report['by_entity'][$entity]['not_normalised']++;
                }
                $report['details'][] = $this->imageAuditDetail($assignment, 'target_below_minimum');
            } elseif ($analysis['needs_normalisation']) {
                $report['pending']++;
                foreach ($entities as $entity) {
                    $report['by_entity'][$entity]['not_normalised']++;
                }
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
            'lines' => array(),
        );

        foreach ($this->getImageProfileAssignments($settings) as $assignment) {
            if (!$selected) {
                break;
            }

            if ($assignment['conflict'] || !empty($assignment['invalid_reference']) || !is_file($assignment['source'])) {
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
                $result['lines'][] = array('status' => 'warning', 'file' => $this->imageRelativePath($assignment['source']));
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
                $result['lines'][] = array('status' => 'notice', 'file' => $this->imageRelativePath($assignment['source']));
            } else {
                $result['failed']++;
                $result['failed_files'][] = $this->imageRelativePath($assignment['source']);
                $result['lines'][] = array('status' => 'error', 'file' => $this->imageRelativePath($assignment['source']));
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
                ->select($db->quoteName(array('id', 'catname', 'image', 'image_path')))
                ->from($db->quoteName('#__jem_categories'))
                ->where($db->quoteName('id') . ' > 1')
        );
        foreach ((array) $db->loadObjectList() as $category) {
            $folder = JemCategoryImagePath::normaliseRelativeFolder($category->image_path ?? '');
            $this->addImageProfileAssignment(
                $assignments,
                'category',
                (int) $category->id,
                (string) $category->catname,
                (string) $category->image,
                JemCategoryImagePath::absoluteImageFolder($folder),
                JemCategoryImagePath::absoluteThumbFolder($folder),
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
        if ($filename === '') {
            return;
        }

        if (File::makeSafe($filename) !== $filename || strpbrk($filename, '/\\') !== false) {
            $assignments['invalid:' . $entity . ':' . $id . ':' . $profile] = array(
                'source' => '',
                'reference' => $filename,
                'invalid_reference' => true,
                'thumbnail' => '',
                'profile' => (string) $profile,
                'signature' => '',
                'conflict' => false,
                'uses' => array(array(
                    'entity' => (string) $entity,
                    'id' => (int) $id,
                    'title' => (string) $title,
                    'profile' => (string) $profile,
                )),
            );
            return;
        }

        $source = Path::clean(rtrim((string) $sourceFolder, '\\/') . '/' . $filename);

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
            'file' => (string) ($assignment['reference'] ?? basename((string) $assignment['source'])),
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
            'diagnostic_reports',
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
        // TRUNCATE bypasses the category and venue Table::delete() asset cleanup.
        // Validate the shared Joomla tree before any files or records are removed.
        try {
            $recordAssetIds = $this->getRecordAssetIds($db);
        } catch (\Throwable $error) {
            throw new JemHousekeepingResetPreflightException('JEM data reset preflight failed', 0, $error);
        }
        $tablesReset = true;

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
                $tablesReset = false;
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

        // Do not remove ACL for records that a failed TRUNCATE may have retained.
        if ($tablesReset && !$this->deleteRecordAssets($db, $recordAssetIds)) {
            $result = false;
        }

        $categoryTable = $this->getTable('category', 'JemTable');
        $categoryTable->addRoot();

        return $result;
    }

    /**
     * Return only JEM category and venue record assets, deepest first.
     * The global nested set must be sound before the irreversible reset begins.
     */
    private function getRecordAssetIds($db): array
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'parent_id', 'lft', 'rgt', 'level', 'name')))
            ->from($db->quoteName('#__assets'));
        $assets = (array) $db->setQuery($query)->loadAssocList('id');
        $recordAssets = array_filter($assets, static function ($asset) {
            return preg_match('/^com_jem\.(?:category|venue)\.[0-9]+$/D', (string) $asset['name']) === 1;
        });

        require_once __DIR__ . '/../classes/aclassetmigration.class.php';
        JemAclAssetMigration::validateTree($assets);
        uasort($recordAssets, static function ($left, $right) {
            return ((int) $right['level'] <=> (int) $left['level'])
                ?: ((int) $right['lft'] <=> (int) $left['lft']);
        });

        return array_map('intval', array_keys($recordAssets));
    }

    /** Delete record assets without cascading into assets owned by other extensions. */
    private function deleteRecordAssets($db, array $ids): bool
    {
        try {
            foreach ($ids as $id) {
                $asset = new Asset($db);

                if (!$asset->load($id)
                    || preg_match('/^com_jem\.(?:category|venue)\.[0-9]+$/D', (string) $asset->name) !== 1
                    || !$asset->delete($id, false)) {
                    JemHelper::addLogEntry('Error deleting JEM record asset #' . $id, __METHOD__, Log::ERROR);
                    return false;
                }
            }

            return $this->getRecordAssetIds($db) === array();
        } catch (\Throwable $error) {
            JemHelper::addLogEntry('Error deleting JEM record assets: ' . $error->getMessage(), __METHOD__, Log::ERROR);
            return false;
        } finally {
            Access::clearStatics();
        }
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

    /** Count relations whose event or category no longer exists. */
    public function getCountcats()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery('SELECT COUNT(*) FROM #__jem_cats_event_relations AS rel'
            . ' LEFT JOIN #__jem_events AS e ON e.id = rel.itemid'
            . ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
            . ' WHERE e.id IS NULL OR c.id IS NULL');

        return (int) $db->loadResult();
    }

    /**
     * Method to determine the images to delete
     *
     * @access private
     * @return array
     */
    private function getImages($type)
    {
        $images = self::unassignedImages($this->getAvailable($type), $this->getAssigned($type));

        return $images;
    }

    /** Compare names as the hosting filesystem does, while retaining original paths for deletion. */
    private static function unassignedImages(array $available, array $assigned): array
    {
        $key = static function ($path): string {
            $path = str_replace('\\', '/', rawurldecode((string) $path));

            return PHP_OS_FAMILY === 'Windows' ? mb_strtolower($path, 'UTF-8') : $path;
        };
        $used = array_fill_keys(array_map($key, $assigned), true);

        return array_values(array_filter($available, static fn($path): bool => !isset($used[$key($path)])));
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
        } elseif ($type === JemModelHousekeeping::CATEGORIES) {
            $query = 'SELECT CONCAT_WS(' . $this->_db->quote('/') . ', NULLIF(image_path, '
                . $this->_db->quote('') . '), image)'
                . ' FROM #__jem_categories WHERE image <> ' . $this->_db->quote('');
        } else {
            $query = 'SELECT '.$this->map[$type]['field'].' FROM #__jem_'.$this->map[$type]['table'];
        }

        $this->_db->setQuery($query);
        $assigned = $this->_db->loadColumn();

        return array_merge((array) $assigned, $this->getLinkImages($type));
    }

    /**
     * Link images may live in the same folders as event, venue or category images.
     */
    private function getLinkImages($type): array
    {
        $folder = $this->map[$type]['folder'];
        $query = $this->_db->getQuery(true)
            ->select($this->_db->quoteName('params'))
            ->from($this->_db->quoteName('#__jem_links'))
            ->where($this->_db->quoteName('params') . ' LIKE ' . $this->_db->quote('%"image"%'));
        $this->_db->setQuery($query);

        return self::extractLinkImages((array) $this->_db->loadColumn(), $folder);
    }

    /** Return folder-relative paths from stored link parameters. */
    private static function extractLinkImages(array $rows, string $folder): array
    {
        $prefix = 'images/jem/' . $folder . '/';
        $images = array();

        foreach ($rows as $json) {
            $params = json_decode((string) $json, true);
            $image = is_array($params) ? trim((string) ($params['image'] ?? '')) : '';
            $fragment = strpos($image, '#joomlaImage://');
            if ($fragment !== false) {
                $image = substr($image, 0, $fragment);
            }
            $image = rawurldecode(str_replace('\\', '/', $image));
            $image = ltrim($image, '/');

            if (str_starts_with($image, $prefix) && strlen($image) > strlen($prefix)) {
                $images[] = substr($image, strlen($prefix));
            }
        }

        return array_values(array_unique($images));
    }

    /** Resolve a file only if its real path remains inside the requested folder. */
    private static function containedExistingFile(string $basePath, string $relative): ?string
    {
        if ($relative === '' || str_contains($relative, "\0")) {
            return null;
        }

        $base = realpath($basePath);
        $file = realpath($basePath . '/' . $relative);
        if ($base === false || $file === false || !is_file($file)) {
            return null;
        }

        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
        $normalised = str_replace('\\', '/', $file);
        $expected = str_replace('\\', '/', Path::clean($base . $relative));
        if (PHP_OS_FAMILY === 'Windows') {
            $base = strtolower($base);
            $normalised = strtolower($normalised);
            $expected = strtolower($expected);
        }

        return str_starts_with($normalised, $base) && $normalised === $expected ? $file : null;
    }

    private static function isImageCandidate(string $relative): bool
    {
        $name = basename($relative);

        return $name !== '' && $name[0] !== '.'
            && (bool) preg_match('/\.(?:bmp|gif|jpe|jpeg|jfif|jpg|png|webp)$/iD', $name);
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
                if (self::isImageCandidate($relative)
                    && self::containedExistingFile($basePath, $relative) !== null) {
                    $images[] = $relative;
                }
            }
        }

        return $images;
    }
}
?>
