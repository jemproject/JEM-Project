<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationidentity.class.php';
require_once JEM_TEST_ROOT . '/site/classes/registrationtransition.class.php';
require_once JEM_TEST_ROOT . '/site/classes/registrationservice.class.php';

final class RegistrationServiceTest extends TestCase
{
    public function testCreationTracksAllSemanticFields(): void
    {
        $after = $this->registration(0, 7, 15, 1, 0, 2, 'note');

        self::assertSame(
            array('event', 'uid', 'places', 'status', 'waiting', 'comment'),
            JemRegistrationService::changedFields(null, $after)
        );
        self::assertSame('created', JemRegistrationService::inferAction(null, $after));
    }

    public function testCancellationAndPromotionActionsAreExplicit(): void
    {
        $attending = $this->registration(41, 7, 15, 1, 0, 1, '');
        $cancelled = clone $attending;
        $cancelled->status = -1;
        $waiting = clone $attending;
        $waiting->waiting = 1;

        self::assertSame('cancelled', JemRegistrationService::inferAction($attending, $cancelled));
        self::assertSame('promoted', JemRegistrationService::inferAction($waiting, $attending));
        self::assertSame(array('status'), JemRegistrationService::changedFields($attending, $cancelled));
    }

    public function testNoOpAndPlaceChangesAreDistinguished(): void
    {
        $before = $this->registration(41, 7, 15, 1, 0, 1, '');
        $same = clone $before;
        $changed = clone $before;
        $changed->places = 3;

        self::assertSame(array(), JemRegistrationService::changedFields($before, $same));
        self::assertSame('updated', JemRegistrationService::inferAction($before, $same));
        self::assertSame(array('places'), JemRegistrationService::changedFields($before, $changed));
        self::assertSame('places_changed', JemRegistrationService::inferAction($before, $changed));
    }

    public function testLegacyZeroActivationValueBelongsToAnActiveAccount(): void
    {
        $method = new ReflectionMethod(JemRegistrationService::class, 'activationRequiresVerification');

        self::assertFalse($method->invoke(null, ''));
        self::assertFalse($method->invoke(null, '0'));
        self::assertFalse($method->invoke(null, ' 0 '));
        self::assertTrue($method->invoke(null, 'pending-activation-token'));
    }

    private function registration(int $id, int $event, int $uid, int $status, int $waiting, int $places, string $comment): object
    {
        return (object) compact('id', 'event', 'uid', 'status', 'waiting', 'places', 'comment');
    }
}
