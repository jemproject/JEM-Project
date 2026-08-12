<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\String\StringHelper;

/**
 * Versioned venue-capacity configuration used by Point 4D.
 *
 * Phase 4D exposes one default profile containing one or more spaces. Each
 * space selects one current immutable layout revision. The relational model
 * already supports additional profiles and assigned seating later without
 * changing event snapshots or stable capacity-area codes.
 */
class JemVenueCapacityService
{
    public const DEFAULT_PROFILE_CODE = 'default';
    public const DEFAULT_PROFILE_NAME = 'Default configuration';
    public const ALLOCATION_QUANTITY = 'quantity';

    /**
     * Create the mandatory default profile without inventing rooms or areas.
     */
    public static function ensureDefaultProfile(int $venueId): int
    {
        if ($venueId < 1) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_SAVED_VENUE_REQUIRED'));
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jem_venue_capacity_profiles'))
            ->where($db->quoteName('venue_id') . ' = ' . $venueId)
            ->where($db->quoteName('code') . ' = ' . $db->quote(self::DEFAULT_PROFILE_CODE));
        $db->setQuery($query);
        $profileId = (int) $db->loadResult();

        if ($profileId > 0) {
            return $profileId;
        }

        $now = Factory::getDate()->toSql();
        $identity = Factory::getApplication()->getIdentity();
        $userId = (int) ($identity->id ?? 0);
        $profile = (object) array(
            'venue_id'    => $venueId,
            'code'        => self::DEFAULT_PROFILE_CODE,
            'name'        => self::DEFAULT_PROFILE_NAME,
            'revision'    => 1,
            'is_default'  => 1,
            'published'   => 1,
            'created'     => $now,
            'created_by'  => $userId,
        );

        try {
            $db->insertObject('#__jem_venue_capacity_profiles', $profile, 'id');
        } catch (RuntimeException $e) {
            // A concurrent save may have created the unique venue/code row.
            $db->setQuery($query);
            $profile->id = (int) $db->loadResult();
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
        $db = Factory::getContainer()->get('DatabaseDriver');
        $profileId = self::ensureDefaultProfile($venueId);
        $query = $db->getQuery(true)
            ->select(array('id AS profile_id', 'code AS profile_code', 'name AS profile_name', 'revision AS profile_revision'))
            ->from($db->quoteName('#__jem_venue_capacity_profiles'))
            ->where($db->quoteName('id') . ' = ' . $profileId);
        $db->setQuery($query);
        $configuration = array_merge(array(
            'profile_id'       => $profileId,
            'profile_code'     => self::DEFAULT_PROFILE_CODE,
            'profile_name'     => self::DEFAULT_PROFILE_NAME,
            'profile_revision' => 1,
            'spaces'           => array(),
        ), $db->loadAssoc() ?: array());

        $query = $db->getQuery(true)
            ->select(array(
                'ps.id AS assignment_id', 'ps.ordering',
                's.id AS space_id', 's.code AS space_code', 's.name AS space_name',
                's.description AS space_description',
                'l.id AS layout_id', 'l.code AS layout_code', 'l.name AS layout_name',
                'l.revision AS layout_revision', 'l.capacity AS layout_capacity',
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
                ->select(array('id', 'code', 'name', 'description', 'capacity', 'allocation_mode', 'published', 'ordering'))
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
            $item->capacity_profile_name = self::DEFAULT_PROFILE_NAME;
            $item->capacity_profile_revision = 1;
            $item->capacity_spaces = array();
            $item->capacity_configuration_json = json_encode(array('spaces' => array()));

            return;
        }

        $configuration = self::getDefaultConfiguration((int) $item->id);
        $item->capacity_profile_id = (int) $configuration['profile_id'];
        $item->capacity_profile_name = (string) $configuration['profile_name'];
        $item->capacity_profile_revision = (int) $configuration['profile_revision'];
        $item->capacity_spaces = $configuration['spaces'];
        $item->capacity_configuration_json = json_encode(
            array('spaces' => $configuration['spaces']),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Validate and canonicalise the multi-space profile editor payload.
     */
    public static function normaliseFormData(array $data, int $venueCapacity): array
    {
        $normalised = array('spaces' => array());
        $spaceCodes = array();
        $profileCapacity = 0;

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
            $profileCapacity += $spaceData['layout_capacity'];
            $normalised['spaces'][] = $spaceData;
        }

        if ($profileCapacity > $venueCapacity) {
            throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_PROFILE_LIMIT'));
        }

        return $normalised;
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
     * Save a new immutable layout revision only when effective data changed.
     */
    public static function saveDefaultConfiguration(int $venueId, array $configuration): array
    {
        $current = self::getDefaultConfiguration($venueId);
        $currentBySpaceId = array();
        foreach ($current['spaces'] as $space) {
            $currentBySpaceId[(int) $space['space_id']] = $space;
        }
        foreach ($configuration['spaces'] as &$space) {
            $spaceId = (int) ($space['space_id'] ?? 0);
            if ($spaceId > 0 && isset($currentBySpaceId[$spaceId])) {
                // A physical space keeps its stable code for its whole lifetime.
                $space['space_code'] = (string) $currentBySpaceId[$spaceId]['space_code'];
            }
        }
        unset($space);

        if (self::configurationFingerprint($current) === self::configurationFingerprint($configuration)) {
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

            foreach ($configuration['spaces'] as $ordering => $spaceData) {
                $spaceId = (int) ($spaceData['space_id'] ?? 0);
                $currentSpace = $spaceId > 0 ? ($currentBySpaceId[$spaceId] ?? null) : null;
                if ($spaceId > 0 && $currentSpace === null) {
                    throw new InvalidArgumentException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_SPACE_OWNERSHIP'));
                }

                if ($currentSpace !== null) {
                    $spaceRow = (object) array(
                        'id'          => $spaceId,
                        'name'        => $spaceData['space_name'],
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

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__jem_venue_profile_spaces'))
                ->where($db->quoteName('venue_profile_id') . ' = ' . $profileId);
            if ($keptAssignmentIds) {
                $query->where($db->quoteName('id') . ' NOT IN (' . implode(',', array_map('intval', $keptAssignmentIds)) . ')');
            }
            $db->setQuery($query)->execute();

            $profile = (object) array(
                'id'          => $profileId,
                'revision'    => (int) $current['profile_revision'] + 1,
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
    public static function buildEventSnapshot(int $venueId): array
    {
        $configuration = self::getDefaultConfiguration($venueId);
        if (empty($configuration['spaces'])) {
            throw new RuntimeException(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_LAYOUT_REQUIRED'));
        }

        return array(
            'schema'             => 'jem-venue-capacity/v1',
            'venue_id'           => $venueId,
            'profile_id'         => (int) $configuration['profile_id'],
            'profile_code'       => (string) $configuration['profile_code'],
            'profile_name'       => (string) $configuration['profile_name'],
            'profile_revision'   => (int) $configuration['profile_revision'],
            'spaces'             => array_map(static function (array $space): array {
                return array(
                    'id'          => (int) $space['space_id'],
                    'code'        => (string) $space['space_code'],
                    'name'        => (string) $space['space_name'],
                    'description' => (string) $space['space_description'],
                    'layout'      => array(
                        'id'       => (int) $space['layout_id'],
                        'code'     => (string) $space['layout_code'],
                        'name'     => (string) $space['layout_name'],
                        'revision' => (int) $space['layout_revision'],
                        'capacity' => (int) $space['layout_capacity'],
                    ),
                    'capacity_areas' => array_map(static function (array $area): array {
                        return array(
                            'id'              => (int) $area['id'],
                            'code'            => (string) $area['code'],
                            'name'            => (string) $area['name'],
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

    /**
     * Canonicalise one room, its selected layout and its quantity areas.
     */
    private static function normaliseSpace(array $space): array
    {
        $normalised = array(
            'space_id'          => (int) ($space['space_id'] ?? 0),
            'space_code'        => self::normaliseCode($space['space_code'] ?? ''),
            'space_name'        => trim((string) ($space['space_name'] ?? '')),
            'space_description' => trim((string) ($space['space_description'] ?? '')),
            'layout_id'         => (int) ($space['layout_id'] ?? 0),
            'layout_code'       => self::normaliseCode($space['layout_code'] ?? ''),
            'layout_name'       => trim((string) ($space['layout_name'] ?? '')),
            'layout_capacity'   => self::normaliseCapacity($space['layout_capacity'] ?? 0),
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
                'description'     => (string) ($area['description'] ?? ''),
                'capacity'        => (int) ($area['capacity'] ?? 0),
                'allocation_mode' => (string) ($area['allocation_mode'] ?? self::ALLOCATION_QUANTITY),
                'published'       => (int) ($area['published'] ?? 0),
            );
        }

        return hash('sha256', json_encode(array(
            'layout_code'     => (string) ($space['layout_code'] ?? ''),
            'layout_name'     => (string) ($space['layout_name'] ?? ''),
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
                'space_description' => (string) ($space['space_description'] ?? ''),
                'layout'            => self::layoutFingerprint($space),
            );
        }

        return hash('sha256', json_encode($spaces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
