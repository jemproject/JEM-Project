<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\Sqlite\SqliteDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise the venue policy with Joomla's real query driver and isolated SQL rows. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventVenueAssignmentTest extends TestCase
{
    private SqliteDriver $db;

    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to an installed Joomla root.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        if (!SqliteDriver::isSupported()) {
            self::markTestSkipped('Enable pdo_sqlite and sqlite3 for the isolated SQL tests.');
        }
        $this->db = new SqliteDriver(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $this->db->setQuery('CREATE TABLE t_jem_venues (id INTEGER PRIMARY KEY, venue TEXT, published INTEGER)')->execute();
        $this->db->setQuery("INSERT INTO t_jem_venues VALUES (10,'Assigned venue',1),(20,'Unpublished venue',0),(30,'Other venue',1)")->execute();
        $this->db->setQuery('CREATE TABLE t_jem_config (keyname TEXT, value TEXT)')->execute();
        $container = new Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db);
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        require_once JEM_TEST_ROOT . '/site/classes/aclmigrationstatus.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/venueaccess.class.php';
        JemAclMigrationStatus::reset();
    }

    /** @return iterable<string, array{?int,int,bool,array,bool}> Edit/create/copy policy cases. */
    public static function assignments(): iterable
    {
        yield 'retain denied venue' => [10, 10, false, [], true];
        yield 'retain allowed venue' => [10, 10, false, [10], true];
        yield 'retain unpublished denied venue' => [20, 20, false, [], true];
        yield 'retain physically deleted venue' => [99, 99, false, [], false];
        yield 'deleted venue even with use grant' => [99, 99, false, [99], false];
        yield 'new denied venue' => [null, 10, true, [], false];
        yield 'new allowed venue' => [null, 10, true, [10], true];
        yield 'new without venue' => [null, 0, true, [], true];
        yield 'change denied venue' => [10, 30, false, [], false];
        yield 'change allowed venue' => [10, 30, false, [30], true];
        yield 'clear denied venue' => [10, 0, false, [], true];
        yield 'clear deleted venue' => [99, 0, false, [], true];
        yield 'copy with denied venue' => [10, 10, true, [], false];
        yield 'copy with allowed venue' => [10, 10, true, [10], true];
        yield 'copy without venue' => [10, 0, true, [], true];
        yield 'no loaded event cannot retain' => [null, 10, false, [], false];
        yield 'negative requested ID' => [10, -1, false, [], false];
    }

    #[DataProvider('assignments')]
    public function testAssignmentPolicy(?int $previous, int $requested, bool $new, array $allowed, bool $expected): void
    {
        $stored = $previous === null ? null : (object) ['id' => 100, 'locid' => $previous];
        self::assertSame($expected, JemVenueAccess::canAssignToEvent($this->user($allowed), $requested, $stored, $new));
        if ($stored) {
            self::assertSame($previous, $stored->locid, 'Checking permission must not change the stored association.');
        }
    }

    public function testRetainingDoesNotExpandSelectableVenues(): void
    {
        $user = $this->user([]);
        self::assertTrue(JemVenueAccess::canAssignToEvent($user, 10, (object) ['id' => 100, 'locid' => 10], false));
        self::assertFalse(JemVenueAccess::canUse($user, 10));
        self::assertSame([], JemVenueAccess::getAuthorisedIds($user));
    }

    public function testMissingEventIdDoesNotQualifyForRetention(): void
    {
        self::assertFalse(JemVenueAccess::canAssignToEvent($this->user([]), 10, (object) ['id' => 0, 'locid' => 10], false));
    }

    public function testGuestsCannotRetainAnExistingVenue(): void
    {
        self::assertFalse(JemVenueAccess::canAssignToEvent($this->user([], true), 10, (object) ['id' => 100, 'locid' => 10], false));
    }

    public function testMigrationGateStillBlocksRetentionAndClearing(): void
    {
        $this->db->setQuery("INSERT INTO t_jem_config VALUES ('acl_migration_state','{\"complete\":false}')")->execute();
        JemAclMigrationStatus::reset();
        $event = (object) ['id' => 100, 'locid' => 10];
        self::assertFalse(JemVenueAccess::canAssignToEvent($this->user([10]), 10, $event, false));
        self::assertFalse(JemVenueAccess::canAssignToEvent($this->user([10]), 0, $event, false));
    }

    public function testSuperUserCannotRetainDeletedVenue(): void
    {
        $user = $this->user([], false, true);
        self::assertFalse(JemVenueAccess::canAssignToEvent($user, 99, (object) ['id' => 100, 'locid' => 99], false));
        self::assertTrue(JemVenueAccess::canAssignToEvent($user, 30, (object) ['id' => 100, 'locid' => 10], false));
    }

    public function testEventFormsAllowClearingWithoutChangingOtherForms(): void
    {
        foreach (['admin', 'site'] as $client) {
            $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $client . '/models/forms/event.xml');
            self::assertSame('true', (string) $xml->xpath('//field[@name="locid"]')[0]['clear']);
        }
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        self::assertNotSame('true', (string) $xml->xpath('//field[@name="defaultVenue"]')[0]['clear']);
        foreach (['admin/models/fields/modal/venue.php', 'admin/models/fields/venue.php', 'site/models/fields/modal/venue.php'] as $file) {
            $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
            self::assertStringContainsString("Text::_('COM_JEM_VENUE_SELECTION_UNAVAILABLE')", $source);
        }
    }

    public function testSaveUsesStoredAssociationAndCopyBoundary(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        self::assertStringContainsString('JemVenueAccess::canAssignToEvent(', $source);
        self::assertStringContainsString("\$existingEvent, \$new || \$task === 'save2copy'", $source);
        self::assertStringContainsString('COM_JEM_EVENT_ERROR_VENUE_MISSING', $source);
    }

    /**
     * Supply controlled user permissions while keeping existence checks in SQL.
     *
     * @param array<int> $allowed Venue IDs the user may assign.
     * @param bool $guest Whether the actor is anonymous.
     * @param bool $admin Whether the actor has core.admin.
     * @return object Actor implementing the permission interface used by JEM.
     */
    private function user(array $allowed, bool $guest = false, bool $admin = false): object
    {
        return new class ($allowed, $guest, $admin) {
            public int $id;
            public function __construct(private array $allowed, private bool $guest, private bool $admin)
            {
                $this->id = $guest ? 0 : 100;
            }
            public function get($key, $default = null)
            {
                return $key === 'guest' ? $this->guest : $default;
            }
            public function authorise($action, $asset = null): bool
            {
                return $action === 'core.admin' ? $this->admin
                    : in_array((int) substr((string) $asset, strlen('com_jem.venue.')), $this->allowed, true);
            }
        };
    }
}
