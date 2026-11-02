<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\Database\Sqlite\SqliteDriver;
use Joomla\DI\Container;

// Native libraries only: never load the installed site's application or database.
$joomlaRoot = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
require_once $joomlaRoot . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($joomlaRoot): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $file = $joomlaRoot . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
define('JPATH_BASE', JEM_TEST_ROOT);
define('JPATH_ROOT', $joomlaRoot);
Factory::$language = new Language('en-GB', false);

/** Asset/document boundary for rendering native fancy-select HTML without a site application. */
final class ReminderSelectionDocument
{
    private array $strings = [];

    /** @return self Asset registration does not execute JavaScript in this fixture. */
    public function getWebAssetManager(): self
    {
        return $this;
    }

    /** @param string $name Script name. @return self */
    public function useScript(string $name): self
    {
        return $this;
    }

    /** @param string $name Preset name. @return self */
    public function usePreset(string $name): self
    {
        return $this;
    }

    /** @param string $key Options key. @return array */
    public function getScriptOptions(string $key): array
    {
        return $this->strings[$key] ?? [];
    }

    /** @param string $key Options key. @param array $value Strings. @param bool $merge Unused. @return void */
    public function addScriptOptions(string $key, array $value, bool $merge): void
    {
        $this->strings[$key] = $value;
    }
}

Factory::$application = new class {
    /** @return ReminderSelectionDocument The isolated rendering document. */
    public function getDocument(): ReminderSelectionDocument
    {
        static $document;
        return $document ??= new ReminderSelectionDocument();
    }
};
Factory::$document = Factory::$application->getDocument();

$db = new SqliteDriver(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'test_']);
$db->setQuery('CREATE TABLE test_jem_reminders (
    id INTEGER PRIMARY KEY, event_id INTEGER, source_id INTEGER, code TEXT,
    title TEXT, ordering INTEGER, minutes INTEGER, published INTEGER
)')->execute();
$db->setQuery("INSERT INTO test_jem_reminders VALUES
    (1, 0, 0, 'first', 'First', 1, 60, 0),
    (2, 0, 0, 'second', 'Second', 2, 120, 1),
    (3, 0, 0, 'third', 'Third', 3, 180, 1),
    (4, 42, 2, 'second', 'Event copy', 4, 120, 1),
    (5, 99, 3, 'third', 'Other event', 5, 180, 1)")->execute();
$container = new Container();
$container->set('DatabaseDriver', $db);
$container->set(Joomla\CMS\HTML\Registry::class, new Joomla\CMS\HTML\Registry());
(new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
require_once JEM_TEST_ROOT . '/site/classes/reminderservice.class.php';

/** Controlled feature policy for the exact production model validation block. */
final class JemFeaturePolicy
{
    public const FEATURE_NOTIFICATION_AUTOMATION = 'notifications';
    public static bool $allowed = true;

    /** @return self Current fixture policy. */
    public static function current(): self
    {
        return new self();
    }

    /** @param string $feature Feature key. @return bool Whether the fixture allows it. */
    public function allows(string $feature): bool
    {
        return self::$allowed;
    }
}
