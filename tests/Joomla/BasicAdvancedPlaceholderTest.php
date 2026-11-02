<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Render the actual template once per process with native Joomla language loading. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BasicAdvancedPlaceholderTest extends TestCase
{
    private string $scratch = '';
    private const KEY = 'MOD_JEM_NO_TITLE_OR_VENUE';

    /** @return void Bootstrap libraries with isolated language files, overrides and cache. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
        }
        $this->scratch = sys_get_temp_dir() . '/jem-basic-placeholder-' . bin2hex(random_bytes(8));
        mkdir($this->scratch . '/language/en-GB', 0777, true);
        define('JPATH_BASE', $this->scratch);
        define('JPATH_ROOT', $this->scratch);
        define('JPATH_CACHE', $this->scratch . '/cache');
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/basic_advanced_placeholder_environment.php';
        copy(
            JEM_TEST_ROOT . '/modules/mod_jem/language/en-GB/mod_jem.ini',
            $this->scratch . '/language/en-GB/mod_jem.ini'
        );
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/';
        (new ReflectionProperty(Uri::class, 'base'))->setValue(
            null,
            ['prefix' => 'https://example.test', 'path' => '']
        );
    }

    /** @return void Remove only this test's generated directory and cache. */
    protected function tearDown(): void
    {
        if ($this->scratch === '' || !is_dir($this->scratch)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->scratch, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->scratch);
    }

    /** @return iterable<string, array{array, bool}> Display/link variants with normal and overridden language. */
    public static function configurations(): iterable
    {
        $cases = [
            'plain title' => [],
            'eye link' => ['linkdet' => 1],
            'title link' => ['linkdet' => 2],
            'featured without link' => ['featured' => 1],
            'featured highlighting disabled' => ['featured' => 1, 'highlight_featured' => 0],
            'hidden title with eye link' => ['showtitle' => 0, 'linkdet' => 1],
            'hidden title without link' => ['showtitle' => 0],
            'hidden featured title' => ['showtitle' => 0, 'featured' => 1],
            'venue link' => ['showtitle' => 0, 'showvenue' => 1],
            'plain venue' => ['showtitle' => 0, 'showvenue' => 1, 'linkloc' => 0],
            'missing venue with eye link' => ['showtitle' => 0, 'showvenue' => 1, 'venue' => '', 'linkdet' => 1],
            'category visible' => ['showcategory' => 1, 'linkdet' => 2],
        ];
        foreach ($cases as $name => $settings) {
            foreach ([false, true] as $override) {
                yield $name . ($override ? ' override' : '') => [$settings, $override];
            }
        }
    }

    /**
     * Load source translations and optionally a genuine Joomla language override.
     *
     * @param bool $override Whether to include attribute-sensitive text.
     * @return string Expected tooltip.
     */
    private function loadLanguage(bool $override): string
    {
        $expected = $override ? 'No "title" & <venue> displayed' : 'No title or venue displayed';
        if ($override) {
            mkdir($this->scratch . '/language/overrides', 0777, true);
            file_put_contents(
                $this->scratch . '/language/overrides/en-GB.override.ini',
                self::KEY . '="' . str_replace('"', '\\"', $expected) . '"' . PHP_EOL
            );
        }
        $language = new Language('en-GB', false);
        self::assertTrue($language->load('mod_jem', $this->scratch, 'en-GB', true, false));
        Factory::$language = $language;
        return $expected;
    }

    /**
     * Verify placeholders independently of links and preserve date markup and highlighting.
     *
     * @param array $changes Scenario-specific options/data.
     * @param bool $override Whether to load a language override.
     * @return void
     */
    #[DataProvider('configurations')]
    public function testPlaceholderSemanticsAndExistingLinks(array $changes, bool $override): void
    {
        $tooltip = $this->loadLanguage($override);
        $options = array_replace([
            'showtitle' => 1, 'showvenue' => 0, 'showcategory' => 0,
            'linkdet' => 0, 'linkloc' => 1, 'highlight_featured' => 1,
            'showiconcountry' => 0, 'featured' => 0, 'venue' => 'Example venue',
        ], $changes);
        $params = new Registry($options);
        $list = [(object) [
            'featured' => $options['featured'], 'title' => 'Example event', 'venue' => $options['venue'],
            'link' => '/event/7', 'venueurl' => '/venue/2', 'dates' => '2026-11-02',
            'times' => '12:00', 'enddates' => '2026-11-02', 'endtimes' => '13:00',
            'dateinfo' => '2 November', 'catname' => 'Example category',
        ]];
        ob_start();
        require JEM_TEST_ROOT . '/modules/mod_jem/tmpl/table-advanced.php';
        $html = ob_get_clean();
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($document);
        $row = $xpath->query('//tr[td]')->item(0);
        self::assertNotNull($row);
        $cells = $xpath->query('./td', $row);
        self::assertCount($options['showcategory'] ? 7 : 6, $cells);
        $fallback = !$options['showtitle'] && (!$options['showvenue'] || $options['venue'] === '');
        $minus = $xpath->query('//i[contains(@class, "fa-minus")]');
        self::assertCount($fallback ? 1 : 0, $minus);
        if ($fallback) {
            self::assertSame($tooltip, $minus->item(0)->getAttribute('title'));
            self::assertCount(1, $xpath->query('.//i[contains(@class, "fa-minus")]', $cells->item(0)));
            self::assertSame(2, $minus->item(0)->attributes->length, 'Only class and title attributes.');
            self::assertSame(0, $minus->item(0)->childElementCount);
        }
        self::assertStringNotContainsString('MOD_JEM_NO_LINK', $html);
        self::assertStringNotContainsString(self::KEY, $html);
        self::assertSame(substr_count($html, '<span'), substr_count($html, '</span>'));
        self::assertSame('2026-11-02', trim($cells->item(1)->textContent));
        self::assertSame('12:00', trim($cells->item(2)->textContent));
        self::assertSame('2026-11-02', trim($cells->item(3)->textContent));
        self::assertSame('13:00', trim($cells->item(4)->textContent));
        $dateSpans = $xpath->query('./span', $cells->item(1));
        self::assertCount(1, $dateSpans);
        self::assertSame(
            $options['highlight_featured'] && $options['featured'] ? 'event-title highlight_featured' : 'event-title',
            $dateSpans->item(0)->getAttribute('class')
        );
        $expectedLinks = [];
        if ($options['showtitle'] && $options['linkdet'] === 2) {
            $expectedLinks[] = '/event/7';
        } elseif (!$options['showtitle'] && $options['showvenue'] && $options['venue'] !== '' && $options['linkloc'] === 1) {
            $expectedLinks[] = '/venue/2';
        }
        if ($options['linkdet'] === 1) {
            $expectedLinks[] = '/event/7';
        }
        self::assertSame(
            $expectedLinks,
            array_map(static fn ($node) => $node->value, iterator_to_array($xpath->query('//a/@href')))
        );
    }

    /** @return void Require one translated key, and keep empty-list behavior. */
    public function testLanguageDefinitionAndEmptyList(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem/language/en-GB/mod_jem.ini');
        self::assertSame(1, preg_match_all('/^' . self::KEY . '\s*=/m', $source));
        $this->loadLanguage(false);
        $params = new Registry();
        $list = [];
        ob_start();
        require JEM_TEST_ROOT . '/modules/mod_jem/tmpl/table-advanced.php';
        $html = ob_get_clean();
        self::assertStringContainsString('No events found.', $html);
        self::assertStringNotContainsString('fa-minus', $html);
    }
}
