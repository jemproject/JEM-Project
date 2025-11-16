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

            // Migration 2.1.5 -> 2.1.6
            if (
                version_compare($this->oldRelease, '2.1.6', 'le') &&
                version_compare($this->newRelease, '2.1.6', 'ge')
            ) {
                $this->updateParams216();
            }
        }
        if ($type == 'uninstall') {
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

    /**
     * Migration: convert catid/venid strings to arrays
     *
     * Required for updates <= 2.1.6
     */
    private function updateParams216(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('id, params')
            ->from('#__modules')
            ->where('module = ' . $db->quote($this->name));

        $db->setQuery($query);
        $items = $db->loadObjectList();

        foreach ($items as $item) {

            $reg = new Registry;
            $reg->loadString($item->params);

            $modified = false;

            // catid (string → array)
            $ids = $reg->get('catid');
            if (!empty($ids) && is_string($ids)) {
                $reg->set('catid', explode(',', $ids));
                $modified = true;
            }

            // venid (string → array)
            $ids = $reg->get('venid');
            if (!empty($ids) && is_string($ids)) {
                $reg->set('venid', explode(',', $ids));
                $modified = true;
            }

            // Save back
            if ($modified) {
                $query = $db->getQuery(true)
                    ->update('#__modules')
                    ->set('params = ' . $db->quote((string) $reg))
                    ->where('id = ' . (int) $item->id);

                $db->setQuery($query);
                $db->execute();
            }
        }
    }
}
