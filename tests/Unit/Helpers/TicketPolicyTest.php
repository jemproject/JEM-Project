<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/ticketpolicy.class.php';
require_once JEM_TEST_ROOT . '/site/classes/ticketservice.class.php';

final class TicketPolicyTest extends TestCase
{
    /** @return iterable<string,array> Invalid monetary inputs that must never be rounded. */
    public static function invalidAmounts(): iterable
    {
        foreach (['-1', '1.001', '1e2', 'nan', '', '1,20', '01', '999999999999'] as $value) {
            yield $value ?: 'empty' => [$value];
        }
    }

    #[DataProvider('invalidAmounts')]
    public function testRejectsAmbiguousMoney(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemTicketPolicy::cents($amount);
    }

    public function testExactMoneyAndZeroPricedOrder(): void
    {
        self::assertSame(1010, JemTicketPolicy::cents('10.10'));
        self::assertSame('10.10', JemTicketPolicy::money(1010));
        $row = $this->row(['grand_total' => '0.00']);
        self::assertSame('free', JemTicketPolicy::payment($row, 0)['state']);
    }

    public function testUnknownIsNotFreeAndCancellationDoesNotInventRefund(): void
    {
        self::assertSame('unknown', JemTicketPolicy::payment($this->row(['grand_total' => null]), 0)['state']);
        $payment = JemTicketPolicy::payment($this->row(['status' => -1]), 2000);
        self::assertSame(2000, $payment['refund']);
        self::assertSame('refund_pending', $payment['state']);
        self::assertSame(2000, $payment['paid']);
    }

    public function testPartialCancellationRetainsCoverageOfRemainingPlaces(): void
    {
        $row = $this->row(['grand_total' => '10.00', 'places' => 1]);
        $payment = JemTicketPolicy::payment($row, 2000);
        self::assertSame(1000, $payment['refund']);
        self::assertSame(0, $payment['due']);
        self::assertSame('', JemTicketPolicy::blocked((object) ['published' => 1, 'event_status' => 'scheduled'], $row, $payment, 0));
    }

    public function testAllBlockingStatesAndAlreadyUsedPlaces(): void
    {
        $row = $this->row();
        foreach (['cancelled', 'postponed'] as $state) {
            self::assertSame('COM_JEM_TK_EVENT_CLOSED', JemTicketPolicy::blocked((object) ['published' => 1, 'event_status' => $state], $row, JemTicketPolicy::payment($row, 2000), 0));
        }
        foreach ([-2, 0, 2] as $publication) {
            self::assertSame('COM_JEM_TK_EVENT_CLOSED', JemTicketPolicy::blocked((object) ['published' => $publication, 'event_status' => 'scheduled'], $row, JemTicketPolicy::payment($row, 2000), 0));
        }
        $event = (object) ['published' => 1, 'event_status' => 'scheduled'];
        foreach ([$this->row(['status' => -1]), $this->row(['status' => 0]), $this->row(['waiting' => 1])] as $invalid) {
            self::assertSame('COM_JEM_TK_NOT_CONFIRMED', JemTicketPolicy::blocked($event, $invalid, JemTicketPolicy::payment($invalid, 2000), 0));
        }
        self::assertSame('COM_JEM_TK_PAYMENT_REQUIRED', JemTicketPolicy::blocked($event, $row, JemTicketPolicy::payment($row, 0), 0));
        self::assertSame('COM_JEM_TK_USED', JemTicketPolicy::blocked($event, $row, JemTicketPolicy::payment($row, 2000), 2));
    }

    public function testDedicatedRolesAndCategoryScope(): void
    {
        $user = new class {
            public int $id = 7;
            public function authorise(string $action, string $asset): bool
            { return $action === 'jem.tickets.admit' && $asset === 'com_jem.category.2'; }
        };
        self::assertTrue(JemTicketService::can($user, 'admit', [2]));
        self::assertFalse(JemTicketService::can($user, 'pay', [2]));
        self::assertFalse(JemTicketService::can($user, 'admit', [3]));
        $user->id = 0;
        self::assertFalse(JemTicketService::can($user, 'admit', [2]));
    }

    /** @param array $values Overrides. @return object Reservation fixture. */
    private function row(array $values = []): object
    {
        return (object) ($values + ['pricing_mode' => 'priced', 'status' => 1, 'waiting' => 0,
            'places' => 2, 'currency' => 'EUR', 'grand_total' => '20.00']);
    }
}
