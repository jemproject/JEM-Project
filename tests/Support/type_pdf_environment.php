<?php

declare(strict_types=1);

namespace JemProject\Tests\TypePdf;

/** Isolated view dependencies; no installed site, database, or PDF files are touched. */
final class Input
{
    /** @param string $layout Requested output layout. */
    public function __construct(public string $layout)
    {
    }

    /** @param string $key Input name. @param string $default Fallback. @return string Requested layout. */
    public function getCmd(string $key, string $default): string
    {
        return $key === 'layout' ? $this->layout : $default;
    }
}

final class Application
{
    public bool $closed = false;
    public Input $input;

    /** @param string $layout Requested output layout. */
    public function __construct(string $layout)
    {
        $this->input = new Input($layout);
    }

    /** @return void Record the non-PDF early exit. */
    public function close(): void
    {
        $this->closed = true;
    }
}

final class Factory
{
    public static Application $app;

    /** @return Application Current isolated request. */
    public static function getApplication(): Application
    {
        return self::$app;
    }
}

final class Text
{
    /** @param string $key Language key. @return string Stable untranslated label. */
    public static function _(string $key): string
    {
        return $key;
    }

    /** @param string $key Language key. @param string $value Type name. @return string Stable title. */
    public static function sprintf(string $key, string $value): string
    {
        return $key . ':' . $value;
    }
}

final class JemFrontendAccess
{
    public static array $calls = [];
    public static bool $throwOnDenial = false;

    /** @param bool $allowed Access decision. @param Application $app Request. @return bool Access result. */
    public static function enforceViewAccess(bool $allowed, Application $app): bool
    {
        self::$calls[] = $allowed;
        if (!$allowed && self::$throwOnDenial) {
            throw new \RuntimeException('Access denied', 403);
        }

        return $allowed;
    }
}

final class JemPdfView
{
    public static ?array $output = null;

    /** @param string $title Title. @param mixed $type Resolved type. @param array $rows Rows. @param string $file Filename. @return void */
    public static function renderTypeVenueList(string $title, $type, array $rows, string $file): void
    {
        self::$output = ['kind' => 'venue', 'title' => $title, 'type' => $type, 'rows' => $rows, 'file' => $file];
    }

    /** @param string $title Title. @param mixed $type Resolved type. @param array $rows Rows. @param string $file Filename. @return void */
    public static function renderTypeEventList(string $title, $type, array $rows, string $file): void
    {
        self::$output = ['kind' => 'event', 'title' => $title, 'type' => $type, 'rows' => $rows, 'file' => $file];
    }
}

final class Model
{
    public int $typeCalls = 0;
    public int $itemCalls = 0;
    public array $state = [];

    /** @param mixed $type Model-resolved type. @param array $items Synthetic list rows. */
    public function __construct(public mixed $type, public array $items)
    {
    }

    /** @param string $key State key. @param mixed $value Value. @return void */
    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /** @return mixed Model result, independent of raw URL input. */
    public function getType(): mixed
    {
        $this->typeCalls++;

        return $this->type;
    }

    /** @return array Synthetic rows, intentionally available even without a type to expose the regression. */
    public function getItems(): array
    {
        $this->itemCalls++;

        return $this->items;
    }
}

class ViewDependencies
{
    /** @param Model $model Model used by the extracted production display method. */
    public function __construct(public Model $model)
    {
    }

    /** @return Model Assigned model. */
    public function getModel(): Model
    {
        return $this->model;
    }
}
