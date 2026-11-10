<?php
/**
 * @package JEM
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

namespace JemProject\Tests\MailerBody;

/** Capture only the mail boundary; no transport or installed Joomla is loaded. */
final class Mail
{
    public string $Body = '';
    public string $AltBody = '';
    public string $Encoding = '';
    public string $subject = '';
    public string $recipient = '';
    public array $sender = [];
    public bool $html = false;
    public int $sendCalls = 0;
    public bool|\Exception $result = true;
    public ?\Exception $exception = null;

    /** @param array $sender Sender address and name. @return void */
    public function setSender(array $sender): void
    {
        $this->sender = $sender;
    }

    /** @param string $subject Subject. @return void */
    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    /** @param string $body Body passed by the real plugin method. @return void */
    public function setBody(string $body): void
    {
        $this->Body = $body;
    }

    /** @param bool $html Selected format. @return void */
    public function isHTML(bool $html): void
    {
        $this->html = $html;
    }

    /** @param string $recipient Validated destination. @return void */
    public function addRecipient(string $recipient): void
    {
        $this->recipient = $recipient;
    }

    /** @return bool|\Exception Configured result, never a real mail delivery. */
    public function send(): bool|\Exception
    {
        ++$this->sendCalls;
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->result;
    }
}

final class Factory
{
    public static Mail $mail;

    /** @return Mail In-memory mail capture. */
    public static function getMailer(): Mail
    {
        return self::$mail;
    }
}

final class Text
{
    public static array $strings = [];
    /** @param string $key Translation key. @return string Stable fixture label. */
    public static function _(string $key): string
    {
        return self::$strings[$key] ?? ($key === 'COM_JEM_EVENT_VENUE_CONFIGURATION' ? 'Venue configuration' : $key);
    }

    /** @param string $key Translation key. @param mixed ...$args Arguments. @return string Key. */
    public static function sprintf(string $key, mixed ...$args): string
    {
        return isset(self::$strings[$key]) ? sprintf(self::$strings[$key], ...$args) : $key;
    }
}

final class Log
{
    public const WARNING = 4;
}

final class JemHelper
{
    public static array $logs = [];

    /** @param string $message Message. @param mixed ...$context Log metadata. @return void */
    public static function addLogEntry(string $message, mixed ...$context): void
    {
        self::$logs[] = $message;
    }
}

final class Params
{
    /** @param bool $html Mail format selected by the administrator. */
    public function __construct(private bool $html)
    {
    }

    /** @param string $key Option. @param mixed $default Default. @return mixed Option value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $key === 'send_html' ? (int) $this->html : $default;
    }
}

class MailerBoundary
{
    protected string $_MailFrom = 'sender@example.invalid';
    protected string $_FromName = 'JEM fixture';
    protected Params $params;

    /** @param bool $html Mail format; all other dependencies stay isolated. */
    public function __construct(bool $html)
    {
        $this->params = new Params($html);
    }
}

/**
 * Load unchanged production methods in an isolated namespace, retaining private visibility.
 *
 * @return void
 */
function loadMailer(): void
{
    if (class_exists(Mailer::class, false)) {
        return;
    }

    $source = file_get_contents(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/mailer.php');
    $names = ['_send', '_appendEventStatusInfo'];
    if (str_contains($source, 'private function _appendVenueConfiguration(')) {
        $names[] = '_appendVenueConfiguration';
    }
    $methods = '';
    foreach ($names as $name) {
        if (
            !preg_match(
                '/private function ' . preg_quote($name, '/') . '\([^\n]*\)[^\n]*\s*\{.*?^    \}/ms',
                $source,
                $match
            )
        ) {
            throw new \RuntimeException('Missing production method: ' . $name);
        }
        $methods .= $match[0] . "\n";
    }

    eval('namespace ' . __NAMESPACE__ . '; use \\Exception; use \\Throwable;'
        . ' final class Mailer extends MailerBoundary {' . $methods . '}');
}

