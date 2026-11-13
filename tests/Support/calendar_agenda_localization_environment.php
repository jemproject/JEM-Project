<?php

declare(strict_types=1);

/** Isolates unrelated configuration and special-day services from calendar rendering. */
final class JemHelper
{
    /** @param mixed $date Civil date. @return bool Valid date. */
    public static function isValidDate($date): bool
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
    }

    /** @return object Minimal PDF configuration, without active-menu text. */
    public static function config(): object
    {
        return (object) ['pdf_include_view_text' => 0];
    }

    /** @param string $start First day. @param string $end Last day. @return array No special days. */
    public static function calendarSpecialDays(string $start, string $end): array
    {
        return [];
    }
}

/** Keeps time strings unchanged; time formatting is outside the localization regression. */
final class JemOutput
{
    /** @param object $event Row. @return string No indicator in the localization fixture. */
    public static function eventStatusLabel($event): string { return ''; }

    /** @param object $event Row. @return string Neutral indicator in this unrelated rendering fixture. */
    public static function moduleEventStatusBadge($event): string { return ''; }

    /** @param mixed $time Time. @param mixed ...$options Formatting options. @return string Time. */
    public static function formattime($time, ...$options): string
    {
        return (string) $time;
    }
}

/** PDF engine scaling boundary; the actual PDF HTML builders remain under test. */
final class JemPdf
{
    /** @param string $paper Paper size. @return float Unscaled fixture. */
    public static function getPosterScale(string $paper): float
    {
        return 1.0;
    }
}

/** Isolates interactive filter attributes from the template's Agenda rendering call. */
final class JemCalendarFilterHelper
{
    /** @param object $event Event. @param string $class CSS classes. @return string Attributes. */
    public static function attributes(object $event, string $class): string
    {
        return ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"';
    }
}
