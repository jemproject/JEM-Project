<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventStructuredDataContractsTest extends TestCase
{
    public function testFactoryLoadsTheProfileIndependentEventBuilder(): void
    {
        $factory = $this->read('/site/factory.php');
        $builder = $this->read('/site/classes/eventstructureddata.class.php');
        $packageBuilder = $this->read('/scripts/build-packages.php');

        self::assertStringContainsString("classes/eventstructureddata.class.php", $factory);
        self::assertStringContainsString('final class JemEventStructuredData', $builder);
        self::assertStringNotContainsString('JemFeaturePolicy', $builder);
        self::assertStringNotContainsString("'offers'", $builder);
        self::assertStringContainsString("'site/classes/eventstructureddata.class.php'", $packageBuilder);
    }

    public function testEventDetailAddsCanonicalAndOneJsonLdProjection(): void
    {
        $view = $this->read('/site/views/event/view.html.php');

        self::assertStringContainsString("\$document->addHeadLink(\$canonicalUrl, 'canonical');", $view);
        self::assertStringContainsString('JemEventStructuredData::analyse($item, array(', $view);
        self::assertStringContainsString('JemEventStructuredData::render(', $view);
        self::assertSame(1, substr_count($view, '$document->addCustomTag($jsonLd);'));
        self::assertStringContainsString("if (\$this->print || \$canonicalUrl === '')", $view);
        self::assertStringContainsString("'physical_location_visible' => \$showVenue || \$showVenueName", $view);
        self::assertStringContainsString("'physical_address_visible' => \$showVenueAddress", $view);
        self::assertStringContainsString("'virtual_location_visible' => \$showOnlineMeeting", $view);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicEventSurfaceProvider(): iterable
    {
        yield 'site/controller.php' => array('/site/controller.php');

        foreach (array('/site/views', '/site/common/views', '/modules') as $relativeRoot) {
            $root = JEM_TEST_ROOT . $relativeRoot;
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(JEM_TEST_ROOT)));
                    yield ltrim($relativePath, '/') => array($relativePath);
                }
            }
        }
    }

    #[DataProvider('publicEventSurfaceProvider')]
    public function testCollectionSurfacesDoNotEmitCompetingEventMicrodata(string $relativePath): void
    {
        $code = $this->read($relativePath);

        self::assertStringNotContainsString('schema.org/Event', $code, $relativePath);
        self::assertStringNotContainsString('formatSchemaOrgDateTime(', $code, $relativePath);
        self::assertStringNotContainsString('dateschema', $code, $relativePath);
        self::assertDoesNotMatchRegularExpression('/itemprop=["\'](?:event|eventStatus|offers|startDate|endDate)["\']/i', $code, $relativePath);
    }

    public function testLegacyListBadgeHelperReturnsVisualBadgesOnly(): void
    {
        $output = $this->read('/site/classes/output.class.php');
        $start = strpos($output, 'static public function eventStateBadges(');
        $end = strpos($output, 'static public function typeBadge(', $start);

        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $method = substr($output, $start, $end - $start);

        self::assertStringContainsString('Retained for template compatibility; ignored since 5.1', $output);
        self::assertStringNotContainsString('itemprop', $method);
        self::assertStringNotContainsString('schema.org', $method);
        self::assertStringContainsString('jem-event-state-badge', $method);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
