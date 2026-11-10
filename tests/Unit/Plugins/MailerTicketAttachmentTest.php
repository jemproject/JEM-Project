<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use JemProject\Tests\MailerBody\Factory;
use JemProject\Tests\MailerBody\Mail;
use JemProject\Tests\MailerBody\Mailer;

final class MailerTicketAttachmentTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/mailer_body_environment.php';
        \JemProject\Tests\MailerBody\loadMailer();
    }

    public function testPrivatePdfIsAttachedAsBytesWithoutPublicFile(): void
    {
        Factory::$mail = new Mail();
        $bytes = "%PDF-1.7\nPrivate reservation fixture";
        $attachments = [['name' => 'reservation.pdf', 'content_base64' => base64_encode($bytes), 'sha256' => hash('sha256', $bytes)]];
        $error = '';
        $args = ['owner@example.invalid', 'Reservation', 'Body', '', &$error, $attachments];
        $send = new ReflectionMethod(Mailer::class, '_send');
        self::assertTrue($send->invokeArgs(new Mailer(false), $args));
        self::assertSame($bytes, Factory::$mail->attachments[0]['bytes']);
        self::assertSame('application/pdf', Factory::$mail->attachments[0]['mime']);
        self::assertSame(1, Factory::$mail->sendCalls);
    }

    public function testCorruptedAttachmentPreventsTransport(): void
    {
        Factory::$mail = new Mail();
        $error = '';
        $args = ['owner@example.invalid', 'Reservation', 'Body', '', &$error,
            [['name' => 'reservation.pdf', 'content_base64' => base64_encode('changed'), 'sha256' => hash('sha256', 'original')]]];
        $send = new ReflectionMethod(Mailer::class, '_send');
        self::assertFalse($send->invokeArgs(new Mailer(false), $args));
        self::assertSame(0, Factory::$mail->sendCalls);
    }
}
