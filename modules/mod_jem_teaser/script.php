<?php
/**
 * @package    JEM
 * @subpackage JEM Teaser Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

/**
 * Script file of JEM component
*/
class mod_jem_teaserInstallerScript
{
    /**
     * Module name (extension element)
     */
    private string $name = 'mod_jem_teaser';

    private string $oldRelease = '';
    private string $newRelease = '';

    /**
     * Run before install/update/uninstall
     */
    public function preflight($type, $parent)
    {
        $type = strtolower($type);

        if ($type === 'update') {

            // Installed module version (from manifest cache)
            $this->oldRelease = (string) $this->getParam('version');

            // Version being installed (manifest)
            $this->newRelease = (string) $parent->getManifest()->version;

            // Abort if new version is older
            if (version_compare($this->newRelease, $this->oldRelease, 'lt')) {
                return false;
            }
        }
        return true;
    }

    /**
     * Run after install/update/uninstall
     */
    public function postflight($type, $parent)
    {
        $type = strtolower($type);

        if ($type === 'install') {
            return true;
        }
        if ($type === 'update') {            
            return true;
        }
        if ($type === 'uninstall') {
            return true;
        }
    }

    /**
     * Get a parameter from the manifest cache
     */
    private function getParam(string $name)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('manifest_cache')
            ->from('#__extensions')
            ->where([
                "type = 'module'",
                "element = " . $db->quote($this->name)
            ]);

        $db->setQuery($query);
        $manifest = json_decode($db->loadResult(), true);

        return $manifest[$name] ?? null;
    }
}
