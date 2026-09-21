<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class CategoryAclJoomlaIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();
    }

    public function testEveryJemCategoryHasTheExpectedJoomlaAsset(): void
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $columns = array_change_key_case(
            $db->getTableColumns($db->replacePrefix('#__jem_categories'), false),
            CASE_LOWER
        );

        self::assertArrayHasKey('asset_id', $columns);

        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('c.id'),
                $db->quoteName('c.parent_id'),
                $db->quoteName('c.asset_id'),
                $db->quoteName('a.name', 'asset_name'),
                $db->quoteName('pa.name', 'asset_parent_name'),
            ))
            ->from($db->quoteName('#__jem_categories', 'c'))
            ->leftJoin($db->quoteName('#__assets', 'a') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('c.asset_id'))
            ->leftJoin($db->quoteName('#__assets', 'pa') . ' ON ' . $db->quoteName('pa.id') . ' = ' . $db->quoteName('a.parent_id'))
            ->where($db->quoteName('c.id') . ' > 1')
            ->order($db->quoteName('c.lft') . ' ASC');
        $db->setQuery($query);
        $categories = (array) $db->loadObjectList();

        foreach ($categories as $category) {
            self::assertGreaterThan(0, (int) $category->asset_id);
            self::assertSame('com_jem.category.' . (int) $category->id, (string) $category->asset_name);
            self::assertSame(
                (int) $category->parent_id > 1
                    ? 'com_jem.category.' . (int) $category->parent_id
                    : 'com_jem',
                (string) $category->asset_parent_name
            );
        }
    }
}
