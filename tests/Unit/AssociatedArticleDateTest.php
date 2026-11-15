<?php
declare(strict_types=1);

namespace JemProject\Tests\ArticleDate;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Production title/date builders with real Joomla language files and isolated settings providers. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AssociatedArticleDateTest extends TestCase
{
    /** @return void Load actual methods and Joomla's language factory without a database. */
    protected function setUp(): void
    {
        if (!getenv('JEM_TEST_JOOMLA_ROOT')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to Joomla with de-DE, es-ES and en-GB installed.');
        }
        require JEM_TEST_ROOT . '/tests/Support/ics_import_environment.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        $methods = '';
        foreach (['buildAssociatedArticleTitle', 'formatAssociatedArticleDate'] as $name) {
            preg_match('/    protected function ' . $name . '\(.*?^    \}/ms', $source, $match);
            self::assertNotEmpty($match);
            $methods .= str_replace(['protected function', '\\Joomla\\CMS\\Component\\ComponentHelper'], ['public function', 'Configuration'], $match[0]);
        }
        eval('namespace ' . __NAMESPACE__ . '; use Joomla\\CMS\\Language\\LanguageFactoryInterface;'
            . 'use ' . __NAMESPACE__ . '\\Configuration as ComponentHelper; class Model {' . $methods . '}');
        JemHelper::$format = '{title} | {date} | {date_iso} | {lang} | {time}';
        Configuration::$site = 'de-DE';
    }

    /** @return iterable Locales and fallbacks, independent of the administrator interface. */
    public static function languages(): iterable
    {
        yield 'German' => ['de-DE', '10.11.2026'];
        yield 'Spanish' => ['es-ES', '10-11-2026'];
        yield 'English' => ['en-GB', '2026-11-10'];
        yield 'All uses site' => ['*', '10.11.2026'];
        yield 'empty uses site' => ['', '10.11.2026'];
        yield 'uninstalled uses site' => ['zz-ZZ', '10.11.2026'];
        yield 'invalid tag uses site' => ['../../de-DE', '10.11.2026'];
    }

    /** @param string $tag Content language. @param string $expected Short date. @return void */
    #[DataProvider('languages')]
    public function testTitleUsesContentLanguageWithStableIsoAlternative(string $tag, string $expected): void
    {
        $before = \Joomla\CMS\Factory::$language;
        $event = ['title' => 'Lecture', 'dates' => '2026-11-10', 'times' => '19:30:00', 'language' => $tag];
        $title = (new Model())->buildAssociatedArticleTitle($event, 123);
        self::assertStringContainsString('Lecture | ' . $expected . ' | 2026-11-10 |', $title);
        self::assertStringEndsWith('19:30:00', $title);
        self::assertSame($before, \Joomla\CMS\Factory::$language);
        self::assertSame('en-GB', $before->getTag());
    }

    /** @return iterable Calendar boundaries and invalid legacy input. */
    public static function dates(): iterable
    {
        yield 'leap day' => ['2028-02-29', '29.02.2028'];
        yield 'epoch is valid' => ['1970-01-01', '01.01.1970'];
        yield 'DST boundary' => ['2026-03-29', '29.03.2026'];
        yield 'open date' => ['', ''];
        yield 'zero date' => ['0000-00-00', ''];
        yield 'invalid leap day' => ['2026-02-29', ''];
        yield 'invalid day' => ['2026-11-31', ''];
        yield 'wrong stored format' => ['10.11.2026', ''];
    }

    /** @param string $date Stored value. @param string $expected Formatted result. @return void */
    #[DataProvider('dates')]
    public function testCalendarDateIsNotConvertedToAnInstant(string $date, string $expected): void
    {
        $previous = date_default_timezone_get();
        try {
            date_default_timezone_set('Pacific/Honolulu');
            self::assertSame($expected, (new Model())->formatAssociatedArticleDate($date, 'de-DE'));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /** @return void Recurring counters and unrelated tokens keep their existing behavior. */
    public function testRecurringTitleKeepsCounters(): void
    {
        JemHelper::$format = '{title} {##} ({date}) / {date_iso} / {id}';
        self::assertSame('Lecture 02 (10.11.2026) / 2026-11-10 / 123',
            (new Model())->buildAssociatedArticleTitle(['title' => 'Lecture', 'dates' => '2026-11-10', 'language' => 'de-DE'], 123, 2));
    }

    /** @return void The final fallback is deterministic when neither requested nor site language exists. */
    public function testUnavailableSiteLanguageUsesEnglishFallback(): void
    {
        Configuration::$site = 'zz-ZZ';
        self::assertSame('2026-11-10', (new Model())->formatAssociatedArticleDate('2026-11-10', '*'));
    }

    /** @return void Execute the actual title-sync assignment against a differently tagged target article. */
    public function testTitleSyncUsesDestinationLanguageWithoutChangingAlias(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        preg_match('/            \$titleData = array_replace.*?\$update->title = .*?;/s', $source, $match);
        self::assertNotEmpty($match);
        $operation = eval('return function ($eventData, $article, $eventId, $update) {' . $match[0] . ' return $update; };');
        $update = (object) ['alias' => 'existing-alias'];
        $result = $operation->call(new Model(), ['title' => 'Lecture', 'dates' => '2026-11-10', 'language' => 'en-GB'], (object) ['language' => 'de-DE'], 123, $update);
        self::assertStringContainsString('10.11.2026', $result->title);
        self::assertSame('existing-alias', $result->alias);
        self::assertStringContainsString('| en |', $result->title);
    }

    /** @return void Both client and server form validators accept only the intended new token. */
    public function testBothTitlePatternsAcceptDateIso(): void
    {
        $xml = new \DOMDocument();
        self::assertTrue($xml->load(JEM_TEST_ROOT . '/admin/models/forms/settings.xml'));
        $xpath = new \DOMXPath($xml);
        foreach (['event_associated_article_title_format', 'event_associated_article_recurrence_title_format'] as $name) {
            $field = $xpath->query('//field[@name="' . $name . '"]')->item(0);
            self::assertSame($field->getAttribute('pattern'), $field->getAttribute('validate_regex'));
            $regex = '~' . $field->getAttribute('validate_regex') . '~';
            self::assertMatchesRegularExpression($regex, '{title} ({date}) [{date_iso}]');
            self::assertDoesNotMatchRegularExpression($regex, '{date_isoo}');
            self::assertDoesNotMatchRegularExpression($regex, '{date_iso');
        }
    }
}

/** Isolate only the component settings; formatting uses Joomla's installed language implementation. */
final class Configuration
{
    public static string $site = 'de-DE';
    public static function getParams($component): object { return new \Joomla\Registry\Registry(['site' => self::$site]); }
}
final class JemHelper
{
    public static string $format = '';
    public static function globalattribs(): object
    {
        return new \Joomla\Registry\Registry(['event_associated_article_title_format' => self::$format,
            'event_associated_article_recurrence_title_format' => self::$format]);
    }
}
final class Factory
{
    public static function getContainer(): object
    {
        return new class {
            public function get($key): object { return new \Joomla\CMS\Language\LanguageFactory(); }
        };
    }
}
