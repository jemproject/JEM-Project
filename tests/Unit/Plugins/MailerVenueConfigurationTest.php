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

final class MailerVenueConfigurationTest extends TestCase
{
    /** @return void Load the actual append and send methods with transport isolated. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/mailer_body_environment.php';
        \JemProject\Tests\MailerBody\loadMailer();
    }

    /** @return void Reset in-memory transport and logs. */
    protected function setUp(): void
    {
        Factory::$mail = new Mail();
        JemHelper::$logs = [];
    }

    /** @return iterable<string, array{bool}> Both administrator-selected delivery formats. */
    public static function modes(): iterable
    {
        yield 'HTML' => [true];
        yield 'plain text' => [false];
    }

    /** @param bool $html Selected delivery format. @return void */
    #[DataProvider('modes')]
    public function testExplicitHtmlAndPlainAlternativeAreUnchanged(bool $html): void
    {
        $plain = 'Plain <b>literal</b> & text';
        $template = '<p>Prepared <strong>HTML</strong> &amp; text</p>';
        $send = new ReflectionMethod(Mailer::class, '_send');
        self::assertTrue($send->invoke(new Mailer($html), 'recipient@example.invalid', 'Subject', $plain, $template));
        self::assertSame($html ? $template : $plain, Factory::$mail->Body);
        self::assertSame($html ? $plain : '', Factory::$mail->AltBody);
    }

    /** @return iterable<string, array{bool, bool}> Delivery format and availability of an HTML template. */
    public static function configurationModes(): iterable
    {
        foreach ([false, true] as $html) {
            foreach ([false, true] as $template) {
                yield (int) $html . '/' . (int) $template => [$html, $template];
            }
        }
    }

    /**
     * Exercise append-to-send and repeated appends without losing the main notification.
     *
     * @param bool $html Selected delivery format.
     * @param bool $template Whether the message already has an HTML body.
     * @return void
     */
    #[DataProvider('configurationModes')]
    public function testConfigurationPreservesTheWholeMessage(bool $html, bool $template): void
    {
        $mailer = new Mailer($html);
        $append = new ReflectionMethod(Mailer::class, '_appendVenueConfiguration');
        $message = (object) [
            'body' => 'Main body & <literal>',
            'htmlbody' => $template ? '<p>Main <strong>HTML</strong> body</p>' : '',
        ];
        $values = ['venue_configuration' => 'Room A & B <North>'];
        $plain = "Main body & <literal>\n\nVenue configuration: Room A & B <North>";
        $preparedHtml = '<p>Main <strong>HTML</strong> body</p>'
            . '<p><strong>Venue configuration:</strong> Room A &amp; B &lt;North&gt;</p>';
        $convertedText = "Main body &amp; &lt;literal&gt;<br />\n<br />\n"
            . 'Venue configuration: Room A &amp; B &lt;North&gt;';

        self::assertSame($message, $append->invoke($mailer, $message, $values));
        self::assertSame($plain, $message->body);
        self::assertSame($template ? $preparedHtml : '', $message->htmlbody);

        $append->invoke($mailer, $message, $values);
        self::assertSame($plain, $message->body, 'Do not duplicate the plain suffix.');
        self::assertSame($template ? $preparedHtml : '', $message->htmlbody, 'Do not duplicate the HTML suffix.');

        $send = new ReflectionMethod(Mailer::class, '_send');
        self::assertTrue($send->invoke($mailer, 'recipient@example.invalid', 'Subject', $message->body, $message->htmlbody));
        self::assertSame($html ? ($template ? $preparedHtml : $convertedText) : $plain, Factory::$mail->Body);
        self::assertSame($html ? $plain : '', Factory::$mail->AltBody);
        self::assertSame([], JemHelper::$logs);
    }

    /** @return iterable<string, array{array}> Absent or empty configuration is a no-op. */
    public static function emptyConfigurations(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty' => [['venue_configuration' => '']];
        yield 'whitespace' => [['venue_configuration' => " \n "]];
    }

    /** @param array $values Notification values. @return void */
    #[DataProvider('emptyConfigurations')]
    public function testEmptyConfigurationDoesNotModifyEitherBody(array $values): void
    {
        $message = (object) ['body' => 'Main plain body', 'htmlbody' => '<p>Main HTML body</p>'];
        $before = clone $message;
        $append = new ReflectionMethod(Mailer::class, '_appendVenueConfiguration');
        self::assertSame($message, $append->invoke(new Mailer(true), $message, $values));
        self::assertEquals($before, $message);
    }

    /** @return void A template already containing configuration receives no redundant suffix. */
    public function testConfigurationAlreadyInBothBodiesIsNotAppended(): void
    {
        $message = (object) [
            'body' => 'Main plain body: Room A & B',
            'htmlbody' => '<p>Main HTML body: Room A &amp; B</p>',
        ];
        $before = clone $message;
        $append = new ReflectionMethod(Mailer::class, '_appendVenueConfiguration');
        $append->invoke(new Mailer(true), $message, ['venue_configuration' => 'Room A & B']);
        self::assertEquals($before, $message);
    }
}

