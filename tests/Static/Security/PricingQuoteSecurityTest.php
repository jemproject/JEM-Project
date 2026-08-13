<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PricingQuoteSecurityTest extends TestCase
{
    public function testBookingContextComesFromTheServerIdentity(): void
    {
        $service = $this->read('/site/classes/pricingquote.class.php');

        self::assertStringContainsString('final class JemPricingQuoteContext', $service);
        self::assertStringContainsString('getAuthorisedViewLevels', $service);
        self::assertStringContainsString('getAuthorisedGroups', $service);
        self::assertStringContainsString('JemPricingQuoteContext $context', $service);
        self::assertStringNotContainsString("\$context['accessLevels']", $service);
        self::assertStringNotContainsString("\$context['userGroups']", $service);
        self::assertStringNotContainsString("\$context['now']", $service);
        self::assertStringContainsString('$this->clock', $service);
    }

    public function testConfirmationRecalculatesAndLocksBeforeWriting(): void
    {
        $service = $this->read('/site/classes/pricingquote.class.php');
        $locked = $this->method($service, 'withLockedQuote');

        self::assertStringContainsString('transactionStart()', $locked);
        self::assertStringContainsString('lockEventReference($eventId)', $locked);
        self::assertStringContainsString('buildQuote($eventId, $selections, $context, true)', $locked);
        self::assertStringContainsString('hash_equals', $locked);
        self::assertStringContainsString('$operation($quote, $operationReference)', $locked);
        self::assertLessThan(strpos($locked, '$operation('), strpos($locked, 'buildQuote('));
        self::assertLessThan(strpos($locked, '$operation('), strpos($locked, 'hash_equals('));
    }

    public function testQuoteContractSupportsExactWaitingInventoryAndFingerprint(): void
    {
        $service = $this->read('/site/classes/pricingquote.class.php');
        $factory = $this->read('/site/factory.php');

        self::assertStringContainsString("'inventory_state'", $service);
        self::assertStringContainsString("'waiting_list'", $service);
        self::assertStringContainsString("'event_available'", $service);
        self::assertStringContainsString("'quote_fingerprint'", $service);
        self::assertStringContainsString('quoteFingerprint', $service);
        self::assertStringContainsString("classes/pricingquote.class.php", $factory);
    }

    private function method(string $php, string $name): string
    {
        $start = strpos($php, 'function ' . $name . '(');
        self::assertNotFalse($start, 'Method not found: ' . $name);
        $end = strpos($php, "\n    private function", $start);

        return substr($php, $start, ($end === false ? strlen($php) : $end) - $start);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
