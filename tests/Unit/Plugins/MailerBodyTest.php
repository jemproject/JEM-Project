<?php
/**
 * @package JEM
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use JemProject\Tests\MailerBody\Factory;
use JemProject\Tests\MailerBody\JemHelper;
use JemProject\Tests\MailerBody\Mail;
use JemProject\Tests\MailerBody\Mailer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailerBodyTest extends TestCase
{
    /** @return void Load the actual private send method without the Joomla plugin constructor. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/mailer_body_environment.php';
        \JemProject\Tests\MailerBody\loadMailer();
    }

    /** @return void Reset transport captures and logs for each case. */
    protected function setUp(): void
    {
        Factory::$mail = new Mail();
        JemHelper::$logs = [];
    }

    /** @return iterable<string, array{bool, string, string}> Plain text and independently specified HTML. */
    public static function bodies(): iterable
    {
        $cases = [
            'markup' => ['Tom & Jerry <b>VIP</b>', 'Tom &amp; Jerry &lt;b&gt;VIP&lt;/b&gt;'],
            'quotes' => ['"Hello" O\'Reilly <mail@example.invalid>',
                '&quot;Hello&quot; O&#039;Reilly &lt;mail@example.invalid&gt;'],
            'literal entities' => ['&amp; &lt;b&gt; &#39;', '&amp;amp; &amp;lt;b&amp;gt; &amp;#39;'],
            'unicode' => ["Espa\u{00F1}a / \u{65E5}\u{672C} / \u{1F600}", "Espa\u{00F1}a / \u{65E5}\u{672C} / \u{1F600}"],
            'newlines' => ["A\r\nB\rC\nD", "A<br />\r\nB<br />\rC<br />\nD"],
            'invalid UTF-8' => ["Broken \xC3( text", "Broken \u{FFFD}( text"],
            'URL' => ['https://example.invalid/?a=1&b=2', 'https://example.invalid/?a=1&amp;b=2'],
            'empty' => ['', ''],
        ];
        foreach ($cases as $name => [$plain, $html]) {
            yield $name . ' as HTML' => [true, $plain, $html];
            yield $name . ' as text' => [false, $plain, $plain];
        }
    }

    /**
     * Check the complete send method and unchanged envelope/alternative handling.
     *
     * @param bool $html Selected mail mode.
     * @param string $body Plain notification text.
     * @param string $expected Expected body at the mail boundary.
     * @return void
     */
    #[DataProvider('bodies')]
    public function testBodyEncoding(bool $html, string $body, string $expected): void
    {
        $send = new ReflectionMethod(Mailer::class, '_send');
        self::assertTrue($send->invoke(new Mailer($html), 'recipient@example.invalid', 'Subject & <text>', $body));
        $mail = Factory::$mail;
        self::assertSame($expected, $mail->Body);
        self::assertSame($html, $mail->html);
        self::assertSame($html ? 'base64' : '', $mail->Encoding);
        self::assertSame('Subject & <text>', $mail->subject);
        self::assertSame(['sender@example.invalid', 'JEM fixture'], $mail->sender);
        self::assertSame('recipient@example.invalid', $mail->recipient);
        self::assertSame(1, $mail->sendCalls);
        self::assertSame([], JemHelper::$logs);
        // 5.1 provides an explicit plain alternative; 5.0.2 has no such existing contract.
        self::assertSame($html && $send->getNumberOfParameters() > 3 ? $body : '', $mail->AltBody);
    }

    /** @return iterable<string, array{string}> Invalid recipients must never reach transport. */
    public static function invalidRecipients(): iterable
    {
        yield 'empty' => [''];
        yield 'invalid' => ['not-an-email'];
    }

    /** @param string $recipient Invalid destination. @return void */
    #[DataProvider('invalidRecipients')]
    public function testInvalidRecipientDoesNotSend(string $recipient): void
    {
        $send = new ReflectionMethod(Mailer::class, '_send');
        self::assertFalse($send->invoke(new Mailer(true), $recipient, 'Subject', '<body>'));
        self::assertSame(0, Factory::$mail->sendCalls);
    }

    /** @return iterable<string, array{string}> Existing transport failure paths. */
    public static function failures(): iterable
    {
        yield 'false' => ['false'];
        yield 'returned exception' => ['returned'];
        yield 'thrown exception' => ['thrown'];
    }

    /** @param string $failure Simulated transport failure, never a real connection. @return void */
    #[DataProvider('failures')]
    public function testTransportFailuresRemainReported(string $failure): void
    {
        if ($failure === 'false') {
            Factory::$mail->result = false;
        } elseif ($failure === 'returned') {
            Factory::$mail->result = new RuntimeException('Fixture failure');
        } else {
            Factory::$mail->exception = new RuntimeException('Fixture failure');
        }

        $send = new ReflectionMethod(Mailer::class, '_send');
        self::assertFalse($send->invoke(new Mailer(true), 'recipient@example.invalid', 'Subject', '<body>'));
        self::assertSame(1, Factory::$mail->sendCalls);
        self::assertCount(1, JemHelper::$logs);
    }
}

