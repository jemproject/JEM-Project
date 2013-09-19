<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Categories Controller
 *
 * @package JEM
 *
 */
class JEMControllerCategories extends JControllerLegacy
{
	/**
	 * Constructor
	 *
	 *
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask('add'  ,		 	'edit');
		$this->registerTask('apply', 			'save');
		$this->registerTask('accesspublic', 	'access');
		$this->registerTask('accessregistered','access');
		$this->registerTask('accessspecial', 	'access');
	}

	/**
	 * Logic to save a category
	 *
	 * @access public
	 * @return void
	 *
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		$task = JRequest::getVar('task');

		//Sanitize
		$post = JRequest::get('post');
		$post['catdescription'] = JRequest::getVar('catdescription', '', 'post', 'string', JREQUEST_ALLOWRAW);
		$post['catdescription']	= str_replace('<br>', '<br />', $post['catdescription']);

		//sticky forms
		$session = JFactory::getSession();
		$session->set('categoryform', $post, 'com_jem');

		$model = $this->getModel('category');

		if ($returnid = $model->store($post)) {
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_jem&view=category&cid[]='.$returnid;
					break;

				default :
					$link = 'index.php?option=com_jem&view=categories';
					break;
			}
			$msg = JText::_('COM_JEM_CATEGORY_SAVED');

			$cache = JFactory::getCache('com_jem');
			$cache->clean();

			$session->clear('categoryform', 'com_jem');
		} else {
			$msg 	= '';
			$link 	= 'index.php?option=com_jem&view=category';
		}

		$model->checkin();

		$this->setRedirect($link, $msg);
	}

	/**
	 * Logic to publish categories
	 *
	 * @access public
	 * @return void
	 *
	 */
	function publish()
	{
		$cid 	= JRequest::getVar('cid', array(0), 'post', 'array');

		if (!is_array($cid) || count($cid) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_PUBLISH'));
		}

		$model = $this->getModel('categories');

		if(!$model->publish($cid, 1)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onFinderCategoryChangeState', array('com_jem', $cid, 1));

		$total = count($cid);
		$msg = $total.' '.JText::_('COM_JEM_CATEGORY_PUBLISHED');

		$this->setRedirect('index.php?option=com_jem&view=categories', $msg);
	}

	/**
	 * Logic to unpublish categories
	 *
	 * @access public
	 * @return void
	 *
	 */
	function unpublish()
	{
		$cid 	= JRequest::getVar('cid', array(0), 'post', 'array');

		if (!is_array($cid) || count($cid) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_UNPUBLISH'));
		}

		$model = $this->getModel('categories');

		if(!$model->publish($cid, 0)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onFinderCategoryChangeState', array('com_jem', $cid, 1));

		$total = count($cid);
		$msg = $total.' '.JText::_('COM_JEM_CATEGORY_UNPUBLISHED');

		$this->setRedirect('index.php?option=com_jem&view=categories', $msg);
	}

	/**
	 * Logic to orderup a category
	 *
	 * @access public
	 * @return void
	 *
	 */
	function orderup()
	{
		$model = $this->getModel('categories');
		$model->move(-1);

		$this->setRedirect('index.php?option=com_jem&view=categories');
	}

	/**
	 * Logic to orderdown a category
	 *
	 * @access public
	 * @return void
	 *
	 */
	function orderdown()
	{
		$model = $this->getModel('categories');
		$model->move(1);

		$this->setRedirect('index.php?option=com_jem&view=categories');
	}

	/**
	 * Logic to mass ordering categories
	 *
	 * @access public
	 * @return void
	 *
	 */
	function saveordercat()
	{
		$cid 	= JRequest::getVar('cid', array(0), 'post', 'array');
		$order 	= JRequest::getVar('order', array(0), 'post', 'array');
		JArrayHelper::toInteger($order, array(0));

		$model = $this->getModel('categories');
		$model->saveorder($cid, $order);

		$msg = 'New ordering saved';
		$this->setRedirect('index.php?option=com_jem&view=categories', $msg);
	}

	/**
	 * Logic to delete categories
	 *
	 * @access public
	 * @return void
	 *
	 */
	function remove()
	{
		$cid= JRequest::getVar('cid', array(0), 'post', 'array');

		if (!is_array($cid) || count($cid) < 1) {
			JError::raiseWarning(500, JText::_('COM_JEM_SELECT_ITEM_TO_DELETE'));
		}

		$model = $this->getModel('categories');

		$msg = $model->delete($cid);

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		$this->setRedirect('index.php?option=com_jem&view=categories', $msg);
	}

	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 *
	 */
	function cancel()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		$session 	= JFactory::getSession();
		$session->clear('categoryform', 'com_jem');

		$category = JTable::getInstance('jem_categories', '');
		$category->bind(JRequest::get('post'));
		$category->checkin();

		$this->setRedirect('index.php?option=com_jem&view=categories');
	}

	/**
	 * Logic to set the category access level
	 *
	 * @access public
	 * @return void
	 *
	 */
	function access()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		$cid		= JRequest::getVar('cid', array(0), 'post', 'array');
		$id			= (int)$cid[0];
		$task		= JRequest::getVar('task');

		if ($task == 'accesspublic') {
			$access = 0;
		} elseif ($task == 'accessregistered') {
			$access = 1;
		} else {
			$access = 2;
		}

		$model = $this->getModel('categories');
		$model->access($id, $access);

		$this->setRedirect('index.php?option=com_jem&view=categories');
	}

	/**
	 * Logic to create the view for the edit categoryscreen
	 *
	 * @access public
	 * @return void
	 *
	 */
	function edit()
	{
		JRequest::setVar('view', 'category');
		JRequest::setVar('hidemainmenu', 1);

		$model 	= $this->getModel('category');
		$user	= JFactory::getUser();

		// Error if checkedout by another administrator
		if ($model->isCheckedOut($user->get('id'))) {
			$this->setRedirect('index.php?option=com_jem&view=categories', JText::_('COM_JEM_EDITED_BY_ANOTHER_ADMIN'));
		}

		$model->checkout();

		parent::display();
	}
}
