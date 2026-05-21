<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class JemSchemaTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function jemTableProvider(): iterable
    {
        foreach (array(
            'jem_events',
            'jem_venues',
            'jem_categories',
            'jem_cats_event_relations',
            'jem_register',
            'jem_groups',
            'jem_groupmembers',
            'jem_config',
            'jem_attachments',
            'jem_countries',
            'jem_links',
            'jem_types',
        ) as $table) {
            yield $table => array($table);
        }
    }

    #[DataProvider('jemTableProvider')]
    public function testExpectedJemTablesExist(string $table): void
    {
        self::assertContains(
            $this->db()->replacePrefix('#__' . $table),
            $this->db()->getTableList(),
            '#__' . $table . ' should exist in the configured Joomla database.'
        );
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function criticalColumnProvider(): iterable
    {
        yield 'events' => array('jem_events', array('id', 'title', 'dates', 'enddates', 'published', 'created_by', 'access', 'event_status', 'ticket_availability', 'type_id', 'attribs'));
        yield 'venues' => array('jem_venues', array('id', 'venue', 'alias', 'url', 'latitude', 'longitude', 'published', 'created_by', 'access', 'type_id', 'attribs'));
        yield 'categories' => array('jem_categories', array('id', 'catname', 'alias', 'parent_id', 'published', 'access', 'type_id'));
        yield 'attachments' => array('jem_attachments', array('id', 'object', 'file', 'name', 'description', 'frontend', 'access', 'created_by'));
        yield 'links' => array('jem_links', array('id', 'event_id', 'type', 'title', 'description', 'url', 'params', 'state', 'created_by'));
        yield 'types' => array('jem_types', array('id', 'name', 'alias', 'entity', 'translations', 'published', 'access', 'language'));
        yield 'config' => array('jem_config', array('keyname', 'value', 'access'));
    }

    #[DataProvider('criticalColumnProvider')]
    public function testCriticalJemColumnsExist(string $table, array $columns): void
    {
        $actual = array_keys($this->db()->getTableColumns('#__' . $table));

        foreach ($columns as $column) {
            self::assertContains($column, $actual, '#__' . $table . ' should define column ' . $column . '.');
        }
    }

    private function db(): DatabaseDriver
    {
        return Factory::getContainer()->get(DatabaseDriver::class);
    }
}
