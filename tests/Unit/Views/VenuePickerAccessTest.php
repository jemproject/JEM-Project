<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class VenuePickerAccessTest extends TestCase
{
    #[DataProvider('accessProvider')]
    public function testPickerAccessIsSeparateFromVenueManagement(
        array $componentGrants,
        array $venueGrants,
        array $publishedIds,
        bool $pending,
        bool $expected
    ): void {
        require_once JEM_TEST_ROOT . '/tests/Support/venue_picker_access_stubs.php';
        require_once JEM_TEST_ROOT . '/admin/classes/backendacl.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/venueaccess.class.php';
        require_once JEM_TEST_ROOT . '/admin/views/venueelement/view.html.php';
        require_once JEM_TEST_ROOT . '/admin/views/venues/view.html.php';

        JemFactory::$user = new class ($componentGrants, $venueGrants) {
            public int $id = 42;

            public function __construct(private array $componentGrants, private array $venueGrants)
            {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $key === 'guest' ? false : $default;
            }

            public function authorise(string $action, string $asset = 'com_jem'): bool
            {
                if ($asset === 'com_jem') {
                    return in_array($action, $this->componentGrants, true);
                }

                return $action === 'jem.venues.use' && in_array($asset, $this->venueGrants, true);
            }
        };
        VenuePickerDatabase::$publishedIds = $publishedIds;
        JemAclMigrationStatus::$isPending = $pending;

        self::assertSame($expected, $this->passesAccessCheck(new JemViewVenueelement()));
        $managerAllowed = JemHelperBackend::can('venue', 'access');
        self::assertSame($managerAllowed, $this->passesAccessCheck(new JemViewVenues()));

        $ids = JemVenueAccess::getAuthorisedIds(JemFactory::getUser(), true);
        if (!$pending) {
            self::assertTrue(VenuePickerDatabase::$publishedOnly);
            foreach ($ids as $id) {
                self::assertContains($id, $publishedIds);
                self::assertTrue(
                    JemFactory::getUser()->authorise('core.admin')
                    || JemFactory::getUser()->authorise('jem.venues.use', 'com_jem.venue.' . $id)
                );
            }
        } else {
            self::assertSame([], $ids);
        }
        if (!$managerAllowed && $expected) {
            self::assertSame([1], $ids, 'Only the permitted published venue may be selected.');
        }
    }

    private function passesAccessCheck(object $view): bool
    {
        try {
            $view->display();
            self::fail('The view must either reject access or start rendering.');
        } catch (VenuePickerAccessGranted $exception) {
            return true;
        } catch (Exception $exception) {
            self::assertSame(403, $exception->getCode());
            self::assertSame('JERROR_ALERTNOAUTHOR', $exception->getMessage());

            return false;
        }
    }

    public static function accessProvider(): iterable
    {
        yield 'delegated editor can use A but not B' => [
            ['jem.events.access'], ['com_jem.venue.1'], [1, 2], false, true,
        ];
        yield 'event access alone is insufficient' => [['jem.events.access'], [], [1, 2], false, false];
        yield 'venue use without event access is insufficient' => [[], ['com_jem.venue.1'], [1, 2], false, false];
        yield 'unpublished permitted venue does not grant picker access' => [
            ['jem.events.access'], ['com_jem.venue.1'], [2], false, false,
        ];
        yield 'no published venues' => [['jem.events.access'], ['com_jem.venue.1'], [], false, false];
        yield 'pending migration fails closed for editor' => [
            ['jem.events.access'], ['com_jem.venue.1'], [1, 2], true, false,
        ];
        yield 'venue manager keeps access with an empty list' => [['jem.venues.access'], [], [], false, true];
        yield 'venue manager without use permission keeps access' => [['jem.venues.access'], [], [1, 2], false, true];
        yield 'administrator keeps access' => [['core.admin'], [], [1, 2], false, true];
    }
}
