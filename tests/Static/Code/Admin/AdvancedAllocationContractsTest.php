<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdvancedAllocationContractsTest extends TestCase
{
    public function testStableVenueAliasesAreGeneratedServerSide(): void
    {
        $service = (string) file_get_contents(JEM_TEST_ROOT . '/admin/classes/venuecapacity.class.php');

        self::assertStringContainsString('$baseCode = self::normaliseCode($name);', $service);
        self::assertStringContainsString("if (\$normalised['space_id'] < 1)", $service);
        self::assertStringContainsString("if (\$normalised['layout_id'] < 1)", $service);
        self::assertStringContainsString("\$space['space_code'] = (string) \$ownedSpace['space_code'];", $service);
        self::assertStringContainsString("\$space['layout_code'] = (string) \$ownedSpace['layout_code'];", $service);
    }

    public function testProgrammeItemsAndSeriesKeepPhysicalAllocationsConsistent(): void
    {
        $service = $this->read('/admin/classes/eventpricingcapacity.class.php');
        $model = $this->read('/admin/models/event.php');

        self::assertStringContainsString('assertProgrammeAllocation(', $service);
        self::assertStringContainsString('COM_JEM_EVENT_PROGRAMME_ALLOCATION_REQUIRED', $service);
        self::assertStringContainsString('COM_JEM_EVENT_PROGRAMME_ALLOCATION_OUTSIDE_PARENT', $service);
        self::assertStringContainsString('synchronisePhysicalSeriesAllocation(', $model);
        self::assertStringContainsString('eventHasRegistrations(', $model);
        self::assertStringContainsString('JemEventPricingCapacityService::saveChildren(', $model);
        self::assertStringContainsString('JemSpaceAvailabilityService::assertAvailable(', $model);
        self::assertStringContainsString('JemEventPricingCapacityService::assertProgrammeAllocation(', $model);
    }

    public function testSpaceConflictsUseUtcHalfOpenIntervalsAndAuditedOverrides(): void
    {
        $availability = $this->read('/admin/classes/spaceavailability.class.php');
        $form = $this->read('/admin/models/forms/event.xml');
        $install = $this->read('/admin/sql/install.mysql.utf8.sql');

        self::assertStringContainsString('return $firstStart < $secondEnd && $firstEnd > $secondStart;', $availability);
        self::assertStringContainsString("->where('e.start_utc < '", $availability);
        self::assertStringContainsString("->where('e.end_utc > '", $availability);
        self::assertStringContainsString("->where('e.published <> -2')", $availability);
        self::assertStringContainsString("event_status <> ", $availability);
        self::assertStringContainsString('$parentEventId > 0', $availability);
        self::assertStringContainsString('COM_JEM_EVENT_SPACE_CONFLICT_REASON_REQUIRED', $availability);
        self::assertStringContainsString('#__jem_space_conflict_overrides', $availability);
        self::assertStringContainsString('name="space_conflict_override"', $form);
        self::assertStringContainsString('name="space_conflict_reason"', $form);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_space_conflict_overrides`', $install);
    }

    public function testVenueConfigurationRefreshIsLocalCsrfProtectedAndAuthorised(): void
    {
        $controller = $this->read('/admin/controllers/event.php');
        $view = $this->read('/admin/views/event/tmpl/edit_capacity.php');

        self::assertStringContainsString("Session::checkToken('get')", $controller);
        self::assertStringContainsString('JemFeaturePolicy::FEATURE_VENUE_CAPACITY', $controller);
        self::assertStringContainsString('$this->allowEdit(', $controller);
        self::assertStringContainsString('$this->allowAdd()', $controller);
        self::assertStringContainsString("fetch('index.php?'", $view);
        self::assertStringContainsString('query.set(ajaxToken', $view);
        self::assertStringNotContainsString("fetch('http", $view);
    }

    private function read(string $path): string
    {
        $file = JEM_TEST_ROOT . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }
}
