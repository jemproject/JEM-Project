<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

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