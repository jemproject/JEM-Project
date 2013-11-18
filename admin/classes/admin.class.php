<?php
/**
 * @version 1.9.5
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

	}

	
	/**
	 * Retrieves settings.
	 *
	 */
	static function config()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array('*'));
		$query->from('#__jem_settings');
		$query->where(array('id= '.$db->quote('1')));

		$db->setQuery($query);
		$config = $db->loadObject();

		return $config;
	}

	static function buildtimeselect($max, $name, $selected, $class = 'class="inputbox"')
	{
		$timelist = array();
		$timelist[0] = JHtml::_('select.option', '', '');

		foreach(range(0, $max) as $value) {
			if($value >= 10) {
				$timelist[] = JHtml::_('select.option', $value, $value);
			} else {
				$timelist[] = JHtml::_('select.option', '0'.$value, '0'.$value);
			}
		}
		return JHtml::_('select.genericlist', $timelist, $name, $class, 'value', 'text', $selected);
	}
}

?>