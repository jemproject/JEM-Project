<?php
declare(strict_types=1);

/** Native SQL driver with a narrowly injected delete failure. */
final class DeletedResourceDriver extends Joomla\Database\Mysqli\MysqliDriver
{
    public string $failTable = '';

    /** @return bool Actual statement outcome unless its deletion stage is under test. */
    public function execute()
    {
        if ($this->failTable !== '' && preg_match('/^\s*DELETE\b/i', (string) $this->sql)
            && str_contains((string) $this->sql, $this->failTable)) {
            throw new RuntimeException('Injected SQL deletion failure.');
        }
        return parent::execute();
    }
}

/** Minimal application; no installed site's session, ACL or logger is modified. */
final class DeletedResourceApplication
{
    public array $messages = [];
    public array $state = [];
    public bool $admin = true;
    public Joomla\Input\Input $input;
    /** Initialize request data without reading the real web request. */
    public function __construct() { $this->input = new Joomla\Input\Input([]); }
    /** @return mixed Server-owned fixture state. */
    public function getUserState($key, $default = null) { return $this->state[$key] ?? $default; }
    /** @return void Save fixture state only. */
    public function setUserState($key, $value): void { $this->state[$key] = $value; }
    /** @return Joomla\Event\DispatcherInterface Isolated table-event dispatcher. */
    public function getDispatcher(): Joomla\Event\DispatcherInterface
    {
        return Joomla\CMS\Factory::getContainer()->get(Joomla\Event\DispatcherInterface::class);
    }
    /** @return Joomla\CMS\Language\Language Native language object for warning messages. */
    public function getLanguage(): Joomla\CMS\Language\Language
    {
        return Joomla\CMS\Factory::getContainer()->get(Joomla\CMS\Language\LanguageFactoryInterface::class)->createLanguage('en-GB');
    }
    /** @return Joomla\Registry\Registry Fixture configuration only. */
    public function getConfig(): Joomla\Registry\Registry { return new Joomla\Registry\Registry(); }
    /** @return object Synthetic administrator identity. */
    public function getIdentity(): object
    {
        return new class ($this->admin) {
            public int $id = 7;
            public function __construct(private bool $allowed) {}
            public function authorise($permission): bool { return $this->allowed; }
        };
    }
    /** @return mixed Native language factory defaults. */
    public function get($key, $default = null) { return $default; }
    /** @return void Capture user warnings. */
    public function enqueueMessage($message, $type = 'message'): void { $this->messages[] = [$message, $type]; }
}

/** Test configuration and log capture. */
final class JemHelper
{
    public static array $logs = [];
    public static bool $token = true;
    public static bool $nonce = true;
    /** @return void Simulate Joomla's CSRF gate in the actual controller. */
    public static function requirePostToken(): void
    {
        if (!self::$token) { throw new RuntimeException('Invalid token', 403); }
    }
    /** @return bool Simulate the one-time confirmation nonce gate. */
    public static function consumeActionNonce($action, $nonce): bool { return self::$nonce; }
    /** @return object Fixture attachments root. */
    public static function config(): object { return (object) ['attachments_path' => 'files']; }
    /** @return void Capture optional logging. */
    public static function addLogEntry($message, ...$args): void { self::$logs[] = $message; }
}

/** Permission provider for actual controller execution in an isolated application. */
final class JemHelperBackend
{
    public static bool $allowed = true;
    /** @return bool Fixture housekeeping permission. */
    public static function canManage($permission): bool { return self::$allowed; }
}

/** Registration logic is outside this fixture; parent and owned-resource SQL remain real. */
final class JemRegistrationService
{
    /** @param mixed $db Unused fixture dependency. */
    public function __construct($db) {}
    /** @return void No active registrations are created by these tests. */
    public function purgeForEventLocked($id, $context): void {}
}

/** No notifications are created by this resource-cleanup fixture. */
final class JemNotificationService
{
    /** @param mixed $db Unused fixture dependency. */
    public function __construct($db) {}
    /** @return void No reminders to cancel in this fixture. */
    public function cancelPendingReminders($registrationId, $eventId): void {}
}

/** Shared image publication is not exercised by attachment cleanup fixtures. */
final class JemImagePublicationPolicy
{
    /** @return array No invalid images in the synthetic publish/restore fixture. */
    public static function invalidPublishRecords(...$args): array { return []; }
}
