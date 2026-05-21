<?php
defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Factory;
use Joomla\CMS\Version;

class PlgQuickiconJemInstallerScript extends InstallerScript
{
    public function preflight($type, $parent)
    {
        return Version::MAJOR_VERSION === 6;
    }

    public function postflight($type, $parent)
    {
        if ($type === 'install' || $type === 'discover_install') {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('jem'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('quickicon'));
            $db->setQuery($query);
            $db->execute();
        }
    }
}
