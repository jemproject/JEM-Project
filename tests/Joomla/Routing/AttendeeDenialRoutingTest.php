<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use JemProject\Tests\AttendeeDenial as Guard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Production guards/tasks and actual Joomla routing; application/data boundaries are isolated. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AttendeeDenialRoutingTest extends TestCase
{
    /** @return void Load native router libraries without connecting to an installed site. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires Joomla 5/6 libraries; no installed site is modified.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/day_router_environment.php';
        require_once JEM_TEST_ROOT . '/tests/Support/attendee_denial_environment.php';
        require_once JEM_TEST_ROOT . '/site/router.php';
        Guard\loadGuards();
        Guard\JemFactory::$user = new Guard\User();
        Factory::$application = new Guard\Application();
    }

    /** @return iterable Root/subdirectory, SEF on/off and absent/linked/home menus. */
    public static function routingCases(): iterable
    {
        foreach (['', '/joomla'] as $base) {
            foreach ([false, true] as $sef) {
                foreach (['none', 'linked', 'home'] as $menu) {
                    yield json_encode([$base, $sef, $menu]) => [$base, $sef, $menu];
                }
            }
        }
    }

    /**
     * Configure actual SiteRouter rules and the checkout component router.
     *
     * @param string $base Site path.
     * @param bool $sef SEF mode.
     * @param string $menuKind Synthetic menu configuration.
     * @return array{SiteRouter, string} Router and canonical current URL.
     */
    private function route(string $base, bool $sef, string $menuKind): array
    {
        $linked = $menuKind !== 'none';
        $menu = new DayRouterMenu($linked ? [77 => (object) [
            'id' => 77, 'component_id' => 42, 'component' => 'com_jem', 'language' => '*',
            'query' => ['option' => 'com_jem', 'view' => 'attendeeregistrations'],
            'route' => 'registrations', 'home' => (int) ($menuKind === 'home'),
        ]] : []);
        $menu->activeId = $linked ? 77 : null;
        $site = (new ReflectionClass(SiteRouter::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(SiteRouter::class, 'menu'))->setValue($site, $menu);
        $site->setComponentRouter('com_jem', new JemRouter(new DayRouterApplication(), $menu));
        $site->setComponentRouter('com_users', new \Joomla\CMS\Component\Router\RouterLegacy('Users'));
        $site->attachBuildRule([$site, 'buildInit'], Router::PROCESS_BEFORE);
        $site->attachBuildRule([$site, 'buildComponentPreprocess'], Router::PROCESS_BEFORE);
        if ($sef) {
            $site->attachBuildRule([$site, 'buildSefRoute'], Router::PROCESS_DURING);
        }
        $site->attachBuildRule([$site, 'buildBase'], Router::PROCESS_AFTER);
        $vars = ['option' => 'com_jem', 'view' => 'attendeeregistrations'];
        if ($linked) {
            $vars['Itemid'] = 77;
        }
        $site->setVars($vars);
        foreach (['base', 'root'] as $prop) {
            (new ReflectionProperty(Uri::class, $prop))->setValue(null,
                ['prefix' => 'https://example.test', 'path' => $base]);
        }
        $current = $site->build('index.php?' . http_build_query($vars))->toString(['path', 'query']);
        (new ReflectionProperty(Uri::class, 'instances'))->setValue(null,
            ['SERVER' => new Uri('https://example.test' . $current)]);
        (new ReflectionProperty(Route::class, '_router'))->setValue(null, ['site' => $site]);
        return [$site, $current];
    }

    /** @param object $object Target. @param string $method Method. @return Throwable|null Captured result. */
    private function invoke(object $object, string $method): ?Throwable
    {
        try {
            (new ReflectionMethod($object, $method))->invoke($object);
        } catch (Throwable $error) {
            return $error;
        }
        return null;
    }

    /**
     * Denial must not redirect to the inherited current route; every permission combination is covered.
     *
     * @param string $base Site path.
     * @param bool $sef SEF setting.
     * @param string $menu Menu kind.
     * @return void
     */
    #[DataProvider('routingCases')]
    public function testDeniedAndAllowedGuards(string $base, bool $sef, string $menu): void
    {
        [$router, $current] = $this->route($base, $sef, $menu);
        self::assertSame($current, $router->build('index.php')->toString(['path', 'query']),
            'Native Joomla reproduces why the old bare-index redirect cannot escape.');
        foreach (range(0, 15) as $mask) {
            Guard\JemFactory::$user->mask = $mask;
            $allowed = (bool) (($mask & 1) || (($mask & 14) === 14));
            foreach ([Guard\HtmlView::class => ['display', 'document'],
                Guard\PdfView::class => ['display', 'params'],
                Guard\Controller::class => ['assertCanManageAttendeeRegistrations', null]] as $class => [$method, $boundary]) {
                $app = new Guard\Application();
                Factory::$application = $app;
                $error = $this->invoke(new $class(), $method);
                self::assertNull($app->redirect, $class . ' mask=' . $mask);
                self::assertSame([], $app->messages);
                if ($allowed) {
                    if ($boundary === null) {
                        self::assertNull($error);
                    } else {
                        self::assertInstanceOf(Guard\ReachedBoundary::class, $error);
                        self::assertSame($boundary, $error->getMessage());
                    }
                } else {
                    self::assertInstanceOf(\Exception::class, $error);
                    self::assertSame(403, $error->getCode());
                    self::assertSame($class === Guard\PdfView::class
                        ? 'JERROR_ALERTNOAUTHOR' : 'COM_JEM_ATTENDEE_REGISTRATIONS_NO_ACCESS', $error->getMessage());
                }
            }
        }
    }

    /** @return iterable Write actions using the shared manager guard. */
    public static function tasks(): iterable
    {
        foreach (['setstatus', 'renotify', 'batch'] as $task) {
            yield $task => [$task];
        }
    }

    /** @param string $task Actual controller method. @return void No data/plugin operation follows denial. */
    #[DataProvider('tasks')]
    public function testTasksStopBeforeModelAccess(string $task): void
    {
        $this->route('/joomla', true, 'none');
        foreach (range(0, 15) as $mask) {
            Factory::$application = new Guard\Application();
            Guard\JemFactory::$user->mask = $mask;
            Guard\Session::$checks = 0;
            $error = $this->invoke(new Guard\Controller(), $task);
            self::assertSame(1, Guard\Session::$checks, 'Task still checks CSRF before authorization.');
            self::assertNull(Factory::$application->redirect);
            if (($mask & 1) || (($mask & 14) === 14)) {
                self::assertInstanceOf(Guard\ReachedBoundary::class, $error);
                self::assertSame('model', $error->getMessage());
            } else {
                self::assertInstanceOf(\Exception::class, $error);
                self::assertSame(403, $error->getCode());
                self::assertSame('COM_JEM_ATTENDEE_REGISTRATIONS_NO_ACCESS', $error->getMessage());
            }
        }
    }

    /**
     * Guest login and return address are unchanged for views and controller tasks.
     *
     * @param string $base Site path.
     * @param bool $sef SEF setting.
     * @param string $menu Menu kind.
     * @return void
     */
    #[DataProvider('routingCases')]
    public function testGuestsKeepLoginAndReturn(string $base, bool $sef, string $menu): void
    {
        [, $current] = $this->route($base, $sef, $menu);
        Guard\JemFactory::$user->id = 0;
        foreach ([Guard\HtmlView::class => ['display'], Guard\PdfView::class => ['display'],
            Guard\Controller::class => ['setstatus', 'renotify', 'batch']] as $class => $methods) {
            foreach ($methods as $method) {
                $app = new Guard\Application();
                Factory::$application = $app;
                $error = $this->invoke(new $class(), $method);
                self::assertNotNull($app->redirect);
                $uri = new Uri($app->redirect);
                self::assertSame('login', $uri->getVar('view'));
                self::assertSame('https://example.test' . $current, base64_decode($uri->getVar('return'), true));
                self::assertSame([['COM_JEM_ATTENDEE_REGISTRATIONS_LOGIN_REQUIRED', 'warning']], $app->messages);
                if ($class === Guard\Controller::class) {
                    self::assertInstanceOf(Guard\ReachedBoundary::class, $error);
                    self::assertSame('closed', $error->getMessage());
                } else {
                    self::assertNull($error);
                }
            }
        }
    }
}

