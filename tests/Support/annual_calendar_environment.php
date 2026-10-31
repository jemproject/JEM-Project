<?php

declare(strict_types=1);

namespace JemProject\Tests\AnnualCalendar;

use DateTimeImmutable;
use DateTimeZone;

/** Controlled clock used only by the extracted Joomla-local date helper. */
final class Clock
{
    public static string $now = '2026-12-31 23:30:00';
    public static string $zone = 'UTC';
    public static int $reads = 0;
}

final class FrozenDateTime extends DateTimeImmutable
{
    /** @param string $date Date input. @param DateTimeZone|null $zone Explicit timezone. */
    public function __construct(string $date = 'now', ?DateTimeZone $zone = null)
    {
        if ($date === 'now') {
            ++Clock::$reads;
            $zone ??= new DateTimeZone(date_default_timezone_get());
            $date = (new DateTimeImmutable(Clock::$now, new DateTimeZone('UTC')))
                ->setTimezone($zone)->format('Y-m-d H:i:s');
        }
        parent::__construct($date, $zone);
    }
}

/** Settings and input boundary; no application or database is bootstrapped. */
class Registry
{
    /** @param array<string, mixed> $values Configuration values. */
    public function __construct(public array $values = [])
    {
    }

    /** @param string $key Key. @param mixed $default Fallback. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $key Key. @param mixed $value Value. @return void */
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /** @param string $key Key. @param mixed $default Fallback. @return mixed Resolved default. */
    public function def(string $key, mixed $default): mixed
    {
        return $this->values[$key] ??= $default;
    }
}

final class Input extends Registry
{
    /** @param string $key Input. @param int $default Fallback. @return int Filtered integer. */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /** @param string $key Input. @param string $default Fallback. @return string Command. */
    public function getCmd(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    /** @param string $key Input. @param bool $default Fallback. @return bool Flag. */
    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }
}

final class Application
{
    public Input $input;
    public Registry $params;

    /** @param array<string, mixed> $input Request. @param int $month First month. */
    public function __construct(array $input, int $month)
    {
        $this->input = new Input($input);
        $this->params = new Registry(['annual_start_month' => $month]);
    }

    /** @return Registry Menu parameters. */
    public function getParams(): Registry
    {
        return $this->params;
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

final class Database
{
    /** @param string $value Date. @return string Quoted date; never executes SQL. */
    public function quote(string $value): string
    {
        return "'" . $value . "'";
    }
}

/** Only unrelated menu/publication/query boundaries are replaced. */
class ModelBoundary
{
    public bool $show_archived_events = false;
    public int $menuFilterCalls = 0;
    protected array $state = [];
    protected Database $_db;
    private bool $populated = false;

    /** @return void Create quoting boundary. */
    public function __construct()
    {
        $this->_db = new Database();
    }

    /** @param string|null $key State key. @return mixed Initialized state. */
    public function getState(?string $key = null): mixed
    {
        if (!$this->populated) {
            $this->populateState();
            $this->populated = true;
        }
        return $key === null ? $this->state : ($this->state[$key] ?? null);
    }

    /** @param string $key State key. @param mixed $value State value. @return void */
    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /** @param Registry $params Menu settings. @return void */
    protected function applyMenuEventFilters(Registry $params): void
    {
        ++$this->menuFilterCalls;
    }

    /** @param string $task Publication context. @return void */
    protected function _populatePublishState(string $task): void
    {
        $this->setState('filter.published', $task === 'archive' ? 2 : 1);
    }

    /** @return array Empty rows after actual model state initialization. */
    public function getItems(): array
    {
        $this->getState();
        return [];
    }
}

/** Extra helper services needed by the actual raw view display method. */
class HelperBoundary
{
    public static ?string $filename = null;

    /** @return object Export limit. */
    public static function config(): object
    {
        return (object) ['ical_max_items' => 100];
    }

    /** @return Registry Enabled iCalendar. */
    public static function globalattribs(): Registry
    {
        return new Registry(['global_show_ical_icon' => 1]);
    }

    /** @return object Empty export collector. */
    public static function getCalendarTool(): object
    {
        return new \stdClass();
    }

    /** @param object $calendar Collector. @param string $filename Output name. @return void */
    public static function sendCalendar(object $calendar, string $filename): void
    {
        self::$filename = $filename;
    }
}

#[\AllowDynamicProperties]
class ViewBoundary
{
    public ?array $pdfPeriod = null;

    /** @param ModelBoundary $model Annual model. */
    public function __construct(protected ModelBoundary $model)
    {
    }

    /** @return ModelBoundary Shared model. */
    public function getModel(): ModelBoundary
    {
        return $this->model;
    }

    /** @param DateTimeImmutable $start Start. @param DateTimeImmutable $end End. @param Registry $params Menu. @return void */
    protected function renderPdf(DateTimeImmutable $start, DateTimeImmutable $end, Registry $params): void
    {
        $this->pdfPeriod = [$start, $end];
        $this->model->getItems();
    }

    /** @param string $value Text. @return string Escaped HTML. */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

final class Route
{
    /** @param string $url Link. @return string Unchanged internal link. */
    public static function _(string $url): string
    {
        return $url;
    }
}

final class Text
{
    /** @param string $key Language key. @return string Stable test label. */
    public static function _(string $key): string
    {
        return $key;
    }
}

final class HTMLHelper
{
    /** @param string $name Helper. @param string $date Date. @param string $format Format. @return string Label. */
    public static function _(string $name, string $date, string $format): string
    {
        return (new DateTimeImmutable($date))->format($format);
    }
}
