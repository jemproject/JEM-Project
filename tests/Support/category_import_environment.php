<?php

declare(strict_types=1);

/** Only application/user/config services are doubles; tables, imports and SQL are real. */
final class CategoryImportApplication
{
    public function getDispatcher(): Joomla\Event\Dispatcher
    {
        static $dispatcher;
        return $dispatcher ??= new Joomla\Event\Dispatcher();
    }

    public function getIdentity(): object
    {
        return (object) ['id' => 99];
    }

    public function get($key, $default = null)
    {
        return $key === 'access' ? 1 : $default;
    }

    public function getLanguage(): object
    {
        return Joomla\CMS\Factory::$language;
    }
}

final class JemHelper
{
    public static function config(): object
    {
        return (object) [];
    }

    public static function globalattribs(): Joomla\Registry\Registry
    {
        return new Joomla\Registry\Registry();
    }
}

