<?php

declare(strict_types=1);

use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseFactory;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Execute the actual installer and venue gate with native Joomla ACL and disposable SQLite. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class VenueUseMigrationTest extends TestCase
{
    private ?DatabaseDriver $db = null;

    /** @return void Load libraries only; never load the installed site's configuration/database. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite') || !class_exists('SQLite3')) {
            self::markTestSkipped('Requires Joomla libraries, pdo_sqlite and sqlite3; no installed site is modified.');
        }

        define('JDEBUG', false);
        define('JPATH_BASE', $root);
        require $root . '/includes/defines.php';
        require $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require JEM_TEST_ROOT . '/script.php';
        require JEM_TEST_ROOT . '/site/classes/venueaccess.class.php';

        $this->db = (new DatabaseFactory())->getDriver('sqlite', ['database' => ':memory:', 'prefix' => 't_']);
        $container = new Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->alias(DatabaseInterface::class, 'DatabaseDriver');
        Factory::$database = $this->db;
        Factory::$language = Language::getInstance('en-GB');
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        (new ReflectionProperty(ComponentHelper::class, 'components'))->setValue(null, [
            'com_jem' => (object) ['option' => 'com_jem', 'enabled' => 1],
        ]);
        Factory::$application = new class {
            /** @return Dispatcher Native event dispatcher, without installed plugins. */
            public function getDispatcher(): Dispatcher
            {
                return new Dispatcher();
            }

            /** @param string $message Installer notice. @param string $type Severity. @return void */
            public function enqueueMessage($message, $type = 'message'): void
            {
            }

            /** @return Language Native language service for installer notices. */
            public function getLanguage(): Language
            {
                return Factory::$language;
            }

            /** @param string $key Configuration key. @param mixed $default Fallback. @return mixed */
            public function get($key, $default = null)
            {
                return $default;
            }
        };

        $this->execute('CREATE TABLE #__assets (id INTEGER PRIMARY KEY, parent_id INTEGER, lft INTEGER, rgt INTEGER, level INTEGER, name TEXT UNIQUE, title TEXT, rules TEXT)');
        $this->execute('CREATE TABLE #__usergroups (id INTEGER PRIMARY KEY, parent_id INTEGER, lft INTEGER, rgt INTEGER, title TEXT)');
        $this->execute('CREATE TABLE #__user_usergroup_map (user_id INTEGER, group_id INTEGER)');
        $this->execute('CREATE TABLE #__jem_venues (id INTEGER PRIMARY KEY, published INTEGER)');
        // IDs intentionally differ from tree order: child 3 must inherit from parent 30.
        $this->execute("INSERT INTO #__usergroups VALUES (1,0,0,11,'Public'),(20,1,1,8,'Registered'),(30,20,2,5,'Author'),(3,30,3,4,'Editor'),(10,20,6,7,'Other'),(40,1,9,10,'Blocked')");
        $this->execute('INSERT INTO #__user_usergroup_map VALUES (100,30),(101,3),(102,10),(103,30),(103,40)');
        $this->execute('INSERT INTO #__jem_venues VALUES (1,1),(2,1)');
        $this->execute("INSERT INTO #__assets VALUES (1,0,0,7,0,'root.1','Root','{}'),(2,1,1,6,1,'com_jem','JEM','{}'),(3,2,2,3,2,'com_jem.venue.1','Venue 1','{}'),(4,2,4,5,2,'com_jem.venue.2','Venue 2','{}')");
        Access::clearStatics();
    }

    /** @return void Dispose of the in-memory fixture. */
    protected function tearDown(): void
    {
        $this->db?->disconnect();
        parent::tearDown();
    }

    /** @param string $sql Fixture statement. @return void */
    private function execute(string $sql): void
    {
        $this->db->setQuery($sql)->execute();
    }

    /** @param string $asset Asset name. @param array $rules Native ACL rules. @return void */
    private function setRules(string $asset, array $rules): void
    {
        $this->execute('UPDATE #__assets SET rules=' . $this->db->quote(json_encode($rules, JSON_THROW_ON_ERROR))
            . ' WHERE name=' . $this->db->quote($asset));
        Access::clearStatics();
    }

    /** @return array Persisted component rules, not cached authorization results. */
    private function storedRules(): array
    {
        return json_decode($this->db->setQuery("SELECT rules FROM #__assets WHERE name='com_jem'")->loadResult(), true);
    }

    /** @param string $version Source release. @param string $type Install/update. @return void */
    private function migrate(string $version, string $type = 'update'): void
    {
        $installer = new com_jemInstallerScript();
        (new ReflectionProperty($installer, 'oldRelease'))->setValue($installer, $version);
        $gate = new ReflectionMethod($installer, 'shouldMigrateBackendAcl');
        if ($gate->invoke(null, $type, $version)) {
            (new ReflectionMethod($installer, 'migrateBackendAcl'))->invoke($installer, $type === 'update');
        }
    }

    /** @param int $id Synthetic user ID. @return User Native Joomla user using real group/asset authorization. */
    private function user(int $id): User
    {
        $user = new User();
        $user->id = $id;
        $user->guest = 0;
        return $user;
    }

    /** @return iterable<string, array> Source actions, inherited decisions and resource-level restrictions. */
    public static function upgradeCases(): iterable
    {
        $create = ['jem.events.create' => [30 => true]];
        $cases = [
            'create' => [$create, [], [], [30 => true], true, true, false],
            'edit' => [['jem.events.edit' => [30 => true]], [], [], [30 => true], true, true, false],
            'edit own' => [['jem.events.edit.own' => [30 => true]], [], [], [30 => true], true, true, false],
            'venue create only' => [['jem.venues.create' => [30 => true]], [], [], [], false, false, false],
            'no permissions' => [[], [], [], [], false, false, false],
            'explicit allow' => [$create + ['jem.venues.use' => [30 => true]], [], [], [30 => true], true, true, false],
            'explicit deny' => [$create + ['jem.venues.use' => [30 => false]], [], [], [30 => false], false, false, false],
            'inherited allow' => [$create + ['jem.venues.use' => [20 => true]], [], [], [20 => true], true, true, true],
            'inherited deny' => [$create + ['jem.venues.use' => [20 => false]], [], [], [20 => false], false, false, false],
            'root allow' => [$create, ['jem.venues.use' => [20 => true]], [], [], true, true, true],
            'root deny' => [$create, ['jem.venues.use' => [20 => false]], [], [], false, false, false],
            'child deny' => [$create + ['jem.venues.use' => [3 => false]], [], [], [3 => false, 30 => true], true, false, false],
            'venue deny' => [$create, [], ['jem.venues.use' => [30 => false]], [30 => true], false, false, false],
            'inherited event permission' => [['jem.events.edit.own' => [20 => true]], [], [], [20 => true], true, true, true],
            'root event permission' => [[], ['jem.events.edit' => [30 => true]], [], [30 => true], true, true, false],
            'denied event permission' => [['jem.events.edit' => [20 => true, 30 => false]], [], [], [20 => true], true, true, true],
            'multiple groups deny wins' => [$create + ['jem.venues.use' => [40 => false]], [], [], [40 => false, 30 => true], true, true, false],
        ];
        foreach (['5.0.1', '5.0.2'] as $version) {
            foreach ($cases as $name => $args) {
                yield $version . '/' . $name => [$version, ...$args];
            }
        }
    }

    /**
     * Migrate real rules and test both persisted grants and native venue authorization.
     *
     * @param string $version Source version.
     * @param array $component Component rules.
     * @param array $root Root asset rules.
     * @param array $venue Venue-specific rules.
     * @param array $expected Expected explicit venue-use rules (no redundant descendants).
     * @param bool $authorUse Expected author permission for venue 1.
     * @param bool $childUse Expected descendant permission for venue 1.
     * @param bool $otherUse Expected sibling permission for venue 1.
     * @return void
     */
    #[DataProvider('upgradeCases')]
    public function testUpgrade(
        string $version,
        array $component,
        array $root,
        array $venue,
        array $expected,
        bool $authorUse,
        bool $childUse,
        bool $otherUse
    ): void {
        $component['custom.action'] = [30 => true, 40 => false];
        $this->setRules('root.1', $root);
        $this->setRules('com_jem', $component);
        $this->setRules('com_jem.venue.1', $venue);
        $before = [];
        foreach (['jem.events.create', 'jem.events.edit', 'jem.events.edit.own', 'jem.venues.edit'] as $action) {
            $before[$action] = Access::checkGroup(30, $action, 'com_jem');
        }

        $this->migrate($version);
        $stored = $this->storedRules();
        self::assertEquals($expected, $stored['jem.venues.use'] ?? []);
        foreach ($component as $action => $rules) {
            if ($action !== 'jem.venues.use') {
                // Joomla's Asset table serializes boolean ACL decisions as 0/1.
                self::assertSame(array_map('boolval', $rules), array_map('boolval', $stored[$action]));
            }
        }
        foreach ($before as $action => $allowed) {
            self::assertSame($allowed, Access::checkGroup(30, $action, 'com_jem'), $action . ' must not change');
        }
        $author = $this->user(100);
        self::assertSame($authorUse, JemVenueAccess::canUse($author, 1));
        self::assertSame($childUse, JemVenueAccess::canUse($this->user(101), 1));
        self::assertSame($otherUse, JemVenueAccess::canUse($this->user(102), 1));
        self::assertSame($authorUse && !isset($component['jem.venues.use'][40]), JemVenueAccess::canUse($this->user(103), 1));
        self::assertSame($authorUse, in_array(1, JemVenueAccess::getAuthorisedIds($author), true));
        self::assertTrue(JemVenueAccess::canUse($author, 0));
        self::assertFalse(JemVenueAccess::canUse($author, 999));
        self::assertFalse(JemVenueAccess::canUse(new User(), 1));
        if ($venue) {
            self::assertTrue(JemVenueAccess::canUse($author, 2), 'Resource denial must not deny every venue');
        }

        $this->migrate($version);
        self::assertSame($stored, $this->storedRules(), 'Repeating migration must not materialise inherited grants');
    }

    /** @return iterable<string, array{string, string}> Cases outside the granular first-entry policy. */
    public static function excludedVersions(): iterable
    {
        foreach (['', '4.5.0', '5.0.0', '5.1.0dev', '5.1.0beta5', '5.1.0beta6', '5.1.0'] as $version) {
            yield 'update/' . $version => [$version, 'update'];
        }
        yield 'fresh install' => ['', 'install'];
    }

    /** @param string $version Source version. @param string $type Installer type. @return void */
    #[DataProvider('excludedVersions')]
    public function testDoesNotRegrantOutsideFirstGranularUpgrade(string $version, string $type): void
    {
        $rules = ['jem.events.create' => [30 => true]];
        $this->setRules('com_jem', $rules);
        $this->migrate($version, $type);
        self::assertSame($rules, $this->storedRules());
        self::assertFalse(JemVenueAccess::canUse($this->user(100), 1));
    }
}
