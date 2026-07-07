<?php
/**
 * @package    JEM
 * @subpackage JEM Types Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Version;

/**
 * Script file for the JEM Types module.
 */
class mod_jem_typesInstallerScript
{
    /**
     * Module name (extension element).
     */
    private string $name = 'mod_jem_types';

    private string $oldRelease = '';
    private string $newRelease = '';

    /**
     * Run before install/update/uninstall.
     */
    public function preflight($type, $parent)
    {
        $type = strtolower($type);

        if (version_compare(JVERSION, '5.4.0', 'lt') || Version::MAJOR_VERSION > 6) {
            return false;
        }

        if ($type === 'update') {
            $this->oldRelease = (string) $this->getParam('version');
            $this->newRelease = (string) $parent->getManifest()->version;

            if (version_compare($this->newRelease, $this->oldRelease, 'lt')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Run after install/update/uninstall.
     */
    public function postflight($type, $parent)
    {
        return true;
    }

    /**
     * Get a parameter from the manifest cache.
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
