<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('JPATH_BASE') or die;

/**
 * Hits Field.
 */
class JFormFieldHits extends JFormField
{
	/**
	 * The form field type.
	 * @var		string
	 */
	protected $type = 'Hits';

	/**
	 * Method to get the field input markup.
	 *
	 * @return	string	The field input markup.
	 */
	protected function getInput()
	{
		$onclick	= ' onclick="document.id(\''.$this->id.'\').value=\'0\';"';

		return '<input style="border:0;" type="text" name="'.$this->name.'" id="'.$this->id.'" value="'.htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8').'" readonly="readonly" /><input type="button"'.$onclick.' value="'.JText::_('COM_JEM_RESET_HITS').'" class="button"/>';
	}
}