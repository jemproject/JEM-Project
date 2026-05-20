<?php

declare(strict_types=1);

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class JemModelInstantiationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();
        require_once JPATH_SITE . '/components/com_jem/factory.php';
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function adminModelProvider(): iterable
    {
        yield 'event model' => array('event.php', 'JemModelEvent', 'JemTableEvent');
        yield 'venue model' => array('venue.php', 'JemModelVenue', 'JemTableVenue');
        yield 'category model' => array('category.php', 'JemModelCategory', 'JemTableCategory');
    }

    #[DataProvider('adminModelProvider')]
    public function testAdminModelsInstantiateWithoutMutatingDatabase(string $file, string $modelClass, string $tableClass): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/' . $file;

        $model = new $modelClass(array('ignore_request' => true));

        self::assertInstanceOf(AdminModel::class, $model);
        self::assertTrue(method_exists($model, 'getTable'));

        $table = $model->getTable();

        self::assertInstanceOf(Table::class, $table);
        self::assertInstanceOf($tableClass, $table);
    }
}
