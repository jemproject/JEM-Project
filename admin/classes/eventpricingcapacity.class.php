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

require_once JPATH_ADMINISTRATOR . '/components/com_jem/classes/venuecapacity.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/money.class.php';

/**
 * Administrator service for Point 4D event capacity snapshots and prices.
 */
class JemEventPricingCapacityService
{
    public const MODE_CLASSIC = 'classic';
    public const MODE_SINGLE = 'single';
    public const MODE_MULTIPLE = 'multiple';

    /**
     * Add relational capacity and price rows to an event form item.
     */
    public static function populateFormItem(object $item): void
    {
        $item->capacity_pools = array();
        $item->event_prices = array();
        $item->pricing_requirements = self::getVenueRequirements((int) ($item->locid ?? 0));
        $item->venue_configuration_key = '';
        $item->venue_assignment_ids = '[]';

        if (!empty($item->id)) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__jem_capacity_pools'))
                ->where($db->quoteName('event_id') . ' = ' . (int) $item->id)
                ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            $item->capacity_pools = (array) $db->loadAssocList();

            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__jem_event_prices'))
                ->where($db->quoteName('event_id') . ' = ' . (int) $item->id)
                ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            $item->event_prices = (array) $db->loadAssocList();

            $assignmentRows = self::loadEventAssignments((int) $item->id);
            $assignmentIds = self::sortedIds(array_column($assignmentRows, 'venue_profile_space_id'));
            if (!$assignmentIds) {
                $assignmentIds = self::assignmentIdsFromSnapshot(
                    self::decodeSnapshot((string) ($item->venue_snapshot ?? '')),
                    (array) ($item->pricing_requirements['configuration_assignments'] ?? array())
                );
            }
            $item->venue_assignment_ids = json_encode($assignmentIds);
            $item->venue_configuration_key = self::configurationKeyForAssignments(
                $assignmentIds,
                (array) ($item->pricing_requirements['configuration_options'] ?? array())
            );
            if ($item->venue_configuration_key === '' && $assignmentIds) {
                $item->venue_configuration_key = 'saved';
            } elseif ($item->venue_configuration_key === '' && $assignmentRows) {
                $item->venue_configuration_key = 'saved';
            }
        } else {
            $firstOption = $item->pricing_requirements['configuration_options'][0] ?? array();
            $assignmentIds = (array) ($firstOption['assignment_ids'] ?? array());
            $item->venue_configuration_key = (string) ($firstOption['key'] ?? '');
            $item->venue_assignment_ids = json_encode(array_values(array_map('intval', $assignmentIds)));
        }

        if (($item->pricing_mode ?? '') === 'priced') {
            $item->pricing_mode = count($item->event_prices) > 1 ? self::MODE_MULTIPLE : self::MODE_SINGLE;
        } elseif (empty($item->pricing_mode)) {
            $item->pricing_mode = self::MODE_CLASSIC;
        }
        if (!isset($item->prices_include_tax)) {
            $item->prices_include_tax = 1;
        }
        if (!isset($item->management_fee_value) || $item->management_fee_value === '') {
            $item->management_fee_value = '0.00';
        }
        if (empty($item->currency)) {
            $suggestedCurrency = strtoupper(trim((string) ($item->pricing_requirements['suggested_currency'] ?? '')));
            $defaultCurrency = strtoupper(trim((string) (JemHelper::config()->defaultCurrency ?? '')));
            $currency = preg_match('/^[A-Z]{3}$/D', $suggestedCurrency) === 1
                ? $suggestedCurrency
                : $defaultCurrency;
            if (preg_match('/^[A-Z]{3}$/D', $currency) === 1) {
                $item->currency = $currency;
            }
        }
        if (empty($item->id) && empty($item->maxplaces)) {
            $firstOption = $item->pricing_requirements['configuration_options'][0] ?? array();
            if (!empty($firstOption['capacity'])) {
                $item->maxplaces = (int) $firstOption['capacity'];
            }
        }

