<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PricedRegistrationServiceTest extends TestCase
{
    public function testPoint4fUsesTheQuoteTransactionAndAppendOnlyRevisions(): void
    {
        $priced = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/pricedregistration.class.php');
        $registration = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/registrationservice.class.php');

        self::assertStringContainsString('final class JemPricedRegistrationService', $priced);
        self::assertStringContainsString('->withLockedQuote(', $priced);
        self::assertStringNotContainsString('transactionStart(', $priced);
        self::assertStringContainsString("\$saveOptions['commercialLines']", $priced);
        self::assertStringContainsString("\$saveOptions['forceRevision']", $priced);
        self::assertStringContainsString("'registration_revision' => (int) \$after->revision", $registration);
        self::assertStringContainsString('Registration places must equal the current admission quantities.', $registration);
    }

    public function testExplicitModificationCancellationAndReactivationContractsAreVisible(): void
    {
        $priced = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/pricedregistration.class.php');
        $registration = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/registrationservice.class.php');

        self::assertStringContainsString('applyLockedTerms(', $priced);
        self::assertStringContainsString('// Reactivation deliberately uses current prices.', $priced);
        self::assertStringContainsString("? (string) \$before->price_locked_at", $priced);
        self::assertStringContainsString('commercialItems(', $registration);
        self::assertStringContainsString('A cancelled priced registration must be reactivated from a current quote.', $registration);
    }

    public function testLegacyWriterAndWaitingPromotionCannotBypassPricedRules(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        $promotion = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/waitinglistpromotion.class.php');

        self::assertStringContainsString('COM_JEM_PRICED_REGISTRATION_REQUIRES_ORDER', $model);
        self::assertStringContainsString('selectPromotableLocked(', $promotion);
        self::assertStringContainsString('$effectiveForce = $force && !$priced;', $promotion);
    }
}
