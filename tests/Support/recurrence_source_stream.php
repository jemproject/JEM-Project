<?php

declare(strict_types=1);

/** Read-only installation-shaped view of the checkout; no local Joomla files are changed. */
final class RecurrenceSourceStream
{
    public $context;
    private $handle;

    private static function resolve(string $path): string
    {
        $prefix = 'jemrecurrence://site/components/com_jem/';
        if (!str_starts_with($path, $prefix)) {
            throw new RuntimeException('Unexpected recurrence fixture path: ' . $path);
        }

        return JEM_TEST_ROOT . '/site/' . substr($path, strlen($prefix));
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if (!in_array($mode, ['r', 'rb'], true)) {
            return false;
        }
        $openedPath = realpath(self::resolve($path)) ?: self::resolve($path);
        $this->handle = fopen($openedPath, 'rb');

        return $this->handle !== false;
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->handle, $count);
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    public function stream_stat(): array|false
    {
        return fstat($this->handle);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    public function url_stat(string $path, int $flags): array|false
    {
        return stat(self::resolve($path));
    }

    public function stream_close(): void
    {
        fclose($this->handle);
    }
}
