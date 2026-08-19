<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CapacityRegistrationContractsTest extends TestCase
{
    public function testCapacitySelectionsAreValidatedAndStoredPerRegistrationRevision(): void
    {
        $service = $this->read('/site/classes/registrationservice.class.php');

        self::assertStringContainsString('prepareCapacityAllocations(', $service);
        self::assertStringContainsString('persistCapacityAllocations(', $service);
        self::assertStringContainsString('#__jem_register_capacity_allocations', $service);
        self::assertStringContainsString('registration_revision', $service);
        self::assertStringContainsString('The selected capacity areas must equal the number of places.', $service);
        self::assertStringContainsString('The requested capacity area no longer has enough available places.', $service);
        self::assertStringContainsString('r.revision = a.registration_revision', $service);
        self::assertStringContainsString('JemRegistrationTransition::WAITING_LIST', $service);
    }

    public function testSiteAndAdministratorUseTheSameCapacityAllocationService(): void
    {
        $siteModel = $this->read('/site/models/event.php');
        $siteView = $this->read('/site/views/event/view.html.php');
        $siteForm = $this->read('/site/views/event/tmpl/default_capacityareas.php');
        $adminModel = $this->read('/admin/models/attendee.php');
        $adminForm = $this->read('/admin/views/attendee/tmpl/default.php');
        $adminList = $this->read('/admin/views/attendees/tmpl/default.php');

        self::assertStringContainsString("get('capacity_areas', array(), 'array')", $siteModel);
        self::assertStringContainsString("'capacityAllocations' =>", $siteModel);
        self::assertStringContainsString('capacityOptions(', $siteView);
        self::assertStringContainsString('name="capacity_areas[', $siteForm);
        self::assertStringContainsString('jem-capacity-area-quantity', $siteForm);
        self::assertStringContainsString("\$data['capacity_areas']", $adminModel);
        self::assertStringContainsString("'capacityAllocations' =>", $adminModel);
        self::assertStringContainsString('jem-capacity-area-quantity', $adminForm);
        self::assertStringContainsString('$this->capacityBreakdowns', $adminList);
    }

    public function testSchemaInstallerAndReportsUseCapacityOnlyAllocations(): void
    {
        $install = $this->read('/admin/sql/install.mysql.utf8.sql');
        $update = $this->read('/admin/sql/updates/mysql/5.1.0.sql');
        $installer = $this->read('/script.php');
        $statistics = $this->read('/admin/models/statistics.php');

        foreach (array($install, $update, $installer) as $source) {
            self::assertStringContainsString('#__jem_register_capacity_allocations', $source);
        }
        foreach (array('register_id', 'registration_revision', 'event_id', 'venue_capacity_area_id', 'venue_layout_id', 'quantity') as $column) {
            self::assertMatchesRegularExpression(
                '/CREATE TABLE IF NOT EXISTS `#__jem_register_capacity_allocations`[\s\S]+?`' . $column . '`/i',
                $install
            );
        }
        self::assertStringContainsString("from(\$db->quoteName('#__jem_register_capacity_allocations', 'a'))", $statistics);
        self::assertStringContainsString('snapshotCapacityPools(', $statistics);
        self::assertStringNotContainsString("from(\$db->quoteName('#__jem_capacity_pools', 'p'))", $statistics);
    }

    private function read(string $path): string
    {
        $file = JEM_TEST_ROOT . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }
}
