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
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

require_once JPATH_ADMINISTRATOR . '/components/com_jem/helpers/languagecatalog.php';

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
            $download = JemRemoteSourceHelper::download(
                $package['url'],
                array('zip'),
                'zip',
                $package['bytes']
            );

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

            if (!$installer->install($unpacked['dir'])) {
                throw new RuntimeException('package_install');
            }

            $this->setRedirect(
                $redirect,
                Text::sprintf('COM_JEM_LANGUAGES_INSTALL_SUCCESS', $package['tag'], $package['version'])
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
