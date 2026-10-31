<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views\CategoryResponse;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/** Exercise production missing-category and adjacent access guards without a Joomla HTTP server. */
final class CategoryMissingResourceTest extends TestCase
{
    /** @return iterable<string, array{string, string}> View paths and resolved category states. */
    public static function cases(): iterable
    {
        foreach (['calendar', 'list', 'pdf', 'ical'] as $path) {
            $states = ['missing_null', 'missing_false', 'missing_array', 'valid_empty', 'root'];
            $states = array_merge(
                $states,
                in_array($path, ['calendar', 'list'], true)
                    ? ['denied_guest', 'denied_member']
                    : ['denied_export']
            );
            foreach ($states as $state) {
                yield $path . ':' . $state => [$path, $state];
            }
        }
    }

    /**
     * Extract the real missing-resource guard together with its adjacent access check.
     *
     * @param string $path Category output path.
     * @return string Production guard statements.
     */
    private static function guard(string $path): string
    {
        $html = in_array($path, ['calendar', 'list'], true);
        $source = file_get_contents(
            JEM_TEST_ROOT . '/site/views/category/view.' . ($html ? 'html' : 'raw') . '.php'
        );
        $pattern = $html
            ? '/if \(empty\(\$category\)\) \{.*?(?=\s*\/\/ (?:Set Page title|are events available))/s'
            : '/if \(empty\(\$(?:category|categoryItem)\)\) \{.*?'
                . 'if \(!JemFrontendAccess::enforceViewAccess[^\n]+\) \{\s*return;\s*\}/s';
        self::assertSame(2, preg_match_all($pattern, $source, $guards));

        return $guards[0][in_array($path, ['calendar', 'pdf'], true) ? 0 : 1];
    }

    /**
     * Missing categories must raise 404; valid/root/access-denied paths retain their existing decisions.
     *
     * @param string $path Output path.
     * @param string $state Resolved category/access scenario.
     * @return void
     */
    #[DataProvider('cases')]
    public function testMissingResourceAndAccessDecisions(string $path, string $state): void
    {
        $category = match ($state) {
            'missing_null' => null,
            'missing_false' => false,
            'missing_array' => [],
            default => (object) [
                'id' => $state === 'root' ? 'root' : 7,
                'user_has_access_category' => !str_starts_with($state, 'denied'),
            ],
        };
        $categoryItem = $category;
        $items = $rows = [];
        $app = new Application();
        $user = new User($state === 'denied_guest');
        $uri = new Uri();
        ExportAccess::$calls = [];
        $guard = self::guard($path);
        $run = eval(
            'namespace ' . __NAMESPACE__ . ';'
            . 'use ' . __NAMESPACE__ . '\ExportAccess as JemFrontendAccess;'
            . 'return static function ($app, $user, $uri, $category, $categoryItem, $items, $rows) {'
            . $guard . 'return "continue"; };'
        );
        $error = null;
        $result = null;
        try {
            $result = $run($app, $user, $uri, $category, $categoryItem, $items, $rows);
        } catch (Throwable $caught) {
            $error = $caught;
        }

        if (str_starts_with($state, 'missing')) {
            self::assertNotNull($error, 'A missing category must not return a success/empty response.');
            self::assertSame(404, $error->getCode());
            self::assertSame('JGLOBAL_CATEGORY_NOT_FOUND', $error->getMessage());
            self::assertSame([], $app->messages);
            self::assertNull($app->redirect);
            self::assertFalse($app->closed);
            self::assertSame([], ExportAccess::$calls, 'Do not confuse a missing resource with access denial.');
            self::assertNull($result);

            return;
        }

        self::assertFalse($app->closed);
        if ($state === 'denied_member') {
            self::assertNotNull($error);
            self::assertSame(403, $error->getCode());
            self::assertSame('JERROR_ALERTNOAUTHOR', $error->getMessage());
            self::assertSame([], $app->messages);
            self::assertNull($app->redirect);
            self::assertNull($result);
        } elseif ($state === 'denied_guest') {
            self::assertNull($error);
            self::assertSame([['COM_JEM_LOGIN_TO_ACCESS', 'warning']], $app->messages);
            self::assertStringContainsString('option=com_users&view=login&return=', $app->redirect ?? '');
            self::assertNull($result);
        } elseif ($state === 'denied_export') {
            self::assertNull($error);
            self::assertSame([false], ExportAccess::$calls);
            self::assertNull($result, 'Raw views must stop when the existing access helper denies access.');
        } else {
            self::assertNull($error);
            self::assertSame([], $app->messages);
            self::assertNull($app->redirect);
            self::assertSame('continue', $result, 'A resolved category is valid even when there are no events.');
            self::assertSame(
                in_array($path, ['pdf', 'ical'], true) ? [true] : [],
                ExportAccess::$calls
            );
        }
    }
}

/** Record application effects without emitting headers or terminating the test. */
final class Application
{
    public array $messages = [];
    public ?string $redirect = null;
    public bool $closed = false;

    /** @param string $text Message. @param string $kind Severity. @return void */
    public function enqueueMessage(string $text, string $kind): void
    {
        $this->messages[] = [$text, $kind];
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

/** User boundary for the unchanged HTML access branches. */
final class User
{
    /** @param bool $guest Whether the visitor is anonymous. */
    public function __construct(private bool $guest)
    {
    }

    /** @param string $key User property. @return int|bool Guest or identifier. */
    public function get(string $key): int|bool
    {
        return $key === 'guest' ? $this->guest : ($this->guest ? 0 : 17);
    }
}

/** Raw-view access-helper spy; tests verify delegation, not the helper's internal ACL policy. */
final class ExportAccess
{
    public static array $calls = [];

    /** @param bool $allowed Resolved category access. @param Application $app Application. @return bool Decision. */
    public static function enforceViewAccess(bool $allowed, Application $app): bool
    {
        self::$calls[] = $allowed;
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

/** Routing boundary for login redirects; no SEF simulation. */
final class Route
{
    /** @param string $url Destination. @param bool $xhtml Escape flag. @return string Unchanged URL. */
    public static function _(string $url, bool $xhtml = true): string
    {
        return $url;
    }
}

/** Stable return URL for anonymous access checks. */
final class Uri
{
    /** @return string Current category route. */
    public function toString(): string
    {
        return 'index.php?option=com_jem&view=category&id=7';
    }
}
