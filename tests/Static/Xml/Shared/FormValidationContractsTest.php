<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormValidationContractsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function eventFormProvider(): iterable
    {
        yield 'admin event form' => array(JEM_TEST_ROOT . '/admin/models/forms/event.xml');
        yield 'site event form' => array(JEM_TEST_ROOT . '/site/models/forms/event.xml');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function venueFormProvider(): iterable
    {
        yield 'admin venue form' => array(JEM_TEST_ROOT . '/admin/models/forms/venue.xml');
        yield 'site venue form' => array(JEM_TEST_ROOT . '/site/models/forms/venue.xml');
    }

    #[DataProvider('eventFormProvider')]
    public function testEventFormsKeepRequiredFieldsAndSafeHtmlFilters(string $path): void
    {
        $xpath = $this->xpath($path);

        $this->assertFieldAttribute($xpath, 'title', 'required', 'true', $path);
        $this->assertFieldAttribute($xpath, 'cats', 'required', 'true', $path);
        $this->assertFieldAttribute($xpath, 'articletext', 'filter', '\\Joomla\\CMS\\Component\\ComponentHelper::filterText', $path);
        $this->assertFieldAttribute($xpath, 'dates', 'format', '%Y-%m-%d', $path);
        $this->assertFieldAttribute($xpath, 'enddates', 'format', '%Y-%m-%d', $path);
    }

    #[DataProvider('venueFormProvider')]
    public function testVenueFormsKeepRequiredFieldsAndUrlFilters(string $path): void
    {
        $xpath = $this->xpath($path);

        $this->assertFieldAttribute($xpath, 'venue', 'required', 'true', $path);
        $this->assertFieldAttribute($xpath, 'url', 'type', 'url', $path);
        $this->assertFieldAttribute($xpath, 'url', 'filter', 'url', $path);
        $this->assertFieldAttribute($xpath, 'locdescription', 'filter', '\\Joomla\\CMS\\Component\\ComponentHelper::filterText', $path);
        $this->assertFieldAttribute($xpath, 'published', 'filter', 'intval', $path);
    }

    #[DataProvider('eventFormProvider')]
    public function testEventLinkSubformsFilterUserEditableFields(string $path): void
    {
        $xpath = $this->xpath($path);

        $this->assertFieldAttribute($xpath, 'description', 'filter', 'safehtml', $path);
        $this->assertFieldAttribute($xpath, 'url', 'type', 'url', $path);
        $this->assertFieldAttribute($xpath, 'url', 'filter', 'url', $path);
        $this->assertFieldAttribute($xpath, 'max_width', 'type', 'number', $path);
        $this->assertFieldAttribute($xpath, 'max_width', 'min', '0', $path);
        $this->assertFieldAttribute($xpath, 'max_height', 'type', 'number', $path);
        $this->assertFieldAttribute($xpath, 'max_height', 'min', '0', $path);
    }

    public function testVenueTableKeepsRuntimeUrlValidation(): void
    {
        $contents = (string) file_get_contents(JEM_TEST_ROOT . '/admin/tables/venue.php');

        self::assertStringContainsString('$this->url = strip_tags($this->url)', $contents);
        self::assertStringContainsString('filter_var($urlToValidate, FILTER_VALIDATE_URL)', $contents);
        self::assertStringContainsString('in_array($parsed[\'scheme\'], [\'http\', \'https\'])', $contents);
    }

    private function xpath(string $path): DOMXPath
    {
        self::assertFileExists($path);

        $document = new DOMDocument();
        $document->load($path);

        return new DOMXPath($document);
    }

    private function assertFieldAttribute(DOMXPath $xpath, string $field, string $attribute, string $expected, string $path): void
    {
        $nodes = $xpath->query('//field[@name="' . $field . '"]');

        self::assertNotFalse($nodes);
        self::assertGreaterThan(0, $nodes->length, $this->relativePath($path) . ' should define field ' . $field . '.');

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            if ($node->hasAttribute($attribute)) {
                self::assertSame(
                    $expected,
                    $node->getAttribute($attribute),
                    $this->relativePath($path) . ' field ' . $field . ' should keep ' . $attribute . '="' . $expected . '".'
                );
                return;
            }
        }

        self::fail($this->relativePath($path) . ' field ' . $field . ' should define ' . $attribute . '="' . $expected . '".');
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
