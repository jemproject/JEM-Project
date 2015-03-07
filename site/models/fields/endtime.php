<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
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
		
		$endhours = JEMHelper::buildtimeselect(23, 'endhours', substr( $this->value, 0, 2 ));
		$endminutes = JEMHelper::buildtimeselect(59, 'endminutes', substr($this->value, 3, 2 ));
		
		$var2 = $endhours.$endminutes;
	
		return $var2;
		
	}
	
}
