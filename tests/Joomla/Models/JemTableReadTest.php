<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class JemTableReadTest extends JoomlaTestCase
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
    public static function tableClassProvider(): iterable
    {
        yield 'event' => array('admin/tables/event.php', 'JemTableEvent', '#__jem_events');
        yield 'venue' => array('admin/tables/venue.php', 'JemTableVenue', '#__jem_venues');
        yield 'category' => array('admin/tables/category.php', 'JemTableCategory', '#__jem_categories');
        yield 'type' => array('admin/tables/jem_types.php', 'jem_types', '#__jem_types');
        yield 'attachment' => array('admin/tables/jem_attachments.php', 'jem_attachments', '#__jem_attachments');
    }

    #[DataProvider('tableClassProvider')]
    public function testJemTablesInstantiateAgainstJoomlaDatabase(string $relativePath, string $className, string $tableName): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/' . substr($relativePath, strlen('admin/'));

        $db = $this->db();
        $table = new $className($db);

        self::assertInstanceOf(Table::class, $table);
        self::assertSame($this->db()->replacePrefix($tableName), $this->db()->replacePrefix($table->getTableName()));
    }

    #[DataProvider('tableClassProvider')]
    public function testJemTablesReturnFalseForInvalidPrimaryKeyLoad(string $relativePath, string $className): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/' . substr($relativePath, strlen('admin/'));

        $db = $this->db();
        $table = new $className($db);

        self::assertFalse($table->load(-999999999), $className . ' should not load a negative synthetic ID.');
    }

    public function testAttachmentTableRejectsInvalidObjectAndPathTraversalFile(): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/tables/jem_attachments.php';

        $db = $this->db();
        $table = new jem_attachments($db);
        $table->object = '../../event1';
        $table->file = '../payload.php';

        self::assertFalse($table->check());
    }

    public function testAttachmentTableNormalisesSafeMetadataWithoutStoring(): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/tables/jem_attachments.php';

        $db = $this->db();
        $table = new jem_attachments($db);
        $table->object = 'event1';
        $table->file = 'agenda.pdf';
        $table->name = '<script>alert(1)</script>Agenda';
        $table->description = '<img src=x onerror=alert(1)>Download';
        $table->frontend = 7;
        $table->access = -3;
        $table->ordering = '4';
        $table->created_by = '15';

        self::assertTrue($table->check());
        self::assertStringNotContainsString('<script', $table->name);
        self::assertStringNotContainsString('<img', $table->description);
        self::assertSame(1, $table->frontend);
        self::assertSame(1, $table->access);
        self::assertSame(4, $table->ordering);
        self::assertSame(15, $table->created_by);
    }

    private function db(): DatabaseDriver
    {
        return Factory::getContainer()->get(DatabaseDriver::class);
    }
}
