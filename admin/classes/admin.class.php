<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Holds helpfull administration related stuff
 * 
 */
class JEMAdmin {

	/**
	* Writes footer.
	*
	*/
	static function footer()
	{
		$params =  JComponentHelper::getParams('com_jem');

		// if ($params->get('copyright') == 1) {
		echo '<font color="grey">Powered by <a href="http://www.joomlaeventmanager.net" target="_blank">JEM</a></font>';
		// }
	}

	static function config()
	{
		$db = JFactory::getDBO();

		$sql = 'SELECT * FROM #__jem_settings WHERE id = 1';
		$db->setQuery($sql);
		$config = $db->loadObject();

		return $config;
	}

	static function buildtimeselect($max, $name, $selected, $class = 'class="inputbox"')
	{
		$timelist = array();
		$timelist[0] = JHTML::_('select.option', '', '');

		foreach(range(0, $max) as $value) {
			if($value >= 10) {
				$timelist[] = JHTML::_('select.option', $value, $value);
			} else {
				$timelist[] = JHTML::_('select.option', '0'.$value, '0'.$value);
			}
		}
		return JHTML::_('select.genericlist', $timelist, $name, $class, 'value', 'text', $selected);
	}
}

?>