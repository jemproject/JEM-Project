<?php
/**
 * @package    JEM
 * @subpackage AcyMailing 10 integration
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;

/**
 * Registers the add-on in AcyMailing's "My add-ons" list.
 */
final class AcymJemInstallerScript implements InstallerScriptInterface
{
    private const FOLDER_NAME = 'jem';
    private const ADDON_VERSION = '5.0.1';

    private CMSApplicationInterface $app;
    private DatabaseInterface $db;

    public function __construct(CMSApplicationInterface $app, DatabaseInterface $db)
    {
        $this->app = $app;
        $this->db = $db;
    }

    public function install(InstallerAdapter $parent): bool
    {
        return true;
    }

    public function update(InstallerAdapter $parent): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $parent): bool
    {
        return true;
    }

    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        try {
            $db = $this->db;
            $table = $db->replacePrefix('#__acym_plugin');

            if (!in_array($table, $db->getTableList(), true)) {
                $this->app->enqueueMessage(
                    'JEM Events was installed, but AcyMailing 10 was not found. Install AcyMailing and reinstall this add-on to register it in My add-ons.',
                    'warning'
                );

                return true;
            }

            $columns = array_change_key_case($db->getTableColumns($table, false), CASE_LOWER);
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__acym_plugin'))
                ->where($db->quoteName('folder_name').' = '.$db->quote(self::FOLDER_NAME));
            $existingId = (int) $db->setQuery($query)->loadResult();

            $metadata = [
                'title' => 'JEM - Events for AcyMailing',
                'folder_name' => self::FOLDER_NAME,
                'version' => self::ADDON_VERSION,
                'category' => 'Events management',
                'level' => 'starter',
                'uptodate' => 1,
                'description' => '- Insert JEM events in emails<br>- Insert upcoming JEM events automatically by category',
                'latest_version' => self::ADDON_VERSION,
                'type' => 'ADDON',
            ];
            $metadata = array_intersect_key($metadata, $columns);

            if ($existingId > 0) {
                $metadata['id'] = $existingId;
                $addon = (object) $metadata;
                $db->updateObject('#__acym_plugin', $addon, 'id');
            } else {
                if (isset($columns['active'])) {
                    $metadata['active'] = 1;
                }
                $addon = (object) $metadata;
                $db->insertObject('#__acym_plugin', $addon);
            }

            $this->app->enqueueMessage(
                'JEM Events is available in AcyMailing under My add-ons.',
                'message'
            );
        } catch (Throwable $exception) {
            $this->app->enqueueMessage(
                'JEM Events was installed, but it could not be registered in AcyMailing My add-ons: '.$exception->getMessage(),
                'warning'
            );
        }

        return true;
    }

    public function uninstall(InstallerAdapter $parent): bool
    {
        try {
            $db = $this->db;
            $table = $db->replacePrefix('#__acym_plugin');

            if (!in_array($table, $db->getTableList(), true)) {
                return true;
            }

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__acym_plugin'))
                ->where($db->quoteName('folder_name').' = '.$db->quote(self::FOLDER_NAME))
                ->where($db->quoteName('type').' = '.$db->quote('ADDON'))
                ->where($db->quoteName('version').' = '.$db->quote(self::ADDON_VERSION));
            $db->setQuery($query)->execute();
        } catch (Throwable $exception) {
            $this->app->enqueueMessage(
                'The JEM Events files were removed, but its AcyMailing registration could not be deleted: '.$exception->getMessage(),
                'warning'
            );
        }

        return true;
    }
}

return new AcymJemInstallerScript(
    Factory::getApplication(),
    Factory::getContainer()->get(DatabaseInterface::class)
);
