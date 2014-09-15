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
 * StartHours Field class.
 *
 * 
 */
class JFormFieldStarthours extends JFormField
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
