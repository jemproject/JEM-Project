<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views\VenueAddressOrder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Render the production address fragments with filled and empty optional fields. */
final class VenueAddressOrderTest extends TestCase
{
    /** @return iterable Layout path, address property and optional-field state. */
    public static function layouts(): iterable
    {
        foreach (['venue' => 'venue', 'event' => 'item'] as $view => $entity) {
            foreach (['default.php', 'responsive/default.php'] as $layout) {
                foreach ([true, false] as $filled) {
                    yield $view . '/' . $layout . '/' . (int) $filled => [
                        'site/views/' . $view . '/tmpl/' . $layout, $entity, $filled,
                    ];
                }
            }
        }
    }

    /** @param string $path Template. @param string $entity Address property. @param bool $filled Optional fields. */
    #[DataProvider('layouts')]
    public function testDisplayedOrderAndOptionalEscapedFields(string $path, string $entity, bool $filled): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/' . $path);
        $start = strpos($source, '<?php if ($this->' . $entity . '->street) : ?>');
        $country = strpos($source, '<?php if ($this->' . $entity . '->country) : ?>', $start);
        $end = strpos($source, '<?php endif; ?>', $country) + strlen('<?php endif; ?>');
        $fragment = substr($source, $start, $end - $start);
        $address = (object) ['street' => 'Street', 'district' => $filled ? '<District>' : '',
            'postalCode' => '12345', 'city' => 'City', 'state' => 'County',
            'region' => $filled ? '<Region>' : '', 'country' => 'ES', 'countryimg' => ''];
        $html = (new AddressFixture($address))->render($fragment);
        preg_match_all('/<dt class="venue_([^" ]+)/', $html, $matches);
        self::assertSame($filled ? ['street', 'district', 'postalCode', 'city', 'state', 'region', 'country']
            : ['street', 'postalCode', 'city', 'state', 'country'], $matches[1]);
        self::assertStringNotContainsString('<District>', $html);
        self::assertStringNotContainsString('<Region>', $html);
        if ($filled) {
            self::assertStringContainsString('&lt;District&gt;', $html);
            self::assertStringContainsString('&lt;Region&gt;', $html);
        }
    }
}

/** Minimal view boundary, keeping the actual production conditional markup. */
final class AddressFixture
{
    public object $venue;
    public object $item;

    public function __construct(object $address)
    {
        $this->venue = $this->item = $address;
    }

    /** @param string $value Raw field. @return string Escaped HTML text. */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /** @param string $fragment Production markup. @return string Rendered HTML. */
    public function render(string $fragment): string
    {
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . '; ?>' . $fragment);
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

/** Translation boundary for rendering without a Joomla application. */
final class Text
{
    public static function _(string $key): string
    {
        return $key;
    }
}
