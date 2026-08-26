<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/site/classes/loadmorerequestpolicy.class.php';

final class LoadMoreRequestPolicyTest extends TestCase
{
    private string $rateDirectory;

    protected function setUp(): void
    {
        $this->rateDirectory = sys_get_temp_dir() . '/jem-loadmore-rate-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->rateDirectory)) {
            return;
        }

        foreach ((array) glob($this->rateDirectory . '/*') as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $marker = $this->rateDirectory . '/.cleanup';

        if (is_file($marker)) {
            unlink($marker);
        }

        rmdir($this->rateDirectory);
    }

    public function testNormalisesTheExplicitEventsListRequest(): void
    {
        $request = JemLoadMoreRequestPolicy::normaliseParameters(array(
            'option' => 'com_jem',
            'view' => 'eventslist',
            'task' => 'loadmore',
            'format' => 'json',
            'Itemid' => '42',
            'offset' => '250',
            'limit' => '25',
            'lastDisplayedMonth' => 'August 2026',
            'loadmore_context' => 'archive',
            'lang' => 'en-GB',
        ));

        self::assertSame(250, $request['offset']);
        self::assertSame(25, $request['limit']);
        self::assertSame('August 2026', $request['lastDisplayedMonth']);
        self::assertSame('archive', $request['context']);
    }

    #[DataProvider('invalidRequestProvider')]
    public function testRejectsUnexpectedMalformedOrUnboundedInput(array $changes): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemLoadMoreRequestPolicy::normaliseParameters(array_merge($this->validRequest(), $changes));
    }

    public static function invalidRequestProvider(): array
    {
        return array(
            'unexpected parameter' => array(array('filter_search' => 'untrusted')),
            'different component' => array(array('option' => 'com_content')),
            'different view' => array(array('view' => 'category')),
            'different task' => array(array('task' => 'display')),
            'different format' => array(array('format' => 'html')),
            'offset above budget' => array(array('offset' => '10001')),
            'negative offset' => array(array('offset' => '-1')),
            'limit above budget' => array(array('limit' => '101')),
            'zero limit' => array(array('limit' => '0')),
            'array Itemid' => array(array('Itemid' => array('42'))),
            'array month' => array(array('lastDisplayedMonth' => array('August 2026'))),
            'oversized month' => array(array('lastDisplayedMonth' => str_repeat('m', 65))),
            'month control character' => array(array('lastDisplayedMonth' => "August\n2026")),
            'unknown context' => array(array('loadmore_context' => 'featured')),
            'invalid language' => array(array('lang' => '../en-GB')),
        );
    }

    public function testMethodQueryAndAddressPoliciesAreStrict(): void
    {
        self::assertTrue(JemLoadMoreRequestPolicy::isGetRequest('GET'));
        self::assertFalse(JemLoadMoreRequestPolicy::isGetRequest('POST'));
        self::assertFalse(JemLoadMoreRequestPolicy::isGetRequest(array('GET')));
        self::assertTrue(JemLoadMoreRequestPolicy::isQueryStringAllowed(str_repeat('a', 4096)));
        self::assertFalse(JemLoadMoreRequestPolicy::isQueryStringAllowed(str_repeat('a', 4097)));
        self::assertSame('192.0.2.10', JemLoadMoreRequestPolicy::normaliseRemoteAddress('192.0.2.10'));
        self::assertSame('unknown', JemLoadMoreRequestPolicy::normaliseRemoteAddress('not-an-address'));
    }

    public function testRateLimitIsAtomicPerRemoteAddressAndResets(): void
    {
        self::assertTrue(JemLoadMoreRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.1', 2, 60, 1000));
        self::assertTrue(JemLoadMoreRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.1', 2, 60, 1001));
        self::assertFalse(JemLoadMoreRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.1', 2, 60, 1002));
        self::assertTrue(JemLoadMoreRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.2', 2, 60, 1002));
        self::assertTrue(JemLoadMoreRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.1', 2, 60, 1060));
    }

    private function validRequest(): array
    {
        return array(
            'option' => 'com_jem',
            'view' => 'eventslist',
            'task' => 'loadmore',
            'format' => 'json',
            'Itemid' => '42',
            'offset' => '0',
            'limit' => '10',
            'lastDisplayedMonth' => '',
            'loadmore_context' => '',
        );
    }
}
