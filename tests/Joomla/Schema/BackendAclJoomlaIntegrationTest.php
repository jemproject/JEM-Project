<?php

declare(strict_types=1);

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class BackendAclJoomlaIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootJoomlaSite();
    }

    public function testLegacyBackendManagersCanOpenNotificationAdministration(): void
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $db->setQuery('SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__usergroups'));
        $managerGroups = array_values(array_filter(
            array_map('intval', (array) $db->loadColumn()),
            static fn (int $groupId): bool => (bool) Access::checkGroup($groupId, 'core.manage', 'com_jem')
        ));

        self::assertNotSame(array(), $managerGroups, 'The installed JEM component should have at least one backend manager group.');
        foreach ($managerGroups as $groupId) {
            self::assertTrue(
                Access::checkGroup($groupId, 'jem.notifications.templates', 'com_jem'),
                'Backend manager group ' . $groupId . ' must be able to open Notifications.'
            );
            self::assertTrue(
                Access::checkGroup($groupId, 'jem.registrations.history', 'com_jem'),
                'Backend manager group ' . $groupId . ' must be able to open Registration History.'
            );
        }
    }
}
