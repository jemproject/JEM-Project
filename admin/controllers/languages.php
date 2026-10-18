<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

require_once __DIR__ . '/../helpers/languagecatalog.php';

/**
 * Controller for verified JEM language package installation.
 */
class JemControllerLanguages extends BaseController
{
    public function install()
    {
        JemHelper::requirePostToken();

        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.admin')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $packageId = $app->getInput()->getString('package_id');
        $package = JemLanguageCatalogHelper::getPackage($packageId);
        $redirect = 'index.php?option=com_jem&view=languages';

        if ($package === null) {
            $this->setRedirect($redirect, Text::_('COM_JEM_LANGUAGES_PACKAGE_INVALID'), 'error');
            return false;
        }

        $jemVersion = (string) JemHelper::getParam(1, 'version', 1, 'com_jem');

        if (JemLanguageCatalogHelper::getVersionMajor($package['jem'])
            !== JemLanguageCatalogHelper::getVersionMajor($jemVersion)) {
            $this->setRedirect($redirect, Text::_('COM_JEM_LANGUAGES_PACKAGE_INCOMPATIBLE'), 'error');
            return false;
        }

        if (!$this->isJoomlaLanguageInstalled($package['tag'])) {
            $this->setRedirect($redirect, Text::_('COM_JEM_LANGUAGES_JOOMLA_REQUIRED'), 'error');
            return false;
        }

        $temporaryPackage = '';
        $unpacked = null;

        try {
            // Read current installed metadata on every POST; never trust a UI action/version.
            $installedVersion = $this->getInstalledPackageVersion($package['element']);
            $state = JemLanguageCatalogHelper::getPackageState($package['tag'], true, $installedVersion, $package);

            if ($state === 'newer_installed') {
                $this->setRedirect($redirect, Text::_('COM_JEM_LANGUAGES_DOWNGRADE_BLOCKED'), 'warning');
                return false;
            }

            if (!in_array($state, ['available', 'update', 'reinstall'], true)) {
                $this->setRedirect($redirect, Text::_('COM_JEM_LANGUAGES_PACKAGE_INVALID'), 'error');
                return false;
            }

            $download = $this->downloadPackage($package);

            if (strlen($download['body']) !== $package['bytes']
                || !hash_equals($package['checksum'], hash('sha256', $download['body']))) {
                throw new RuntimeException('package_integrity');
            }

            $temporaryPackage = Path::clean(
                $app->get('tmp_path') . '/jem-language-' . bin2hex(random_bytes(12)) . '.zip'
            );

            if (!File::write($temporaryPackage, $download['body'])) {
                throw new RuntimeException('package_write');
            }

            $unpacked = InstallerHelper::unpack($temporaryPackage, true);

            if (!is_array($unpacked)
                || ($unpacked['type'] ?? '') !== 'file'
                || !$this->validatePackageManifest($unpacked['dir'] ?? '', $package)) {
                throw new RuntimeException('package_manifest');
            }

            $installer = Installer::getInstance();

            if ($installedVersion !== null) {
                // Use Joomla's update route without uninstalling existing translations.
                $installer->setUpgrade(true);
                $installer->setOverwrite(true);
            }

            if (!$installer->install($unpacked['dir'])) {
                throw new RuntimeException('package_install');
            }

            $this->setRedirect(
                $redirect,
                Text::sprintf(
                    $state === 'reinstall' ? 'COM_JEM_LANGUAGES_REINSTALL_SUCCESS' : 'COM_JEM_LANGUAGES_INSTALL_SUCCESS',
                    $package['tag'],
                    $package['version']
                )
            );

            return true;
        } catch (\Throwable $exception) {
            $this->setRedirect($redirect, Text::_('COM_JEM_LANGUAGES_INSTALL_FAILED'), 'error');

            return false;
        } finally {
            $this->cleanupPackage($temporaryPackage, $unpacked);
        }
    }

    protected function isJoomlaLanguageInstalled($tag)
    {
        $installed = LanguageHelper::getInstalledLanguages(null, false, false, 'element');

        return isset($installed[0][$tag]) || isset($installed[1][$tag]);
    }

    protected function downloadPackage(array $package): array
    {
        return JemRemoteSourceHelper::download(
            $package['url'],
            ['zip'],
            'zip',
            $package['bytes']
        );
    }

    protected function getInstalledPackageVersion(string $element): ?string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('file'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element));
        $cache = $db->setQuery($query)->loadResult();

        if ($cache === null) {
            return null;
        }

        $manifest = json_decode((string) $cache, true);
        $version = is_array($manifest) ? trim((string) ($manifest['version'] ?? '')) : '';

        if ($version === '') {
            throw new RuntimeException('installed_package_metadata');
        }

        return $version;
    }

    protected function validatePackageManifest($directory, array $package)
    {
        $directory = trim((string) $directory);

        if ($directory === '') {
            return false;
        }

        $directory = Path::clean($directory);

        if (!is_dir($directory)) {
            return false;
        }

        $manifestPath = $directory . '/' . $package['element'] . '.xml';

        if (!is_file($manifestPath)) {
            return false;
        }

        $source = @file_get_contents($manifestPath);

        return is_string($source)
            && JemLanguageCatalogHelper::validatePackageManifestXml($source, $package);
    }

    protected function cleanupPackage($temporaryPackage, $unpacked)
    {
        try {
            if (is_array($unpacked)) {
                $packageFile = (string) ($unpacked['packagefile'] ?? $temporaryPackage);

                if ($packageFile !== '' && !is_file($packageFile)) {
                    $packageFile = Factory::getApplication()->get('tmp_path') . '/' . $packageFile;
                }

                InstallerHelper::cleanupInstall($packageFile, $unpacked['extractdir'] ?? null);
            } elseif ($temporaryPackage !== '' && is_file($temporaryPackage)) {
                File::delete($temporaryPackage);
            }
        } catch (\Throwable $exception) {
            // Installation result must not be hidden by a temporary-file cleanup failure.
        }
    }
}
