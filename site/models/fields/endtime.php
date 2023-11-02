<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\FormField;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');

/**
 * Endtime Field class.
 *
 * 
 */
class JFormFieldEndtime extends FormField
{
	/**
	 * The form field type.
	 *
	 */
	protected $type = 'Endtime';

	
	public function getInput()
	{
		
		$endhours = JEMHelper::buildtimeselect(23, 'endhours', substr( $this->value, 0, 2 ));
		$endminutes = JEMHelper::buildtimeselect(59, 'endminutes', substr($this->value, 3, 2 ));
		
		$var2 = $endhours.$endminutes;
	
		return $var2;
		
	}
	
}
