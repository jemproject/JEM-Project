<?php
/**
 * @package    JEM
 * @subpackage JEM Calendar Module
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
class mod_jem_calInstallerScript
{
    /**
     * Module name (extension element)
     */
    private string $name = 'mod_jem_cal';

    private string $oldRelease = "";
    private string $newRelease = "";

    /**
     * Method to run before an install/update/uninstall method
     *
     * @return bool|void
     */
    function preflight($type, $parent)
    {
        if (strtolower($type) === 'update') {

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
     * Method to run after an install/update/uninstall method
     *
     * @return void
     */
    function postflight($type, $parent)
    {
        if (strtolower($type) == 'install' ) {
            return true;
        }
        if (strtolower($type) === 'update') {

            // Migration 2.1.5 -> 2.1.6
            if (
                version_compare($this->oldRelease, '2.1.6', 'le') &&
                version_compare($this->newRelease, '2.1.6', 'ge')
            ) {
                $this->updateParams216();
            }
        }
        if (strtolower($type) == 'uninstall') {
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

    /**
     * Add use_ajax,
     * take over instances from mod_jem_calajax which becomes obsolete
     *
     * @return void
     */
    private function updateParams223()
    {
        // get all "mod_jem..." entries
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('id, module, note, params');
        $query->from('#__modules');
        $query->where('module = "' . $this->name . '" OR module = "mod_jem_calajax"');
        $db->setQuery($query);
        $items = $db->loadObjectList();

        $use_ajax = version_compare(JVERSION, '3.2.7', 'ge') ? '1' : '0';
        $obs_params = array('UseJoomlaLanguage', 'locale_override');
        $fix_params = array('cal15q_tooltips_title', 'cal15q_tooltipspl_title');

        foreach ($items as $item) {
            // Decode the item params
            $reg = new Registry;
            $reg->loadString($item->params);

            $mod_params = false;
            $mod_mod = $item->module == 'mod_jem_calajax';

            // add use_ajax if missing
            if (!$reg->exists('use_ajax')) {
                $reg->set('use_ajax', $use_ajax);
                $mod_params = true;
            }

            // adapt text defines
            foreach ($fix_params as $f) {
                $str = $reg->get($f, '');
                $str2 = mb_ereg_replace('MOD_JEM_CALAJAX_', 'MOD_JEM_CAL_', $str);
                if ($str !== $str2) {
                    $reg->set($f, $str2);
                    $mod_params = true;
                }
            }

            // remove obsolete params
            foreach ($obs_params as $o) {
                if ($reg->exists($o)) {
                    $reg->set($o, null);
                    $mod_params = true;
                }
            }

            // write back
            if ($mod_params || $mod_mod) {
                // write changed data back into DB
                $query = $db->getQuery(true);
                $query->update('#__modules');
                if ($mod_params) {
                    $query->set('params = '.$db->quote((string)$reg));
                }
                if ($mod_mod) { // change module and append a note
                    $query->set('module = '.$db->quote($this->name));
                    $query->set('note = '.$db->quote(join(' -- ', array($item->note, 'PLEASE CHECK this transfered from obsolete mod_jem_calajax, then uninstall mod_jem_calajax!'))));
                }
                $query->where(array('id = '.$db->quote($item->id)));
                $db->setQuery($query);
                $db->execute();
            }
        }
    }
}
