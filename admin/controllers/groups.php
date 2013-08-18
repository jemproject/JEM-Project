<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */

defined( '_JEXEC' ) or die;

jimport('joomla.application.component.controlleradmin');

/**
 * JEM Component Groups Controller
 *
 */
class JEMControllerGroups extends JControllerAdmin
{

	/**
	 * @var		string	The prefix to use with controller messages.
	 *
	 */
	protected $text_prefix = 'COM_JEM_GROUPS';



	/**
	 * Proxy for getModel.
	 *
	 */
	public function getModel($name = 'Group', $prefix = 'JEMModel', $config = array('ignore_request' => true))
	{
		$model = parent::getModel($name, $prefix, $config);
		return $model;
	}




}
?>