<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Validation policy for editable JEM stylesheet filenames.
 */
final class JemCssFilePolicy
{
    private const EXECUTABLE_SEGMENTS = array(
        'asp', 'aspx', 'cgi', 'jsp', 'jspx', 'pht', 'phtml', 'phar', 'php', 'php3', 'php4',
        'php5', 'php7', 'php8', 'phps', 'pl', 'py', 'rb', 'sh', 'shtm', 'shtml',
    );

    public static function isValidFileName($fileName): bool
    {
        if (!is_string($fileName) || $fileName === '') {
            return false;
        }

        if (!preg_match('/^(?!.*\.\.)[\pL\pN_-][\pL\pN._-]*\.css$/iuD', $fileName)) {
            return false;
        }

        $segments = explode('.', strtolower($fileName));
        array_pop($segments);

        return !array_intersect($segments, self::EXECUTABLE_SEGMENTS);
    }
}
