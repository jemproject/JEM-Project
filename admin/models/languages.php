<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;

require_once JPATH_ADMINISTRATOR . '/components/com_jem/helpers/languagecatalog.php';

/**
 * Model for the JEM language package catalog.
 */
class JemModelLanguages extends BaseDatabaseModel
{
    protected $items;
    protected $catalogStatus;
    protected $jemVersion;

    public function __construct($config = array(), $factory = null)
    {
        parent::__construct($config, $factory);

        if (method_exists($this, 'setDispatcher')) {
            $this->setDispatcher(Factory::getApplication()->getDispatcher());
        }
    }

    /**
     * Build the catalog, Joomla language and installed JEM package matrix.
     */
    public function getItems()
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $catalogLanguages = JemLanguageCatalogHelper::getLanguages();
        $joomlaLanguages = $this->getInstalledJoomlaLanguages();
        $jemLanguages = $this->getInstalledJemLanguages();
        $tags = array_unique(array_merge(array('en-GB'), array_keys($catalogLanguages), array_keys($joomlaLanguages), array_keys($jemLanguages)));
        $jemVersion = $this->getJemVersion();
        $items = array();

        foreach ($tags as $tag) {
            $catalogLanguage = $catalogLanguages[$tag] ?? null;
            $joomlaLanguage = $joomlaLanguages[$tag] ?? array(
                'name' => $tag,
                'site' => false,
                'administrator' => false,
            );
            $installedVersion = (string) ($jemLanguages[$tag] ?? '');
            $packages = $catalogLanguage['packages'] ?? array();
            $package = JemLanguageCatalogHelper::selectCompatiblePackage($packages, $jemVersion);
            $joomlaInstalled = !empty($joomlaLanguage['site']) || !empty($joomlaLanguage['administrator']);
            $state = JemLanguageCatalogHelper::getPackageState(
                $tag,
                $joomlaInstalled,
                $installedVersion,
                $package
            );

            if ($catalogLanguage === null && $tag !== 'en-GB') {
                $state = $installedVersion !== '' ? 'installed' : 'not_available';
            }

            $items[] = array(
                'tag' => $tag,
                'name' => (string) ($catalogLanguage['name'] ?? $joomlaLanguage['name'] ?? $tag),
                'joomla_site' => (bool) $joomlaLanguage['site'],
                'joomla_administrator' => (bool) $joomlaLanguage['administrator'],
                'joomla_installed' => $joomlaInstalled,
                'installed_version' => $installedVersion,
                'package' => $package,
                'packages' => $packages,
                'state' => $state,
            );
        }

        $defaultLanguage = trim((string) ComponentHelper::getParams('com_languages')->get('site', 'en-GB'));
        $this->items = JemLanguageCatalogHelper::sortLanguageItems($items, $defaultLanguage);

        return $this->items;
    }

    public function getCatalogStatus()
    {
        if ($this->catalogStatus === null) {
            $this->catalogStatus = JemLanguageCatalogHelper::getStatus();
        }

        return $this->catalogStatus;
    }

    public function getJemVersion()
    {
        if ($this->jemVersion === null) {
            $this->jemVersion = (string) JemHelper::getParam(1, 'version', 1, 'com_jem');
        }

        return $this->jemVersion;
    }

    public function getJemMajor()
    {
        return JemLanguageCatalogHelper::getVersionMajor($this->getJemVersion());
    }

    /**
     * Return enabled Joomla language extensions by language tag and client.
     */
    protected function getInstalledJoomlaLanguages()
    {
        $installed = LanguageHelper::getInstalledLanguages(null, false, false, 'element');
        $languages = array();

        foreach (array(0 => 'site', 1 => 'administrator') as $clientId => $clientName) {
            foreach ((array) ($installed[$clientId] ?? array()) as $extension) {
                $tag = (string) $extension->element;

                if (preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $tag) !== 1) {
                    continue;
                }

                if (!isset($languages[$tag])) {
                    $languages[$tag] = array(
                        'name' => (string) ($extension->name ?: $tag),
                        'site' => false,
                        'administrator' => false,
                    );
                }

                $languages[$tag][$clientName] = true;
            }
        }

        return $languages;
    }

    /**
     * Return installed JEM file extension versions indexed by language tag.
     */
    protected function getInstalledJemLanguages()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('element', 'manifest_cache')))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('file'))
            ->where($db->quoteName('element') . ' LIKE ' . $db->quote('com_jem_%'));
        $db->setQuery($query);

        $languages = array();

        foreach ((array) $db->loadObjectList() as $extension) {
            if (preg_match('/^com_jem_([a-z]{2,3}-[A-Z]{2})$/', (string) $extension->element, $matches) !== 1) {
                continue;
            }

            $manifest = json_decode((string) $extension->manifest_cache, true);
            $version = is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';
            $languages[$matches[1]] = $version;
        }

        return $languages;
    }
}
