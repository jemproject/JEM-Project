<?php

declare(strict_types=1);

/** Only menu lookup is stubbed; helper, timezone resolution and iCalcreator are real. */
final class JemHelperRoute
{
    public static function getEventRoute($slug): string
    {
        return 'index.php?option=com_jem&view=event&id=' . rawurlencode((string) $slug);
    }
}

