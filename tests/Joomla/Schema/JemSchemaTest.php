<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Schema\ChangeSet;
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
            'jem_event_series',
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
        yield 'events' => array('jem_events', array('id', 'title', 'dates', 'enddates', 'timezone_mode', 'timezone', 'start_utc', 'end_utc', 'last_visit', 'series_id', 'series_order', 'published', 'created_by', 'access', 'event_status', 'ticket_availability', 'type_id', 'attribs'));
        yield 'venues' => array('jem_venues', array('id', 'venue', 'alias', 'url', 'district', 'level', 'capacity', 'timezone', 'email', 'phone', 'mobile', 'latitude', 'longitude', 'published', 'created_by', 'access', 'type_id', 'attribs'));
        yield 'categories' => array('jem_categories', array('id', 'catname', 'alias', 'parent_id', 'published', 'access', 'type_id'));
        yield 'attachments' => array('jem_attachments', array('id', 'object', 'file', 'name', 'description', 'frontend', 'access', 'created_by', 'downloads', 'last_download'));
        yield 'links' => array('jem_links', array('id', 'event_id', 'type', 'title', 'description', 'url', 'params', 'state', 'created_by'));
        yield 'types' => array('jem_types', array('id', 'name', 'alias', 'entity', 'translations', 'published', 'access', 'language'));
        yield 'countries' => array('jem_countries', array('id', 'continent', 'iso2', 'iso3', 'name', 'published'));
        yield 'config' => array('jem_config', array('keyname', 'value', 'access'));
        yield 'event series' => array('jem_event_series', array('id', 'root_event_id', 'title', 'series_type', 'created', 'created_by', 'modified', 'modified_by', 'published'));
    }

    #[DataProvider('criticalColumnProvider')]
    public function testCriticalJemColumnsExist(string $table, array $columns): void
    {
        $actual = array_keys($this->db()->getTableColumns('#__' . $table));

        foreach ($columns as $column) {
            self::assertContains($column, $actual, '#__' . $table . ' should define column ' . $column . '.');
        }
    }

    public function testJoomlaRecognisesEvery501SchemaStatement(): void
    {
        $changeSet = new ChangeSet($this->db(), JEM_TEST_ROOT . '/admin/sql/updates');
        $status = $changeSet->getStatus();
        $items = array_merge($status['unchecked'], $status['ok'], $status['error'], $status['skipped']);
        $items501 = array_values(array_filter(
            $items,
            static fn ($item): bool => basename((string) $item->file) === '5.0.1.sql'
        ));
        $recognised = array_values(array_filter(
            $items501,
            static fn ($item): bool => in_array($item->queryType, array('ADD_COLUMN', 'ADD_INDEX'), true)
        ));

        self::assertCount(25, $items501, 'Joomla must parse every statement in 5.0.1.sql separately.');
        self::assertCount(19, $recognised, 'Joomla must recognise all 5.0.1 table and index changes.');
        self::assertCount(16, array_filter($recognised, static fn ($item): bool => $item->queryType === 'ADD_COLUMN'));
        self::assertCount(3, array_filter($recognised, static fn ($item): bool => $item->queryType === 'ADD_INDEX'));

        $changeSet->check();
        $errors501 = array_filter(
            $changeSet->getStatus()['error'],
            static fn ($item): bool => basename((string) $item->file) === '5.0.1.sql'
        );
        self::assertSame(array(), array_values($errors501), 'The configured Joomla database must satisfy the 5.0.1 schema.');
    }

    private function db(): DatabaseDriver
    {
        return Factory::getContainer()->get(DatabaseDriver::class);
    }
}
