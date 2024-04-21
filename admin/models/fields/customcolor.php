<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Color Form Field
 */
class JFormFieldCustomColor extends JFormField
{
	/**
	 * The form field type.
	 */
	protected $type = 'CustomColor';

	/**
	 * Method to get the field input markup.
	 */
	protected function getInput()
	{
		// Initialize field attributes.
		$size = $this->element['size'] ? ' size="' . (int) $this->element['size'] . '"' : '';
		$classes = (string) $this->element['class'];
		$disabled = ((string) $this->element['disabled'] == 'true') ? ' disabled="disabled"' : '';

		if (!$disabled)
		{
			$classes .= ' colorpicker';
		}

		// load script.
		$script = array();
		
		$script[] = '	function jClearColor(id) {';
		$script[] = '		document.getElementById(id).value = "";';
		$script[] = '		document.getElementById(id).style.background = "";';
		$script[] = '	}';
			
		// Add the script to the document head.
		Factory::getDocument()->addScriptDeclaration(implode("\n", $script));
		
		// Initialize JavaScript field attributes.
		$onclick = ' onclick="openPicker(\''.$this->id.'\', -200, 20)"';
		$class = $classes ? ' class="' . trim($classes) . '"' : '';

		$html	= array();
		$html[] = '<input style="background:'.$this->value.'" type="text" name="' . $this->name . '" id="' . $this->id . '"' . ' value="'
			. htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8') . '"' . $class . $size .$onclick. '/>';
		$html[] = '<input title="'.Text::_('JCLEAR').'" type="text" class="button" size="1" value="" id="clear" onclick="return jClearColor(\''.$this->id.'\')">';
		
		return implode("\n", $html);
	}
}
