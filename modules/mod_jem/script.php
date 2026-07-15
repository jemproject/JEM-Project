<?php
/**
 * @package    JEM
 * @subpackage JEM Basic Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Version;

/**
 * Script file of JEM component
 */
class mod_jemInstallerScript
{
    /**
     * Module name (extension element)
     */
    private string $name = 'mod_jem';

    private string $oldRelease = '';
    private string $newRelease = '';

    /**
     * Run before install/update/uninstall
     */
    public function preflight($type, $parent)
    {
        $type = strtolower($type);

        if (version_compare(JVERSION, '5.4.0', 'lt') || Version::MAJOR_VERSION > 6) {
            return false;
        }

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
            return $this->migrateLegacyModuleParams();
        }
        if ($type === 'uninstall') {
            return true;
        }
    }

    /**
     * Migrates parameters stored by JEM 4.4.
     */
    private function migrateLegacyModuleParams(): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('params'),
            ])
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('module') . ' = ' . $db->quote($this->name));

        $modules = $db->setQuery($query)->loadObjectList();

        foreach ($modules as $module) {
            $currentParams = json_decode((string) $module->params, true);

            if (!is_array($currentParams)) {
                continue;
            }

            $migratedParams = self::migrateLegacyParams($currentParams);

            if ($migratedParams === $currentParams) {
                continue;
            }

            $encodedParams = json_encode($migratedParams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($encodedParams === false) {
                return false;
            }

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__modules'))
                ->set($db->quoteName('params') . ' = ' . $db->quote($encodedParams))
                ->where($db->quoteName('id') . ' = ' . (int) $module->id);

            $db->setQuery($update)->execute();
        }

        return true;
    }

    /**
     * Converts the former exclusive title/venue selector to the independent JEM 5 options.
     *
     * @param array<string, mixed> $params Stored module parameters.
     *
     * @return array<string, mixed>
     */
    private static function migrateLegacyParams(array $params): array
    {
        if (!array_key_exists('showtitloc', $params)) {
            return $params;
        }

        $legacyShowsTitle = (int) $params['showtitloc'] === 1;

        if (!array_key_exists('showtitle', $params)) {
            $params['showtitle'] = $legacyShowsTitle ? '1' : '0';
        }

        if (!array_key_exists('showvenue', $params)) {
            $params['showvenue'] = $legacyShowsTitle ? '0' : '1';
        }

        unset($params['showtitloc']);

        return $params;
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
