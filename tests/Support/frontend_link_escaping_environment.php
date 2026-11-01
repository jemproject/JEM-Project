<?php
declare(strict_types=1);

/** Synthetic internal routes; native Joomla and JEM routers perform the actual routing. */
final class JemHelperRoute
{
    public static string $extra = '';

    /** @param string $slug Event slug. @return string Internal event route. */
    public static function getEventRoute($slug): string
    {
        return 'index.php?option=com_jem&view=event&id=' . $slug . self::$extra;
    }

    /** @param string $slug Venue slug. @return string Internal venue route. */
    public static function getVenueRoute($slug): string
    {
        return 'index.php?option=com_jem&view=venue&id=' . $slug . self::$extra;
    }

    /** @param int $id Type ID. @return string Internal venue-type route. */
    public static function getTypevenuesRoute($id): string
    {
        return 'index.php?option=com_jem&view=typevenues&id=' . $id . self::$extra;
    }

    /** @param int $id Type ID. @return string Internal event-type route. */
    public static function getTypeeventsRoute($id): string
    {
        return 'index.php?option=com_jem&view=typeevents&id=' . $id . self::$extra;
    }
}

/** Formatting is outside this regression; actual templates still escape all tested fields. */
final class JemOutput
{
    /** @param string $name Formatting helper. @param array $arguments Arguments. @return string Empty decoration. */
    public static function __callStatic($name, $arguments): string
    {
        return '';
    }
}

/** Isolate unrelated module policy while retaining an extra layout query parameter. */
final class JemHelper
{
    /** @param string $route Internal route. @param object $params Parameters. @return string Route with layout. */
    public static function applyEventRouteLayout($route, $params): string
    {
        return $route . '&layout=default';
    }

    /** @param mixed $value Setting. @return string Disable unrelated article links. */
    public static function getMoreInformationDisplay($value): string
    {
        return '';
    }
}

/** Context for evaluating the precise URL expressions from larger templates. */
final class LinkEscapingView
{
    public object $item;

    /** @param string $value Text/attribute. @return string Same escaping as Joomla HtmlView. */
    public function escape($value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
