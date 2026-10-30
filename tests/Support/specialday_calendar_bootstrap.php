<?php

declare(strict_types=1);

/** Real Joomla fields/layouts, with no application boot, database or installed-file writes. */
$joomlaRoot = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
define('JPATH_ROOT', $joomlaRoot);
define('JPATH_BASE', $joomlaRoot);
define('JPATH_LIBRARIES', $joomlaRoot . '/libraries');
require_once $joomlaRoot . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($joomlaRoot): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $path = $joomlaRoot . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

$language = (new ReflectionClass(Joomla\CMS\Language\Language::class))->newInstanceWithoutConstructor();
// Read INI data directly: Language's constructor would create installed-site caches.
(new ReflectionProperty($language, 'strings'))->setValue($language, array_merge(
    parse_ini_file($joomlaRoot . '/language/en-GB/joomla.ini', false, INI_SCANNER_RAW),
    parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW)
));
Joomla\CMS\Factory::$language = $language;
// Field loading injects a driver but never executes SQL. Reject any accidental connection.
(new ReflectionProperty(Joomla\CMS\Factory::class, 'container'))->setValue(null, new Joomla\DI\Container());
Joomla\CMS\Factory::getContainer()->set(Joomla\Database\DatabaseInterface::class, new class ([]) extends Joomla\Database\Mysqli\MysqliDriver {
    public function connect(): void
    {
        throw new RuntimeException('No database access is allowed in Special Day date tests.');
    }
});

require_once __DIR__ . '/recurrence_source_stream.php';
stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
define('JPATH_SITE', 'jemrecurrence://site');
define('JPATH_ADMINISTRATOR', JPATH_SITE . '/administrator');
define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_jem');

$document = new class {
    private array $options = [];

    public function getDirection(): string
    {
        return 'ltr';
    }

    public function getScriptOptions($key): array
    {
        return $this->options[$key] ?? [];
    }

    public function addScriptOptions($key, $value): void
    {
        $this->options[$key] = $value;
    }

    public function getWebAssetManager(): object
    {
        return new class {
            public function assetExists(...$args): bool
            {
                return false;
            }

            public function __call($name, $args): self
            {
                return $this;
            }
        };
    }
};
Joomla\CMS\Factory::$document = $document;
Joomla\CMS\Factory::$application = new class ($document, $language) {
    public function __construct(private object $document, private object $language)
    {
    }

    public function getDocument(): object
    {
        return $this->document;
    }

    public function getLanguage(): object
    {
        return $this->language;
    }

    public function getIdentity(): object
    {
        return new Joomla\CMS\User\User();
    }
};

require_once JEM_TEST_ROOT . '/admin/models/fields/specialdaycalendar.php';
require_once JEM_TEST_ROOT . '/admin/tables/jem_special_days.php';

/** Bypass template lookup only; render the real Joomla calendar layout. */
class SpecialDayFixtureCalendar extends JFormFieldSpecialdaycalendar
{
    protected function getRenderer($layoutId = 'default')
    {
        return new class {
            public function render(array $displayData): string
            {
                ob_start();
                require JPATH_ROOT . '/layouts/joomla/form/field/calendar.php';
                return (string) ob_get_clean();
            }
        };
    }
}
