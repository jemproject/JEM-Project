<?php

declare(strict_types=1);

/** Test only: the production helper validates both POST and Joomla's token. */
final class JemHelper
{
    public static bool $token = false;

    public static function requirePostToken(): void
    {
        if (!self::$token) {
            throw new RuntimeException('Invalid token', 403);
        }
    }
}
