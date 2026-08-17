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
use Joomla\String\StringHelper;

if (defined('JPATH_SITE') && is_file(JPATH_SITE . '/components/com_jem/classes/venueimagepath.class.php')) {
    require_once JPATH_SITE . '/components/com_jem/classes/venueimagepath.class.php';
}

/**
 * Versioned venue-capacity configuration used by Point 4D.
 *
 * Phase 4D exposes an optional default profile containing one or more spaces. Each
 * space selects one current immutable layout revision. The relational model
 * already supports additional profiles and assigned seating later without
 * changing event snapshots or stable capacity-area codes.
 */
class JemVenueCapacityService
{
    public const DEFAULT_PROFILE_CODE = 'default';
    public const DEFAULT_PROFILE_NAME = 'Main';
    public const ALLOCATION_QUANTITY = 'quantity';
    public const DEFAULT_SPACE_COLOR = '#2F6F9F';
    public const DEFAULT_LAYOUT_COLOR = '#B78324';
    public const DEFAULT_AREA_COLOR = '#8A6D3B';

    /**
     * Find the default profile without creating one.
     */
    private static function findDefaultProfileId(int $venueId): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jem_venue_capacity_profiles'))
            ->where($db->quoteName('venue_id') . ' = ' . $venueId)
            ->where($db->quoteName('code') . ' = ' . $db->quote(self::DEFAULT_PROFILE_CODE));
        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Create the default profile only after an explicit profile submission.
     */
    public static function ensureDefaultProfile(int $venueId): int
    {
        if ($venueId < 1) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_SAVED_VENUE_REQUIRED'));
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $profileId = self::findDefaultProfileId($venueId);

        if ($profileId > 0) {
            return $profileId;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('capacity'))
                ->from($db->quoteName('#__jem_venues'))
                ->where($db->quoteName('id') . ' = ' . $venueId)
        );
        $venueCapacity = (int) $db->loadResult();

        $now = Factory::getDate()->toSql();
        $identity = Factory::getApplication()->getIdentity();
        $userId = (int) ($identity->id ?? 0);
        $profile = (object) array(
            'venue_id'    => $venueId,
            'code'        => self::DEFAULT_PROFILE_CODE,
            'name'        => self::DEFAULT_PROFILE_NAME,
            'revision'    => 1,
            'capacity'    => $venueCapacity,
            'is_default'  => 1,
            'published'   => 1,
            'created'     => $now,
            'created_by'  => $userId,
        );

        try {
            $db->insertObject('#__jem_venue_capacity_profiles', $profile, 'id');
        } catch (RuntimeException $e) {
            // A concurrent save may have created the unique venue/code row.
            $profile->id = self::findDefaultProfileId($venueId);
            if (empty($profile->id)) {
                throw $e;
            }
        }

