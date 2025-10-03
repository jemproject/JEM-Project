<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Script file of JEM Jubilee module
 */
class mod_jem_jubileeInstallerScript
{
    /**
     * Module element name
     */
    private $name = 'mod_jem_jubilee';

    /**
     * Preflight method
     * Called before install/update/uninstall
     * Not required for installations and updates >= 2.3.6
     *
     * @param string $type   The type of action (install, update, discover_install)
     * @param object $parent The class calling this method
     */
    function preflight($type, $parent)
    {
        // No preflight checks required
    }

    /**
     * Postflight method
     * Called after install/update/uninstall
     * Currently no post-install actions required
     *
     * @param string $type   The type of action (install, update, discover_install)
     * @param object $parent The class calling this method
     */
    function postflight($type, $parent)
    {
        // No postflight actions required
    }
}
