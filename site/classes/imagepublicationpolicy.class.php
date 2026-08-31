<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

require_once __DIR__ . '/imageprofilepolicy.class.php';
require_once __DIR__ . '/eventimagepath.class.php';
require_once __DIR__ . '/venueimagepath.class.php';
require_once __DIR__ . '/categoryimagepath.class.php';

/**
 * Enforces optional image requirements at the publication boundary.
 */
final class JemImagePublicationPolicy
{
    /**
     * Add the client-side publication guard to an event, venue or category form.
     * The table validation remains authoritative; this only improves feedback.
     */
    public static function configureEditingForm($form, string $entity, $settings): void
    {
        $definitions = array(
            'event' => array(
                array(JemImageProfilePolicy::EVENT_INTRO, array('jform_datimage', 'jform_datimage_image'), 'jform_userfile', 'removeimage'),
                array(JemImageProfilePolicy::EVENT_FULL, array('jform_fullimage', 'jform_fullimage_image'), 'jform_fulluserfile', 'removefullimage'),
            ),
            'venue' => array(
                array(JemImageProfilePolicy::VENUE, array('jform_locimage', 'jform_locimage_image', 'locimage'), 'jform_userfile', 'removeimage'),
            ),
            'category' => array(
                array(JemImageProfilePolicy::CATEGORY, array('jform_image', 'jform_image_image'), 'jform_userfile', 'removeimage'),
            ),
        );

        if (!isset($definitions[$entity])) {
            return;
        }

        $rules = array();
        foreach ($definitions[$entity] as [$profile, $selectionIds, $uploadId, $removeId]) {
            if (!JemImageProfilePolicy::isRequired($settings, $profile)) {
                continue;
            }

            $rules[] = array(
                'profile' => $profile,
                'selectionIds' => $selectionIds,
                'uploadId' => $uploadId,
                'removeId' => $removeId,
                'label' => Text::_(self::profileLabelKey($profile)),
            );
        }

        if (!$rules) {
            return;
        }

        $document = Factory::getApplication()->getDocument();
        $document->addScriptOptions('jem.imagePublication', array(
            'publishId' => 'jform_published',
            'rules' => $rules,
            'requiredText' => Text::_('COM_JEM_IMAGE_REQUIRED_FOR_PUBLICATION'),
            'message' => Text::_('COM_JEM_IMAGE_REQUIRED_PUBLISH_FORM_ERROR'),
        ));
        $document->getWebAssetManager()
            ->registerScript('jem.image-publication', 'com_jem/image-publication.js', array('version' => 'auto'), array('defer' => true))
            ->useScript('jem.image-publication');
    }

    /**
     * @return array<int, string> Missing profile identifiers.
     */
    public static function missingForRecord(string $entity, $record, $settings): array
    {
        if ((int) self::value($record, 'published', 0) !== 1) {
            return array();
        }

        switch ($entity) {
            case 'event':
                $missing = array();
                if (JemImageProfilePolicy::isRequired($settings, JemImageProfilePolicy::EVENT_INTRO)
                    && !self::eventImageExists($record, 'datimage')) {
                    $missing[] = JemImageProfilePolicy::EVENT_INTRO;
                }
                if (JemImageProfilePolicy::isRequired($settings, JemImageProfilePolicy::EVENT_FULL)
                    && !self::eventImageExists($record, 'fullimage')) {
                    $missing[] = JemImageProfilePolicy::EVENT_FULL;
                }

                return $missing;

            case 'venue':
                return JemImageProfilePolicy::isRequired($settings, JemImageProfilePolicy::VENUE)
                    && !self::venueImageExists($record)
                    ? array(JemImageProfilePolicy::VENUE)
                    : array();

            case 'category':
                return JemImageProfilePolicy::isRequired($settings, JemImageProfilePolicy::CATEGORY)
                    && !self::categoryImageExists($record)
                    ? array(JemImageProfilePolicy::CATEGORY)
                    : array();
        }

        return array();
    }