        if (!$item->event_prices) {
            $singlePoolId = count($item->capacity_pools) === 1 ? (int) $item->capacity_pools[0]['id'] : null;
            $item->event_prices[] = array(
                'id'                => 0,
                'capacity_pool_id'  => $singlePoolId,
                'code'              => 'general',
                'name'              => Text::_('COM_JEM_EVENT_PRICE_DEFAULT_NAME'),
                'description'       => '',
                'amount'            => '0.00',
                'tax_rate_id'       => $item->default_tax_rate_id ?? null,
                'quota'             => null,
                'min_quantity'      => 1,
                'max_quantity'      => !empty($item->maxbookeduser) ? (int) $item->maxbookeduser : null,
                'available_from'    => null,
                'available_until'   => null,
                'min_age'           => null,
                'max_age'           => null,
                'access_level_id'   => 1,
                'verification_mode' => 'none',
                'published'         => 1,
            );
        }
    }

    /**
     * Replace event-owned identifiers with stable source codes before save-as-copy.
     */
    public static function prepareCopiedPriceRows(array $rows, int $sourceEventId): array
    {
        $poolCodes = array_column(self::loadPoolRows($sourceEventId), 'code', 'id');
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $poolId = (int) ($row['capacity_pool_id'] ?? 0);
            $row['id'] = 0;
            $row['capacity_pool_id'] = $poolId > 0 && isset($poolCodes[$poolId])
                ? 'source:' . (string) $poolCodes[$poolId]
                : null;
        }
        unset($row);

        return $rows;
    }

    /**
     * Validate event pricing input before the parent event row is stored.
     *
     * @return array Context consumed by saveChildren() after the event gets an ID.
     */
    public static function prepareEventData(array &$data, int $eventId): array
    {
        $poolsSubmitted = array_key_exists('capacity_pools', $data);
        $pricesSubmitted = array_key_exists('event_prices', $data);
        $rawPools = $poolsSubmitted ? (array) $data['capacity_pools'] : array();
        $rawPrices = $pricesSubmitted ? (array) $data['event_prices'] : array();
        $reload = !empty($data['reload_venue_capacity']);
        $submittedAssignmentIds = self::normaliseAssignmentIds($data['venue_assignment_ids'] ?? array());
        $submittedConfigurationKey = trim((string) ($data['venue_configuration_key'] ?? ''));
        unset(
            $data['capacity_pools'],
            $data['event_prices'],
            $data['reload_venue_capacity'],
            $data['venue_assignment_ids'],
            $data['venue_configuration_key']
        );

        $mode = strtolower(trim((string) ($data['pricing_mode'] ?? self::MODE_CLASSIC)));
        if ($mode === 'priced') {
            $mode = count($rawPrices) > 1 ? self::MODE_MULTIPLE : self::MODE_SINGLE;
        }
        if (!in_array($mode, array(self::MODE_CLASSIC, self::MODE_SINGLE, self::MODE_MULTIPLE), true)) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_MODE'));
        }
        $data['pricing_mode'] = $mode;

        $existing = $eventId > 0 ? self::loadEventPricingRow($eventId) : array();
        $context = array(
            'active'           => $mode !== self::MODE_CLASSIC,
            'reload'           => false,
            'pools_submitted'  => $poolsSubmitted,
            'prices_submitted' => $pricesSubmitted,
            'pools'            => array(),
            'prices'           => array(),
            'snapshot'         => array(),
            'assignments'      => array(),
            'country_code'     => '',
        );

        if ($mode === self::MODE_CLASSIC) {
            $data['currency'] = self::normaliseOptionalCurrency($data['currency'] ?? '');
            $data['default_tax_rate_id'] = self::normaliseNullableId($data['default_tax_rate_id'] ?? null);
            $data['prices_include_tax'] = !empty($data['prices_include_tax']) ? 1 : 0;
            $data['management_fee_mode'] = 'fixed_per_ticket';
            $data['management_fee_value'] = self::normaliseMoney($data['management_fee_value'] ?? '0.00', $data['currency'] ?: 'XXX');
            $data['management_fee_basis'] = 'gross';
            $data['management_fee_tax_rate_id'] = self::normaliseNullableId($data['management_fee_tax_rate_id'] ?? null);
            $data['management_fee_refundable'] = !empty($data['management_fee_refundable']) ? 1 : 0;
            $desiredFingerprint = self::eventPolicyFingerprint($data)
                . '|' . self::rowsFingerprint(self::loadPoolRows($eventId), 'pool')
                . '|' . self::rowsFingerprint(self::loadPriceRows($eventId), 'price');
            self::setPricingRevision($data, $existing, $desiredFingerprint, $eventId);

            return $context;
        }

        $venueId = (int) ($data['locid'] ?? 0);
        $requirements = self::getVenueRequirements($venueId);
        if (!$requirements['venue_exists']) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_VENUE'));
        }
        if (!$requirements['country_code']) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_COUNTRY'));
        }
        if (!$requirements['capacity_ready']) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_CAPACITY_CONFIGURATION'));
        }

        $storedSnapshot = self::decodeSnapshot($existing['venue_snapshot'] ?? '');
        if ($storedSnapshot && empty($storedSnapshot['country_code'])) {
            // Snapshots created before country_code was added remain valid.
            // Enrich them on the next event save without rebuilding their
            // immutable Space/Layout selection.
            $storedSnapshot['country_code'] = $requirements['country_code'];
        }
        $storedAssignmentRows = self::loadEventAssignments($eventId);
        $storedAssignmentIds = self::sortedIds(array_column($storedAssignmentRows, 'venue_profile_space_id'));
        if (!$storedAssignmentIds && $storedSnapshot) {
            $storedAssignmentIds = self::assignmentIdsFromSnapshot(
                $storedSnapshot,
                (array) ($requirements['configuration_assignments'] ?? array())
            );
        }

        if (!$submittedAssignmentIds) {
            if ($eventId > 0 && $storedSnapshot && $submittedConfigurationKey === 'saved') {
                $submittedAssignmentIds = $storedAssignmentIds;
            } elseif ($eventId > 0 && $storedAssignmentIds && $submittedConfigurationKey === 'saved') {
                $submittedAssignmentIds = $storedAssignmentIds;
            } elseif ($eventId > 0 && $storedAssignmentIds && !$reload) {
                $submittedAssignmentIds = $storedAssignmentIds;
            } else {
                $submittedAssignmentIds = (array) (
                    $requirements['configuration_options'][0]['assignment_ids'] ?? array()
                );
            }
        }

        $venueChanged = $eventId > 0 && (
            (int) ($existing['locid'] ?? 0) !== $venueId
            || ($storedSnapshot && (int) ($storedSnapshot['venue_id'] ?? 0) !== $venueId)
        );
        if ($venueChanged && !$reload) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_RELOAD_REQUIRED'));
        }
        $selectionChanged = $eventId > 0 && (
            ($storedAssignmentIds
                && self::sortedIds($storedAssignmentIds) !== self::sortedIds($submittedAssignmentIds))
            || (!$storedAssignmentIds
                && $storedSnapshot
                && $submittedConfigurationKey !== 'saved'
                && $submittedAssignmentIds)
        );
        if ($selectionChanged && !$reload) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_RELOAD_REQUIRED'));
        }
        $reload = $eventId === 0 || !$storedSnapshot || $reload || $venueChanged || $selectionChanged;
        if ($reload && $eventId > 0 && self::hasCommercialRegistrations($eventId)) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_RELOAD_REGISTERED'));
        }

        $snapshot = $reload
            ? JemVenueCapacityService::buildEventSnapshot($venueId, $submittedAssignmentIds)
            : $storedSnapshot;
        $snapshotCapacity = self::snapshotCapacity($snapshot);
        $eventCapacity = self::normaliseUnsignedInteger($data['maxplaces'] ?? 0, true);
        if ($eventCapacity < 1 && !$storedSnapshot) {
            $eventCapacity = $snapshotCapacity;
            $data['maxplaces'] = $eventCapacity;
        }
        if ($eventCapacity < 1) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_EXACT_CAPACITY'));
        }
        if ($eventCapacity > $snapshotCapacity
            || $eventCapacity > (int) $requirements['profile_capacity']
            || $eventCapacity > (int) $requirements['venue_capacity']) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_CAPACITY_LIMIT'));
        }

        $currency = self::normaliseOptionalCurrency($data['currency'] ?? '');
        if ($currency === '') {
            $currency = (string) $requirements['suggested_currency'];
        }
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_CURRENCY'));
        }
        $data['currency'] = $currency;
        $data['prices_include_tax'] = !empty($data['prices_include_tax']) ? 1 : 0;
        $data['management_fee_mode'] = 'fixed_per_ticket';
        $data['management_fee_basis'] = 'gross';
        $data['management_fee_value'] = self::normaliseMoney($data['management_fee_value'] ?? '0.00', $currency);
        $data['management_fee_refundable'] = !empty($data['management_fee_refundable']) ? 1 : 0;
        $data['default_tax_rate_id'] = self::normaliseNullableId($data['default_tax_rate_id'] ?? null);
        $data['management_fee_tax_rate_id'] = self::normaliseNullableId($data['management_fee_tax_rate_id'] ?? null);
        if ($data['management_fee_value'] !== '0.00' && $data['management_fee_tax_rate_id'] === null) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_FEE_TAX'));
        }

        $effectiveDate = self::normaliseEffectiveTaxDate($data['dates'] ?? null);
        self::validateTaxIds(
            array_filter(array($data['default_tax_rate_id'], $data['management_fee_tax_rate_id'])),
            $requirements['country_code'],
            $effectiveDate
        );

        $data['venue_profile_id'] = (int) $snapshot['profile_id'];
        $data['venue_profile_revision'] = (int) $snapshot['profile_revision'];
        $data['venue_snapshot'] = json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $desiredPools = $reload
            ? self::buildPoolRowsFromSnapshot($snapshot)
            : ($poolsSubmitted ? self::normalisePoolRows($rawPools, $eventId, $snapshot) : self::loadPoolRows($eventId));
        $desiredPrices = $pricesSubmitted
            ? self::normalisePriceRows($rawPrices, $eventId, $currency, $requirements['country_code'], $effectiveDate)
            : self::loadPriceRows($eventId);

        $publishedPrices = array_values(array_filter(
            $desiredPrices,
            static fn (array $price): bool => (int) $price['published'] === 1
        ));
        if (!$publishedPrices) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_REQUIRED'));
        }
        if ($mode === self::MODE_SINGLE && count($publishedPrices) !== 1) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_SINGLE_PRICE'));
        }
        if ($mode === self::MODE_MULTIPLE && count($publishedPrices) < 2) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_MULTIPLE_PRICES'));
        }
        self::validatePriceCapacityLimits($desiredPrices, $desiredPools, $eventId, $eventCapacity);

        $desiredFingerprint = self::eventPolicyFingerprint($data)
            . '|' . self::rowsFingerprint($desiredPools, 'pool')
            . '|' . self::rowsFingerprint($desiredPrices, 'price');
        self::setPricingRevision($data, $existing, $desiredFingerprint, $eventId);

        $context['reload'] = $reload;
        $context['pools'] = $desiredPools;
        $context['prices'] = $desiredPrices;
        $context['snapshot'] = $snapshot;
        $context['assignments'] = $reload
            ? self::assignmentRowsFromSnapshot($snapshot)
            : ($storedAssignmentRows ?: self::assignmentRowsFromSnapshot($snapshot));
        $context['country_code'] = $requirements['country_code'];

        return $context;
    }

    /**
     * Persist event-owned capacity and price rows in one transaction.
     */
    public static function saveChildren(int $eventId, array $context, bool $manageTransaction = true): void
    {
        if (empty($context['active']) || $eventId < 1) {
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        if ($manageTransaction) {
            $db->transactionStart();
        }
        try {
            self::saveEventAssignments($eventId, (array) ($context['assignments'] ?? array()));
            $pools = self::savePoolRows($eventId, $context['pools']);
            $poolIds = array_fill_keys(array_map(static fn (array $pool): int => (int) $pool['id'], $pools), true);
            $prices = $context['prices'];
            if (count($pools) === 1) {
                foreach ($prices as &$price) {
                    if (empty($price['capacity_pool_id']) && empty($price['_capacity_pool_code'])) {
                        $price['capacity_pool_id'] = (int) $pools[0]['id'];
                    }
                }
                unset($price);
            }
            $poolsByCode = array_column($pools, null, 'code');
            foreach ($prices as &$price) {
                $sourceCode = (string) ($price['_capacity_pool_code'] ?? '');
                if ($sourceCode !== '') {
                    if (!isset($poolsByCode[$sourceCode])) {
                        throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_POOL'));
                    }
                    $price['capacity_pool_id'] = (int) $poolsByCode[$sourceCode]['id'];
                }
                unset($price['_capacity_pool_code']);
            }
            unset($price);
            foreach ($prices as $price) {
                $poolId = (int) ($price['capacity_pool_id'] ?? 0);
                if ($poolId > 0 && !isset($poolIds[$poolId])) {
                    throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_POOL'));
                }
            }
            self::savePriceRows($eventId, $prices);
            if ($manageTransaction) {
                $db->transactionCommit();
            }
        } catch (Throwable $e) {
            if ($manageTransaction) {
                $db->transactionRollback();
            }
            throw $e;
        }
    }

    /**
     * Venue/country/profile state used both by form UX and authoritative save.
     */
    public static function getVenueRequirements(int $venueId): array
    {
        $requirements = array(
            'venue_exists'       => false,
            'country_code'       => '',
            'suggested_currency' => '',
            'venue_capacity'     => 0,
            'capacity_ready'     => false,
            'profile_revision'   => 0,
            'profile_capacity'   => 0,
            'space_count'        => 0,
            'configured_capacity'=> 0,
            'pool_candidates'    => array(),
            'configuration_assignments' => array(),
            'configuration_options' => array(),
            'configuration_custom_required' => false,
            'configuration_summary' => '',
        );
        if ($venueId < 1) {
            return $requirements;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array('v.id', 'v.country', 'v.capacity', 'c.currency'))
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->join('LEFT', $db->quoteName('#__jem_countries', 'c') . ' ON c.iso2 = v.country')
            ->where('v.id = ' . $venueId);
        $db->setQuery($query);
        $venue = $db->loadObject();
        if (!$venue) {
            return $requirements;
        }

        $requirements['venue_exists'] = true;
        $requirements['country_code'] = strtoupper(trim((string) $venue->country));
        $requirements['suggested_currency'] = strtoupper(trim((string) $venue->currency));
        $requirements['venue_capacity'] = (int) $venue->capacity;

        try {
            $configuration = JemVenueCapacityService::getDefaultConfiguration($venueId);
            $requirements['profile_revision'] = (int) $configuration['profile_revision'];
            $requirements['profile_capacity'] = (int) $configuration['profile_capacity'];
            $requirements['space_count'] = count($configuration['spaces']);
            foreach ($configuration['spaces'] as $space) {
                $requirements['configured_capacity'] += (int) ($space['layout_capacity'] ?? 0);
            }
            $requirements['capacity_ready'] = $requirements['venue_capacity'] > 0
                && $requirements['profile_capacity'] > 0
                && $requirements['country_code'] !== ''
                && $requirements['space_count'] > 0
                && $requirements['configured_capacity'] > 0
                && $requirements['configured_capacity'] <= $requirements['profile_capacity']
                && $requirements['profile_capacity'] <= $requirements['venue_capacity']
                && self::configurationHasCapacity($configuration);
            if ($requirements['capacity_ready']) {
                $requirements['configuration_summary'] = Text::sprintf(
                    'COM_JEM_EVENT_PRICING_CONFIGURATION_SUMMARY',
                    $requirements['space_count'],
                    $requirements['configured_capacity'],
                    $requirements['profile_revision']
                );
                $eventConfigurations = JemVenueCapacityService::getEventConfigurationOptions($venueId);
                $requirements['configuration_assignments'] = $eventConfigurations['assignments'];
                $requirements['configuration_custom_required'] = !empty($eventConfigurations['custom_required']);
                foreach ((array) $eventConfigurations['options'] as $option) {
                    $snapshot = JemVenueCapacityService::buildEventSnapshot(
                        $venueId,
                        (array) $option['assignment_ids']
                    );
                    $option['pool_candidates'] = array_map(
                        static fn (array $pool): array => array(
                            'code' => (string) $pool['code'],
                            'name' => (string) $pool['name'],
                            'capacity' => (int) $pool['capacity'],
                            'venue_layout_id' => (int) $pool['venue_layout_id'],
                        ),
                        self::buildPoolRowsFromSnapshot($snapshot)
                    );
                    $requirements['configuration_options'][] = $option;
                }

                $snapshot = JemVenueCapacityService::buildEventSnapshot($venueId);
                foreach (self::buildPoolRowsFromSnapshot($snapshot) as $pool) {
                    $requirements['pool_candidates'][] = array(
                        'code'     => (string) $pool['code'],
                        'name'     => (string) $pool['name'],
                        'capacity' => (int) $pool['capacity'],
                        'venue_layout_id' => (int) $pool['venue_layout_id'],
                    );
                }
            }
        } catch (Throwable $e) {
            // The installer may still be creating the additive 4D tables.
        }

        return $requirements;
    }

    /**
     * JSON-safe venue state for the dynamic event editor.
     */
    public static function getVenueConfigurationPayload(int $venueId): array
    {
        return self::getVenueRequirements($venueId);
    }

    private static function configurationHasCapacity(array $configuration): bool
    {
        foreach ($configuration['spaces'] as $space) {
            if ((int) ($space['layout_capacity'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private static function snapshotCapacity(array $snapshot): int
    {
        $total = 0;
        foreach ((array) ($snapshot['spaces'] ?? array()) as $space) {
            $total += (int) ($space['layout']['capacity'] ?? 0);
        }

        return $total;
    }

    private static function buildPoolRowsFromSnapshot(array $snapshot): array
    {
        $rows = array();
        foreach ((array) ($snapshot['spaces'] ?? array()) as $space) {
            $publishedAreas = array_values(array_filter(
                (array) ($space['capacity_areas'] ?? array()),
                static fn (array $area): bool => (int) ($area['published'] ?? 0) === 1
            ));
            if (!$publishedAreas) {
                $rows[] = array(
                    'id'                     => 0,
                    'venue_capacity_area_id' => null,
                    'venue_layout_id'         => (int) $space['layout']['id'],
                    'venue_layout_revision'   => (int) $space['layout']['revision'],
                    'allocation_mode'         => 'quantity',
                    'code'                    => self::poolCode((string) $space['code'], 'general'),
                    'name'                    => (string) $space['name'],
                    'description'             => (string) $space['description'],
                    'capacity'                => (int) $space['layout']['capacity'],
                    'published'               => 1,
                );
                continue;
            }

            foreach ($publishedAreas as $area) {
                $rows[] = array(
                    'id'                     => 0,
                    'venue_capacity_area_id' => (int) $area['id'],
                    'venue_layout_id'         => (int) $space['layout']['id'],
                    'venue_layout_revision'   => (int) $space['layout']['revision'],
                    'allocation_mode'         => (string) $area['allocation_mode'],
                    'code'                    => self::poolCode((string) $space['code'], (string) $area['code']),
                    'name'                    => (string) $space['name'] . ' - ' . (string) $area['name'],
                    'description'             => (string) $area['description'],
                    'capacity'                => (int) $area['capacity'],
                    'published'               => 1,
                );
            }
        }

        return $rows;
    }

    private static function normalisePoolRows(array $rows, int $eventId, array $snapshot): array
    {
        $existing = array_column(self::loadPoolRows($eventId), null, 'id');
        $sourceLimits = array();
        foreach (self::buildPoolRowsFromSnapshot($snapshot) as $source) {
            $key = self::poolSourceKey($source);
            $sourceLimits[$key] = $source;
        }

        $normalised = array();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1 || !isset($existing[$id])) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_POOL_OWNERSHIP'));
            }
            $stored = $existing[$id];
            $key = self::poolSourceKey($stored);
            if (!isset($sourceLimits[$key])) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_POOL_SOURCE'));
            }
            $capacity = self::normaliseUnsignedInteger($row['capacity'] ?? 0, true);
            if ($capacity > (int) $sourceLimits[$key]['capacity']) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_POOL_LIMIT'));
            }
            $published = !empty($row['published']) ? 1 : 0;
            if ($published && $capacity < 1) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_POOL_CAPACITY'));
            }
            $normalised[] = array_merge($stored, array(
                'name'        => StringHelper::substr(trim((string) ($row['name'] ?? $stored['name'])), 0, 255),
                'description' => trim((string) ($row['description'] ?? '')),
                'capacity'    => $capacity,
                'published'   => $published,
            ));
        }

        return $normalised;
    }

    private static function normalisePriceRows(
        array $rows,
        int $eventId,
        string $currency,
        string $countryCode,
        string $effectiveDate
    ): array
    {
        $existingRows = array_column(self::loadPriceRows($eventId), null, 'id');
        $poolIds = array_fill_keys(array_map(
            static fn (array $pool): int => (int) $pool['id'],
            self::loadPoolRows($eventId)
        ), true);
        $codes = array();
        $taxIds = array();
        $normalised = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && !isset($existingRows[$id])) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_OWNERSHIP'));
            }
            $stored = $id > 0 ? $existingRows[$id] : array();
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_NAME'));
            }
            $code = $id > 0
                ? (string) $stored['code']
                : self::normaliseCode($row['code'] ?? $name);
            if ($code === '' || isset($codes[$code])) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_CODE'));
            }
            $codes[$code] = true;

            $poolReference = trim((string) ($row['capacity_pool_id'] ?? ''));
            $poolSourceCode = str_starts_with($poolReference, 'source:')
                ? self::normaliseCode(substr($poolReference, strlen('source:')))
                : '';
            $poolId = $poolSourceCode === '' ? self::normaliseNullableId($poolReference) : null;
            if ($poolId !== null && $eventId > 0 && !isset($poolIds[$poolId])) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_POOL'));
            }
            $taxId = self::normaliseNullableId($row['tax_rate_id'] ?? null);
            if ($taxId === null) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_TAX'));
            }
            $taxIds[] = $taxId;

            $quota = self::normaliseNullableUnsignedInteger($row['quota'] ?? null);
            $minQuantity = self::normaliseUnsignedInteger($row['min_quantity'] ?? 1, false);
            $maxQuantity = self::normaliseNullableUnsignedInteger($row['max_quantity'] ?? null);
            if ($maxQuantity !== null && $maxQuantity < max(1, $minQuantity)) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_QUANTITY_RANGE'));
            }
            $minAge = self::normaliseNullableUnsignedInteger($row['min_age'] ?? null, 255);
            $maxAge = self::normaliseNullableUnsignedInteger($row['max_age'] ?? null, 255);
            if ($minAge !== null && $maxAge !== null && $minAge > $maxAge) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_AGE_RANGE'));
            }
            $availableFrom = self::normaliseNullableDateTime($row['available_from'] ?? null);
            $availableUntil = self::normaliseNullableDateTime($row['available_until'] ?? null);
            if ($availableFrom !== null && $availableUntil !== null && $availableFrom > $availableUntil) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_AVAILABILITY_RANGE'));
            }
            $verificationMode = (string) ($row['verification_mode'] ?? 'none');
            if (!in_array($verificationMode, array('none', 'declaration', 'manual'), true)) {
                $verificationMode = 'none';
            }

            $normalised[] = array(
                'id'                => $id,
                'capacity_pool_id'  => $poolId,
                '_capacity_pool_code' => $poolSourceCode,
                'code'              => $code,
                'name'              => StringHelper::substr($name, 0, 255),
                'description'       => trim((string) ($row['description'] ?? '')),
                'amount'            => self::normaliseMoney($row['amount'] ?? '0.00', $currency),
                'tax_rate_id'       => $taxId,
                'quota'             => $quota,
                'min_quantity'      => $minQuantity,
                'max_quantity'      => $maxQuantity,
                'available_from'    => $availableFrom,
                'available_until'   => $availableUntil,
                'min_age'           => $minAge,
                'max_age'           => $maxAge,
                'access_level_id'   => self::normaliseNullableId($row['access_level_id'] ?? ($stored['access_level_id'] ?? null)),
                'user_group_id'     => self::normaliseNullableId($row['user_group_id'] ?? ($stored['user_group_id'] ?? null)),
                'verification_mode' => $verificationMode,
                'published'         => !empty($row['published']) ? 1 : 0,
            );
        }

        self::validateTaxIds($taxIds, $countryCode, $effectiveDate);
        self::validateEligibilityIds($normalised);

        return $normalised;
    }

    private static function validateEligibilityIds(array $prices): void
    {
        $accessIds = array_values(array_unique(array_filter(array_map(
            static fn (array $price): int => (int) ($price['access_level_id'] ?? 0),
            $prices
        ))));
        $groupIds = array_values(array_unique(array_filter(array_map(
            static fn (array $price): int => (int) ($price['user_group_id'] ?? 0),
            $prices
        ))));
        $db = Factory::getContainer()->get('DatabaseDriver');

        if ($accessIds) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__viewlevels'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $accessIds) . ')');
            $db->setQuery($query);
            $valid = array_map('intval', (array) $db->loadColumn());
            if (array_diff($accessIds, $valid)) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_ACCESS_LEVEL'));
            }
        }

        if ($groupIds) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__usergroups'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $groupIds) . ')');
            $db->setQuery($query);
            $valid = array_map('intval', (array) $db->loadColumn());
            if (array_diff($groupIds, $valid)) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_USER_GROUP'));
            }
        }
    }

    private static function validateTaxIds(array $taxIds, string $countryCode, string $effectiveDate): void
    {
        $taxIds = array_values(array_unique(array_filter(array_map('intval', $taxIds))));
        if (!$taxIds) {
            return;
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array('id', 'country_code', 'valid_from', 'valid_until'))
            ->from($db->quoteName('#__jem_tax_rates'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $taxIds) . ')')
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
        $valid = array();
        $countryApplicable = array();
        foreach ((array) $db->loadObjectList() as $tax) {
            $taxCountry = strtoupper(trim((string) $tax->country_code));
            $validFrom = trim((string) $tax->valid_from);
            $validUntil = trim((string) $tax->valid_until);
            $dateIsValid = ($validFrom === '' || $validFrom === '0000-00-00' || $validFrom <= $effectiveDate)
                && ($validUntil === '' || $validUntil === '0000-00-00' || $validUntil >= $effectiveDate);
            if ($taxCountry === '' || $taxCountry === $countryCode) {
                $countryApplicable[(int) $tax->id] = true;
            }
            if (isset($countryApplicable[(int) $tax->id]) && $dateIsValid) {
                $valid[(int) $tax->id] = true;
            }
        }
        foreach ($taxIds as $taxId) {
            if (!isset($valid[$taxId])) {
                throw new InvalidArgumentException(Text::_(
                    isset($countryApplicable[$taxId])
                        ? 'COM_JEM_EVENT_PRICING_ERROR_TAX_VALIDITY'
                        : 'COM_JEM_EVENT_PRICING_ERROR_TAX_COUNTRY'
                ));
            }
        }
    }

    private static function validatePriceCapacityLimits(
        array $prices,
        array $pools,
        int $eventId,
        int $eventCapacity
    ): void {
        $limitsById = array();
        $limitsByCode = array();
        foreach ($pools as $pool) {
            $limit = array(
                'capacity'  => (int) ($pool['capacity'] ?? 0),
                'published' => (int) ($pool['published'] ?? 0),
            );
            $poolId = (int) ($pool['id'] ?? 0);
            if ($poolId > 0) {
                $limitsById[$poolId] = $limit;
            }
            $limitsByCode[(string) ($pool['code'] ?? '')] = $limit;
        }

        $existingCodesById = array_column(self::loadPoolRows($eventId), 'code', 'id');
        foreach ($prices as $price) {
            if ((int) ($price['published'] ?? 0) !== 1) {
                continue;
            }
            $limit = array('capacity' => $eventCapacity, 'published' => 1);
            $poolId = (int) ($price['capacity_pool_id'] ?? 0);
            $sourceCode = (string) ($price['_capacity_pool_code'] ?? '');
            if ($sourceCode !== '') {
                if (!isset($limitsByCode[$sourceCode])) {
                    throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_POOL'));
                }
                $limit = $limitsByCode[$sourceCode];
            } elseif ($poolId > 0) {
                if (isset($limitsById[$poolId])) {
                    $limit = $limitsById[$poolId];
                } elseif (isset($existingCodesById[$poolId], $limitsByCode[$existingCodesById[$poolId]])) {
                    $limit = $limitsByCode[$existingCodesById[$poolId]];
                } else {
                    throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_POOL'));
                }
                if ((int) $limit['published'] !== 1) {
                    throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_POOL_UNPUBLISHED'));
                }
            }
            $quota = $price['quota'] ?? null;
            if ($quota !== null && (int) $quota > min($eventCapacity, (int) $limit['capacity'])) {
                throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_PRICE_QUOTA'));
            }
        }
    }

    private static function savePoolRows(int $eventId, array $rows): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $existing = self::loadPoolRows($eventId);
        $existingById = array_column($existing, null, 'id');
        $existingByCode = array_column($existing, null, 'code');
        $keptIds = array();
        $savedRows = array();
        $now = Factory::getDate()->toSql();
        $identity = Factory::getApplication()->getIdentity();
        $userId = (int) ($identity->id ?? 0);

        foreach ($rows as $ordering => $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1 && isset($existingByCode[$row['code']])) {
                $id = (int) $existingByCode[$row['code']]['id'];
            }
            $object = (object) array(
                'event_id'               => $eventId,
                'venue_capacity_area_id' => $row['venue_capacity_area_id'] ?? null,
                'venue_layout_id'         => $row['venue_layout_id'] ?? null,
                'venue_layout_revision'   => (int) ($row['venue_layout_revision'] ?? 0),
                'allocation_mode'         => 'quantity',
                'code'                    => (string) $row['code'],
                'name'                    => (string) $row['name'],
                'description'             => (string) ($row['description'] ?? ''),
                'capacity'                => (int) $row['capacity'],
                'published'               => !empty($row['published']) ? 1 : 0,
                'ordering'                => (int) $ordering,
            );
            if ($id > 0 && isset($existingById[$id])) {
                $object->id = $id;
                $object->modified = $now;
                $object->modified_by = $userId;
                $db->updateObject('#__jem_capacity_pools', $object, 'id', true);
            } else {
                $object->created = $now;
                $object->created_by = $userId;
                $db->insertObject('#__jem_capacity_pools', $object, 'id');
                $id = (int) $object->id;
            }
            $keptIds[] = $id;
            $savedRows[] = array_merge($row, array('id' => $id));
        }

        foreach ($existing as $old) {
            if (!in_array((int) $old['id'], $keptIds, true)) {
                $update = (object) array('id' => (int) $old['id'], 'published' => 0, 'modified' => $now, 'modified_by' => $userId);
                $db->updateObject('#__jem_capacity_pools', $update, 'id');
            }
        }

        return $savedRows;
    }

    private static function savePriceRows(int $eventId, array $rows): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $existing = self::loadPriceRows($eventId);
        $existingById = array_column($existing, null, 'id');
        $keptIds = array();
        $now = Factory::getDate()->toSql();
        $identity = Factory::getApplication()->getIdentity();
        $userId = (int) ($identity->id ?? 0);

        foreach ($rows as $ordering => $row) {
            $id = (int) ($row['id'] ?? 0);
            unset($row['_capacity_pool_code']);
            $object = (object) array_merge($row, array(
                'event_id'   => $eventId,
                'ordering'   => (int) $ordering,
                'created_by' => $userId,
            ));
            unset($object->id);
            if ($id > 0 && isset($existingById[$id])) {
                $object->id = $id;
                unset($object->created_by);
                $object->modified = $now;
                $object->modified_by = $userId;
                $db->updateObject('#__jem_event_prices', $object, 'id', true);
            } else {
                $object->created = $now;
                $db->insertObject('#__jem_event_prices', $object, 'id');
                $id = (int) $object->id;
            }
            $keptIds[] = $id;
        }

        foreach ($existing as $old) {
            $oldId = (int) $old['id'];
            if (in_array($oldId, $keptIds, true)) {
                continue;
            }
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__jem_register_items'))
                ->where($db->quoteName('event_price_id') . ' = ' . $oldId);
            $db->setQuery($query);
            if ((int) $db->loadResult() > 0) {
                $update = (object) array('id' => $oldId, 'published' => 0, 'modified' => $now, 'modified_by' => $userId);
                $db->updateObject('#__jem_event_prices', $update, 'id');
            } else {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__jem_event_prices'))
                    ->where($db->quoteName('id') . ' = ' . $oldId)
                    ->where($db->quoteName('event_id') . ' = ' . $eventId);
                $db->setQuery($query)->execute();
            }
        }
    }

    private static function normaliseAssignmentIds($value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return array();
            }
            try {
                $decoded = json_decode($trimmed, true, 32, JSON_THROW_ON_ERROR);
                $value = is_array($decoded) ? $decoded : explode(',', $trimmed);
            } catch (JsonException $e) {
                $value = explode(',', $trimmed);
            }
        }

        return self::sortedIds((array) $value);
    }

    private static function sortedIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private static function configurationKeyForAssignments(array $ids, array $options): string
    {
        $ids = self::sortedIds($ids);
        foreach ($options as $option) {
            if ($ids === self::sortedIds((array) ($option['assignment_ids'] ?? array()))) {
                return (string) ($option['key'] ?? '');
            }
        }

        return '';
    }

    private static function assignmentIdsFromSnapshot(array $snapshot, array $assignments): array
    {
        $bySpaceLayout = array();
        $byId = array();
        foreach ($assignments as $assignment) {
            $bySpaceLayout[(int) ($assignment['space_id'] ?? 0) . ':' . (int) ($assignment['layout_id'] ?? 0)]
                = (int) ($assignment['id'] ?? 0);
            $byId[(int) ($assignment['id'] ?? 0)] = $assignment;
        }

        $ids = array();
        foreach ((array) ($snapshot['spaces'] ?? array()) as $space) {
            $id = (int) ($space['profile_space_id'] ?? 0);
            if ($id > 0 && (
                !isset($byId[$id])
                || (int) ($byId[$id]['space_id'] ?? 0) !== (int) ($space['id'] ?? 0)
                || (int) ($byId[$id]['layout_id'] ?? 0) !== (int) ($space['layout']['id'] ?? 0)
            )) {
                $id = 0;
            }
            if ($id < 1) {
                $id = (int) ($bySpaceLayout[
                    (int) ($space['id'] ?? 0) . ':' . (int) ($space['layout']['id'] ?? 0)
                ] ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return self::sortedIds($ids);
    }

    private static function assignmentRowsFromSnapshot(array $snapshot): array
    {
        $rows = array();
        foreach ((array) ($snapshot['spaces'] ?? array()) as $ordering => $space) {
            $rows[] = array(
                'venue_profile_id' => (int) ($snapshot['profile_id'] ?? 0),
                'venue_profile_revision' => (int) ($snapshot['profile_revision'] ?? 0),
                'venue_profile_space_id' => (int) ($space['profile_space_id'] ?? 0),
                'venue_space_id' => (int) ($space['id'] ?? 0),
                'venue_layout_id' => (int) ($space['layout']['id'] ?? 0),
                'venue_layout_revision' => (int) ($space['layout']['revision'] ?? 0),
                'ordering' => (int) $ordering,
            );
        }

        return $rows;
    }

    private static function loadEventAssignments(int $eventId): array
    {
        if ($eventId < 1) {
            return array();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jem_event_space_layouts'))
            ->where($db->quoteName('event_id') . ' = ' . $eventId)
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
        $db->setQuery($query);

        return (array) $db->loadAssocList();
    }

    private static function saveEventAssignments(int $eventId, array $rows): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__jem_event_space_layouts'))
                ->where($db->quoteName('event_id') . ' = ' . $eventId)
        )->execute();

        $now = Factory::getDate()->toSql();
        $identity = Factory::getApplication()->getIdentity();
        $userId = (int) ($identity->id ?? 0);
        foreach ($rows as $ordering => $row) {
            $profileSpaceId = (int) ($row['venue_profile_space_id'] ?? 0);
            $object = (object) array(
                'event_id' => $eventId,
                'venue_profile_id' => (int) ($row['venue_profile_id'] ?? 0),
                'venue_profile_revision' => (int) ($row['venue_profile_revision'] ?? 0),
                'venue_profile_space_id' => $profileSpaceId > 0 ? $profileSpaceId : null,
                'venue_space_id' => (int) ($row['venue_space_id'] ?? 0),
                'venue_layout_id' => (int) ($row['venue_layout_id'] ?? 0),
                'venue_layout_revision' => (int) ($row['venue_layout_revision'] ?? 0),
                'ordering' => (int) $ordering,
                'created' => $now,
                'created_by' => $userId,
            );
            $db->insertObject('#__jem_event_space_layouts', $object, 'id');
        }
    }

    private static function loadEventPricingRow(int $eventId): array
    {
        if ($eventId < 1) {
            return array();
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                'id', 'locid', 'pricing_mode', 'pricing_revision', 'currency', 'default_tax_rate_id',
                'prices_include_tax', 'management_fee_mode', 'management_fee_value', 'management_fee_basis',
                'management_fee_tax_rate_id', 'management_fee_refundable', 'venue_profile_id',
                'venue_profile_revision', 'venue_snapshot', 'maxplaces',
            ))
            ->from($db->quoteName('#__jem_events'))
            ->where($db->quoteName('id') . ' = ' . $eventId);
        $db->setQuery($query);

        return $db->loadAssoc() ?: array();
    }

    private static function loadPoolRows(int $eventId): array
    {
        if ($eventId < 1) {
            return array();
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jem_capacity_pools'))
            ->where($db->quoteName('event_id') . ' = ' . $eventId)
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
        $db->setQuery($query);

        return (array) $db->loadAssocList();
    }

    private static function loadPriceRows(int $eventId): array
    {
        if ($eventId < 1) {
            return array();
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jem_event_prices'))
            ->where($db->quoteName('event_id') . ' = ' . $eventId)
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
        $db->setQuery($query);

        return (array) $db->loadAssocList();
    }

    private static function hasCommercialRegistrations(int $eventId): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jem_register_items', 'i'))
            ->join('INNER', $db->quoteName('#__jem_register', 'r') . ' ON r.id = i.register_id')
            ->where($db->quoteName('r.event') . ' = ' . $eventId);
        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    private static function decodeSnapshot($value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return array();
        }
        try {
            $snapshot = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return array();
        }

        return is_array($snapshot) && ($snapshot['schema'] ?? '') === 'jem-venue-capacity/v1'
            ? $snapshot
            : array();
    }

    private static function eventPolicyFingerprint(array $data): string
    {
        $fields = array(
            'pricing_mode', 'currency', 'default_tax_rate_id', 'prices_include_tax',
            'management_fee_mode', 'management_fee_value', 'management_fee_basis',
            'management_fee_tax_rate_id', 'management_fee_refundable', 'venue_profile_id',
            'venue_profile_revision', 'venue_snapshot', 'locid', 'maxplaces',
        );
        $integerFields = array(
            'default_tax_rate_id', 'prices_include_tax', 'management_fee_tax_rate_id',
            'management_fee_refundable', 'venue_profile_id', 'venue_profile_revision',
            'locid', 'maxplaces',
        );
        $values = array();
        foreach ($fields as $field) {
            $values[$field] = $data[$field] ?? null;
            if (in_array($field, $integerFields, true)) {
                $values[$field] = $values[$field] === null || $values[$field] === ''
                    ? null
                    : (int) $values[$field];
            } elseif ($values[$field] !== null) {
                $values[$field] = (string) $values[$field];
            }
        }

        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function rowsFingerprint(array $rows, string $type): string
    {
        $fields = $type === 'pool'
            ? array(
                'venue_capacity_area_id', 'venue_layout_id', 'venue_layout_revision',
                'allocation_mode', 'code', 'name', 'description', 'capacity', 'published',
            )
            : array(
                'capacity_pool_id', 'code', 'name', 'description', 'amount', 'tax_rate_id',
                'quota', 'min_quantity', 'max_quantity', 'available_from', 'available_until',
                'min_age', 'max_age', 'access_level_id', 'user_group_id',
                'verification_mode', 'published',
            );
        $integerFields = array(
            'venue_capacity_area_id', 'venue_layout_id', 'venue_layout_revision', 'capacity',
            'published', 'capacity_pool_id', 'tax_rate_id', 'quota', 'min_quantity',
            'max_quantity', 'min_age', 'max_age', 'access_level_id', 'user_group_id',
        );
        $canonical = array();
        foreach ($rows as $row) {
            $entry = array();
            foreach ($fields as $field) {
                $value = $row[$field] ?? null;
                if (in_array($field, $integerFields, true)) {
                    $value = $value === null || $value === '' ? null : (int) $value;
                } elseif ($value !== null) {
                    $value = (string) $value;
                }
                $entry[$field] = $value;
            }
            $canonical[] = $entry;
        }

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function existingFingerprint(array $existing, int $eventId): string
    {
        if (!$existing) {
            return '';
        }

        return self::eventPolicyFingerprint($existing)
            . '|' . self::rowsFingerprint(self::loadPoolRows($eventId), 'pool')
            . '|' . self::rowsFingerprint(self::loadPriceRows($eventId), 'price');
    }

    private static function setPricingRevision(array &$data, array $existing, string $desiredFingerprint, int $eventId): void
    {
        if ($eventId < 1 || !$existing) {
            $data['pricing_revision'] = 1;

            return;
        }

        $current = self::existingFingerprint($existing, $eventId);
        $data['pricing_revision'] = max(1, (int) ($existing['pricing_revision'] ?? 1));
        if ($current !== $desiredFingerprint) {
            $data['pricing_revision']++;
        }
    }

    private static function normaliseMoney($value, string $currency): string
    {
        try {
            $money = JemMoney::fromDecimal($value === '' || $value === null ? '0.00' : $value, $currency);
        } catch (InvalidArgumentException | OverflowException $e) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_MONEY'));
        }
        if ($money->minorUnits() < 0) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_MONEY'));
        }

        return $money->decimal();
    }

    private static function normaliseOptionalCurrency($value): string
    {
        $currency = strtoupper(trim((string) $value));
        if ($currency !== '' && preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_CURRENCY'));
        }

        return $currency;
    }

    private static function normaliseNullableId($value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if ($id === false) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_INTEGER'));
        }

        return (int) $id;
    }

    private static function normaliseUnsignedInteger($value, bool $allowZero, ?int $maximum = null): int
    {
        $minimum = $allowZero ? 0 : 1;
        $options = array('min_range' => $minimum);
        if ($maximum !== null) {
            $options['max_range'] = $maximum;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, array('options' => $options));
        if ($number === false) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_INTEGER'));
        }

        return (int) $number;
    }

    private static function normaliseNullableUnsignedInteger($value, ?int $maximum = null): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return self::normaliseUnsignedInteger($value, true, $maximum);
    }

    private static function normaliseNullableDateTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_DATETIME'));
        }

        return $value;
    }

    private static function normaliseEffectiveTaxDate($value): string
    {
        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date && $date->format('Y-m-d') === $value) {
            return $value;
        }

        return Factory::getDate('now', 'UTC')->format('Y-m-d');
    }

    private static function normaliseCode($value): string
    {
        $code = strtolower(trim((string) $value));
        $code = preg_replace('/[^a-z0-9_-]+/', '-', $code);

        return trim(StringHelper::substr((string) $code, 0, 64), '-_');
    }

    private static function poolCode(string $spaceCode, string $areaCode): string
    {
        $code = self::normaliseCode($spaceCode . '__' . $areaCode);
        if (strlen($code) <= 64) {
            return $code;
        }

        return substr($code, 0, 51) . '-' . substr(hash('sha256', $code), 0, 12);
    }

    private static function poolSourceKey(array $pool): string
    {
        $areaId = (int) ($pool['venue_capacity_area_id'] ?? 0);

        return $areaId > 0
            ? 'area:' . $areaId
            : 'layout:' . (int) ($pool['venue_layout_id'] ?? 0);
    }
}