        return (int) $profile->id;
    }

    /**
     * Load the current default profile and every selected space/layout.
     */
    public static function getDefaultConfiguration(int $venueId): array
    {
        if ($venueId < 1) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_SAVED_VENUE_REQUIRED'));
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $profileId = self::findDefaultProfileId($venueId);
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('country'))
                ->from($db->quoteName('#__jem_venues'))
                ->where($db->quoteName('id') . ' = ' . $venueId)
        );
        $countryCode = strtoupper(trim((string) $db->loadResult()));
        $configuration = array(
            'profile_id'       => $profileId,
            'profile_code'     => $profileId > 0 ? self::DEFAULT_PROFILE_CODE : '',
            'profile_name'     => $profileId > 0 ? self::DEFAULT_PROFILE_NAME : '',
            'profile_revision' => $profileId > 0 ? 1 : 0,
            'profile_capacity' => 0,
            'country_code'     => $countryCode,
            'spaces'           => array(),
        );

        if ($profileId < 1) {
            return $configuration;
        }

        $query = $db->getQuery(true)
            ->select(array(
                'p.id AS profile_id', 'p.code AS profile_code', 'p.name AS profile_name',
                'p.image AS profile_image', 'p.image_alt AS profile_image_alt',
                'p.revision AS profile_revision', 'p.capacity AS profile_capacity',
            ))
            ->from($db->quoteName('#__jem_venue_capacity_profiles', 'p'))
            ->where('p.id = ' . $profileId);
        $db->setQuery($query);
        $configuration = array_merge($configuration, $db->loadAssoc() ?: array());

        $query = $db->getQuery(true)
            ->select(array(
                'ps.id AS assignment_id', 'ps.ordering',
                's.id AS space_id', 's.code AS space_code', 's.name AS space_name', 's.color AS space_color',
                's.description AS space_description', 's.image AS space_image', 's.image_alt AS space_image_alt',
                'l.id AS layout_id', 'l.code AS layout_code', 'l.name AS layout_name',
                'l.revision AS layout_revision', 'l.capacity AS layout_capacity', 'l.color AS layout_color',
                'l.image AS layout_image', 'l.image_alt AS layout_image_alt',
            ))
            ->from($db->quoteName('#__jem_venue_profile_spaces', 'ps'))
            ->join('INNER', $db->quoteName('#__jem_venue_spaces', 's') . ' ON s.id = ps.venue_space_id')
            ->join('INNER', $db->quoteName('#__jem_venue_layouts', 'l') . ' ON l.id = ps.venue_layout_id')
            ->where('ps.venue_profile_id = ' . $profileId)
            ->order('ps.ordering ASC, ps.id ASC');
        $db->setQuery($query);

        foreach ((array) $db->loadAssocList() as $space) {
            $space['areas'] = array();
            $query = $db->getQuery(true)
                ->select(array('id', 'code', 'name', 'image', 'image_alt', 'color', 'description', 'capacity', 'allocation_mode', 'published', 'ordering'))
                ->from($db->quoteName('#__jem_venue_capacity_areas'))
                ->where($db->quoteName('venue_layout_id') . ' = ' . (int) $space['layout_id'])
                ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            $space['areas'] = (array) $db->loadAssocList();
            $configuration['spaces'][] = $space;
        }

        return $configuration;
    }

    /**
     * Add the current capacity configuration to a venue form item.
     */
    public static function populateFormItem(object $item): void
    {
        if (empty($item->id)) {
            $item->capacity_configuration_submitted = 0;
            $item->capacity_profile_id = 0;
            $item->capacity_profile_name = self::DEFAULT_PROFILE_NAME;
            $item->capacity_profile_revision = 1;
            $item->capacity_profile_capacity = (int) ($item->capacity ?? 0);
            $item->capacity_spaces = array();
            $item->capacity_configuration_json = json_encode(array('spaces' => array()));

            return;
        }

        $configuration = self::getDefaultConfiguration((int) $item->id);
        $item->capacity_configuration_submitted = (int) $configuration['profile_id'] > 0 ? 1 : 0;
        $item->capacity_profile_id = (int) $configuration['profile_id'];
        $item->capacity_profile_name = (string) ($configuration['profile_name'] ?: self::DEFAULT_PROFILE_NAME);
        $item->capacity_profile_revision = max(1, (int) $configuration['profile_revision']);
        $item->capacity_profile_capacity = (int) $configuration['profile_capacity'];
        $item->capacity_spaces = $configuration['spaces'];
        $item->capacity_configuration_json = json_encode(
            array('spaces' => $configuration['spaces']),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Validate and canonicalise the multi-space profile editor payload.
     */
    public static function normaliseFormData(array $data, int $profileCapacity, ?int $venueCapacity = null): array
    {
        if ($venueCapacity !== null && $profileCapacity > $venueCapacity) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_PROFILE_PHYSICAL_LIMIT'));
        }

        $normalised = array(
            'profile_capacity' => $profileCapacity,
            'spaces' => array(),
        );
        $spaceCodes = array();
        $combinedLayoutCapacity = 0;

        foreach ((array) ($data['spaces'] ?? array()) as $space) {
            if (!is_array($space)) {
                continue;
            }
            if (!self::spaceHasConfiguration($space)) {
                continue;
            }

            $spaceData = self::normaliseSpace($space);
            if (isset($spaceCodes[$spaceData['space_code']])) {
                throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_SPACE_CODE_UNIQUE'));
            }

            $spaceCodes[$spaceData['space_code']] = true;
            $combinedLayoutCapacity += $spaceData['layout_capacity'];
            $normalised['spaces'][] = $spaceData;
        }

        if ($combinedLayoutCapacity > $profileCapacity) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_PROFILE_LIMIT'));
        }

        return $normalised;
    }

    /**
     * Normalise the editable display name of the default profile.
     */
    public static function normaliseProfileName(?string $name): string
    {
        $name = trim((string) $name);

        return $name === ''
            ? self::DEFAULT_PROFILE_NAME
            : StringHelper::substr($name, 0, 255);
    }

    private static function spaceHasConfiguration(array $space): bool
    {
        foreach (array('space_name', 'space_code', 'space_description', 'layout_name', 'layout_code') as $field) {
            if (trim((string) ($space[$field] ?? '')) !== '') {
                return true;
            }
        }
        if ((int) ($space['layout_capacity'] ?? 0) > 0) {
            return true;
        }
        foreach ((array) ($space['areas'] ?? array()) as $area) {
            if (!is_array($area)) {
                continue;
            }
            foreach (array('name', 'code', 'description') as $field) {
                if (trim((string) ($area[$field] ?? '')) !== '') {
                    return true;
                }
            }
            if ((int) ($area['capacity'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Save profile metadata and create immutable layout revisions only when
     * the effective physical capacity configuration changed.
     */
    public static function saveDefaultConfiguration(
        int $venueId,
        array $configuration,
        ?string $profileName = null
    ): array
    {
        $current = self::getDefaultConfiguration($venueId);
        if ((int) $current['profile_id'] < 1) {
            self::ensureDefaultProfile($venueId);
            $current = self::getDefaultConfiguration($venueId);
        }
        $profileName = self::normaliseProfileName($profileName ?? (string) $current['profile_name']);
        $profileCapacity = (int) ($configuration['profile_capacity'] ?? $current['profile_capacity']);
        $configuration['profile_capacity'] = $profileCapacity;
        $currentBySpaceId = array();
        foreach ($current['spaces'] as $space) {
            $currentBySpaceId[(int) $space['space_id']] = $space;
        }
        foreach ($configuration['spaces'] as &$space) {
            $spaceId = (int) ($space['space_id'] ?? 0);
            if ($spaceId > 0 && isset($currentBySpaceId[$spaceId])) {
                // Physical spaces and their immutable layout revision chains keep
                // their aliases for their whole lifetime.
                $space['space_code'] = (string) $currentBySpaceId[$spaceId]['space_code'];
                $space['layout_code'] = (string) $currentBySpaceId[$spaceId]['layout_code'];
            }
        }
        unset($space);

        $configurationChanged = self::configurationFingerprint($current)
            !== self::configurationFingerprint($configuration);
        $profileNameChanged = $profileName !== (string) $current['profile_name'];

        if (!$configurationChanged && !$profileNameChanged) {
            return $current;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $now = Factory::getDate()->toSql();
        $identity = Factory::getApplication()->getIdentity();
        $userId = (int) ($identity->id ?? 0);
        $db->transactionStart();

        try {
            $profileId = (int) $current['profile_id'];
            $keptAssignmentIds = array();

            foreach ($configurationChanged ? $configuration['spaces'] : array() as $ordering => $spaceData) {
                $spaceId = (int) ($spaceData['space_id'] ?? 0);
                $currentSpace = $spaceId > 0 ? ($currentBySpaceId[$spaceId] ?? null) : null;
                if ($spaceId > 0 && $currentSpace === null) {
                    throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_SPACE_OWNERSHIP'));
                }

                if ($currentSpace !== null) {
                    $spaceRow = (object) array(
                        'id'          => $spaceId,
                        'name'        => $spaceData['space_name'],
                        'color'       => $spaceData['space_color'],
                        'description' => $spaceData['space_description'],
                        'modified'    => $now,
                        'modified_by' => $userId,
                    );
                    $db->updateObject('#__jem_venue_spaces', $spaceRow, 'id', true);
                } else {
                    $spaceRow = (object) array(
                        'venue_id'    => $venueId,
                        'code'        => $spaceData['space_code'],
                        'name'        => $spaceData['space_name'],
                        'color'       => $spaceData['space_color'],
                        'description' => $spaceData['space_description'],
                        'published'   => 1,
                        'ordering'    => (int) $ordering,
                        'created'     => $now,
                        'created_by'  => $userId,
                    );
                    $db->insertObject('#__jem_venue_spaces', $spaceRow, 'id');
                    $spaceId = (int) $spaceRow->id;
                }

                $layoutUnchanged = $currentSpace !== null
                    && self::layoutFingerprint($currentSpace) === self::layoutFingerprint($spaceData);
                if ($layoutUnchanged) {
                    $layoutId = (int) $currentSpace['layout_id'];
                } else {
                    $query = $db->getQuery(true)
                        ->select('MAX(' . $db->quoteName('revision') . ')')
                        ->from($db->quoteName('#__jem_venue_layouts'))
                        ->where($db->quoteName('venue_space_id') . ' = ' . $spaceId)
                        ->where($db->quoteName('code') . ' = ' . $db->quote($spaceData['layout_code']));
                    $db->setQuery($query);
                    $layoutRevision = (int) $db->loadResult() + 1;
                    $layout = (object) array(
                        'venue_space_id' => $spaceId,
                        'code'           => $spaceData['layout_code'],
                        'name'           => $spaceData['layout_name'],
                        'revision'       => $layoutRevision,
                        'capacity'       => $spaceData['layout_capacity'],
                        'color'          => $spaceData['layout_color'],
                        'published'      => 1,
                        'ordering'       => 0,
                        'created'        => $now,
                        'created_by'     => $userId,
                    );
                    $db->insertObject('#__jem_venue_layouts', $layout, 'id');
                    $layoutId = (int) $layout->id;

                    foreach ($spaceData['areas'] as $areaOrdering => $areaData) {
                        $area = (object) array_merge($areaData, array(
                            'venue_layout_id' => $layoutId,
                            'ordering'        => (int) $areaOrdering,
                            'created'         => $now,
                            'created_by'      => $userId,
                        ));
                        unset($area->id);
                        $db->insertObject('#__jem_venue_capacity_areas', $area, 'id');
                    }
                }

                $assignmentId = (int) ($currentSpace['assignment_id'] ?? 0);
                if ($assignmentId > 0) {
                    $assignment = (object) array(
                        'id'              => $assignmentId,
                        'venue_layout_id' => $layoutId,
                        'ordering'         => (int) $ordering,
                    );
                    $db->updateObject('#__jem_venue_profile_spaces', $assignment, 'id');
                } else {
                    $assignment = (object) array(
                        'venue_profile_id' => $profileId,
                        'venue_space_id'   => $spaceId,
                        'venue_layout_id'  => $layoutId,
                        'ordering'         => (int) $ordering,
                    );
                    $db->insertObject('#__jem_venue_profile_spaces', $assignment, 'id');
                    $assignmentId = (int) $assignment->id;
                }
                $keptAssignmentIds[] = $assignmentId;
            }

            if ($configurationChanged) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__jem_venue_profile_spaces'))
                    ->where($db->quoteName('venue_profile_id') . ' = ' . $profileId);
                if ($keptAssignmentIds) {
                    $query->where($db->quoteName('id') . ' NOT IN (' . implode(',', array_map('intval', $keptAssignmentIds)) . ')');
                }
                $db->setQuery($query)->execute();
            }

            $profile = (object) array(
                'id'          => $profileId,
                'name'        => $profileName,
                'revision'    => (int) $current['profile_revision'] + ($configurationChanged ? 1 : 0),
                'capacity'    => $profileCapacity,
                'modified'    => $now,
                'modified_by' => $userId,
            );
            $db->updateObject('#__jem_venue_capacity_profiles', $profile, 'id');
            $db->transactionCommit();
        } catch (Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }

        return self::getDefaultConfiguration($venueId);
    }

    /**
     * Build a non-executable, authoritative event snapshot from structured data.
     */
    public static function buildEventSnapshot(int $venueId, ?array $assignmentIds = null): array
    {
        $configuration = self::getDefaultConfiguration($venueId);
        if (empty($configuration['spaces'])) {
            throw new RuntimeException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_LAYOUT_REQUIRED'));
        }

        if ($assignmentIds !== null) {
            $assignmentIds = array_values(array_unique(array_filter(
                array_map('intval', $assignmentIds),
                static fn (int $id): bool => $id > 0
            )));
            if (!$assignmentIds) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_CONFIGURATION_SELECTION'));
            }

            $available = array_column($configuration['spaces'], null, 'assignment_id');
            foreach ($assignmentIds as $assignmentId) {
                if (!isset($available[$assignmentId])) {
                    throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_CONFIGURATION_SELECTION'));
                }
            }
            $configuration['spaces'] = array_values(array_filter(
                $configuration['spaces'],
                static fn (array $space): bool => in_array((int) $space['assignment_id'], $assignmentIds, true)
            ));
        }

        $selectedCapacity = array_sum(array_map(
            static fn (array $space): int => (int) $space['layout_capacity'],
            $configuration['spaces']
        ));

        return array(
            'schema'             => 'jem-venue-capacity/v1',
            'venue_id'           => $venueId,
            'country_code'       => (string) $configuration['country_code'],
            'profile_id'         => (int) $configuration['profile_id'],
            'profile_code'       => (string) $configuration['profile_code'],
            'profile_name'       => (string) $configuration['profile_name'],
            'profile_revision'   => (int) $configuration['profile_revision'],
            'profile_capacity'   => (int) $configuration['profile_capacity'],
            'selected_capacity'  => $selectedCapacity,
            'spaces'             => array_map(static function (array $space): array {
                return array(
                    'profile_space_id' => (int) $space['assignment_id'],
                    'id'          => (int) $space['space_id'],
                    'code'        => (string) $space['space_code'],
                    'name'        => (string) $space['space_name'],
                    'color'       => (string) $space['space_color'],
                    'description' => (string) $space['space_description'],
                    'layout'      => array(
                        'id'       => (int) $space['layout_id'],
                        'code'     => (string) $space['layout_code'],
                        'name'     => (string) $space['layout_name'],
                        'revision' => (int) $space['layout_revision'],
                        'capacity' => (int) $space['layout_capacity'],
                        'color'    => (string) $space['layout_color'],
                    ),
                    'capacity_areas' => array_map(static function (array $area): array {
                        return array(
                            'id'              => (int) $area['id'],
                            'code'            => (string) $area['code'],
                            'name'            => (string) $area['name'],
                            'color'           => (string) $area['color'],
                            'description'     => (string) $area['description'],
                            'capacity'        => (int) $area['capacity'],
                            'allocation_mode' => (string) $area['allocation_mode'],
                            'published'       => (int) $area['published'],
                            'ordering'        => (int) $area['ordering'],
                        );
                    }, $space['areas']),
                );
            }, $configuration['spaces']),
        );
    }

    /**
     * Store mutable Space and Layout media after their stable identifiers are
     * known. Media changes never create a physical layout revision.
     */
    public static function saveConfigurationMedia(
        int $venueId,
        array $savedConfiguration,
        array $submittedConfiguration,
        array $spaceFiles = array(),
        array $layoutFiles = array()
    ): void {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $settings = JemHelper::config();
        $savedSpaces = array_values((array) ($savedConfiguration['spaces'] ?? array()));
        $submittedSpaces = array_values((array) ($submittedConfiguration['spaces'] ?? array()));

        foreach ($savedSpaces as $index => $savedSpace) {
            $submitted = (array) ($submittedSpaces[$index] ?? array());
            $spaceId = (int) ($savedSpace['space_id'] ?? 0);
            $layoutId = (int) ($savedSpace['layout_id'] ?? 0);
            if ($spaceId < 1 || $layoutId < 1) {
                continue;
            }

            $spaceImage = self::storeMediaFile(
                JemVenueImagePath::spaceFolder($venueId, $spaceId),
                (array) ($spaceFiles[$index] ?? array()),
                (string) ($savedSpace['space_image'] ?? $submitted['space_image'] ?? ''),
                !empty($submitted['space_image_remove']),
                $settings
            );
            $db->updateObject('#__jem_venue_spaces', (object) array(
                'id' => $spaceId,
                'image' => $spaceImage,
                'image_alt' => $spaceImage !== '' ? self::normaliseAltText($submitted['space_image_alt'] ?? '') : '',
            ), 'id', true);

            $oldLayoutId = (int) ($submitted['layout_id'] ?? 0);
            $layoutImage = File::makeSafe((string) ($submitted['layout_image'] ?? ''));
            if ($oldLayoutId === $layoutId) {
                $layoutImage = File::makeSafe((string) ($savedSpace['layout_image'] ?? $layoutImage));
            }
            $layoutFile = (array) ($layoutFiles[$index] ?? array());
            if (empty($layoutFile['name']) && empty($submitted['layout_image_remove'])
                && $layoutImage !== '' && $oldLayoutId > 0 && $oldLayoutId !== $layoutId) {
                $sourceFolder = JemVenueImagePath::layoutFolder($venueId, $spaceId, $oldLayoutId);
                $targetFolder = JemVenueImagePath::layoutFolder($venueId, $spaceId, $layoutId);
                $sourcePath = Path::clean(JPATH_SITE . '/' . JemVenueImagePath::imagePath($sourceFolder, $layoutImage));
                if (File::exists($sourcePath)) {
                    JemVenueImagePath::relocateImages($sourceFolder, $targetFolder, array($layoutImage), $settings, false);
                } else {
                    $layoutImage = '';
                }
            }
            $layoutImage = self::storeMediaFile(
                JemVenueImagePath::layoutFolder($venueId, $spaceId, $layoutId),
                $layoutFile,
                $layoutImage,
                !empty($submitted['layout_image_remove']),
                $settings
            );
            $db->updateObject('#__jem_venue_layouts', (object) array(
                'id' => $layoutId,
                'image' => $layoutImage,
                'image_alt' => $layoutImage !== '' ? self::normaliseAltText($submitted['layout_image_alt'] ?? '') : '',
            ), 'id', true);
        }
    }

    private static function storeMediaFile(string $folder, array $file, string $current, bool $remove, object $settings): string
    {
        $current = File::makeSafe($current);
        if ($remove) {
            self::deleteMediaFile($folder, $current);
            $current = '';
        }

        if (empty($file['name'])) {
            return $current;
        }
        if (!empty($file['error']) || empty($file['tmp_name']) || JemImage::check($file, $settings) === false) {
            throw new RuntimeException(Text::_('COM_JEM_UPLOAD_FAILED'));
        }
        if (!JemVenueImagePath::ensureFolders($folder)) {
            throw new RuntimeException(Text::_('COM_JEM_UPLOAD_FAILED'));
        }

        $targetFolder = JemVenueImagePath::absoluteImageFolder($folder);
        $filename = JemImage::sanitize($targetFolder, (string) $file['name']);
        $target = Path::clean($targetFolder . $filename);
        if (!File::upload((string) $file['tmp_name'], $target)) {
            throw new RuntimeException(Text::_('COM_JEM_UPLOAD_FAILED'));
        }

        JemVenueImagePath::createThumbnail($folder, $filename, $target, $settings);
        if ($current !== '' && $current !== $filename) {
            self::deleteMediaFile($folder, $current);
        }

        return $filename;
    }

    private static function deleteMediaFile(string $folder, string $filename): void
    {
        $filename = File::makeSafe($filename);
        if ($filename === '') {
            return;
        }
        foreach (array(
            JemVenueImagePath::imagePath($folder, $filename),
            JemVenueImagePath::thumbPath($folder, $filename),
        ) as $relative) {
            $path = Path::clean(JPATH_SITE . '/' . $relative);
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    private static function normaliseAltText($value): string
    {
        return StringHelper::substr(trim(strip_tags((string) $value)), 0, 255);
    }

    private static function normaliseImageFilename($value): string
    {
        $filename = basename(str_replace('\\', '/', (string) $value));
        if (class_exists(File::class)) {
            $filename = File::makeSafe($filename);
        } else {
            $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename);
        }

        return StringHelper::substr(trim((string) $filename, '.- '), 0, 100);
    }

    /**
     * Build the small preset list used by the event editor. Complex venues
     * keep an explicit Custom option instead of flooding the administrator
     * with every possible space combination.
     */
    public static function getEventConfigurationOptions(int $venueId, int $combinationLimit = 12): array
    {
        $configuration = self::getDefaultConfiguration($venueId);
        $spaces = array_values((array) ($configuration['spaces'] ?? array()));
        if (!$spaces) {
            return array(
                'profile' => $configuration,
                'assignments' => array(),
                'options' => array(),
                'custom_required' => false,
            );
        }

        $assignments = array_map(static function (array $space): array {
            return array(
                'id' => (int) $space['assignment_id'],
                'space_id' => (int) $space['space_id'],
                'space_code' => (string) $space['space_code'],
                'space_name' => (string) $space['space_name'],
                'space_color' => (string) $space['space_color'],
                'layout_id' => (int) $space['layout_id'],
                'layout_code' => (string) $space['layout_code'],
                'layout_name' => (string) $space['layout_name'],
                'layout_color' => (string) $space['layout_color'],
                'layout_revision' => (int) $space['layout_revision'],
                'capacity' => (int) $space['layout_capacity'],
            );
        }, $spaces);

        $count = count($assignments);
        $combinationCount = $count >= 31 ? PHP_INT_MAX : (2 ** $count) - 1;
        $customRequired = $combinationCount > $combinationLimit;
        $sets = array();

        $allIds = array_column($assignments, 'id');
        $sets[] = $allIds;
        if ($customRequired) {
            foreach (array_slice($assignments, 0, max(0, $combinationLimit - 2)) as $assignment) {
                $sets[] = array((int) $assignment['id']);
            }
        } else {
            for ($size = 1; $size <= $count; $size++) {
                foreach (self::combinations($allIds, $size) as $ids) {
                    if ($ids !== $allIds) {
                        $sets[] = $ids;
                    }
                }
            }
        }

        $byId = array_column($assignments, null, 'id');
        $options = array();
        foreach ($sets as $ids) {
            sort($ids, SORT_NUMERIC);
            $selected = array_values(array_intersect_key($byId, array_flip($ids)));
            $capacity = array_sum(array_column($selected, 'capacity'));
            $entire = count($ids) === $count;
            if ($entire) {
                $label = Text::sprintf(
                    'COM_JEM_EVENT_VENUE_CONFIGURATION_ENTIRE',
                    (string) $configuration['profile_name'],
                    $count,
                    $capacity
                );
            } elseif (count($selected) === 1) {
                $label = Text::sprintf(
                    'COM_JEM_EVENT_VENUE_CONFIGURATION_SPACE',
                    (string) $selected[0]['space_name'],
                    (string) $selected[0]['layout_name'],
                    $capacity
                );
            } else {
                $label = Text::sprintf(
                    'COM_JEM_EVENT_VENUE_CONFIGURATION_COMBINATION',
                    implode(' + ', array_column($selected, 'space_name')),
                    $capacity
                );
            }
            $options[] = array(
                'key' => 'selection:' . implode(',', $ids),
                'label' => $label,
                'assignment_ids' => $ids,
                'capacity' => $capacity,
                'spaces' => $selected,
                'entire_profile' => $entire,
            );
        }

        return array(
            'profile' => $configuration,
            'assignments' => $assignments,
            'options' => $options,
            'custom_required' => $customRequired,
        );
    }

    private static function combinations(array $values, int $size, int $offset = 0, array $prefix = array()): array
    {
        if ($size === 0) {
            return array($prefix);
        }

        $sets = array();
        $limit = count($values) - $size;
        for ($index = $offset; $index <= $limit; $index++) {
            $sets = array_merge(
                $sets,
                self::combinations($values, $size - 1, $index + 1, array_merge($prefix, array($values[$index])))
            );
        }

        return $sets;
    }

    private static function normaliseCode($value): string
    {
        $code = strtolower(trim((string) $value));
        $code = preg_replace('/[^a-z0-9_-]+/', '-', $code);

        return trim(StringHelper::substr((string) $code, 0, 64), '-_');
    }

    private static function normaliseCapacity($value): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
        if ($value === false) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_INTEGER'));
        }

        return (int) $value;
    }

    private static function normaliseColor($value, string $default): string
    {
        $color = strtoupper(trim((string) $value));
        if ($color === '') {
            return $default;
        }
        if (preg_match('/^#[0-9A-F]{6}$/D', $color) !== 1) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_COLOR'));
        }

        return $color;
    }

    /**
     * Canonicalise one room, its selected layout and its quantity areas.
     */
    private static function normaliseSpace(array $space): array
    {
        $normalised = array(
            'space_id'          => (int) ($space['space_id'] ?? 0),
            'space_code'        => self::normaliseCode($space['space_code'] ?? ''),
            'space_name'        => trim((string) ($space['space_name'] ?? '')),
            'space_color'       => self::normaliseColor($space['space_color'] ?? '', self::DEFAULT_SPACE_COLOR),
            'space_description' => trim((string) ($space['space_description'] ?? '')),
            'space_image'       => self::normaliseImageFilename($space['space_image'] ?? ''),
            'space_image_alt'   => self::normaliseAltText($space['space_image_alt'] ?? ''),
            'space_image_remove'=> !empty($space['space_image_remove']) ? 1 : 0,
            'layout_id'         => (int) ($space['layout_id'] ?? 0),
            'layout_code'       => self::normaliseCode($space['layout_code'] ?? ''),
            'layout_name'       => trim((string) ($space['layout_name'] ?? '')),
            'layout_color'      => self::normaliseColor($space['layout_color'] ?? '', self::DEFAULT_LAYOUT_COLOR),
            'layout_capacity'   => self::normaliseCapacity($space['layout_capacity'] ?? 0),
            'layout_image'      => self::normaliseImageFilename($space['layout_image'] ?? ''),
            'layout_image_alt'  => self::normaliseAltText($space['layout_image_alt'] ?? ''),
            'layout_image_remove'=> !empty($space['layout_image_remove']) ? 1 : 0,
            'areas'             => array(),
        );

        if (StringHelper::strlen($normalised['space_name']) > 255 || StringHelper::strlen($normalised['layout_name']) > 255) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_NAME_LENGTH'));
        }

        $areaCodes = array();
        $areaTotal = 0;
        foreach ((array) ($space['areas'] ?? array()) as $area) {
            if (!is_array($area)) {
                continue;
            }

            $name = trim((string) ($area['name'] ?? ''));
            $code = self::normaliseCode($area['code'] ?? '');
            $description = trim((string) ($area['description'] ?? ''));
            $capacity = self::normaliseCapacity($area['capacity'] ?? 0);
            $published = !empty($area['published']) ? 1 : 0;
            if ($name === '' && $code === '' && $description === '' && $capacity === 0) {
                continue;
            }
            if ($name === '') {
                throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_AREA_NAME'));
            }
            if ($code === '') {
                $code = self::normaliseCode($name);
            }
            if ($code === '' || isset($areaCodes[$code])) {
                throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_AREA_CODE'));
            }
            if ($published && $capacity < 1) {
                throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_AREA_CAPACITY'));
            }

            $areaCodes[$code] = true;
            if ($published) {
                $areaTotal += $capacity;
            }
            $normalised['areas'][] = array(
                'id'              => (int) ($area['id'] ?? 0),
                'code'            => $code,
                'name'            => StringHelper::substr($name, 0, 255),
                'image'           => self::normaliseImageFilename($area['image'] ?? ''),
                'image_alt'       => self::normaliseAltText($area['image_alt'] ?? ''),
                'color'           => self::normaliseColor($area['color'] ?? '', self::DEFAULT_AREA_COLOR),
                'description'     => $description,
                'capacity'        => $capacity,
                'allocation_mode' => self::ALLOCATION_QUANTITY,
                'published'       => $published,
            );
        }

        if ($normalised['space_name'] === '' || $normalised['layout_name'] === '') {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_SPACE_LAYOUT'));
        }
        if ($normalised['space_code'] === '') {
            $normalised['space_code'] = self::normaliseCode($normalised['space_name']);
        }
        if ($normalised['layout_code'] === '') {
            $normalised['layout_code'] = self::normaliseCode($normalised['layout_name']);
        }
        if ($normalised['space_code'] === '' || $normalised['layout_code'] === '') {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_CODES'));
        }
        if ($normalised['layout_capacity'] === 0 && $areaTotal > 0) {
            $normalised['layout_capacity'] = $areaTotal;
        }
        if ($areaTotal > $normalised['layout_capacity']) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_AREA_TOTAL'));
        }

        return $normalised;
    }

    /**
     * Ignore row identifiers when deciding whether a new layout revision exists.
     */
    private static function layoutFingerprint(array $space): string
    {
        $areas = array();
        foreach ((array) ($space['areas'] ?? array()) as $area) {
            $areas[] = array(
                'code'            => (string) ($area['code'] ?? ''),
                'name'            => (string) ($area['name'] ?? ''),
                'color'           => (string) ($area['color'] ?? self::DEFAULT_AREA_COLOR),
                'description'     => (string) ($area['description'] ?? ''),
                'capacity'        => (int) ($area['capacity'] ?? 0),
                'allocation_mode' => (string) ($area['allocation_mode'] ?? self::ALLOCATION_QUANTITY),
                'published'       => (int) ($area['published'] ?? 0),
            );
        }

        return hash('sha256', json_encode(array(
            'layout_code'     => (string) ($space['layout_code'] ?? ''),
            'layout_name'     => (string) ($space['layout_name'] ?? ''),
            'layout_color'    => (string) ($space['layout_color'] ?? self::DEFAULT_LAYOUT_COLOR),
            'layout_capacity' => (int) ($space['layout_capacity'] ?? 0),
            'areas'           => $areas,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function configurationFingerprint(array $configuration): string
    {
        $spaces = array();
        foreach ((array) ($configuration['spaces'] ?? array()) as $space) {
            $spaces[] = array(
                'space_code'        => (string) ($space['space_code'] ?? ''),
                'space_name'        => (string) ($space['space_name'] ?? ''),
                'space_color'       => (string) ($space['space_color'] ?? self::DEFAULT_SPACE_COLOR),
                'space_description' => (string) ($space['space_description'] ?? ''),
                'layout'            => self::layoutFingerprint($space),
            );
        }

        return hash('sha256', json_encode(array(
            'profile_capacity' => (int) ($configuration['profile_capacity'] ?? 0),
            'spaces'           => $spaces,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
