<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;

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
// Use the real bundled translations without writing Joomla language caches.
(new ReflectionProperty(Language::class, 'strings'))->setValue(
    Factory::$language,
    parse_ini_file(JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW)
);
require_once JEM_TEST_ROOT . '/site/classes/ticketavailability.class.php';

/** Controlled config/window boundary, not a replacement for the tested availability policy. */
final class JemHelper
{
    public static array $settings = [];

    /** @return object Fixture settings for the shared output entry point. */
    public static function config(): object
    {
        return (object) self::$settings;
    }

    /** @param object $event Event. @param int|null $now Timestamp. @return bool Controlled registration window. */
    public static function isEventRegistrationOpen($event, $now = null): bool
    {
        return $event->test_registration_open ?? true;
    }
}

/**
 * Extract unchanged production methods, bypassing unrelated site bootstrap dependencies.
 * @param string $name Method name.
 * @return string Exact method declaration and body.
 */
function lastPlacesOutputMethod(string $name): string
{
    $source = file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
    $pattern = '/^    (?:(?:static|public|protected)\s+)+function ' . preg_quote($name, '/') . '\([^\n]*\)\s*\{.*?^    \}/ms';
    if (preg_match($pattern, $source, $match) !== 1) {
        throw new RuntimeException('Missing output method ' . $name);
    }
    return $match[0];
}

$methods = array_map('lastPlacesOutputMethod', [
    'getEffectiveTicketAvailability', 'getModuleEventStatus', 'isModuleStatusActive',
    'getEventStatusPresentation', 'getModuleStatusPresentation', 'eventStateBadges',
]);
eval('use Joomla\\CMS\\Language\\Text; class JemOutput {' . implode("\n", $methods) . '}');
