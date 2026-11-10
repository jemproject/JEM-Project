<?php

declare(strict_types=1);

namespace JemProject\Tests\AttendeeCsv;

require_once JEM_TEST_ROOT . '/site/classes/csv.class.php';

/** Configuration boundary: no installed site or database is loaded. */
final class JemConfig
{
    public static array $settings = [];

    /** @return self Isolated export settings. */
    public static function getInstance(): self
    {
        return new self();
    }

    /** @return self Registry-compatible configuration boundary. */
    public function toRegistry(): self
    {
        return $this;
    }

    /** @param string $name Option. @param mixed $default Fallback. @return mixed Configured value. */
    public function get(string $name, mixed $default = null): mixed
    {
        return self::$settings[$name] ?? $default;
    }
}

final class Text
{
    /** @param string $key Language key. @return string Stable export header or status. */
    public static function _(string $key): string
    {
        return $key === 'COM_JEM_ATTENDEES_REGID' ? 'Reg.ID' : $key;
    }
}

/** Replace only row retrieval; the CSV method and writer are unchanged production code. */
class AttendeeCsvBoundary
{
    public string $pricingMode = 'classic';
    public array $rows = [];

    /** @return object Exported event configuration. */
    public function getEvent(): object
    {
        return (object) ['waitinglist' => 1, 'pricing_mode' => $this->pricingMode];
    }

    /** @return array<object> Registration rows in their selected order. */
    public function getAllItems(): array
    {
        return $this->rows;
    }

    /** @return array<int, array<object>> One admission line per priced reservation. */
    public function getCommercialBreakdowns(): array
    {
        $breakdowns = [];
        foreach ($this->rows as $row) {
            $breakdowns[$row->id] = [(object) ['quantity' => 2, 'item_name' => 'Admission', 'pool_name' => 'Room A']];
        }
        return $breakdowns;
    }
}

// Load the real method in an isolated namespace, following the existing test fixtures.
$source = file_get_contents(JEM_TEST_ROOT . '/admin/models/attendees.php');
if (!preg_match('/public function getCsv\(\)\s*\{.*?^    \}/ms', $source, $match)) {
    throw new \RuntimeException('Missing attendee CSV exporter');
}
eval('namespace ' . __NAMESPACE__ . '; use \\JemCsv; final class AttendeeCsv extends AttendeeCsvBoundary {' . $match[0] . '}');
