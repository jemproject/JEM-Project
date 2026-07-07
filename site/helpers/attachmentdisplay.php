<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Small pure helper for attachment display options.
 */
class JemAttachmentDisplayHelper
{
    /**
     * @var string[]
     */
    public const LAYOUTS = array('row', 'row_full', 'row_uniform', 'column', 'column_full', 'column_uniform');

    /**
     * @var string[]
     */
    public const ICON_SIZES = array('none', 'normal', 'medium', 'large');

    /**
     * Resolve the attachment layout using item override, global setting, and default.
     */
    public static function resolveLayout($override, $global, string $default = 'column'): string
    {
        $override = (string) $override;
        if (in_array($override, self::LAYOUTS, true)) {
            return $override;
        }

        $global = (string) $global;
        if (in_array($global, self::LAYOUTS, true)) {
            return $global;
        }

        return in_array($default, self::LAYOUTS, true) ? $default : 'column';
    }

    /**
     * Resolve the attachment file icon size using item override, global setting, legacy flag, and default.
     */
    public static function resolveIconSize($override, $global, $legacyShowIcon = null, string $default = 'normal'): string
    {
        $override = (string) $override;
        if (in_array($override, self::ICON_SIZES, true)) {
            return $override;
        }

        if ($global !== null) {
            $global = (string) $global;
            if (in_array($global, self::ICON_SIZES, true)) {
                return $global;
            }
        }

        if ($legacyShowIcon !== null) {
            return (int) $legacyShowIcon === 1 ? 'normal' : 'none';
        }

        return in_array($default, self::ICON_SIZES, true) ? $default : 'normal';
    }

    /**
     * Return the extra CSS class for framed attachment output.
     */
    public static function frameClass($frame): string
    {
        return (int) $frame === 1 ? ' jem-attachments-frame' : '';
    }
}
