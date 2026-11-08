<?php
declare(strict_types=1);

/** Native SQL with deterministic failures and a reschedule between selection and row locking. */
final class AutomaticCleanupDriver extends Joomla\Database\Mysqli\MysqliDriver
{
    public string $failSql = '';
    public ?Closure $beforeLock = null;

    /** @return bool Execute the real statement, optionally failing its selected stage. */
    public function execute()
    {
        $sql = (string) $this->sql;
        if ($this->failSql !== '' && str_contains(preg_replace('/\s+/', ' ', $sql), $this->failSql)) {
            throw new RuntimeException('Injected cleanup failure.');
        }
        if ($this->beforeLock && str_starts_with($sql, 'SELECT * FROM #__jem_events WHERE id = ')) {
            $callback = $this->beforeLock;
            $this->beforeLock = null;
            $callback();
        }
        return parent::execute();
    }
}

/** Execute the real plugin, Adapter and Indexer without booting the installed site's application. */
final class AutomaticCleanupFinder extends plgFinderJEM
{
    /** @param object $db Fixture driver. @return void */
    public function __construct($db)
    {
        $this->db = $db;
        $this->params = new Joomla\Registry\Registry();
        $this->indexer = new Joomla\Component\Finder\Administrator\Indexer\Indexer($db);
    }
}

/** Isolated application using Joomla's real event dispatch and plugin registration. */
final class AutomaticCleanupApplication
{
    use Joomla\CMS\Application\EventAware;
    public array $messages = [];
    public AutomaticCleanupFinder $finder;

    /** @return Joomla\Event\DispatcherInterface Fixture event dispatcher. */
    public function getDispatcher(): Joomla\Event\DispatcherInterface
    {
        return Joomla\CMS\Factory::getContainer()->get(Joomla\Event\DispatcherInterface::class);
    }
    /** @return Psr\Log\LoggerInterface Silent infrastructure logger. */
    public function getLogger(): Psr\Log\LoggerInterface { return new Psr\Log\NullLogger(); }
    /** @param string $name Plugin name. @param string $type Plugin group. @return object Real JEM plugin. */
    public function bootPlugin($name, $type): object { return $this->finder; }
    /** @return object Fixture actor. */
    public function getIdentity(): object { return (object) ['id' => 7]; }
    /** @return Joomla\Registry\Registry Fixture configuration. */
    public function getConfig(): Joomla\Registry\Registry { return Joomla\CMS\Factory::$config; }
    /** @return Joomla\CMS\Language\Language Native language without site state. */
    public function getLanguage(): Joomla\CMS\Language\Language
    {
        return Joomla\CMS\Factory::getContainer()->get(Joomla\CMS\Language\LanguageFactoryInterface::class)->createLanguage('en-GB');
    }
    /** @param string $key Setting key. @param mixed $default Fallback. @return mixed Default. */
    public function get($key, $default = null) { return $default; }
    /** @param string $message Warning. @param string $type Severity. @return void */
    public function enqueueMessage($message, $type = 'message'): void { $this->messages[] = [$message, $type]; }
}

/** Capture JEM logging without accessing installed site logs. */
final class JemLog
{
    public static array $messages = [];
    /** @param mixed ...$arguments Production log arguments. @return void */
    public static function add(...$arguments): void { self::$messages[] = $arguments[0]; }
}

/** Provide the production helper's dispatcher facade. */
final class JemFactory
{
    /** @return object Isolated application. */
    public static function getDispatcher(): object { return Joomla\CMS\Factory::getApplication(); }
}
