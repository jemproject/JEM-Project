<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/relocationnotice.class.php';
require_once JEM_TEST_ROOT . '/site/classes/notificationtemplatecatalog.class.php';
require_once JEM_TEST_ROOT . '/site/classes/notificationtemplaterenderer.class.php';

/** Exercise the exact notice formatter and the default named-token Mailer template. */
final class RelocationNotificationTest extends TestCase
{
    public function testNoticeExplainsSavedDistributionMoneyAndDecision(): void
    {
        $labels = parse_ini_file(JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini');
        $change = ['before' => [['label' => 'Area A', 'quantity' => 1], ['label' => 'Area B', 'quantity' => 0]],
            'after' => [['quantity' => 0], ['quantity' => 2]], 'currency' => 'EUR', 'reason' => 'Two places together',
            'financial' => ['old' => 1000, 'quoted' => 3000, 'difference' => 2000, 'adjustment' => -2000,
                'total' => 1000, 'paid' => 1000, 'due' => 0, 'refund' => 0, 'choice' => 'consolidation_supplement']];
        $details = JemRelocationNotice::details($change, static fn ($key) => $labels[$key] ?? $key);
        self::assertStringContainsString('Area A: 1 -> 0', $details);
        self::assertStringContainsString('Area B: 0 -> 2', $details);
        self::assertStringContainsString('Agreed adjustment: -20.00 EUR', $details);
        self::assertStringContainsString('Already collected: 10.00 EUR', $details);
        self::assertStringContainsString('Refund pending: 0.00 EUR', $details);
        self::assertStringContainsString('No supplement - place consolidation', $details);
        $ini = parse_ini_file(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/language/en-GB/plg_jem_mailer.ini');
        $body = JemNotificationTemplateRenderer::render($ini['PLG_JEM_MAILER_RELOCATION_BODY'], [
            'user_name' => 'Ana', 'event_title' => 'Concert', 'comment' => $details, 'site_name' => 'Demo',
        ]);
        self::assertStringContainsString('Hello Ana', $body);
        self::assertStringContainsString($details, $body);
        self::assertStringContainsString('does not confirm a new payment or a completed refund', $body);
        $definition = JemNotificationTemplateCatalog::findByLanguageKeys('PLG_JEM_MAILER_RELOCATION_SUBJECT', 'PLG_JEM_MAILER_RELOCATION_BODY');
        self::assertContains('comment', $definition['recommended_tokens']);
        self::assertSame([], $definition['body_legacy_tokens']);
    }
}
