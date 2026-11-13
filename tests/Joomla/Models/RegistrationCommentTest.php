<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Input\Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise production comment paths without an installed-site write or mail transport. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RegistrationCommentTest extends TestCase
{
    /** @return void Load real Joomla filters, source paths and translations, prohibiting SQL access. */
    protected function setUp(): void
    {
        if (!is_file((string) getenv('JEM_TEST_JOOMLA_ROOT') . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a Joomla 5/6 tree.');
        }
        require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
        require_once JEM_TEST_ROOT . '/site/classes/registrationcomment.class.php';
        if (is_file(JEM_TEST_ROOT . '/site/classes/notificationtemplaterenderer.class.php')) {
            require_once JEM_TEST_ROOT . '/site/classes/notificationtemplaterenderer.class.php';
        }
        require_once JEM_TEST_ROOT . '/tests/Support/mailer_body_environment.php';
        \JemProject\Tests\MailerBody\loadMailer();
        $strings = parse_ini_file(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/language/en-GB/plg_jem_mailer.ini', false, INI_SCANNER_RAW);
        $language = Factory::$language;
        $property = new ReflectionProperty($language, 'strings');
        $property->setValue($language, array_merge($property->getValue($language), $strings));
    }

    /** @return iterable<string,array{string,string}> Valid input and its exact plain-text value. */
    public static function comments(): iterable
    {
        foreach (['Ana & Luis', '"delante" {2 plazas}', 'Gracias <3', '0', '',
            'Literal &amp; &quot;', '{comment} %s {user_name}',
            '</textarea><img src=x onerror=alert(1)>', 'Españoλ 日本 😀',
            str_repeat('&', 255), str_repeat('😀', 255)] as $index => $text) {
            yield 'text ' . $index => [$text, $text];
        }
        yield 'line endings' => ["A\r\nB\rC\nD\tE", "A\nB\nC\nD\tE"];
    }

    /** @return iterable<string,array{mixed,string}> Invalid input must not be silently truncated or converted. */
    public static function invalidComments(): iterable
    {
        foreach ([[], ['x'], 0, true, "Broken \xC3(", "A\0B", "A\x1BB"] as $index => $value) {
            yield 'invalid ' . $index => [$value, 'COM_JEM_REGISTRATION_COMMENT_INVALID'];
        }
        yield '256 ASCII characters' => [str_repeat('&', 256), 'COM_JEM_REGISTRATION_COMMENT_TOO_LONG'];
        yield '256 Unicode characters' => [str_repeat('😀', 256), 'COM_JEM_REGISTRATION_COMMENT_TOO_LONG'];
    }

    /** @param string $input Submitted text. @param string $expected Canonical text. @return void */
    #[DataProvider('comments')]
    public function testActualInputPathsPreserveComments(string $input, string $expected): void
    {
        $fixture = new RegistrationCommentFixture();
        foreach ($this->frontendReads() as $code) {
            self::assertSame($expected, $fixture->run($code . PHP_EOL . 'return $comment;', [
                'app' => (object) ['input' => new Input(['reg_comment' => $input])],
            ]));
            self::assertSame('', $fixture->error);
        }
        $post = $this->backendPost($fixture, $input);
        self::assertSame($input, $post['comment']);
        $validated = $fixture->run($this->backendValidation() . PHP_EOL . 'return $data;', ['data' => $post]);
        self::assertSame($expected, $validated['comment']);
    }

    /** @param mixed $input Invalid request value. @param string $key Error key. @return void */
    #[DataProvider('invalidComments')]
    public function testInvalidInputStopsBeforeAnyBookingWrite($input, string $key): void
    {
        $fixture = new RegistrationCommentFixture();
        foreach ($this->frontendReads() as $code) {
            self::assertFalse($fixture->run($code . PHP_EOL . 'return $comment;', [
                'app' => (object) ['input' => new Input(['reg_comment' => $input])],
            ]));
            self::assertSame(\Joomla\CMS\Language\Text::_($key), $fixture->error);
        }
        self::assertFalse($fixture->run($this->backendValidation() . PHP_EOL . 'return $data;', [
            'data' => $this->backendPost($fixture, $input),
        ]));
        self::assertSame(\Joomla\CMS\Language\Text::_($key), $fixture->error);
    }

    /** @return void Omitted comments remain omitted and old literal entities are not decoded. */
    public function testUnsubmittedAndLegacyCommentsAreNotRewritten(): void
    {
        $fixture = new RegistrationCommentFixture();
        self::assertSame(['id' => 7], $fixture->run($this->backendValidation() . PHP_EOL . 'return $data;', ['data' => ['id' => 7]]));
        self::assertSame('Ana &amp; Luis', JemRegistrationComment::normalise('Ana &amp; Luis'));
    }

    /** @param string $input Original text. @param string $expected Normalised text. @return void */
    #[DataProvider('comments')]
    public function testRealMailBranchesAndTransportEncoding(string $input, string $expected): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/mailer.php');
        foreach (['onEventUserRegistered', 'onEventUserUnregistered'] as $name) {
            self::assertSame(1, preg_match('/public function ' . $name . '\([^\n]*\)[^\n]*\s*\{(.*?)^    \}/ms', $source, $match));
            // Execute the unchanged production formatting/recipient branches after event lookup.
            $code = substr($match[1], strpos($match[1], '        $text_description ='));
            foreach ([false, true] as $onBehalf) {
                $fixture = new RegistrationCommentFixture();
                $event = (object) ['id' => 1, 'created_by' => 1, 'uid' => 1, 'status' => 1, 'waiting' => 0,
                    'text' => 'Description', 'comment' => JemRegistrationComment::normalise($input),
                    'title' => 'Event', 'dates' => '2030-12-06', 'times' => '19:30', 'venue' => 'Venue',
                    'city' => 'City', 'places' => 1, 'registration_id' => 7];
                $fixture->run($code, ['event' => $event, 'attendeeid' => $onBehalf ? 2 : 1, 'userid' => 1,
                    'username' => 'Actor', 'attendeename' => $onBehalf ? 'Attendee' : 'Actor',
                    'registration' => (object) ['places' => 1], 'send_to' => ['user' => true],
                    'notificationOptions' => [], 'link' => 'https://example.invalid/event']);
                self::assertCount(2, $fixture->messages);
                foreach ($fixture->messages as $message) {
                    if ($expected !== '') {
                        self::assertStringContainsString($expected, $message->body);
                        self::assertStringContainsString('comment', strtolower($message->body));
                    }
                    foreach ([false, true] as $html) {
                        \JemProject\Tests\MailerBody\Factory::$mail = new \JemProject\Tests\MailerBody\Mail();
                        $mailer = new \JemProject\Tests\MailerBody\Mailer($html);
                        self::assertTrue((new ReflectionMethod($mailer, '_send'))->invoke(
                            $mailer, 'recipient@example.invalid', 'Subject', $message->body
                        ));
                        $mail = \JemProject\Tests\MailerBody\Factory::$mail;
                        self::assertSame($html ? nl2br(htmlspecialchars($message->body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) : $message->body, $mail->Body);
                    }
                    if (isset($message->htmlbody) && $expected !== '') {
                        self::assertSame('<p>' . htmlspecialchars($expected, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>', $message->htmlbody);
                    }
                }
            }
        }
    }

    /** @return void Render the actual comment fragments; literal markup never creates elements. */
    public function testTextareaListTooltipAndPrintContexts(): void
    {
        $files = [
            'admin/views/attendee/tmpl/default.php',
            'site/views/event/tmpl/default_regform.php',
            'site/views/event/tmpl/responsive/default_regform.php',
            'site/views/event/tmpl/default_pricedregform.php',
            'admin/views/attendees/tmpl/default.php',
            'site/views/attendees/tmpl/default.php',
            'site/views/attendees/tmpl/responsive/default.php',
            'site/views/myattendances/tmpl/default_attendances.php',
            'site/views/myattendances/tmpl/responsive/default_attendances.php',
            'site/views/myattendances/tmpl/timeline_attendances.php',
        ];
        foreach ($files as $file) {
            if (!is_file(JEM_TEST_ROOT . '/' . $file)) {
                continue;
            }
            $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
            foreach (['0', 'A & "B" {2}', 'Literal &amp;', '</textarea><img src=x onerror=alert(1)>'] as $comment) {
                foreach ([false, true] as $print) {
                    $fixture = new RegistrationCommentFixture();
                    $fixture->print = $print;
                    $fixture->row = $fixture->registration = (object) ['comment' => $comment];
                    if (preg_match('/<textarea\b[^\n]*id="reg_comment".*?<\/textarea>/s', $source, $match)) {
                        $code = '?>' . $match[0];
                    } elseif (str_contains($file, 'timeline_attendances')) {
                        $code = $this->between($source, "                \$comment = '';", '                ?>', true);
                        preg_match('/<\?php echo \$this->print \? (.*?); \?>/', $source, $echo);
                        $code .= PHP_EOL . 'echo $this->print ? ' . $echo[1] . ';';
                    } elseif (str_contains($file, 'myattendances')) {
                        self::assertSame(1, preg_match('/\$len\s*=.*?endif;\s*\?>/s', $source, $match));
                        $code = substr($match[0], 0, -2);
                    } else {
                        self::assertSame(1, preg_match('/<\?php \$cmnt =.*?<\/(?:td|div)>/s', $source, $match), $file);
                        $code = '?>' . $match[0];
                    }
                    ob_start();
                    try {
                        $fixture->run($code, ['row' => $fixture->row, 'registration' => $fixture->registration, 'disabledOptions' => false]);
                        $html = ob_get_contents();
                    } finally {
                        ob_end_clean();
                    }
                    $dom = new DOMDocument();
                    @$dom->loadHTML('<?xml encoding="utf-8"?><div id="probe">' . $html . '</div>');
                    $xpath = new DOMXPath($dom);
                    self::assertSame(0, $xpath->query('//img|//script')->length, $file);
                    $text = trim($dom->getElementById('probe')->textContent);
                    self::assertStringStartsWith(substr($comment, 0, 4), $text, $file);
                    foreach ($xpath->query('//*[@title]') as $element) {
                        self::assertSame($comment, $element->getAttribute('title'), $file);
                    }
                    if (($print && str_contains($file, 'myattendances')) || str_contains($html, '<textarea')) {
                        self::assertSame($comment, $text, $file);
                    }
                }
            }
        }
    }

    /** @return void Round-trip validated text through a temporary copy of the actual register schema. */
    public function testExistingDatabaseColumnPreservesAllCharacters(): void
    {
        if (getenv('JEM_TEST_TEMP_TABLES') !== '1') {
            self::markTestSkipped('Set JEM_TEST_TEMP_TABLES=1 for connection-local SQL tables.');
        }
        require_once getenv('JEM_TEST_JOOMLA_ROOT') . '/configuration.php';
        $config = new JConfig();
        self::assertContains($config->host, ['localhost', '127.0.0.1']);
        $db = new mysqli($config->host, $config->user, $config->password, $config->db);
        try {
            $db->set_charset('utf8mb4');
            $db->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");
            $schema = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
            self::assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_register`\s*\(.*?\)\s*ENGINE=[^;]+;/s', $schema, $match));
            $table = 'q2522_' . bin2hex(random_bytes(6)) . '_jem_register';
            $db->query(str_replace(['CREATE TABLE IF NOT EXISTS', '#__jem_register'], ['CREATE TEMPORARY TABLE', $table], $match[0]));
            $identityColumns = str_contains($match[0], '`reference`') ? ', reference, created, modified' : '';
            $identityValues = $identityColumns !== '' ? ", 'JEM-COMMENT-TEST', '2030-12-01 12:00:00', '2030-12-01 12:00:00'" : '';
            $db->query("INSERT INTO `$table` (event, uid, uregdate, uip, comment$identityColumns) VALUES (1,1,'2030-12-01 12:00:00','', ''$identityValues)");
            $write = $db->prepare("UPDATE `$table` SET comment=?");
            foreach (self::comments() as [$input, $expected]) {
                $value = JemRegistrationComment::normalise($input);
                $write->bind_param('s', $value);
                $write->execute();
                $row = $db->query("SELECT comment, CHAR_LENGTH(comment) AS length FROM `$table`")->fetch_assoc();
                self::assertSame($expected, $row['comment']);
                self::assertSame(mb_strlen($expected, 'UTF-8'), (int) $row['length']);
            }
            $write->close();
        } finally {
            $db->close(); // MySQL removes this connection's temporary table automatically.
        }
    }

    /** @return array<string> Production frontend reads, including priced cancellation in 5.1. */
    private function frontendReads(): array
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        $result = [$this->between($source, "//\$app->input->getString('noreg_check', 'off');", '        $regid')];
        if (str_contains($source, 'function cancelRegistrationResponse')) {
            $result[] = $this->between($source, "$" . "app->input->post->get('cancel_capacity_areas', array(), 'array');", '        $service = new JemRegistrationService');
        }
        return $result;
    }

    /** @return string Actual backend model validation before identifiers or database access. */
    private function backendValidation(): string
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/attendee.php');
        $source = substr($source, strpos($source, 'public function store($data)'));
        return $this->between($source, '{', '        $eventid');
    }

    /** @param RegistrationCommentFixture $fixture Execution scope. @param mixed $comment Input. @return array */
    private function backendPost(RegistrationCommentFixture $fixture, $comment): array
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/attendee.php');
        $code = $this->between($source, '        $post = $jinput->post->getArray', '// Retrieving email-setting', true);
        return $fixture->run($code . PHP_EOL . 'return $post;', ['jinput' => (object) ['post' => new Input(['comment' => $comment])]]);
    }

    /** @param string $source Source. @param string $start Start marker. @param string $end End marker. @param bool $include Include start. @return string */
    private function between(string $source, string $start, string $end, bool $include = false): string
    {
        $offset = strpos($source, $start);
        self::assertNotFalse($offset, $start);
        $offset += $include ? 0 : strlen($start);
        $last = strpos($source, $end, $offset);
        self::assertNotFalse($last, $end);
        return substr($source, $offset, $last - $offset);
    }
}

/** Provides only the surrounding view/mail boundaries; snippets always come from production. */
final class RegistrationCommentFixture
{
    public string $error = '';
    public array $messages = [];
    public string $_SiteName = 'JEM fixture';
    public bool $print = false;
    public object $row;
    public object $registration;
    public object $jemsettings;

    /** @return void Enable comments for the rendering fixture. */
    public function __construct()
    {
        $this->jemsettings = (object) ['regallowcomments' => 1];
    }

    /** @param string $code Real source fragment. @param array $scope Dependencies. @return mixed Fragment result. */
    public function run(string $code, array $scope = [])
    {
        extract($scope, EXTR_SKIP);
        return eval('use Joomla\\CMS\\Language\\Text; use Joomla\\String\\StringHelper; use Joomla\\CMS\\Filter\\OutputFilter;'
            . $code);
    }

    /** @param string $message Validation error. @return void */
    public function setError($message): void
    {
        $this->error = $message;
    }

    /** @param string $value Plain text. @return string HTML representation. */
    public function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param mixed ...$args Unused routing context. @return array Synthetic recipients. */
    public function _getRecipients(...$args): array
    {
        return ['user' => ['user@example.invalid'], 'all' => ['admin@example.invalid']];
    }

    /** @param int $id Registration ID. @return string Synthetic URL. */
    public function _reservationUrl($id): string
    {
        return 'https://example.invalid/reservation';
    }

    /** @param mixed ...$args Notification metadata. @return void No persistence in this fixture. */
    public function _attachNotificationContext(...$args): void
    {
    }

    /** @param object $data Prepared notification. @return bool Captured without delivery. */
    public function _mailer($data): bool
    {
        $this->messages[] = clone $data;
        return true;
    }

    /** @param string $subject Subject key. @param string $body Body key. @param array $values Tokens. @param object $event Context. @return object Actual renderer output. */
    public function _renderNotification($subject, $body, $values, $event): object
    {
        return (object) [
            'subject' => JemNotificationTemplateRenderer::render(\Joomla\CMS\Language\Text::_($subject), $values),
            'body' => JemNotificationTemplateRenderer::render(\Joomla\CMS\Language\Text::_($body), $values),
            'htmlbody' => JemNotificationTemplateRenderer::render('<p>{comment}</p>', $values, [], true),
        ];
    }
}
