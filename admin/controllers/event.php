<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */

defined('_JEXEC') or die;

require_once (JPATH_COMPONENT_SITE.'/classes/controller.form.class.php');

/**
 * JEM Component Event Controller
 *
*/
class JEMControllerEvent extends JemControllerForm
{
	/**
	 * @var    string  The prefix to use with controller messages.
	 *
	 */
	protected $text_prefix = 'COM_JEM_EVENT';


	/**
	 * Constructor.
	 *
	 * @param	array An optional associative array of configuration settings.
	 * @see		JController
	 *
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);
	}

	/**
	 * Function that allows child controller access to model data
	 * after the data has been saved.
	 * Here used to trigger the jem plugins, mainly the mailer.
	 *
	 * @param   JModel(Legacy)  $model      The data model object.
	 * @param   array           $validData  The validated data.
	 *
	 * @return  void
	 *
	 * @note    On J! 2.5 first param is 'JModel &$model' but
	 *          on J! 3.x it's 'JModelLegacy $model'
	 *          one of the bad things making extension developer's life hard.
	 */
	protected function _postSaveHook($model, $validData = array())
	{
		$isNew = $model->getState('event.new');
		$id    = $model->getState('event.id');

		// trigger all jem plugins
		JPluginHelper::importPlugin('jem');
		$dispatcher = JemFactory::getDispatcher();
		$dispatcher->trigger('onEventEdited', array($id, $isNew));

		// but show warning if mailer is disabled
		if (!JPluginHelper::isEnabled('jem', 'mailer')) {
			JError::raiseNotice(100, JText::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'));
		}
	}
}