<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/site/classes/invitationpolicy.class.php';

/** Pool accounting and bearer capability invariants. */
final class InvitationPolicyTest extends TestCase
{
    private function row(int $status, int $places = 2): object
    {
        return (object) ['invitation_kind' => 'reserved', 'invitation_quantity' => 2,
            'status' => $status, 'places' => $places, 'waiting' => 0, 'invitation_revoked' => 0];
    }

    public function testPendingInvitationConsumesOnlyTheExistingPool(): void
    {
        self::assertSame(5, JemInvitationPolicy::poolAfter(5, null, $this->row(0), 3));
        self::assertSame(['assigned' => 2, 'confirmed' => 0], JemInvitationPolicy::allocation($this->row(0)));
    }

    public function testAcceptTransfersWithoutDoubleCounting(): void
    {
        self::assertSame(3, JemInvitationPolicy::poolAfter(5, $this->row(0), $this->row(1), 3));
        self::assertSame(['assigned' => 0, 'confirmed' => 2], JemInvitationPolicy::allocation($this->row(1)));
    }

    public function testDeclineDoesNotReleaseTheOrganiserPoolToThePublic(): void
    {
        self::assertSame(5, JemInvitationPolicy::poolAfter(5, $this->row(0), $this->row(-1), 0));
    }

    public function testPartialCancellationReturnsOnlyCancelledOrganiserPlaces(): void
    {
        self::assertSame(4, JemInvitationPolicy::poolAfter(3, $this->row(1, 2), $this->row(1, 1), 0));
        self::assertSame(5, JemInvitationPolicy::poolAfter(3, $this->row(1), $this->row(-1), 0));
    }

    public function testOpenInvitationsDoNotConsumeThePool(): void
    {
        $row = $this->row(0);
        $row->invitation_kind = 'open';
        self::assertSame(['assigned' => 0, 'confirmed' => 0], JemInvitationPolicy::allocation($row));
    }

    public function testOtherPendingHoldsCannotBeTaken(): void
    {
        $this->expectException(DomainException::class);
        JemInvitationPolicy::poolAfter(2, null, $this->row(0), 1);
    }

    public function testWaitingAndRevokedInvitationsDoNotClaimConfirmedPlaces(): void
    {
        $row = $this->row(1); $row->waiting = 1;
        self::assertSame(0, JemInvitationPolicy::allocation($row)['confirmed']);
        $row = $this->row(0); $row->invitation_revoked = 1;
        self::assertSame(0, JemInvitationPolicy::allocation($row)['assigned']);
    }

    public function testCapabilityIsRandomAndStoredAsADigest(): void
    {
        $token = JemInvitationPolicy::token();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $token);
        self::assertNotSame($token, JemInvitationPolicy::token());
        self::assertNotSame($token, JemInvitationPolicy::digest($token));
    }

    public function testTicketLocatorCannotBeUsedAsInvitationToken(): void
    {
        $this->expectException(DomainException::class);
        JemInvitationPolicy::digest('JEM-RESERVATION-123');
    }

    public function testExpiryBoundaryIsClosed(): void
    {
        $row = (object) ['invitation_expires' => '2026-12-01 12:00:00'];
        self::assertTrue(JemInvitationPolicy::expired($row, '2026-12-01 12:00:00'));
        self::assertFalse(JemInvitationPolicy::expired($row, '2026-12-01 11:59:59'));
    }

    public function testExternalEmailNormalisation(): void
    {
        self::assertSame('ana@example.org', JemInvitationPolicy::email(' Ana@Example.org '));
        $this->expectException(DomainException::class);
        JemInvitationPolicy::email("ana@example.org\r\nBcc: other@example.org");
    }

    public function testComplimentaryKeepsOriginalPriceAsProvenance(): void
    {
        $quote = ['subtotal_net' => '10.00', 'tax_total' => '2.10', 'grand_total' => '12.10',
            'lines' => [['conditions' => [], 'unit_gross' => '12.10']]];
        $free = JemInvitationPolicy::complimentary($quote);
        self::assertSame('0.00', $free['grand_total']);
        self::assertSame('0.00', $free['lines'][0]['line_gross']);
        self::assertSame('12.10', $free['lines'][0]['conditions']['invitation_original_unit_gross']);
    }
}
