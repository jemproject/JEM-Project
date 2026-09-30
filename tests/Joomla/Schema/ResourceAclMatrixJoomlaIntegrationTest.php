<?php

declare(strict_types=1);

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class ResourceAclMatrixJoomlaIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootJoomlaSite();
    }

    public function testCategoryAndVenueAllowListsUseRealJoomlaRules(): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/classes/backendacl.class.php';
        require_once JPATH_SITE . '/components/com_jem/classes/resourceacl.class.php';
        require_once JPATH_SITE . '/components/com_jem/classes/venueaccess.class.php';

        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $groupId = 2;

        self::assertTrue($this->groupExists($db, $groupId), 'The Joomla Registered group is required.');

        $componentAsset = $this->componentAsset($db);
        $categoryAssets = $this->resourceAssets($db, '#__jem_categories', 'com_jem.category.', 2);
        $venueAssets = $this->resourceAssets($db, '#__jem_venues', 'com_jem.venue.', 2);

        self::assertCount(2, $categoryAssets, 'At least two JEM Categories are required for the ACL matrix.');
        self::assertCount(2, $venueAssets, 'At least two JEM Venues are required for the ACL matrix.');

        $categoryAllowed = $categoryAssets[0];
        $categoryDenied = $categoryAssets[1];
        $venueAllowed = $venueAssets[0];
        $venueDenied = $venueAssets[1];

        $db->transactionStart();

        try {
            $this->setRule($db, $componentAsset, 'jem.events.access', $groupId, true);
            $this->setRule($db, $componentAsset, 'jem.events.create', $groupId, null);
            $this->setRule($db, $componentAsset, 'jem.venues.use', $groupId, null);
            $this->setRule($db, $categoryAllowed['asset_id'], 'jem.events.create', $groupId, true);
            $this->setRule($db, $categoryDenied['asset_id'], 'jem.events.create', $groupId, false);
            $this->setRule($db, $venueAllowed['asset_id'], 'jem.venues.use', $groupId, true);
            $this->setRule($db, $venueDenied['asset_id'], 'jem.venues.use', $groupId, false);
            Access::clearStatics();

            self::assertTrue(Access::checkGroup($groupId, 'jem.events.access', 'com_jem'));
            self::assertTrue(Access::checkGroup($groupId, 'jem.events.create', $categoryAllowed['asset_name']));
            self::assertFalse(Access::checkGroup($groupId, 'jem.events.create', $categoryDenied['asset_name']));

            $authorise = static function (string $action, string $asset = 'com_jem') use ($groupId): bool {
                return (bool) Access::checkGroup($groupId, $action, $asset);
            };

            self::assertTrue(JemBackendAclPolicy::allowsEventCategories(
                'create',
                array($categoryAllowed['resource_id']),
                null,
                0,
                $authorise
            ));
            self::assertFalse(JemBackendAclPolicy::allowsEventCategories(
                'create',
                array($categoryAllowed['resource_id'], $categoryDenied['resource_id']),
                null,
                0,
                $authorise
            ));
            self::assertFalse(JemBackendAclPolicy::allowsEventCategories(
                'create',
                array($categoryDenied['resource_id']),
                null,
                0,
                $authorise
            ));

            self::assertSame(true, JemResourceAclPolicy::decide(
                'event',
                'add',
                array($categoryAllowed['resource_id']),
                static fn (string $action, string $asset): bool => (bool) Access::checkGroup($groupId, $action, $asset)
            ));
            self::assertSame(false, JemResourceAclPolicy::decide(
                'event',
                'add',
                array($categoryAllowed['resource_id'], $categoryDenied['resource_id']),
                static fn (string $action, string $asset): bool => (bool) Access::checkGroup($groupId, $action, $asset)
            ));

            $user = new class($groupId) {
                public int $id = 987654321;
                private int $groupId;

                public function __construct(int $groupId)
                {
                    $this->groupId = $groupId;
                }

                public function get(string $name, mixed $default = null): mixed
                {
                    return $name === 'guest' ? 0 : $default;
                }

                public function authorise(string $action, string $asset = 'com_jem'): bool
                {
                    return (bool) Access::checkGroup($this->groupId, $action, $asset);
                }
            };

            self::assertTrue(JemVenueAccess::canUse($user, $venueAllowed['resource_id']));
            self::assertFalse(JemVenueAccess::canUse($user, $venueDenied['resource_id']));
            self::assertTrue(JemVenueAccess::canUse($user, 0), 'No Venue must remain a valid Event choice.');

            $authorisedVenueIds = JemVenueAccess::getAuthorisedIds($user, false);
            self::assertContains($venueAllowed['resource_id'], $authorisedVenueIds);
            self::assertNotContains($venueDenied['resource_id'], $authorisedVenueIds);

            $this->setRule($db, $componentAsset, 'jem.events.create', $groupId, false);
            $this->setRule($db, $componentAsset, 'jem.venues.use', $groupId, false);
            Access::clearStatics();

            self::assertFalse(
                Access::checkGroup($groupId, 'jem.events.create', $categoryAllowed['asset_name']),
                'A component Deny must override an Allow on a Category asset.'
            );
            self::assertFalse(
                JemVenueAccess::canUse($user, $venueAllowed['resource_id']),
                'A component Deny must override an Allow on a Venue asset.'
            );
        } finally {
            $db->transactionRollback();
            Access::clearStatics();
        }
    }

    private function groupExists(DatabaseDriver $db, int $groupId): bool
    {
        $query = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('id') . ' = ' . $groupId);
        $db->setQuery($query);

        return (bool) $db->loadResult();
    }

    private function componentAsset(DatabaseDriver $db): int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__assets'))
            ->where($db->quoteName('name') . ' = ' . $db->quote('com_jem'));
        $db->setQuery($query);
        $assetId = (int) $db->loadResult();

        self::assertGreaterThan(0, $assetId, 'The com_jem component asset is required.');

        return $assetId;
    }

    /**
     * @return array<int, array{resource_id: int, asset_id: int, asset_name: string}>
     */
    private function resourceAssets(DatabaseDriver $db, string $table, string $assetPrefix, int $limit): array
    {
        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('r.id', 'resource_id'),
                $db->quoteName('a.id', 'asset_id'),
                $db->quoteName('a.name', 'asset_name'),
            ))
            ->from($db->quoteName($table, 'r'))
            ->innerJoin($db->quoteName('#__assets', 'a') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('r.asset_id'))
            ->where($db->quoteName('a.name') . ' LIKE ' . $db->quote($assetPrefix . '%'))
            ->order($db->quoteName('r.id') . ' ASC');
        $db->setQuery($query, 0, $limit);

        return array_map(
            static fn (array $row): array => array(
                'resource_id' => (int) $row['resource_id'],
                'asset_id' => (int) $row['asset_id'],
                'asset_name' => (string) $row['asset_name'],
            ),
            (array) $db->loadAssocList()
        );
    }

    private function setRule(
        DatabaseDriver $db,
        int $assetId,
        string $action,
        int $groupId,
        ?bool $decision
    ): void {
        $query = $db->getQuery(true)
            ->select($db->quoteName('rules'))
            ->from($db->quoteName('#__assets'))
            ->where($db->quoteName('id') . ' = ' . $assetId);
        $db->setQuery($query);
        $rules = json_decode((string) $db->loadResult(), true);
        $rules = is_array($rules) ? $rules : array();

        if ($decision === null) {
            unset($rules[$action][(string) $groupId]);

            if (isset($rules[$action]) && $rules[$action] === array()) {
                unset($rules[$action]);
            }
        } else {
            $rules[$action] = isset($rules[$action]) && is_array($rules[$action])
                ? $rules[$action]
                : array();
            $rules[$action][(string) $groupId] = $decision ? 1 : 0;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__assets'))
            ->set($db->quoteName('rules') . ' = ' . $db->quote(json_encode($rules, JSON_UNESCAPED_SLASHES)))
            ->where($db->quoteName('id') . ' = ' . $assetId);
        $db->setQuery($query)->execute();
    }
}
