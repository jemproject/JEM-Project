<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

/**
 * Maintains web-server deny rules for attachment directories inside document root.
 *
 * These files are a compatibility mitigation for legacy public storage. They do
 * not replace an out-of-web-root storage provider, particularly on Nginx.
 */
final class JemAttachmentProtection
{
    /**
     * Create the directory and its Apache/IIS direct-access guards.
     *
     * @param string $directory Absolute attachment base directory.
     *
     * @return bool
     */
    public static function protect(string $directory): bool
    {
        $directory = rtrim($directory, '/\\');

        if ($directory === '' || is_link($directory)) {
            return false;
        }

        if (!Folder::exists($directory) && !Folder::create($directory)) {
            return false;
        }

        foreach (self::protectionFiles($directory) as $filename => $contents) {
            $path = $directory . DIRECTORY_SEPARATOR . $filename;

            if (is_file($path) && file_get_contents($path) === $contents) {
                continue;
            }

            if (!File::write($path, $contents) || !is_file($path) || file_get_contents($path) !== $contents) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the managed protection file contents.
     *
     * @param string $directory Absolute attachment base directory.
     *
     * @return array<string, string>
     */
    private static function protectionFiles(string $directory): array
    {
        $segment = htmlspecialchars(basename($directory), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return array(
            '.htaccess' => "# Managed by JEM: block direct access to protected attachments.\n"
                . "<IfModule mod_authz_core.c>\n"
                . "    Require all denied\n"
                . "</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n"
                . "    Order Deny,Allow\n"
                . "    Deny from all\n"
                . "</IfModule>\n"
                . "<IfModule mod_autoindex.c>\n"
                . "    Options -Indexes\n"
                . "</IfModule>\n",
            'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<configuration>\n"
                . "  <system.webServer>\n"
                . "    <directoryBrowse enabled=\"false\" />\n"
                . "    <security>\n"
                . "      <requestFiltering>\n"
                . "        <hiddenSegments>\n"
                . "          <remove segment=\"{$segment}\" />\n"
                . "          <add segment=\"{$segment}\" />\n"
                . "        </hiddenSegments>\n"
                . "      </requestFiltering>\n"
                . "    </security>\n"
                . "  </system.webServer>\n"
                . "</configuration>\n",
            'index.html' => '<!DOCTYPE html><title></title>',
        );
    }
}
