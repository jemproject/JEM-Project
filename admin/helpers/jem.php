<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Load the existing site helper for Joomla's legacy component service.
 */
final class JemLegacyHelperLoader
{
    public static function load()
    {
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
    }
}

// Joomla looks for helpers/jem.php when com_jem is booted outside its normal
// entry point, for example while com_fields collects component contexts.
JemLegacyHelperLoader::load();
