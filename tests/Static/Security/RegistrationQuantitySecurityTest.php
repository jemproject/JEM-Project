<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationQuantitySecurityTest extends TestCase
{
    public function testFrontendUsesStrictQuantityOperations(): void
    {
        $factory = (string) file_get_contents(JEM_TEST_ROOT . '/site/factory.php');
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        $manager = (string) file_get_contents(JEM_TEST_ROOT . '/site/controllers/attendees.php');

        self::assertStringContainsString('classes/registrationquantity.class.php', $factory);
        self::assertStringContainsString('JemRegistrationQuantity::parseOptional(', $model);
        self::assertStringContainsString('JemRegistrationQuantity::resolveResponse(', $model);
        self::assertStringNotContainsString("getInt('addplaces'", $model);
        self::assertStringNotContainsString("getInt('cancelplaces'", $model);
        self::assertStringContainsString("array('attendeeadd', 'attendees.attendeeadd')", $manager);
        self::assertStringContainsString('JemRegistrationQuantity::parseManagerSelection(', $manager);
        self::assertStringNotContainsString("getInt('addplaces'", $manager);
        self::assertStringNotContainsString("getInt('cancelplaces'", $manager);
    }

    public function testTransactionalWriterRevalidatesQuantityPolicy(): void
    {
        $service = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/registrationservice.class.php');
        $siteAttendee = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/attendee.php');
        $adminAttendee = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/attendee.php');

        self::assertStringContainsString('$this->assertQuantityPolicy($after);', $service);
        self::assertStringContainsString('JemRegistrationQuantity::assertStoredRow(', $service);
        self::assertStringContainsString("array('minbookeduser', 'maxbookeduser')", $service);
        self::assertStringContainsString('JemRegistrationService($this->_db))->save(', $siteAttendee);
        self::assertStringContainsString('JemRegistrationService($db))->saveMany(', $adminAttendee);
    }
}
