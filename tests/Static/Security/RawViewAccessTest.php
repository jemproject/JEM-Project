<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RawViewAccessTest extends TestCase
{
    public function testSingleEventIcsAndPdfEnforceTheComputedViewPermission(): void
    {
        $view = self::read('/site/views/event/view.raw.php');
        $guard = "JemFrontendAccess::enforceViewAccess((bool) \$row->params->get('access-view')";

        self::assertSame(2, substr_count($view, $guard));
        self::assertLessThan(strpos($view, 'JemHelper::icalAddEvent'), strpos($view, $guard));
        self::assertLessThan(strpos($view, 'JemPdf::createDocument'), strrpos($view, $guard));
    }

    public function testCategoryAndVenueIcsEnforceContainerAccess(): void
    {
        $category = self::read('/site/views/category/view.raw.php');
        $venue = self::read('/site/views/venue/view.raw.php');

        self::assertStringContainsString(
            'JemFrontendAccess::enforceViewAccess(!empty($categoryItem->user_has_access_category), $app)',
            $category
        );
        self::assertStringContainsString(
            'JemFrontendAccess::enforceViewAccess(!empty($venue->user_has_access_venue), $app)',
            $venue
        );
        self::assertLessThan(strpos($category, 'JemHelper::sendCalendar'), strpos($category, '$categoryItem ='));
        self::assertLessThan(strpos($venue, 'JemHelper::sendCalendar'), strrpos($venue, '$venue ='));
    }

    public function testRestrictedPdfViewsEnforceTheirResourcePermissions(): void
    {
        $contracts = array(
            '/site/views/category/view.raw.php' => 'user_has_access_category',
            '/site/views/venue/view.raw.php' => 'user_has_access_venue',
            '/site/views/typeevents/view.raw.php' => 'user_has_access_type',
            '/site/views/typevenues/view.raw.php' => 'user_has_access_type',
            '/site/views/categories/view.raw.php' => 'user_has_access_type',
            '/site/views/specialdays/view.raw.php' => "authorise('core.manage', 'com_jem')",
        );

        foreach ($contracts as $path => $permission) {
            $view = self::read($path);

            self::assertStringContainsString('JemFrontendAccess::enforceViewAccess', $view, $path);
            self::assertStringContainsString($permission, $view, $path);
        }
    }

    public function testEveryMultiEventIcsUsesThePublicationAndAccessFilteredBaseModel(): void
    {
        $views = array(
            '/site/views/eventslist/view.raw.php' => '/site/models/eventslist.php',
            '/site/views/calendar/view.raw.php' => '/site/models/calendar.php',
            '/site/views/weekcal/view.raw.php' => '/site/models/weekcal.php',
            '/site/views/annualcalendar/view.raw.php' => '/site/models/annualcalendar.php',
            '/site/views/category/view.raw.php' => '/site/models/categorycal.php',
            '/site/views/venue/view.raw.php' => '/site/models/venuecal.php',
        );

        $baseModel = self::read('/site/models/eventslist.php');
        self::assertStringContainsString("if ((\$format == 'raw') || (\$format == 'feed'))", $baseModel);
        self::assertStringContainsString("\$this->setState('filter.published', 1);", $baseModel);
        self::assertStringContainsString("JemHelper::getEventPublicationWhere('a', false)", $baseModel);

        foreach ($views as $viewPath => $modelPath) {
            $view = self::read($viewPath);
            $model = self::read($modelPath);

            self::assertStringContainsString('JemHelper::sendCalendar', $view, $viewPath);

            if ($modelPath !== '/site/models/eventslist.php') {
                self::assertStringContainsString('extends JemModelEventslist', $model, $modelPath);
            }
        }
    }

    public function testAssignedUnavailableEventTypesAreNotTreatedAsUnassigned(): void
    {
        $listModel = self::read('/site/models/eventslist.php');
        $eventModel = self::read('/site/models/event.php');

        self::assertStringContainsString('jt.id IS NOT NULL AND jt.access IN', $listModel);
        self::assertStringNotContainsString('jt.id IS NULL OR jt.access IN', $listModel);
        self::assertStringContainsString('jt.id IS NOT NULL AND jt.access IN', $eventModel);
        self::assertStringNotContainsString('jt.id IS NULL OR jt.access IN', $eventModel);
    }

    private static function read(string $path): string
    {
        $content = file_get_contents(JEM_TEST_ROOT . $path);

        self::assertNotFalse($content, $path);

        return (string) $content;
    }
}
