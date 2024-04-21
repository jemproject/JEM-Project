<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\FormField;

/**
 * Hits Field.
 */
class JFormFieldHits extends FormField
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
		$onclick	= ' onclick="document.getElementById(\''.$this->id.'\').value=\'0\';"';

		return '<input type="text" name="'.$this->name.'" id="'.$this->id.'" class="form-control field-user-input-name valid form-control-success w-20" value="'.htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8').'" readonly="readonly" style="display:inline-block;" /><input type="button"'.$onclick.' value="'.Text::_('COM_JEM_RESET_HITS').'" class="btn btn-primary selectcat" />';
	}
}
