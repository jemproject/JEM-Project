<?php

declare(strict_types=1);

namespace JemProject\Tests\ExportLink;

/** Request boundary: these tests exercise production URL code, not Joomla routing. */
final class Input
{
    /** @param array<string, mixed> $values Simulated GET/POST input. */
    public function __construct(public array $values)
    {
    }

    /** @param string $key Input name. @return bool Whether explicitly supplied. */
    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /** @param string $key Name. @param mixed $default Fallback. @param string $type Filter. @return mixed Value. */
    public function get(string $key, mixed $default = null, string $type = 'string'): mixed
    {
        $value = $this->values[$key] ?? $default;
        return match ($type) {
            'int' => (int) $value,
            'array' => (array) $value,
            default => (string) $value,
        };
    }

    /** @param string $key Name. @param int $default Fallback. @return int Value. */
    public function getInt(string $key, int $default = 0): int
    {
        return $this->get($key, $default, 'int');
    }

    /** @param string $key Name. @param string $default Fallback. @return string Value. */
    public function getCmd(string $key, string $default = ''): string
    {
        return $this->get($key, $default, 'cmd');
    }
}

/** Minimal application; no session, site or database writes. */
final class Application
{
    public Input $input;

    /** @param array<string, mixed> $request Current request values. */
    public function __construct(array $request)
    {
        $this->input = new Input($request);
    }
}

/** Application provider for production methods. */
final class Factory
{
    public static Application $application;

    /** @return Application Test application. */
    public static function getApplication(): Application
    {
        return self::$application;
    }
}

/** Observe parameters at the routing boundary, before SEF transformation. */
final class Route
{
    public static array $calls = [];

    /** @param string $url Internal URL. @param bool $xhtml Escape flag. @return string Unchanged URL. */
    public static function _(string $url, bool $xhtml = true): string
    {
        self::$calls[] = $url;
        return $url;
    }
}

/** Minimal URI supporting the actual fallback-PDF clone/query flow. */
final class Uri
{
    public static self $current;
    private string $path;
    private array $query = [];

    /** @param string $url Source URL. */
    public function __construct(string $url)
    {
        [$this->path, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $this->query);
    }

    /** @return self Shared request URI. */
    public static function getInstance(): self
    {
        return self::$current;
    }

    /** @param bool $array Return an array. @return array<string, mixed> Current query. */
    public function getQuery(bool $array): array
    {
        return $this->query;
    }

    /** @param array<string, mixed> $query Replacement query. @return void */
    public function setQuery(array $query): void
    {
        $this->query = $query;
    }

    /** @param array<string> $parts Requested parts. @return string Current URL. */
    public function toString(array $parts = []): string
    {
        return $this->path . ($this->query ? '?' . http_build_query($this->query) : '');
    }
}

/** Stable entity routes deliberately select another menu, as Joomla helpers can do. */
final class JemHelperRoute
{
    /** @param string $name Route method. @param array $arguments Entity identifiers. @return string Test route. */
    public static function __callStatic(string $name, array $arguments): string
    {
        $view = strtolower(str_replace(['get', 'Route'], '', $name));
        $view = $view === '' ? 'event' : $view;
        return 'index.php?option=com_jem&view=' . $view . '&Itemid=999'
            . (isset($arguments[0]) ? '&id=' . $arguments[0] : '');
    }
}

// Extract unchanged production methods; only external Joomla services are replaced.
$source = file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
$methods = '';
foreach (['prepareExportLink', 'buildPrintLink', 'buildCurrentPdfLink'] as $method) {
    if (!preg_match(
        '/(?:static (?:public|protected)|(?:public|protected) static) function '
        . $method . '\([^\n]*\)[^\n]*\s*\{.*?^    \}/ms',
        $source,
        $match
    )) {
        throw new \RuntimeException('Missing export helper: ' . $method);
    }
    $methods .= str_replace('static protected function', 'public static function', $match[0]) . "\n";
}
$model = file_get_contents(JEM_TEST_ROOT . '/site/models/categories.php');
preg_match('/private static function resolveTypeSelection\([^\n]*\).*?^    \}/ms', $model, $match);
$methods .= str_replace('private static', 'public static', $match[0]);
eval('namespace ' . __NAMESPACE__ . '; final class JemOutput {' . $methods . '}');
