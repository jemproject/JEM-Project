<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Actual schedule validation and parent policy against connection-local SQL fixtures. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ProgrammeParentJoomlaIntegrationTest extends TestCase
{
    private ?\Joomla\Database\Mysqli\MysqliDriver $db = null;

    /** @return void Load Joomla and create temporary tables without changing installed records. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (getenv('JEM_TEST_WRITABLE') !== '1' || !is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires local JEM_TEST_JOOMLA_ROOT and JEM_TEST_WRITABLE=1 for temporary SQL tables.');
        }
        define('JPATH_BASE', $root);
        require $root . '/includes/defines.php';
        $loader = require $root . '/libraries/vendor/autoload.php';
        $loader->addPsr4('Joomla\\CMS\\', $root . '/libraries/src');
        require $root . '/configuration.php';
        $config = new JConfig();
        self::assertContains($config->host, ['localhost', '127.0.0.1']);
        $this->db = new \Joomla\Database\Mysqli\MysqliDriver(['host' => $config->host,
            'user' => $config->user, 'password' => $config->password, 'database' => $config->db, 'prefix' => 'qa2510_']);
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        $container->set(\Joomla\CMS\HTML\Registry::class, new \Joomla\CMS\HTML\Registry());
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$language = new class {
            /** @return string Source language key, without loading installed translations. */
            public function _($key, ...$args)
            {
                return $key;
            }
        };
        require_once JEM_TEST_ROOT . '/tests/Support/programme_parent_environment.php';
        require_once JEM_TEST_ROOT . '/site/models/fields/eventparent.php';
        require_once JEM_TEST_ROOT . '/admin/tables/event.php';
        require_once JEM_TEST_ROOT . '/tests/Support/programme_parent_table.php';
        $this->sql('CREATE TEMPORARY TABLE #__jem_events (id INT PRIMARY KEY, title VARCHAR(100), dates DATE,
            enddates DATE NULL, times TIME NULL, endtimes TIME NULL, access INT, parent_event_id INT NULL,
            locid INT, type_id INT, created_by INT, published INT, publish_up DATETIME NULL, publish_down DATETIME NULL)');
        $this->sql('CREATE TEMPORARY TABLE #__jem_types (id INT, entity INT, published INT, access INT)');
        $this->sql('CREATE TEMPORARY TABLE #__jem_categories (id INT, published INT, access INT)');
        $this->sql('CREATE TEMPORARY TABLE #__jem_cats_event_relations (itemid INT, catid INT)');
        $this->sql('INSERT INTO #__jem_types VALUES (1,1,1,1),(2,1,1,2),(3,1,0,1)');
        $this->sql('INSERT INTO #__jem_categories VALUES (1,1,1),(2,1,2),(3,0,1)');
        $this->sql("INSERT INTO #__jem_events (id,title,dates,access,parent_event_id,locid,type_id,created_by,published) VALUES
            (10,'Pending parent','2026-11-11',1,0,1,1,99,0),
            (11,'Unrelated secret title','2026-11-11',1,0,1,1,99,0),
            (12,'Published parent','2026-11-11',1,0,1,1,99,1),
            (100,'Editable child','2026-11-11',1,10,1,1,5,0),
            (101,'Other child','2026-11-11',1,11,1,1,99,0)");
        $this->sql('INSERT INTO #__jem_cats_event_relations VALUES (10,1),(11,1),(12,1)');
    }

    /** @return void Disconnect to discard all temporary tables. */
    protected function tearDown(): void
    {
        $this->db?->disconnect();
    }

    /** @return iterable<string, array> Form and stored representations of inclusive bounds. */
    public static function scheduleCases(): iterable
    {
        $parent = ['dates' => '2026-11-11', 'enddates' => '2026-12-02', 'times' => '08:00:00', 'endtimes' => '10:00:00'];
        $child = ['dates' => '2026-11-11', 'enddates' => null, 'times' => '08:00', 'endtimes' => '10:00'];
        yield 'equal start' => [$child, $parent, true];
        yield 'equal end when editing parent' => [array_replace($child, ['dates' => '2026-12-02', 'times' => '08:00:00', 'endtimes' => '10:00:00']), array_replace($parent, ['times' => '08:00', 'endtimes' => '10:00']), true];
        yield 'early child' => [array_replace($child, ['times' => '07:59']), $parent, false];
        yield 'late child' => [array_replace($child, ['dates' => '2026-12-02', 'endtimes' => '10:01']), $parent, false];
        yield 'one second early' => [array_replace($child, ['times' => '07:59:59']), $parent, false];
        yield 'one second late' => [array_replace($child, ['dates' => '2026-12-02', 'endtimes' => '10:00:01']), $parent, false];
        yield 'interior' => [array_replace($child, ['times' => '09:00']), $parent, true];
        yield 'missing child end' => [array_replace($child, ['endtimes' => null]), $parent, true];
        yield 'missing parent end' => [array_replace($child, ['dates' => '2026-12-02', 'times' => '23:00', 'endtimes' => '23:59']), array_replace($parent, ['endtimes' => null]), true];
        yield 'date only child outside timed first day' => [array_replace($child, ['times' => null, 'endtimes' => null]), $parent, false];
        yield 'same day equality' => [$child, array_replace($parent, ['enddates' => null]), true];
    }

    /**
     * @param array $child Candidate session schedule.
     * @param array $parent Programme schedule.
     * @param bool $expected Whether the session fits.
     * @return void Verify the production table's comparison, including second precision.
     */
    #[DataProvider('scheduleCases')]
    public function testScheduleBounds(array $child, array $parent, bool $expected): void
    {
        $table = (new ReflectionClass(JemTableEvent::class))->newInstanceWithoutConstructor();
        self::assertSame($expected, (new ReflectionMethod($table, 'isScheduleInside'))->invoke($table, (object) $child, (object) $parent));
    }

    /** @return void An author can select their pending course; unrelated pending courses stay hidden. */
    public function testOwnPendingCourseAndPublicationWindows(): void
    {
        $this->sql('UPDATE #__jem_events SET created_by=5 WHERE id=10');
        $selection = JemEventParentPolicy::selection(JemFactory::getUser());
        self::assertArrayHasKey(10, $selection['options']);
        self::assertArrayNotHasKey(11, $selection['options']);
        self::assertSame(10, JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), 0, 10));
        $this->sql("UPDATE #__jem_events SET published=1, publish_up='2099-01-01' WHERE id IN (10,12)");
        $selection = JemEventParentPolicy::selection(JemFactory::getUser());
        self::assertArrayHasKey(10, $selection['options']);
        self::assertArrayNotHasKey(12, $selection['options']);
    }

    /** @return void An authorised editor retains another author's actual pending parent. */
    public function testStoredPendingParentCanBeKeptChangedOrExplicitlyUnlinked(): void
    {
        $selection = JemEventParentPolicy::selection(JemFactory::getUser(), 100);
        self::assertFalse($selection['locked']);
        self::assertArrayHasKey(10, $selection['options']);
        self::assertArrayNotHasKey(11, $selection['options']);
        self::assertSame(10, JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), 100, null));
        self::assertSame(10, JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), 100, '10'));
        self::assertSame(12, JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), 100, 12));
        self::assertSame(0, JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), 100, '0'));
        $options = (new ReflectionMethod(JFormFieldEventparent::class, 'getOptions'))->invoke($this->field(100, 10));
        self::assertContains(10, array_map(static fn ($option) => (int) $option->value, $options));
    }

    /** @return void A publisher editing another author's child keeps the actual pending parent. */
    public function testPublisherCanRetainParentWhenApprovingAnotherAuthorsChild(): void
    {
        JemFactory::$userId = 7;
        JemFactory::$canEditAny = true;
        self::assertSame(10, JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), 100, 10));
        self::assertArrayHasKey(10, JemEventParentPolicy::selection(JemFactory::getUser(), 100)['options']);
        self::assertArrayNotHasKey(11, JemEventParentPolicy::selection(JemFactory::getUser(), 100)['options']);
    }

    /** @return void A saved child's relationship does not grant rights to use its pending parent in a copy. */
    public function testCopyDoesNotReuseStoredParentException(): void
    {
        self::assertSame(10, JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), 100, 10));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('COM_JEM_EVENT_PARENT_NOT_ALLOWED');
        JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), 0, 10);
    }

    /** @return void Both table entry points use inclusive normalized bounds with stored SQL times. */
    public function testChildAndParentValidationEntryPoints(): void
    {
        $this->sql("UPDATE #__jem_events SET times='08:00:00', endtimes='10:00:00' WHERE id IN (10,100)");
        $table = (new ReflectionClass(ProgrammeParentTableFixture::class))->newInstanceWithoutConstructor();
        $table->id = 100;
        $table->dates = '2026-11-11';
        $table->enddates = null;
        $table->times = '08:00';
        $table->endtimes = '10:00';
        $table->locid = 1;
        $parent = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=10')->loadObject();
        self::assertTrue((new ReflectionMethod($table, 'isInsideParentSchedule'))->invoke($table, $parent));
        $table->id = 10;
        self::assertTrue((new ReflectionMethod($table, 'childrenRemainInsideParent'))->invoke($table));
        $table->endtimes = '09:59';
        self::assertFalse((new ReflectionMethod($table, 'childrenRemainInsideParent'))->invoke($table));
    }

    /** @return iterable<string, array{string}> Reasons an existing parent must stay private and unchanged. */
    public static function unavailableParents(): iterable
    {
        yield 'restricted event' => ['UPDATE #__jem_events SET access=2 WHERE id=10'];
        yield 'restricted category' => ['UPDATE #__jem_cats_event_relations SET catid=2 WHERE itemid=10'];
        yield 'unpublished category' => ['UPDATE #__jem_cats_event_relations SET catid=3 WHERE itemid=10'];
        yield 'restricted type' => ['UPDATE #__jem_events SET type_id=2 WHERE id=10'];
        yield 'unpublished type' => ['UPDATE #__jem_events SET type_id=3 WHERE id=10'];
        yield 'restricted venue' => ['UPDATE #__jem_events SET locid=99 WHERE id=10'];
        yield 'trashed parent' => ['UPDATE #__jem_events SET published=-2 WHERE id=10'];
        yield 'archived parent' => ['UPDATE #__jem_events SET published=2 WHERE id=10'];
        yield 'parent became a child' => ['UPDATE #__jem_events SET parent_event_id=12 WHERE id=10'];
        yield 'missing parent' => ['DELETE FROM #__jem_events WHERE id=10'];
    }

    /**
     * @param string $change SQL change to the fixture parent's eligibility.
     * @return void Omitted or manipulated values cannot detach a hidden parent or expose its name.
     */
    #[DataProvider('unavailableParents')]
    public function testUnavailableParentIsReadOnlyAndPreserved(string $change): void
    {
        $this->sql($change);
        $selection = JemEventParentPolicy::selection(JemFactory::getUser(), 100);
        self::assertTrue($selection['locked']);
        self::assertArrayNotHasKey(10, $selection['options']);
        foreach ([null, 0, 11, 12] as $requested) {
            self::assertSame(10, JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), 100, $requested));
        }
        $html = (new ReflectionMethod(JFormFieldEventparent::class, 'getInput'))->invoke($this->field(100, 11));
        self::assertStringContainsString('COM_JEM_EVENT_PARENT_UNAVAILABLE', $html);
        self::assertStringNotContainsString('Pending parent', $html);
        self::assertStringNotContainsString('Unrelated secret title', $html);
        self::assertStringNotContainsString('<select', $html);
    }

    /** @return iterable<string, array{int, mixed}> Invalid replacements or new associations. */
    public static function forbiddenParents(): iterable
    {
        yield 'unrelated pending' => [100, 11];
        yield 'new event pending other author' => [0, 10];
        yield 'copy cannot inherit pending other author' => [0, 11];
        yield 'nonexistent parent' => [100, 999];
        yield 'self parent' => [100, 100];
        yield 'malformed id' => [100, '10xyz'];
        yield 'negative id' => [100, -1];
    }

    /**
     * @param int $id Edited child, or zero for a new item.
     * @param mixed $requested Submitted parent value.
     * @return void Reject invalid changes before event saving.
     */
    #[DataProvider('forbiddenParents')]
    public function testForbiddenParentSubmission(int $id, $requested): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('COM_JEM_EVENT_PARENT_NOT_ALLOWED');
        JemEventParentPolicy::resolveSubmission(JemFactory::getUser(), $id, $requested);
    }

    /** @return void A forged child id cannot retrieve that child's pending parent. */
    public function testUnauthorisedChildDoesNotDiscloseItsParent(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JERROR_ALERTNOAUTHOR');
        JemEventParentPolicy::selection(JemFactory::getUser(), 101);
    }

    /** @return void Guests do not gain unpublished candidates through created_by=0. */
    public function testGuestDoesNotOwnAnonymousEvents(): void
    {
        JemFactory::$userId = 0;
        $this->sql('UPDATE #__jem_events SET created_by=0 WHERE id=10');
        self::assertArrayNotHasKey(10, JemEventParentPolicy::selection(JemFactory::getUser())['options']);
    }

    /** @param int $id Child id. @param int $value Submitted field value. @return JFormFieldEventparent Real field. */
    private function field(int $id, int $value): JFormFieldEventparent
    {
        $form = new Form('programme-parent');
        $form->load('<form><field name="id" type="hidden" /></form>');
        $form->setValue('id', null, $id);
        $field = new JFormFieldEventparent($form);
        $field->setup(new SimpleXMLElement('<field name="parent_event_id" type="eventparent" />'), $value);
        return $field;
    }

    /** @param string $query SQL against connection-local fixtures. @return void */
    private function sql(string $query): void
    {
        $this->db->setQuery($query)->execute();
    }
}
