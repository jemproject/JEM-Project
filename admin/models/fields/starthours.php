<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\FormField;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');

/**
 * StartHours Field class.
 *
 * 
 */
class JFormFieldStarthours extends FormField
{
	/**
	 * The form field type.
	 *
	 */
	protected $type = 'Starthours';

	
	public function getInput()
	{
	
		$starthours = JEMAdmin::buildtimeselect(23, 'starthours', substr( $this->name, 0, 2 ));
		$startminutes = JEMAdmin::buildtimeselect(59, 'startminutes', substr( $this->name, 3, 2 ));
		
		$var2 = $starthours.$startminutes;
	
		return $var2;
		
	}
	
}
