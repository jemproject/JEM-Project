<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_BASE') or die;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');
jimport('joomla.form.formfield');



/**
 * CountryOptions Field class.
 *
 * 
 */
class JFormFieldStartminutes extends JFormField
{
	/**
	 * The form field type.
	 *
	 */
	protected $type = 'Startminutes';


	
	
	public function getInput()
	{

	
		$startminutes = JEMAdmin::buildtimeselect(59, 'startminutes', substr( $this->name, 3, 2 ), array('class'=>'form-select','class'=>'select-time'));
	
		return $startminutes;
		
	}
	
}
