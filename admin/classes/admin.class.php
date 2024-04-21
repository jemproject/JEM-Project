<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

/**
 * Holds helpfull administration related stuff
 *
 */
class JemAdmin
{
	/**
	 * Writes footer.
	 *
	 */
	static public function footer()
	{

	}

	/**
	 * Retrieves settings.
	 *
	 */
	static public function config()
	{
		$jemConfig = JemConfig::getInstance();

		return $jemConfig->toObject();
	}

	static public function buildtimeselect($max, $name, $selected, $class = array('class'=>'inputbox'))
	{
		$timelist = array();
		$timelist[0] = HTMLHelper::_('select.option', '', '');

		foreach(range(0, $max) as $value) {
			if($value >= 10) {
				$timelist[] = HTMLHelper::_('select.option', $value, $value);
			} else {
				$timelist[] = HTMLHelper::_('select.option', '0'.$value, '0'.$value);
			}
		}
		return HTMLHelper::_('select.genericlist', $timelist, $name, $class, 'value', 'text', $selected);
	}
}

?>
