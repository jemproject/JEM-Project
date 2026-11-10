<?php

declare(strict_types=1);

use JemProject\Tests\MailerBody\Mailer;
use JemProject\Tests\MailerBody\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verify the actual plugin formatting method without connecting a mail transport. */
final class MailerEventStatusTest extends TestCase
{
    /** @return void Load the production private method and its source English translations. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/mailer_body_environment.php';
        \JemProject\Tests\MailerBody\loadMailer();
        Text::$strings = parse_ini_file(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/language/en-GB/plg_jem_mailer.ini', false, INI_SCANNER_RAW);
    }

    /** @return iterable<string,array{string,string}> Every valid event status and legacy fallback. */
    public static function statuses(): iterable
    {
        foreach (['scheduled' => 'Scheduled', 'cancelled' => 'Cancelled', 'postponed' => 'Postponed',
            'rescheduled' => 'Rescheduled', 'moved_online' => 'Moved online', 'unknown' => 'Scheduled'] as $value => $label) {
            yield $value => [$value, $label];
        }
    }

    /** @param string $status Stored value. @param string $label Public label. @return void Preserve the publication message. */
    #[DataProvider('statuses')]
    public function testEventEditNotificationIncludesLifecycle(string $status, string $label): void
    {
        $mailer = new Mailer(false);
        $method = new ReflectionMethod($mailer, '_appendEventStatusInfo');
        self::assertSame("Published\nEvent status: " . $label,
            $method->invoke($mailer, 'Published', (object) ['event_status' => $status]));
    }
}
