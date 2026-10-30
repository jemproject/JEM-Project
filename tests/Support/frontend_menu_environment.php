<?php

declare(strict_types=1);

namespace JemProject\Tests\MenuFallback;

/** Minimal parameter boundary matching Registry::get/set/def semantics. */
final class Registry
{
    /** @param array<string, mixed> $values Initial parameters. */
    public function __construct(private array $values = [])
    {
    }

    /** @param string $key Parameter. @param mixed $default Fallback. @return mixed Effective value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $key Parameter. @param mixed $value New value. @return mixed Stored value. */
    public function set(string $key, mixed $value): mixed
    {
        return $this->values[$key] = $value;
    }

    /** @param string $key Parameter. @param mixed $default Fallback. @return mixed Effective value. */
    public function def(string $key, mixed $default = ''): mixed
    {
        return $this->set($key, $this->get($key, $default));
    }
}

/** Translation boundary that keeps fallback keys distinguishable. */
final class Text
{
    /** @param string $key Language key. @return string Deterministic test text. */
    public static function _(string $key): string
    {
        return $key;
    }
}

/** URL boundary: routing is outside the null-menu regression. */
final class Route
{
    /** @param string $url Internal URL. @return string Unchanged URL for inspection. */
    public static function _(string $url): string
    {
        return $url;
    }
}

/** Pathway boundary mirroring Joomla's behavior for absent entries. */
final class Pathway
{
    public array $renamed = [];

    /** @param array<object> $items Current breadcrumbs. */
    public function __construct(private array $items = [])
    {
    }

    /** @return array<object> Current breadcrumb objects. */
    public function getPathway(): array
    {
        return $this->items;
    }

    /** @param int|false $id Entry index. @param string $name New label. @return bool Whether it existed. */
    public function setItemName(int|false $id, string $name): bool
    {
        $this->renamed[] = $id;
        if (!isset($this->items[$id])) {
            return false;
        }
        $this->items[$id]->name = $name;

        return true;
    }

    /** @param string $name Breadcrumb label. @param string $link Destination. @return void */
    public function addItem(string $name, string $link): void
    {
        $this->items[] = (object) ['name' => $name, 'link' => $link];
    }
}
