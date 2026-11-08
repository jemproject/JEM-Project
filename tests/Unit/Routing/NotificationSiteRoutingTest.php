<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verify notification routing and the equivalent frontend links without sending mail. */
final class NotificationSiteRoutingTest extends TestCase
{
    /** @return void Load the production helper with isolated Joomla routing boundaries. */
    public static function setUpBeforeClass(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/helpers/route.php');
        self::assertSame(1, preg_match('/^    public static function getAbsoluteSiteRoute\([^\n]*\n.*?^    }/ms', $source, $method));
        eval('namespace ' . __NAMESPACE__ . '; use ' . __NAMESPACE__ . '\\NotificationRouteBoundary as Route;'
            . 'use ' . __NAMESPACE__ . '\\NotificationUriBoundary as Uri;'
            . 'class AbsoluteSiteRouteHarness { ' . $method[0] . ' }');
    }

    /** @return void Reset the simulated Joomla router for each independent case. */
    protected function setUp(): void
    {
        NotificationRouteBoundary::$calls = [];
        NotificationRouteBoundary::$error = null;
        NotificationUriBoundary::$root = 'https://example.test/subsite/';
    }

    /** @return iterable<string, array{string}> Internal routes with their existing context. */
    public static function notificationRoutes(): iterable
    {
        yield 'event' => ['index.php?option=com_jem&view=event&id=123:my-event'];
        yield 'venue' => ['index.php?option=com_jem&view=venue&id=45:my-venue'];
        yield 'reservation' => ['index.php?option=com_jem&view=registration&id=67'];
        yield 'menu-language' => ['index.php?option=com_jem&view=event&id=123&Itemid=56&lang=de-DE'];
    }

    /**
     * Route internally using the site client and let Joomla supply the absolute origin.
     * @param string $url Notification route.
     * @return void
     */
    #[DataProvider('notificationRoutes')]
    public function testRoutesWithSiteClientAndUnescapedParameters(string $url): void
    {
        $result = AbsoluteSiteRouteHarness::getAbsoluteSiteRoute($url);
        self::assertSame([['site', $url, false, NotificationRouteBoundary::TLS_IGNORE, true]], NotificationRouteBoundary::$calls);
        self::assertSame('https://example.test/subsite/event?key=one&other=two', $result);
        self::assertStringNotContainsString('&amp;', $result);
    }

    /**
     * A normal routing exception retains the existing functional notification URL.
     * @param string $url Notification route.
     * @return void
     */
    #[DataProvider('notificationRoutes')]
    public function testRouterFailureKeepsQueryLink(string $url): void
    {
        NotificationRouteBoundary::$error = new \RuntimeException('Site router unavailable');
        self::assertSame('https://example.test/subsite/' . $url, AbsoluteSiteRouteHarness::getAbsoluteSiteRoute($url));
    }

    /** @return void Programming errors must remain visible instead of being swallowed. */
    public function testDoesNotHideUnrelatedErrors(): void
    {
        NotificationRouteBoundary::$error = new \LogicException('Unexpected programming error');
        $this->expectException(\LogicException::class);
        AbsoluteSiteRouteHarness::getAbsoluteSiteRoute('index.php?option=com_jem&view=event&id=1');
    }

    /** @return iterable<string, array{string}> Both event layouts. */
    public static function loginLayouts(): iterable
    {
        yield 'default' => ['site/views/event/tmpl/default_attendees.php'];
        yield 'responsive' => ['site/views/event/tmpl/responsive/default_attendees.php'];
    }

