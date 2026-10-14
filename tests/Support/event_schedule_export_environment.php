<?php

declare(strict_types=1);

/** Menu lookup is outside the programme/ICS test; all dates and VEVENTs use production code. */
final class JemHelperRoute
{
    public static function getEventRoute($slug): string
    {
        return 'index.php?option=com_jem&view=event&id=' . rawurlencode((string) $slug);
    }
}
