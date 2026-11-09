<?php
declare(strict_types=1);

/** Small application double for the actual Calendar module's parameter/date block. */
final class CalendarMonthOffsetApplication
{
    public object $input;
    public array $state;

    /** @param array $input Request parameters. @param array $state Remembered module state. */
    public function __construct(array $input, array $state)
    {
        $this->state = $state;
        $this->input = new class ($input) {
            public function __construct(private array $values) {}
            public function getInt(string $key, int $default = 0): int
            {
                return (int) ($this->values[$key] ?? $default);
            }
        };
    }

    /** @return mixed Previously selected month/year, or null before navigation. */
    public function getUserState(string $key): mixed { return $this->state[$key] ?? null; }

    /** Remember the same base date used by the unmodified navigation links. */
    public function setUserState(string $key, mixed $value): void { $this->state[$key] = $value; }
}

/** Factory double used only by the extracted native module block. */
final class CalendarMonthOffsetFactory
{
    public static CalendarMonthOffsetApplication $app;
    public static function getApplication(): CalendarMonthOffsetApplication { return self::$app; }
}

/** Preserve native date handling while isolating dependencies unrelated to month offsets. */
final class CalendarMonthOffsetHelper
{
    public static function getJoomlaTimeZoneName(): string { return 'Europe/Madrid'; }
}

/** Translation keys suffice to verify which month each native layout selects. */
final class CalendarMonthOffsetText
{
    public static function _(mixed $key): string { return (string) $key; }
}

/**
 * Run the unchanged source block, freezing only the current time and external dependencies.
 *
 * @param string $root Source checkout.
 * @param mixed $raw Stored Month_offset value.
 * @param array $input Navigation request.
 * @param array $state Previous request's remembered base date.
 * @param int $remember Whether the module remembers navigation.
 * @return array Calculated dates, navigation links and resulting remembered state.
 */
function calendarMonthOffsetProbe(string $root, mixed $raw, array $input = [], array $state = [], int $remember = 1): array
{
    CalendarMonthOffsetFactory::$app = new CalendarMonthOffsetApplication($input + ['Itemid' => 10], $state);
    $params = new class ($raw, $remember) {
        public function __construct(private mixed $raw, private int $remember) {}
        public function get(string $key, mixed $default = null): mixed
        {
            return match ($key) {
                'Month_offset' => $this->raw,
                'Remember' => $this->remember,
                default => $default,
            };
        }
    };
    $module = (object) ['id' => 9];
    $source = file_get_contents($root . '/modules/mod_jem_cal/mod_jem_cal.php');
    $start = strpos($source, '# Parameters');
    $code = substr($source, $start, strpos($source, '# Requested URL') - $start);
    $code = str_replace("new DateTimeImmutable('now',", "new DateTimeImmutable('2026-11-08 12:00:00',", $code);
    eval('use CalendarMonthOffsetFactory as Factory; use CalendarMonthOffsetHelper as JemHelper; use CalendarMonthOffsetText as Text;' . $code);
    $start = strpos($source, '# Create link params');
    eval(substr($source, $start, strpos($source, '# Get data', $start) - $start));

    return compact('offset_year', 'offset_month', 'prev_month', 'next_month', 'prev_month_year',
        'next_month_year', 'today_month', 'today_year', 'prev_year', 'next_year',
        'props_prev', 'props_next', 'props_home', 'props_prev_year', 'props_next_year')
        + ['state' => CalendarMonthOffsetFactory::$app->state];
}

/**
 * Execute the native layout's month/title block; PHPUnit treats any PHP warning as a failure.
 *
 * @param string $root Source checkout.
 * @param string $layout Native Calendar layout.
 * @param array $date Output of the real module calculation.
 * @return string Rendered calendar title.
 */
function calendarMonthOffsetCaption(string $root, string $layout, array $date): string
{
    extract($date, EXTR_SKIP);
    $first_day = 1;
    $Year_length = 1;
    $Month_length = 0;
    $source = file_get_contents($root . '/modules/mod_jem_cal/tmpl/' . $layout . '.php');
    $start = strpos($source, '$year  = $offset_year;');
    $code = substr($source, $start, strpos($source, '# Begin calendar.', $start) - $start);
    eval('use CalendarMonthOffsetText as Text;' . $code);

    return $title;
}

// Native Joomla validation is isolated from PHPUnit and requires no database or Joomla writes.
if (isset($argv[1]) && $argv[1] === 'validate-joomla') {
    define('_JEXEC', 1);
    $joomla = $argv[2];
    $root = $argv[3];
    require $joomla . '/libraries/src/Form/FormRule.php';
    require $joomla . '/libraries/src/Form/Rule/RegexRule.php';
    $element = simplexml_load_file($root . '/modules/mod_jem_cal/mod_jem_cal.xml')
        ->xpath('//field[@name="Month_offset"]')[0];
    $results = [];
    foreach (['0', '1', '-1', '24', '-24', '121', '-121', '+24', '024', 'abc', '2abc', '1.5', '1e2', '', "24\n"] as $value) {
        $results[] = ['value' => $value, 'accepted' => (new Joomla\CMS\Form\Rule\RegexRule())->test($element, $value)];
    }
    echo json_encode($results, JSON_THROW_ON_ERROR), PHP_EOL;
}
