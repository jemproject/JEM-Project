<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VenueEditActionViewTest extends TestCase
{
    #[DataProvider('venueLayouts')]
    public function testEditActionIsRenderedWithVenueHeading(string $relativePath): void
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        $source = (string) file_get_contents($path);
        $venueMarker = strpos($source, '<!--Venue-->');
        self::assertNotFalse($venueMarker);

        $beforeVenue = substr($source, 0, $venueMarker);
        $venueHeadingEnd = strpos($source, '</h2>', $venueMarker);
        self::assertNotFalse($venueHeadingEnd);
        $venueHeading = substr($source, $venueMarker, $venueHeadingEnd - $venueMarker);

        self::assertStringNotContainsString('JemOutput::editbutton', $beforeVenue);
        self::assertStringContainsString("JemOutput::editbutton(\$this->venue", $venueHeading);
        self::assertStringContainsString("\$this->permissions->canEditVenue, 'venue'", $venueHeading);
    }

    public static function venueLayouts(): array
    {
        return array(
            'legacy' => array('/site/views/venue/tmpl/default.php'),
            'responsive' => array('/site/views/venue/tmpl/responsive/default.php'),
        );
    }
}
