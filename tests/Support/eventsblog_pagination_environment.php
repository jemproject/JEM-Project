<?php

declare(strict_types=1);

namespace JemProject\Tests\EventsBlogPagination;

/** Minimal menu/request values for the production Blog state method. */
final class Values
{
    /** @param array<string, mixed> $values Initial menu or request values. */
    public function __construct(private array $values = [])
    {
    }

    /** @param string $key Key. @param mixed $default Fallback. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @param string $key Input key. @param int $default Fallback. @return int Integer input. */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /** @param string $key Input key. @param string $default Fallback. @return string Command input. */
    public function getCmd(string $key, string $default = ''): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $this->get($key, $default));
    }
}

final class Application
{
    /** @param Values $input Request. @param Values $params Menu configuration. */
    public function __construct(public Values $input, public Values $params)
    {
    }

    /** @return Values Effective menu configuration. */
    public function getParams(): Values
    {
        return $this->params;
    }

    /** @param string $key Setting. @param mixed $default Fallback. @return mixed Default setting. */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === 'DatabaseDriver') {
            // The all-period fixture must not issue SQL or need a database.
            return new \stdClass();
        }

        return $default;
    }
}

final class Factory
{
    public static Application $application;

    /** @return Application Isolated request/application. */
    public static function getApplication(): Application
    {
        return self::$application;
    }

    /** @return Application Minimal service container, without database access. */
    public static function getContainer(): Application
    {
        return self::$application;
    }
}

class ModelDependencies
{
    public array $state = [];

    /** @return void Execute the production Blog populateState method. */
    public function __construct()
    {
        $this->populateState();
    }

    /** @param string $key State key. @param mixed $value Value. @return void */
    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /**
     * Seed generic parent pagination that the Blog must replace with its own policy.
     *
     * @param mixed $ordering Parent ordering.
     * @param mixed $direction Parent direction.
     * @return void
     */
    protected function populateState($ordering = null, $direction = null)
    {
        $this->state['list.limit'] = Factory::$application->input->getInt('limit', 99);
        $this->state['list.start'] = 999;
    }
}