    /**
     * Validate records before a direct list-view publish action.
     *
     * @return array<int, array{title: string, missing: array<int, string>}>
     */
    public static function invalidPublishRecords(string $entity, array $ids, $settings): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return array();
        }

        $definitions = array(
            'event' => array('#__jem_events', 'title', array('id', 'title', 'published', 'datimage', 'fullimage', 'image_path')),
            'venue' => array('#__jem_venues', 'venue', array('id', 'venue', 'published', 'locimage', 'image_path')),
            'category' => array('#__jem_categories', 'catname', array('id', 'catname', 'published', 'image', 'image_path')),
        );
        if (!isset($definitions[$entity])) {
            return array();
        }

        [$table, $titleField, $columns] = $definitions[$entity];
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName($columns))
            ->from($db->quoteName($table))
            ->whereIn($db->quoteName('id'), $ids);
        $db->setQuery($query);
        $invalid = array();

        foreach ((array) $db->loadObjectList() as $record) {
            $record->published = 1;
            $missing = self::missingForRecord($entity, $record, $settings);
            if ($missing) {
                $invalid[] = array(
                    'title' => (string) ($record->$titleField ?? ('#' . (int) $record->id)),
                    'missing' => $missing,
                );
            }
        }

        return $invalid;
    }

    public static function profileLabelKey(string $profile): string
    {
        $labels = array(
            JemImageProfilePolicy::EVENT_INTRO => 'COM_JEM_IMAGE_PROFILE_EVENT_INTRO',
            JemImageProfilePolicy::EVENT_FULL => 'COM_JEM_IMAGE_PROFILE_EVENT_FULL',
            JemImageProfilePolicy::VENUE => 'COM_JEM_IMAGE_PROFILE_VENUE',
            JemImageProfilePolicy::CATEGORY => 'COM_JEM_IMAGE_PROFILE_CATEGORY',
        );

        return $labels[$profile] ?? 'COM_JEM_IMAGE';
    }

    /**
     * Build a concise error without exposing file-system details.
     */
    public static function recordFailureMessage(string $title, array $missing): string
    {
        $labels = array_map(
            static fn(string $profile): string => Text::_(self::profileLabelKey($profile)),
            $missing
        );

        return Text::sprintf(
            'COM_JEM_IMAGE_REQUIRED_PUBLISH_BLOCKED',
            $title,
            implode(', ', $labels)
        );
    }

    /**
     * Build a bulk-publication error for all rejected records.
     */
    public static function failureMessage(array $invalid): string
    {
        $details = array();
        foreach ($invalid as $record) {
            $labels = array_map(
                static fn(string $profile): string => Text::_(self::profileLabelKey($profile)),
                (array) ($record['missing'] ?? array())
            );
            $details[] = (string) ($record['title'] ?? '') . ': ' . implode(', ', $labels);
        }

        return Text::sprintf('COM_JEM_IMAGE_REQUIRED_PUBLISH_LIST_BLOCKED', implode('; ', $details));
    }

    private static function eventImageExists($record, string $field): bool
    {
        $filename = self::safeFilename(self::value($record, $field, ''));
        if ($filename === '') {
            return false;
        }

        $folder = JemEventImagePath::normaliseRelativeFolder(self::value($record, 'image_path', ''));
        $path = Path::clean(JPATH_SITE . '/' . JemEventImagePath::imagePath($folder, $filename));

        if (is_file($path)) {
            return true;
        }

        // Existing event images are moved to a newly calculated event folder
        // after the row is stored. During that save, accept the same file from
        // the currently persisted folder so the normal relocation can finish.
        $recordId = (int) self::value($record, 'id', 0);
        if ($recordId < 1) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array($field, 'image_path')))
            ->from($db->quoteName('#__jem_events'))
            ->where($db->quoteName('id') . ' = ' . $recordId);
        $db->setQuery($query);
        $stored = (array) ($db->loadAssoc() ?: array());

        if (self::safeFilename($stored[$field] ?? '') !== $filename) {
            return false;
        }

        $storedFolder = JemEventImagePath::normaliseRelativeFolder($stored['image_path'] ?? '');

        return is_file(Path::clean(JPATH_SITE . '/' . JemEventImagePath::imagePath($storedFolder, $filename)));
    }

    private static function venueImageExists($record): bool
    {
        $filename = self::safeFilename(self::value($record, 'locimage', ''));
        if ($filename === '') {
            return false;
        }

        $folder = JemVenueImagePath::normaliseRelativeFolder(self::value($record, 'image_path', ''));
        $path = Path::clean(JPATH_SITE . '/' . JemVenueImagePath::imagePath($folder, $filename));

        return is_file($path);
    }

    private static function categoryImageExists($record): bool
    {
        $filename = self::safeFilename(self::value($record, 'image', ''));
        $folder = JemCategoryImagePath::normaliseRelativeFolder(self::value($record, 'image_path', ''));

        return $filename !== ''
            && is_file(Path::clean(JPATH_SITE . '/' . JemCategoryImagePath::imagePath($folder, $filename)));
    }

    private static function safeFilename($value): string
    {
        $value = trim((string) $value);
        $safe = File::makeSafe($value);

        return $safe !== '' && $safe === $value ? $safe : '';
    }

    private static function value($record, string $key, $default)
    {
        if (is_object($record) && isset($record->$key)) {
            return $record->$key;
        }

        if (is_array($record) && array_key_exists($key, $record)) {
            return $record[$key];
        }

        return $default;
    }
}
