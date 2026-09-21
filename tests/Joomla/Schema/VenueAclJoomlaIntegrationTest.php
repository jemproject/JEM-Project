<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class VenueAclJoomlaIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();
    }

    public function testEveryJemVenueHasAnAssetBelowTheComponent(): void
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $columns = array_change_key_case(
            $db->getTableColumns($db->replacePrefix('#__jem_venues'), false),
            CASE_LOWER
        );

        self::assertArrayHasKey('asset_id', $columns);

        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('v.id'),
                $db->quoteName('v.asset_id'),
                $db->quoteName('a.name', 'asset_name'),
                $db->quoteName('pa.name', 'asset_parent_name'),
            ))
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->leftJoin($db->quoteName('#__assets', 'a') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('v.asset_id'))
            ->leftJoin($db->quoteName('#__assets', 'pa') . ' ON ' . $db->quoteName('pa.id') . ' = ' . $db->quoteName('a.parent_id'));
        $db->setQuery($query);

        foreach ((array) $db->loadObjectList() as $venue) {
            self::assertGreaterThan(0, (int) $venue->asset_id);
            self::assertSame('com_jem.venue.' . (int) $venue->id, (string) $venue->asset_name);
            self::assertSame('com_jem', (string) $venue->asset_parent_name);
        }
    }
}
