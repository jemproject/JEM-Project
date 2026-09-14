<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SiteMenuFieldInventoryTest extends TestCase
{
    public function testSiteMenuFieldsMatchTheReviewedInventory(): void
    {
        $expected = $this->loadInventory();
        $actual = array();
        $paths = glob(JEM_TEST_ROOT . '/site/views/*/tmpl/*.xml');

        self::assertIsArray($paths);

        foreach ($paths as $path) {
            $xml = new DOMDocument();
            self::assertTrue($xml->load($path), 'Unable to load ' . $this->relativePath($path));

            $xpath = new DOMXPath($xml);
            $nodes = $xpath->query('//field[@name and not(@type="spacer")]');
            self::assertInstanceOf(DOMNodeList::class, $nodes);

            $fields = array();

            foreach ($nodes as $node) {
                self::assertInstanceOf(DOMElement::class, $node);
                $fields[] = $node->getAttribute('name');
            }

            $fields = array_values(array_unique($fields));
            sort($fields);

            if ($fields !== array()) {
                $actual[$this->relativePath($path)] = $fields;
            }
        }

        ksort($actual);

        self::assertSame(
            $expected,
            $actual,
            'The site menu field inventory changed. Review every added or removed field and update the contract intentionally.'
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadInventory(): array
    {
        $path = __DIR__ . '/site-menu-fields.txt';
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        self::assertIsArray($lines);

        $inventory = array();

        foreach ($lines as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('|', $line, 2);
            self::assertCount(2, $parts, 'Invalid inventory line: ' . $line);

            [$xmlPath, $field] = $parts;
            $inventory[$xmlPath][] = $field;
        }

        foreach ($inventory as &$fields) {
            $fields = array_values(array_unique($fields));
            sort($fields);
        }
        unset($fields);

        ksort($inventory);

        return $inventory;
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
