<?php

declare(strict_types=1);

use Joomla\Database\DatabaseFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise the real writer with connection-local temporary tables, never site records. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RegistrationCapacityTransactionTest extends TestCase
{
    private object $db;
    private bool $history;

    /** @return void Load Joomla's driver and isolate every table used by these transactions. */
    protected function setUp(): void
    {
        $joomla = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!$joomla || getenv('JEM_TEST_TEMP_TABLES') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_TEMP_TABLES=1 for isolated SQL tests.');
        }
        $loader = require $joomla . '/libraries/vendor/autoload.php';
        $loader->addPsr4('Joomla\\CMS\\', $joomla . '/libraries/src');
        require_once $joomla . '/configuration.php';
        require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
        if (is_file(JEM_TEST_ROOT . '/site/classes/registrationidentity.class.php')) {
            require_once JEM_TEST_ROOT . '/site/classes/registrationidentity.class.php';
        }
        require_once JEM_TEST_ROOT . '/site/classes/registrationservice.class.php';
        $config = new JConfig();
        $prefix = 'q2519_' . bin2hex(random_bytes(4)) . '_';
        $this->db = (new DatabaseFactory())->getDriver('mysqli', [
            'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
            'database' => $config->db, 'prefix' => $prefix,
        ]);
        $this->history = method_exists(JemRegistrationService::class, 'commercialItems');
        $schema = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        // Use this branch's schema, not the installed JEM version. No site records are read.
        // TEMPORARY tables disappear when this connection closes.
        foreach (['events', 'register', 'config', 'register_history', 'register_capacity_allocations', 'ticket_operations'] as $name) {
            if (!$this->history && !in_array($name, ['events', 'register'], true)) {
                continue;
            }
            self::assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_' . preg_quote($name, '/')
                . '`\s*\(.*?\)\s*ENGINE=[^;]+;/s', $schema, $matches));
            $this->db->setQuery(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $matches[0]))->execute();
        }
        if ($this->history) {
            $setting = (object) ['keyname' => 'registration_schema_ready', 'value' => '1'];
            $this->db->insertObject('#__jem_config', $setting);
        }
        foreach ([7, 8] as $id) {
            $event = (object) [
                'id' => $id, 'title' => 'Capacity regression ' . $id, 'alias' => 'capacity-' . $id,
                'dates' => '2099-12-01', 'maxplaces' => 6, 'minbookeduser' => 1, 'maxbookeduser' => 5,
                'published' => 1, 'waitinglist' => 1, 'event_status' => 'scheduled',
                'introtext' => '', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => '{}',
            ];
            $this->db->insertObject('#__jem_events', $event);
        }
        foreach ([[41, 7, 15, 1, 0], [42, 7, 16, 4, 0], [43, 7, 17, 1, 1],
            [51, 8, 15, 1, 0], [52, 8, 16, 4, 0], [53, 8, 17, 1, 1]] as [$id, $event, $uid, $places, $waiting]) {
            $row = (object) ['id' => $id, 'event' => $event, 'uid' => $uid, 'places' => $places,
                'status' => 1, 'waiting' => $waiting, 'uregdate' => '2026-11-12 10:00:00', 'uip' => '', 'comment' => ''];
            if ($this->history) {
                $row->reference = 'JEM-' . str_pad((string) $id, 12, '0', STR_PAD_LEFT);
                $row->revision = 1;
            }
            $this->db->insertObject('#__jem_register', $row);
        }
    }

    /** @return void A stale extra-place request preserves all three attendees and audit revisions. */
    public function testStaleRequestLeavesConfirmedAndWaitingRowsUnchanged(): void
    {
        $this->db->setQuery('UPDATE #__jem_register SET places=5 WHERE id=42')->execute();
        $before = $this->snapshot();
        try {
            (new JemRegistrationService($this->db))->save((object) ['id' => 41, 'event' => 7, 'uid' => 15, 'places' => 2], [
                'respectPlaces' => true, 'allowWaiting' => true,
            ]);
            self::fail('The full-event increase must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame(JemRegistrationService::CHANGE_UNAVAILABLE, $exception->getMessage());
        }
        self::assertSame($before, $this->snapshot());
        if ($this->history) {
            self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_history')->loadResult());
        }
    }

    /** @return void A later failure rolls back the earlier successful series occurrence and its history. */
    public function testSeriesRollbackAlsoRestoresEarlierOccurrence(): void
    {
        $this->db->setQuery('UPDATE #__jem_register SET places=5 WHERE id=52')->execute();
        $before = $this->snapshot();
        $rows = [(object) ['id' => 41, 'event' => 7, 'uid' => 15, 'places' => 2],
            (object) ['id' => 51, 'event' => 8, 'uid' => 15, 'places' => 2]];
        try {
            (new JemRegistrationService($this->db))->saveMany($rows, ['respectPlaces' => true, 'allowWaiting' => true]);
            self::fail('The second occurrence must reject the batch.');
        } catch (RuntimeException $exception) {
            self::assertSame(JemRegistrationService::CHANGE_UNAVAILABLE, $exception->getMessage());
        }
        self::assertSame($before, $this->snapshot());
        if ($this->history) {
            self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register_history')->loadResult());
        }
        // Prove that the first update is valid and can commit independently.
        $result = (new JemRegistrationService($this->db))->save($rows[0], ['respectPlaces' => true, 'allowWaiting' => true]);
        self::assertSame(2, (int) $result->after->places);
        self::assertSame(0, (int) $result->after->waiting);
        self::assertSame(2, (int) $this->db->setQuery('SELECT places FROM #__jem_register WHERE id=41')->loadResult());
    }

    /** @return array Full stored rows, including revision and payment fields where present. */
    private function snapshot(): array
    {
        return $this->db->setQuery('SELECT * FROM #__jem_register ORDER BY id')->loadAssocList();
    }
}
