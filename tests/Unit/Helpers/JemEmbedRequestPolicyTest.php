<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/plugins/plg_content_jemembed/requestpolicy.php';

final class JemEmbedRequestPolicyTest extends TestCase
{
    private string $rateDirectory;

    protected function setUp(): void
    {
        $this->rateDirectory = sys_get_temp_dir() . '/jem-embed-rate-' . bin2hex(random_bytes(8));
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

    public function testNormalisesSupportedParametersAndPagination(): void
    {
        $parameters = JemEmbedRequestPolicy::normaliseParameters(array(
            'type' => 'upcoming',
            'show_featured' => '1',
            'title' => 'link',
            'show_date' => '0',
            'show_time' => 'on',
            'show_enddatetime' => 'off',
            'show_category' => 'link',
            'show_venue' => 'off',
            'max_events' => '25',
            'start' => '50',
            'cut_title' => '120',
            'catids' => '3, 1,3',
            'venueids' => '8,9',
            'date_format' => 'd M Y',
            'time_format' => 'H:i',
        ));

        self::assertSame('upcoming', $parameters['type']);
        self::assertSame('on', $parameters['show_featured']);
        self::assertSame('off', $parameters['show_date']);
        self::assertSame(25, $parameters['max_events']);
        self::assertSame(50, $parameters['start']);
        self::assertSame(120, $parameters['cut_title']);
        self::assertSame('3,1', $parameters['catids']);
        self::assertSame('8,9', $parameters['venueids']);
    }

    #[DataProvider('invalidParameterProvider')]
    public function testRejectsInvalidOrOversizedParameters(array $parameters): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemEmbedRequestPolicy::normaliseParameters(array_merge($this->validParameters(), $parameters));
    }

    public static function invalidParameterProvider(): array
    {
        return array(
            'invalid type' => array(array('type' => 'anything')),
            'event limit too high' => array(array('max_events' => '101')),
            'negative event limit' => array(array('max_events' => '-1')),
            'offset too high' => array(array('start' => '10001')),
            'title length too high' => array(array('cut_title' => '501')),
            'malformed category list' => array(array('catids' => '1,,2')),
            'non-numeric venue list' => array(array('venueids' => '1,test')),
            'too many category ids' => array(array('catids' => implode(',', range(1, 51)))),
            'format too long' => array(array('date_format' => str_repeat('Y', 65))),
            'format control character' => array(array('time_format' => "H:i\n")),
        );
    }

    public function testMatchesRawAndHashedTokensWithoutAcceptingInvalidValues(): void
    {
        $token = 'm4FvVGs8m9jA_2uF9tQ0N7cH5bW1xZ3p';
        $digest = 'sha256:' . hash('sha256', $token);

        self::assertTrue(JemEmbedRequestPolicy::tokenMatches($token, 'rotating-token,' . $token));
        self::assertTrue(JemEmbedRequestPolicy::tokenMatches($token, $digest));
        self::assertFalse(JemEmbedRequestPolicy::tokenMatches('wrong-token', $digest));
        self::assertFalse(JemEmbedRequestPolicy::tokenMatches("invalid token", $digest));
        self::assertFalse(JemEmbedRequestPolicy::tokenMatches($token, implode(',', range(1, 21))));
    }

    public function testCanonicalBaseUrlNeverUsesAnUntrustedRequestOrigin(): void
    {
        self::assertSame('https://events.example.org/jem', JemEmbedRequestPolicy::normaliseBaseUrl('https://events.example.org/jem/'));
        self::assertSame('', JemEmbedRequestPolicy::normaliseBaseUrl(''));
        self::assertSame('', JemEmbedRequestPolicy::normaliseBaseUrl('https://user:secret@events.example.org'));
        self::assertSame('', JemEmbedRequestPolicy::normaliseBaseUrl('javascript:alert(1)'));
        self::assertSame('', JemEmbedRequestPolicy::normaliseBaseUrl('https://events.example.org/?origin=other'));
    }

    public function testQueryAndDescriptionBudgetsAreBounded(): void
    {
        self::assertTrue(JemEmbedRequestPolicy::isQueryStringAllowed(str_repeat('a', 4096)));
        self::assertFalse(JemEmbedRequestPolicy::isQueryStringAllowed(str_repeat('a', 4097)));

        $description = str_repeat('x', JemEmbedRequestPolicy::MAX_DESCRIPTION_LENGTH + 100);
        $truncated = JemEmbedRequestPolicy::truncateDescription($description);

        self::assertLessThanOrEqual(JemEmbedRequestPolicy::MAX_DESCRIPTION_LENGTH, mb_strlen($truncated, 'UTF-8'));
        self::assertStringEndsWith('…', $truncated);
    }

    public function testRateLimitIsAtomicPerIdentityAndResetsAfterTheWindow(): void
    {
        self::assertTrue(JemEmbedRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.1', 2, 60, 1000));
        self::assertTrue(JemEmbedRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.1', 2, 60, 1001));
        self::assertFalse(JemEmbedRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.1', 2, 60, 1002));
        self::assertTrue(JemEmbedRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.2', 2, 60, 1002));
        self::assertTrue(JemEmbedRequestPolicy::consumeRateLimit($this->rateDirectory, 'ip:192.0.2.1', 2, 60, 1060));
    }

    private function validParameters(): array
    {
        return array(
            'type' => 'unfinished',
            'show_featured' => 'off',
            'title' => 'on',
            'show_date' => 'on',
            'show_time' => 'on',
            'show_enddatetime' => 'on',
            'show_category' => 'on',
            'show_venue' => 'on',
            'max_events' => '100',
            'start' => '0',
            'cut_title' => '100',
            'catids' => '',
            'venueids' => '',
            'date_format' => '',
            'time_format' => '',
        );
    }
}
