<?php

declare(strict_types=1);

/** Unrelated flag configuration for the actual Basic module template. */
final class JemHelper
{
    /** @return object Flag path used by the template setup. */
    public static function config(): object
    {
        return (object) ['flagicons_path' => 'media/flags-png'];
    }
}

/** Excludes status-badge formatting from the tooltip and navigation regression. */
final class JemOutput
{
    /** @param object $item Event. @return string No extra decoration. */
    public static function moduleEventStatusBadge(object $item): string
    {
        return '';
    }
}
