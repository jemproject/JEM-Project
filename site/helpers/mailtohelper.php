<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class JemMailtoHelper {
    public static function addLink($url)
	{
		$hash = sha1($url);
		self::cleanHashes();

        $app = Factory::getApplication();
		$session      = $app->getSession();
		$mailto_links = $session->get('com_jem.links', array());

		if (!isset($mailto_links[$hash]))
		{
			$mailto_links[$hash] = new stdClass;
		}

		$mailto_links[$hash]->link   = $url;
		$mailto_links[$hash]->expiry = time();
		$session->set('com_jem.links', $mailto_links);

		return $hash;
	}

	public static function cleanHashes($lifetime = 1440)
	{
		// Flag for if we've cleaned on this cycle
		static $cleaned = false;

		if (!$cleaned)
		{
			$past         = time() - $lifetime;
            $app = Factory::getApplication();
			$session      = $app->getSession();
			$mailto_links = $session->get('com_jem.links', array());

			foreach ($mailto_links as $index => $link)
			{
				if ($link->expiry < $past)
				{
					unset($mailto_links[$index]);
				}
			}

			$session->set('com_jem.links', $mailto_links);
			$cleaned = true;
		}
	}

	public static function validateHash($hash)
	{
		$retval  = false;
        $app = Factory::getApplication();
		$session = $app->getSession();

		self::cleanHashes();
		$mailto_links = $session->get('com_jem.links', array());

		if (isset($mailto_links[$hash]))
		{
			$retval = $mailto_links[$hash]->link;
		}

		return $retval;
	}
}

?>