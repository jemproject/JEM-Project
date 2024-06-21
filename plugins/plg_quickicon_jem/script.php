<?php
defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerScript;

class PlgQuickiconJemquickiconInstallerScript extends InstallerScript
{
    public function postflight($type, $parent)
    {
        if ($type === 'install' || $type === 'discover_install') {
            $db = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('jemquickicon'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('quickicon'));
            $db->setQuery($query);
            $db->execute();
        }
    }
}