<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Form\FormHelper;

FormHelper::loadFieldClass('list');


/**
 * CountryOptions Field class
 */
class JFormFieldCountryOptions extends ListField
{
	/**
	 * The form field type.
	 */
	protected $type = 'CountryOptions';

	/**
	 * Method to get the Country options.
	 */
	public function getOptions()
	{
		return JemHelper::getCountryOptions();
	}
}
