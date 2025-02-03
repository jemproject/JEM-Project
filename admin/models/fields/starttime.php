<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\FormField;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');


/**
 * CountryOptions Field class.
 *
 * 
 */
class JFormFieldStarttime extends FormField
{
	/**
	 * The form field type.
	 *
	 */
	protected $type = 'Starttime';

	
	public function getInput()
	{
	
		
		$starthours = JEMHelper::buildtimeselect(23, 'starthours', substr( $this->value, 0, 2 ),array('class'=>'form-select','class'=>'select-time'));
		$startminutes = JEMHelper::buildtimeselect(59, 'startminutes', substr($this->value, 3, 2 ),array('class'=>'form-select','class'=>'select-time'));
		
		$var2 = $starthours.$startminutes;
	
		return $var2;
		
	}
	
}
