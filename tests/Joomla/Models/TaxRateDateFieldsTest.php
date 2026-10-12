<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\CalendarField;
use Joomla\CMS\Language\Language;
use Joomla\Event\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Uses Joomla's real field and JEM table validation without a database connection. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TaxRateDateFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/includes/defines.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a Joomla 5/6 installation. No database is used.');
        }

        define('JPATH_BASE', $root);
        require_once $root . '/includes/defines.php';
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });

        // RAW date filtering must not ask for a user's timezone or any site services.
        Factory::$application = new stdClass();
        $this->setCalendarLanguage('en-GB', '%Y-%m-%d', 'Y-m-d');
        require_once JEM_TEST_ROOT . '/admin/tables/jem_tax_rates.php';
    }

    private function setCalendarLanguage(string $tag, string $format, string $filterFormat): void
    {
        $language = new Language($tag);
        (new ReflectionProperty(Language::class, 'strings'))->setValue($language, [
            'DATE_FORMAT_CALENDAR_DATE' => $format,
            'DATE_FORMAT_FILTER_DATE' => $filterFormat,
        ]);
        Factory::$language = $language;
    }

    private function calendar(string $name, string $value = ''): CalendarField
    {
        $form = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/taxrate.xml');
        $elements = $form->xpath('//field[@name="' . $name . '"]');
        $field = new CalendarField();
        self::assertTrue($field->setup($elements[0], $value));

        return $field;
    }

    private function taxRate(string $from, string $until, int $id = 0): jem_tax_rates
    {
        // Avoid the DB-backed Table constructor, but execute its real check/dispatch path.
        $table = (new ReflectionClass(jem_tax_rates::class))->newInstanceWithoutConstructor();
        $table->setDispatcher(new Dispatcher());
        $table->id = $id;
        $table->code = 'TEST21';
        $table->name = 'Test tax rate';
        $table->rate = '21.00';
        $table->valid_from = $from;
        $table->valid_until = $until;

        return $table;
    }

    public static function calendarLanguages(): iterable
    {
        yield 'English ISO format' => ['en-GB', '%Y-%m-%d', 'Y-m-d'];
        yield 'Dutch day first' => ['nl-NL', '%d-%m-%Y', 'd-m-Y'];
        yield 'Spanish day first' => ['es-ES', '%d/%m/%Y', 'd/m/Y'];
    }

    #[DataProvider('calendarLanguages')]
    public function testCreateAndEditKeepDatesAcrossCalendarLanguages(string $tag, string $format, string $filterFormat): void
    {
        $this->setCalendarLanguage($tag, $format, $filterFormat);
        foreach ([0, 42] as $id) {
            $from = $this->calendar('valid_from', '2028-02-29');
            $until = $this->calendar('valid_until', '2028-10-31');
            self::assertSame('%Y-%m-%d', $from->format);
            self::assertSame('raw', $from->filter);
            self::assertSame('2028-02-29', $from->filter('2028-02-29'));
            self::assertSame('2028-10-31', $until->filter('2028-10-31'));
            $table = $this->taxRate($from->filter('2028-02-29'), $until->filter('2028-10-31'), $id);
            self::assertTrue($table->check(), (string) $table->getError());
            self::assertSame('2028-02-29', $table->valid_from);
            self::assertSame('2028-10-31', $table->valid_until);
        }
    }

    public function testEmptyAndOpenEndedValidityIsStillAllowed(): void
    {
        foreach ([['', ''], ['', '2026-10-31'], ['2026-10-01', '']] as [$from, $until]) {
            $table = $this->taxRate($this->calendar('valid_from')->filter($from), $this->calendar('valid_until')->filter($until));
            self::assertTrue($table->check(), (string) $table->getError());
            self::assertSame($from === '' ? null : $from, $table->valid_from);
            self::assertSame($until === '' ? null : $until, $table->valid_until);
        }
    }

    public function testInvalidDatesAndInvertedIntervalsAreStillRejected(): void
    {
        foreach (['2026-02-29', '2026-02-30', '31-10-2026', '2026-10-31 12:00:00'] as $value) {
            $table = $this->taxRate($this->calendar('valid_from')->filter($value), '');
            self::assertFalse($table->check(), $value);
        }

        $table = $this->taxRate('2026-11-01', '2026-10-31');
        self::assertFalse($table->check());
    }

    public function testOriginalTranslatedCalendarProducesAnUnwantedTime(): void
    {
        $this->setCalendarLanguage('nl-NL', '%d-%m-%Y', 'd-m-Y');
        $field = new CalendarField();
        self::assertTrue($field->setup(new SimpleXMLElement(
            '<field name="valid_from" type="calendar" format="%Y-%m-%d" showtime="false" translateformat="true" />'
        ), ''));
        $value = $field->filter('31-10-2026');
        self::assertMatchesRegularExpression('/^2026-10-31 \d{2}:\d{2}:\d{2}$/D', $value);
        self::assertFalse($this->taxRate($value, '')->check());
    }
}
