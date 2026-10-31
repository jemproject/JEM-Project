<?php

declare(strict_types=1);

namespace JemProject\Tests\PrintLink;

/** Minimal external services; production URL building and escaping are executed by the tests. */
final class Registry
{
    /** @param array<string, mixed> $values Settings. */
    public function __construct(public array $values = [])
    {
    }

    /** @param string $key Setting. @param mixed $default Fallback. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}

final class Input
{
    public int $print = 0;

    /** @param string $key Input key. @param mixed $default Fallback. @param string $type Filter. @return int Print flag. */
    public function get(string $key, mixed $default = '', string $type = 'int'): int
    {
        return $this->print;
    }
}

final class Application
{
    public Input $input;

    /** @return void Set up an isolated request. */
    public function __construct()
    {
        $this->input = new Input();
    }

    /** @param string $client Client name. @return bool Whether it is the site client. */
    public function isClient(string $client): bool
    {
        return $client === 'site';
    }
}

final class Factory
{
    public static Application $application;

    /** @return Application Isolated request. */
    public static function getApplication(): Application
    {
        return self::$application;
    }
}

final class JemHelper
{
    public static Registry $settings;

    /** @return Registry Current test settings. */
    public static function globalattribs(): Registry
    {
        return self::$settings;
    }
}

final class Route
{
    public static array $calls = [];

    /** Simulate only internal-link prefixing; actual Joomla routing is checked separately.
     * @param string $url Input. @return string Routed test URL.
     */
    public static function _(string $url): string
    {
        self::$calls[] = $url;
        if (str_starts_with($url, 'index.php')) {
            return '/joomla/' . $url;
        }
        if (str_starts_with($url, '&')) {
            return '/joomla/index.php?' . substr($url, 1);
        }
        return $url;
    }
}

final class Text
{
    /** @param string $key Language key. @return string Stable label. */
    public static function _(string $key): string
    {
        return $key;
    }
}

final class HTMLHelper
{
    /** @param string $title Label. @param string $text Description. @param int $escape Flag. @return string Safe tooltip. */
    public static function tooltipText(string $title, string $text, int $escape): string
    {
        return htmlspecialchars($title . ': ' . $text, ENT_QUOTES, 'UTF-8');
    }
}

final class jemhtml
{
    /** @param mixed ...$args Icon arguments. @return string Test icon. */
    public static function icon(...$args): string
    {
        return '<i class="jem-printbutton"></i>';
    }
}

final class Uri
{
    /** @param string $url Unchanged request URI. */
    public function __construct(private string $url)
    {
    }

    /** @return string Original URI. */
    public function toString(): string
    {
        return $this->url;
    }
}
