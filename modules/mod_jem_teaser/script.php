<?php
/**
 * @package    JEM
 * @subpackage JEM Teaser Module
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Script file of JEM Teaser module
 */
class mod_jem_teaserInstallerScript
{
    /**
     * Module element name
     */
    private $name = 'mod_jem_teaser';

private $oldRelease = "";
    private $newRelease = "";

    /**
     * Preflight method
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
     *
     * @param string $type   The type of action (install, update, discover_install)
     * @param object $parent The class calling this method
     */
    function postflight($type, $parent)
    {
        if (strtolower($type) == 'uninstall') {
            return true;
        }
        if (strtolower($type) == 'update' ) {
            return true;
        }
        if (strtolower($type) == 'install' ) {
            return true;
        }
    }

    /**
     * Get a parameter from the manifest file (actually, from the manifest cache).
     *
     * @param $name  The name of the parameter
     *
     * @return The parameter
     */
    private function getParam($name)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('manifest_cache')->from('#__extensions')->where(array("type = 'module'", "element = '".$this->name."'"));
        $db->setQuery($query);
        $manifest = json_decode($db->loadResult(), true);
        return $manifest[$name];
    }

}
