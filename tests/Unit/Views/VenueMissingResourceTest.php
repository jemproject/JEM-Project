<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views\VenueResponse;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/** Exercise actual resource/access guards, without simulating an HTTP server or PDF renderer. */
final class VenueMissingResourceTest extends TestCase
{
    /** @return iterable<string, array{string, string}> Output paths and resolved resource states. */
    public static function cases(): iterable
    {
        foreach (['calendar', 'detail', 'calendar_pdf', 'detail_pdf', 'ical'] as $path) {
            $states = ['missing_null', 'missing_false', 'missing_array', 'valid_empty'];
            $states = array_merge($states, in_array($path, ['calendar', 'detail'], true)
                ? ['denied_guest', 'denied_member'] : ['denied_export']);
            foreach ($states as $state) {
                yield $path . ':' . $state => [$path, $state];
            }
        }
    }

    /**
     * Extract the real missing-resource and access decisions; unrelated hierarchy rendering is out of scope.
     *
     * @param string $path Output path.
     * @return string Production statements, with no replacement of the behavior under test.
     */
    private static function guards(string $path): string
    {
        $html = in_array($path, ['calendar', 'detail'], true);
        $paths = $html ? ['calendar', 'detail'] : ['calendar_pdf', 'detail_pdf', 'ical'];
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/venue/view.' . ($html ? 'html' : 'raw') . '.php');
        self::assertSame(count($paths), preg_match_all(
            '/if \(empty\(\$venue\)\) \{.*?^            \}/ms', $source, $missing
        ));
        $pattern = $html
            ? '/if \(empty\(\$venue->user_has_access_venue\)\) \{.*?^            \}/ms'
            : '/if \(!JemFrontendAccess::enforceViewAccess[^\n]+\) \{.*?^            \}/ms';
        self::assertSame(count($paths), preg_match_all($pattern, $source, $access));
        $index = array_search($path, $paths, true);

        return $missing[0][$index] . $access[0][$index];
    }

    /**
     * Require 404 only for missing venues and preserve valid-empty, login and forbidden decisions.
     *
     * @param string $path Output path.
     * @param string $state Resolved resource/access state.
     * @return void
     */
    #[DataProvider('cases')]
    public function testMissingResourceAndAccessDecisions(string $path, string $state): void
    {
        $venue = match ($state) {
            'missing_null' => null,
            'missing_false' => false,
            'missing_array' => [],
            default => (object) ['id' => 7, 'user_has_access_venue' => $state === 'valid_empty'],
        };
        $app = new Application();
        Factory::$app = $app;
        $guest = $state === 'denied_guest';
        $user = new class ($guest) {
            /** @param bool $guest Whether the visitor is anonymous. */
            public function __construct(private bool $guest)
            {
            }

            /** @param string $key User property. @return int|bool Guest flag or ID. */
            public function get(string $key): int|bool
            {
                return $key === 'guest' ? $this->guest : ($this->guest ? 0 : 17);
            }
        };
        $uri = new class {
            /** @return string Stable login return URL. */
            public function toString(): string
            {
                return 'index.php?option=com_jem&view=venue&id=7';
            }
        };
        $run = eval('namespace ' . __NAMESPACE__ . '; use \\Exception;'
            . 'return static function ($venue, $app, $user, $uri, $rows) {'
            . self::guards($path) . 'return "continue"; };');
        $error = null;
        $result = null;
        try {
            $result = $run($venue, $app, $user, $uri, []);
        } catch (Throwable $caught) {
            $error = $caught;
        }

        if (str_starts_with($state, 'missing')) {
            self::assertNotNull($error, 'Missing venues must not return a successful empty response.');
            self::assertSame(404, $error->getCode());
            self::assertSame('COM_JEM_VENUE_ERROR_VENUE_NOT_FOUND', $error->getMessage());
            self::assertFalse($app->closed);
            self::assertSame([], $app->messages);
            self::assertSame([], $app->access);
            self::assertNull($app->redirect);
            self::assertNull($result);
            return;
        }

        self::assertFalse($app->closed);
        if ($state === 'denied_member') {
            self::assertNotNull($error);
            self::assertSame(403, $error->getCode());
            self::assertSame('JERROR_ALERTNOAUTHOR', $error->getMessage());
            self::assertNull($result);
            self::assertNull($app->redirect);
        } elseif ($state === 'denied_guest') {
            self::assertNull($error);
            self::assertSame([['COM_JEM_LOGIN_TO_ACCESS', 'warning']], $app->messages);
            self::assertStringContainsString('option=com_users&view=login&return=', $app->redirect ?? '');
            self::assertNull($result);
        } elseif ($state === 'denied_export') {
            self::assertNull($error);
            self::assertSame([false], $app->access);
            self::assertNull($result, 'A denied raw export must stop at its existing access helper.');
        } else {
            self::assertNull($error);
            self::assertSame('continue', $result, 'A resolved venue without events is still valid.');
            self::assertSame([], $app->messages);
            self::assertNull($app->redirect);
            self::assertSame(in_array($path, ['calendar', 'detail'], true) ? [] : [true], $app->access);
        }
    }
}

/** Capture application effects without sending headers or terminating PHPUnit. */
final class Application
{
    public array $messages = [];
    public array $access = [];
    public ?string $redirect = null;
    public bool $closed = false;

    /** @param string $text Message. @param string $type Severity. @return void */
    public function enqueueMessage(string $text, string $type): void
    {
        $this->messages[] = [$text, $type];
    }

    /** @param string $url Login destination. @return void */
    public function redirect(string $url): void
    {
        $this->redirect = $url;
    }

    /** @return void Record an empty-response exit. */
    public function close(): void
    {
        $this->closed = true;
    }
}

/** Application boundary for the legacy raw guard, used to prove the pre-fix failure. */
final class Factory
{
    public static Application $app;

    /** @return Application Current isolated application. */
    public static function getApplication(): Application
    {
        return self::$app;
    }
}

/** Raw access-helper spy; verifies delegation, not the helper's internal policy. */
final class JemFrontendAccess
{
    /** @param bool $allowed Resolved access. @param Application $app Application. @return bool Decision. */
    public static function enforceViewAccess(bool $allowed, Application $app): bool
    {
        $app->access[] = $allowed;
        return $allowed;
    }
}

/** Translation boundary retaining stable message keys. */
final class Text
{
    /** @param string $key Language key. @return string Unchanged key. */
    public static function _(string $key): string
    {
        return $key;
    }
}

/** Routing boundary for login redirects; no SEF behavior is simulated. */
final class Route
{
    /** @param string $url Destination. @param bool $xhtml Escape flag. @return string Unchanged URL. */
    public static function _(string $url, bool $xhtml = true): string
    {
        return $url;
    }
}