    /**
     * Preserve the full return URL, including base64 characters meaningful in a query string.
     * @param string $file Event attendees template.
     * @return void
     */
    #[DataProvider('loginLayouts')]
    public function testLoginReturnAndSingleHtmlEscaping(string $file): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
        self::assertSame(1, preg_match('/\$urlLogin\s*= Route::_\([^\r\n]+;.*?(?=\$urlRegister\s*=)/s', $source, $match));
        $returnUrl = 'https://example.test/subsite/event?search=>>>???&lang=es-ES';
        $run = eval('namespace ' . __NAMESPACE__ . '; use ' . __NAMESPACE__ . '\\NotificationRouteBoundary as Route;'
            . 'return static function ($returnUrl) { ' . $match[0] . ' return $urlLogin; };');
        $url = $run($returnUrl);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame($returnUrl, base64_decode($query['return'], true));
        self::assertStringNotContainsString('&amp;', $url);
        $html = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        self::assertStringContainsString('&amp;', $html);
        self::assertStringNotContainsString('&amp;amp;', $html);
    }

    /** @return void Cover every equivalent Mailer link without changing its route parameters. */
    public function testMailerCallSitesUseTheSharedSiteRouter(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/mailer.php');
        self::assertSame(4, substr_count($source, 'JEMHelperRoute::getAbsoluteSiteRoute(JEMHelperRoute::getEventRoute($event->slug))'));
        self::assertSame(1, substr_count($source, 'JEMHelperRoute::getAbsoluteSiteRoute(JEMHelperRoute::getVenueRoute($venue->slug))'));
        self::assertDoesNotMatchRegularExpression('/Route::_\(\$uri->root\(\)/', $source);
        if (preg_match('/^    private function _reservationUrl\([^\n]*\n.*?^    }/ms', $source, $method)) {
            eval('namespace ' . __NAMESPACE__ . '; use ' . __NAMESPACE__ . '\\AbsoluteSiteRouteHarness as JEMHelperRoute;'
                . 'class ReservationUrlHarness {' . str_replace('private function', 'public function', $method[0]) . '}');
            $helper = new ReservationUrlHarness();
            self::assertSame('', $helper->_reservationUrl(0));
            self::assertSame('', $helper->_reservationUrl(-1));
            self::assertSame([], NotificationRouteBoundary::$calls);
            $helper->_reservationUrl(67);
            self::assertSame('index.php?option=com_jem&view=registration&id=67', NotificationRouteBoundary::$calls[0][1]);
        }
    }

    /** @return void Route Teaser links internally and escape them at their existing HTML boundary. */
    public function testTeaserRoutesCategoryAndVenue(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem_teaser/helper.php');
        self::assertSame(2, preg_match_all('/\$link = (Route::_\([^\r\n]+);\R\s*\$link = htmlspecialchars/', $source, $matches));
        foreach ($matches[1] as $expression) {
            $run = eval('namespace ' . __NAMESPACE__ . '; use ' . __NAMESPACE__ . '\\NotificationRouteBoundary as Route;'
                . 'return static function () { $catId = 12; $catAlias = "category";'
                . '$row = (object) ["locid" => 34, "l_alias" => "venue"]; return ' . $expression . '; };');
            $url = $run();
            self::assertStringStartsWith('index.php?option=com_jem&view=', $url);
            self::assertStringNotContainsString('&amp;', $url);
        }
    }

    /** @return void New reminder snapshots use site URLs; branches without reminders stay unchanged. */
    public function testReminderSnapshotLinks(): void
    {
        $file = JEM_TEST_ROOT . '/site/classes/reminderservice.class.php';
        if (!is_file($file)) {
            self::assertFileDoesNotExist($file);
            return;
        }
        $source = file_get_contents($file);
        foreach (['event_url' => 'event&id=123', 'reservation_url' => 'registration&id=67'] as $key => $destination) {
            self::assertSame(1, preg_match('/\x27' . $key . '\x27 => ([^\r\n]+),/', $source, $match));
            $run = eval('namespace ' . __NAMESPACE__ . '; use ' . __NAMESPACE__ . '\\AbsoluteSiteRouteHarness as JEMHelperRoute;'
                . 'return static function () { $registration = (object) ["event_id" => 123, "registration_id" => 67];'
                . 'return ' . $match[1] . '; };');
            $run();
            self::assertSame('index.php?option=com_jem&view=' . $destination, end(NotificationRouteBoundary::$calls)[1]);
        }
    }
}

/** Isolated routing boundary; integration probes exercise the real Joomla router separately. */
final class NotificationRouteBoundary
{
    public const TLS_IGNORE = 0;
    public static array $calls = [];
    public static ?\Throwable $error = null;

    /**
     * Record the parameters passed to Joomla's absolute URL builder.
     * @param string $client Requested router client.
     * @param string $url Internal route.
     * @param bool $xhtml Whether to HTML-escape the result.
     * @param int $tls Protocol selection.
     * @param bool $absolute Whether to include the origin.
     * @return string Simulated result with deliberately unescaped query separators.
     */
    public static function link($client, $url, $xhtml, $tls, $absolute): string
    {
        self::$calls[] = [$client, $url, $xhtml, $tls, $absolute];
        if (self::$error !== null) {
            throw self::$error;
        }
        return 'https://example.test/subsite/event?key=one&other=two';
    }

    /**
     * Reproduce Joomla URI's query decoding when rebuilding a route.
     * @param string $url Internal route.
     * @param bool $xhtml Whether to HTML-escape the result.
     * @return string Rebuilt route for checking return-value preservation and HTML escaping.
     */
    public static function _($url, $xhtml = true): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $url = parse_url($url, PHP_URL_PATH) . '?' . urldecode(http_build_query($query, '', '&'));
        return $xhtml ? htmlspecialchars($url, ENT_QUOTES, 'UTF-8') : $url;
    }
}

/** Isolated site origin for the exceptional non-SEF fallback. */
final class NotificationUriBoundary
{
    public static string $root;

    /** @return string Configured origin including the installation subdirectory. */
    public static function root(): string
    {
        return self::$root;
    }
}
