<?php

declare(strict_types=1);

namespace JemProject\Tests\DayPrint;

use DateTimeImmutable;

/** Filtered request boundary; never starts a Joomla session or touches an installed site. */
final class Input
{
    /** @param array $values Request values. */
    public function __construct(public array $values = [])
    {
    }

    /** @param string $key Key. @param mixed $default Default. @param string $type Filter. @return mixed Value. */
    public function get(string $key, mixed $default = null, string $type = 'cmd'): mixed
    {
        $value = $this->values[$key] ?? $default;
        return match ($type) {
            'int' => $value === null ? null : (int) $value,
            default => $value,
        };
    }

    /** @param string $key Key. @param int|null $default Default. @return int|null Value. */
    public function getInt(string $key, ?int $default = 0): ?int
    {
        return $this->get($key, $default, 'int');
    }

    /** @param string $key Key. @param string|null $default Default. @return string|null Value. */
    public function getString(string $key, ?string $default = ''): ?string
    {
        return isset($this->values[$key]) ? (string) $this->values[$key] : $default;
    }

    /** @param string $key Key. @param string $default Default. @return string Value. */
    public function getCmd(string $key, string $default = ''): string
    {
        return $this->getString($key, $default);
    }
}

final class Params
{
    /** @param array $values Menu settings. */
    public function __construct(public array $values = [])
    {
    }

    /** @param string $key Key. @param mixed $default Default. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $key Key. @param mixed $value Value. @return void */
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /** @return array Menu values. */
    public function toArray(): array
    {
        return $this->values;
    }
}

final class Application
{
    public Input $input;
    public Params $params;
    public array $session = [];
    public array $messages = [];
    public ?object $menu = null;

    /** @param array $request Filtered input. @param array $settings Menu values. @param bool $menu Active menu. */
    public function __construct(array $request, array $settings, bool $menu = true)
    {
        $this->input = new Input($request);
        $this->params = new Params($settings);
        if ($menu) {
            $this->menu = new class ($this->params) {
                public int $id = 101;
                public array $query = ['option' => 'com_jem', 'view' => 'day'];
                /** @param Params $params Menu settings. */
                public function __construct(private Params $params)
                {
                }
                /** @return Params Menu settings. */
                public function getParams(): Params
                {
                    return $this->params;
                }
            };
        }
    }

    /** @param string $option Component. @return Params Settings. */
    public function getParams(string $option = ''): Params
    {
        return $this->params;
    }

    /** @return object Active-menu service. */
    public function getMenu(): object
    {
        return new class ($this->menu) {
            /** @param object|null $menu Current menu. */
            public function __construct(private ?object $menu)
            {
            }
            /** @return object|null Active menu. */
            public function getActive(): ?object
            {
                return $this->menu;
            }
        };
    }

    /** @param string $key Session key. @return mixed State. */
    public function getUserState(string $key): mixed
    {
        return $this->session[$key] ?? null;
    }

    /** @param string $key Session key. @param mixed $value Value. @return void */
    public function setUserState(string $key, mixed $value): void
    {
        $this->session[$key] = $value;
    }

    /** @param string $key Session key. @param string $request Input. @param mixed $default Default. @param string $type Type. @return mixed Value. */
    public function getUserStateFromRequest(string $key, string $request, mixed $default, string $type): mixed
    {
        return $this->session[$key] = $this->input->get($request, $this->session[$key] ?? $default, $type);
    }

    /** @param string $message Text. @param string $type Severity. @return void */
    public function enqueueMessage(string $message, string $type): void
    {
        $this->messages[] = [$message, $type];
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
}

final class JemHelper
{
    public static string $today = '2026-10-30';

    /** @return object Default list limit. */
    public static function config(): object
    {
        return (object) ['display_num' => 10];
    }

    /** @param int $offset Days. @return string Frozen Joomla-local date. */
    public static function getJoomlaDate(int $offset = 0): string
    {
        return (new DateTimeImmutable(self::$today))->modify("$offset days")->format('Y-m-d');
    }
}

final class Text
{
    /** @param string $key Key. @return string Stable message. */
    public static function _(string $key): string
    {
        return $key;
    }
}

final class JemCategories
{
    /** @param int $id Category. @return array Fixture subtree without querying Joomla. */
    public static function getChilds(int $id): array
    {
        return [$id, $id + 1];
    }
}

/** Replace only base model services; date/state preparation is production code. */
class ModelBoundary
{
    protected ?string $_date = null;
    protected ?string $_dateEnd = null;
    public bool $show_archived_events = false;
    protected array $state = [];
    private bool $populated = false;

    /** @param string|null $key State key. @param mixed $default Default. @return mixed State. */
    public function getState(?string $key = null, mixed $default = null): mixed
    {
        if (!$this->populated) {
            $this->populated = true;
            $this->populateState();
        }
        return $key === null ? $this->state : ($this->state[$key] ?? $default);
    }

    /** @param string $key Key. @param mixed $value Value. @return void */
    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /** @param int $limit Page size. @return void */
    public function setLimit(int $limit): void
    {
        $this->setState('list.limit', $limit);
    }

    /** @param int $start Offset. @return void */
    public function setLimitStart(int $start): void
    {
        $this->setState('list.start', $start);
    }

    /** @param Params $params Settings. @return void No contact data in these fixtures. */
    protected function applyMenuContactFilters(Params $params): void
    {
    }

    /** @param string $task Context. @return void Fixed guest publication boundary; ACL is not emulated. */
    protected function _populatePublishState(string $task): void
    {
        $this->setState('filter.published', $task === 'archive' ? 2 : 1);
    }
}

class ViewBoundary
{
    /** @param string $layout Effective layout, which need not appear in the request. */
    public function __construct(private string $layout)
    {
    }

    /** @return string Effective layout. */
    public function getLayout(): string
    {
        return $this->layout;
    }
}

final class Route
{
    public static array $calls = [];

    /** @param string $url Internal link. @return string Boundary route with HTML separators. */
    public static function _(string $url): string
    {
        self::$calls[] = $url;
        return '/joomla/' . str_replace('&', '&amp;', $url);
    }
}

