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
 * Script file of JEM Calendar module
 */
class mod_jem_calInstallerScript
{
    /**
     * Module element name
     */
    private $name = 'mod_jem_cal';

    private $oldRelease = "";
    private $newRelease = "";

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
        // abort if the release being installed is not newer than the currently installed version
        if (strtolower($type) == 'update') {
            // Installed component version
            $this->oldRelease = $this->getParam('version');

            // Installing component version as per Manifest file
            $this->newRelease = (string) $parent->getManifest()->version;

            if (version_compare($this->newRelease, $this->oldRelease, 'lt')) {
                return false;
            }
        }
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
