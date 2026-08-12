<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/classes/backendacl.class.php';

final class BackendAclPolicyTest extends TestCase
{
    #[DataProvider('permissionCombinations')]
    public function testResourcePermissionCombinations(
        string $type,
        string $operation,
        array $grants,
        ?int $owner,
        int $userId,
        bool $expected
    ): void {
        $authorise = static fn(string $action): bool => in_array($action, $grants, true);

        self::assertSame(
            $expected,
            JemBackendAclPolicy::allows($type, $operation, $owner, $userId, $authorise)
        );
    }

    public static function permissionCombinations(): iterable
    {
        yield 'core admin has full control' => array('event', 'delete', array('core.admin'), null, 7, true);
        yield 'event access opens only events' => array('event', 'access', array('jem.events.access'), null, 7, true);
        yield 'event access does not open venues' => array('venue', 'access', array('jem.events.access'), null, 7, false);
        yield 'create requires resource access' => array('event', 'create', array('jem.events.create'), null, 7, false);
        yield 'event create with access' => array('event', 'create', array('jem.events.access', 'jem.events.create'), null, 7, true);
        yield 'event edit with global edit' => array('event', 'edit', array('jem.events.access', 'jem.events.edit'), 9, 7, true);
        yield 'event edit own with matching stored owner' => array('event', 'edit', array('jem.events.access', 'jem.events.edit.own'), 7, 7, true);
        yield 'event edit own rejects another owner' => array('event', 'edit', array('jem.events.access', 'jem.events.edit.own'), 9, 7, false);
        yield 'event edit own rejects a missing stored record' => array('event', 'edit', array('jem.events.access', 'jem.events.edit.own'), null, 7, false);
        yield 'edit does not grant state changes' => array('event', 'edit.state', array('jem.events.access', 'jem.events.edit'), 7, 7, false);
        yield 'state permission is independent' => array('event', 'edit.state', array('jem.events.access', 'jem.events.edit.state'), null, 7, true);
        yield 'edit does not grant author changes' => array('event', 'edit.created', array('jem.events.access', 'jem.events.edit'), null, 7, false);
        yield 'author permission is independent' => array('event', 'edit.created', array('jem.events.access', 'jem.events.edit.created'), null, 7, true);
        yield 'venue author permission does not grant event author changes' => array('event', 'edit.created', array('jem.events.access', 'jem.venues.edit.created'), null, 7, false);
        yield 'core.manage on com_users does not grant author changes' => array('event', 'edit.created', array('jem.events.access', 'core.manage'), null, 7, false);
        yield 'venue delete is independent from event delete' => array('venue', 'delete', array('jem.venues.access', 'jem.events.delete'), null, 7, false);
        yield 'venue delete with venue permission' => array('venue', 'delete', array('jem.venues.access', 'jem.venues.delete'), null, 7, true);
        yield 'unknown resources are denied' => array('category', 'edit', array('core.admin'), null, 7, false);
    }

    public function testActionMapUsesSeparateEventAndVenueNamespaces(): void
    {
        self::assertSame('jem.events.edit.own', JemBackendAclPolicy::getAction('event', 'edit.own'));
        self::assertSame('jem.venues.edit.state', JemBackendAclPolicy::getAction('venue', 'edit.state'));
        self::assertSame('jem.events.edit.created', JemBackendAclPolicy::getAction('event', 'edit.created'));
        self::assertSame('jem.venues.edit.created', JemBackendAclPolicy::getAction('venue', 'edit.created'));
        self::assertNull(JemBackendAclPolicy::getAction('event', 'unknown'));
    }
}
