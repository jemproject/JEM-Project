<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CategoryResourceAclTest extends TestCase
{
    public function testCategoryAssetsAreInstalledUpdatedAndRepaired(): void
    {
        $install = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');
        $table = (string) file_get_contents(JEM_TEST_ROOT . '/admin/tables/category.php');
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');
        $assetService = (string) file_get_contents(JEM_TEST_ROOT . '/admin/classes/categoryasset.class.php');

        self::assertStringContainsString('`asset_id` int(10) unsigned', $install);
        self::assertStringContainsString('ADD COLUMN `asset_id`', $update);
        self::assertStringContainsString("return 'com_jem.category.' . (int) \$this->id;", $table);
        self::assertStringContainsString('repair510CategoryAclSchema()', $script);
        self::assertStringContainsString('JemCategoryAsset::ensureSchema($db)', $script);
        self::assertStringContainsString('JemCategoryAsset::repair($db)', $script);
        self::assertStringContainsString('NO_ZERO_DATE', $assetService);
        self::assertStringContainsString('SET SESSION sql_mode', $assetService);
        self::assertStringContainsString("'com_jem.category.' . (int) \$category->id", $assetService);
    }

    public function testCategoryRulesAndOperationsUseStoredCategoryAssets(): void
    {
        $form = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/category.xml');
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/category.php');
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/categories.php');

        self::assertStringContainsString('name="rules"', $form);
        self::assertStringContainsString('section="category"', $form);
        self::assertStringContainsString("authorise('core.admin', 'com_jem')", $model);
        self::assertStringContainsString("canCategory('delete'", $controller);
        self::assertStringContainsString("canCategory('edit.state'", $controller);
    }

    public function testEventAclUsesEveryStoredOrSubmittedCategory(): void
    {
        $backendPolicy = (string) file_get_contents(JEM_TEST_ROOT . '/admin/classes/backendacl.class.php');
        $backendController = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/event.php');
        $backendListController = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/events.php');
        $backendCategoryField = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/fields/catoptions.php');
        $frontendUser = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/user.class.php');
        $frontendPolicy = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/resourceacl.class.php');

        self::assertStringContainsString('allowsEventCategories', $backendPolicy);
        self::assertStringContainsString('foreach ($assets as $asset)', $backendPolicy);
        self::assertStringContainsString('canEventCategories', $backendController);
        self::assertStringNotContainsString("can('event', 'edit');", $backendListController);
        self::assertStringContainsString("canEventCategories('create', array(\$categoryId))", $backendCategoryField);
        self::assertStringContainsString('selectedCategoryIds', $backendCategoryField);
        self::assertStringContainsString('getEventCategoryIdsForAcl', $frontendUser);
        self::assertStringContainsString('foreach ($assets as $asset)', $frontendPolicy);
        self::assertStringContainsString("(\$owner && (bool) \$this->authorise('core.edit.own', \$asset))", $frontendUser);
        self::assertStringContainsString("(array) \$this->getJemGroups(\$fields)", $frontendUser);
    }

    public function testFrontendUnpublishedListsRequireEveryAssignedCategory(): void
    {
        $eventsList = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');
        $categories = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/categories.php');

        foreach (array($eventsList, $categories) as $source) {
            self::assertStringContainsString('getJemCategories(', $source);
            self::assertStringContainsString('NOT EXISTS (SELECT 1 FROM #__jem_cats_event_relations AS acl_rel_denied', $source);
            self::assertStringContainsString('acl_rel_denied.catid NOT IN', $source);
        }

        self::assertStringContainsString('filter.unpublished.events.on_categories', $eventsList);
        self::assertStringContainsString('filter.unpublished.events.own_categories', $eventsList);
        self::assertStringNotContainsString("getJemGroups(array('editevent', 'publishevent'))", $eventsList);
    }
}
