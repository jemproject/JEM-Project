<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;

/**
 * JEM Settings Table
 *
 * @deprecated since version 2.1.6
 */
class JemTableSettings extends Table
{
	public function __construct(&$db)
	{
		parent::__construct('#__jem_settings', 'id', $db);
	}

	/**
	 * Validators
	 * @deprecated since version 2.1.6
	 */
	public function check()
	{
		return true;
	}

	/**
	 * Overloaded the store method
	 * @deprecated since version 2.1.6
	 */
	public function store($updateNulls = false)
	{
		return parent::store($updateNulls);
	}

	/**
	 * @deprecated since version 2.1.6
	 */
	public function bind($array, $ignore = '')
	{
		if (isset($array['globalattribs']) && is_array($array['globalattribs']))
		{
			$registry = new JRegistry;
			$registry->loadArray($array['globalattribs']);
			$array['globalattribs'] = (string) $registry;
		}

		if (isset($array['css']) && is_array($array['css']))
		{
			$registrycss = new JRegistry;
			$registrycss->loadArray($array['css']);
			$array['css'] = (string) $registrycss;
		}

		//don't override without calling base class
		return parent::bind($array, $ignore);
	}
}
?>
