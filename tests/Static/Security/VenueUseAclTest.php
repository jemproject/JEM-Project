<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VenueUseAclTest extends TestCase
{
    public function testVenueAssetsAndUsePermissionAreDefined(): void
    {
        $access = (string) file_get_contents(JEM_TEST_ROOT . '/admin/access.xml');
        $install = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');
        $table = (string) file_get_contents(JEM_TEST_ROOT . '/admin/tables/venue.php');
        $form = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/venue.xml');

        self::assertStringContainsString('name="jem.venues.use"', $access);
        self::assertStringContainsString('<section name="venue">', $access);
        self::assertStringContainsString('`asset_id` int(10) unsigned', $install);
        self::assertStringContainsString('ALTER TABLE `#__jem_venues` ADD COLUMN `asset_id`', $update);
        self::assertStringContainsString("return 'com_jem.venue.' . (int) \$this->id;", $table);
        self::assertStringContainsString('name="rules"', $form);
        self::assertStringContainsString('section="venue"', $form);
    }

    public function testEveryEventVenueSelectionIsFilteredAndValidated(): void
    {
        $backendChooser = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/venueelement.php');
        $frontendChooser = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/editevent.php');
        $eventModel = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $eventController = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/event.php');
        $eventsView = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/events/view.html.php');

        self::assertStringContainsString('JemVenueAccess::getAuthorisedIds', $backendChooser);
        self::assertStringContainsString('JemVenueAccess::getAuthorisedIds', $frontendChooser);
        self::assertStringContainsString('JemVenueAccess::getAuthorisedIds', $eventsView);
        self::assertStringContainsString('JemVenueAccess::canUse', $eventModel);
        self::assertStringContainsString('JemVenueAccess::canUse', $eventController);
        self::assertStringContainsString('COM_JEM_EVENT_ERROR_VENUE_NOT_ALLOWED', $eventModel);
    }
}
