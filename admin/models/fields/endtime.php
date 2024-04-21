<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');
jimport('joomla.form.formfield');



/**
 * Endtime Field class.
 *
 * 
 */
class JFormFieldEndtime extends JFormField
{
	/**
	 * The form field type.
	 *
	 */
	protected $type = 'Endtime';

	
	public function getInput()
	{
		
		$endhours = JEMHelper::buildtimeselect(23, 'endhours', substr( $this->value, 0, 2 ),array('class'=>'form-select','class'=>'select-time'));
		$endminutes = JEMHelper::buildtimeselect(59, 'endminutes', substr($this->value, 3, 2 ),array('class'=>'form-select','class'=>'select-time'));
		
		$var2 = $endhours.$endminutes;
	
		return $var2;
		
	}
	
}
