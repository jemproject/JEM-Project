<?php

declare(strict_types=1);

namespace JemProject\Tests\WeeklyCalendar;

use DateTimeImmutable;
use JemProject\Tests\AnnualCalendar\Clock;
use JemProject\Tests\AnnualCalendar\Input;
use JemProject\Tests\AnnualCalendar\Registry;

/** Joomla application boundary; no database or installed site is changed. */
final class Application
{
    public Input $input;
    public Registry $params;

    /** @param array $input Request. @param array $params Menu settings. */
    public function __construct(array $input, array $params)
    {
        $this->input = new Input($input);
        $this->params = new Registry($params);
    }

    /** @return Registry Menu settings. */
    public function getParams(): Registry
    {
        return $this->params;
    }

    /** @return object No active menu required by these export tests. */
    public function getMenu(): object
    {
        return new class {
            /** @return null No active menu. */
            public function getActive(): mixed
            {
                return null;
            }
        };
    }
}

final class Factory
{
    public static Application $app;

    /** @return Application Synthetic request. */
    public static function getApplication(): Application
    {
        return self::$app;
    }

    /** @return Registry Site timezone. */
    public static function getConfig(): Registry
    {
        return new Registry(['offset' => Clock::$zone]);
    }
}

final class Text
{
    /** @param string $key Key. @return string Deterministic translated label. */
    public static function _(string $key): string
    {
        return match ($key) {
            'COM_JEM_WKCAL_WEEK' => 'Week',
            'COM_JEM_WKCAL_WEEKS' => 'Weeks',
            default => $key,
        };
    }

    /** @param string $key Key. @param mixed ...$args Values. @return string Translated label. */
    public static function sprintf(string $key, mixed ...$args): string
    {
        return $key === 'COM_JEM_WEEKCAL_WEEK_NUMBER' ? sprintf('Week %d, %d', ...$args) : $key;
    }
}

final class jemhtml
{
    /** @param mixed ...$args Icon options. @return string No icon rendering required. */
    public static function icon(mixed ...$args): string
    {
        return '';
    }
}

#[\AllowDynamicProperties]
class ViewBoundary
{
    /** @param WeekModel $model Model under test. */
    public function __construct(protected WeekModel $model)
    {
    }

    /** @return WeekModel Shared model. */
    public function getModel(): WeekModel
    {
        return $this->model;
    }

    /** @param string $key Model getter. @return array Empty rows with actual state initialized. */
    public function get(string $key): array
    {
        return $this->model->getItems();
    }

    /** @param string $text Label. @return string Escaped label. */
    public function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

/** Capture only terminal PDF rendering services; run the actual weekly HTML builder. */
class PdfBoundary
{
    public static array $captured = [];

    /** @param mixed ...$args Export arguments. @return void */
    public static function renderWeeklyCalendar(mixed ...$args): void
    {
        self::$captured = $args;
    }

    /** @param Registry|null $params Menu. @return int First weekday. */
    protected static function getCalendarFirstWeekday(?Registry $params): int
    {
        return (int) ($params?->get('firstweekday', 1)) === 0 ? 0 : 1;
    }

    /** @param Registry|null $params Menu. @return string PDF mode. */
    protected static function getCalendarPdfLayout(?Registry $params): string
    {
        return $params?->get('pdf_layout', 'calendar') ?? 'calendar';
    }

    /** @param array $rows Events. @param DateTimeImmutable $start Start. @param DateTimeImmutable $end End. @return array */
    protected static function buildCalendarEventsByDate(array $rows, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        return [];
    }

    /** @param array $rows Events. @return array Legend. */
    protected static function buildCalendarCategoryLegend(array $rows): array
    {
        return [];
    }

    /** @param mixed ...$args Actual grid arguments. @return string Inspectable capture. */
    protected static function buildCalendarGridHtml(mixed ...$args): string
    {
        return json_encode(['start' => $args[1]->format('Y-m-d'), 'end' => $args[2]->format('Y-m-d'),
            'cells' => $args[4], 'subtitle' => $args[11], 'time' => $args[1]->format('H:i:s')]);
    }

    /** @param mixed ...$args Actual agenda arguments. @return string Inspectable capture. */
    protected static function buildCalendarAgendaHtml(mixed ...$args): string
    {
        return json_encode(['start' => $args[1]->format('Y-m-d'), 'end' => $args[2]->format('Y-m-d'),
            'subtitle' => $args[7], 'time' => $args[1]->format('H:i:s')]);
    }
}

