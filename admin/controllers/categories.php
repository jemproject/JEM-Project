<?php
/**
 * @version     2.1.0
 * @package     JEM
 * @copyright   Copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.controlleradmin');

/**
 * Categories Controller
 */
class JemControllerCategories extends JControllerAdmin
{

	protected $text_prefix = 'COM_JEM_CATEGORIES';


	/**
	 * Proxy for getModel
	 *
	 * @param	string	$name	The model name. Optional.
	 * @param	string	$prefix	The class prefix. Optional.
	 *
	 * @return	object	The model.
	 */
	function getModel($name = 'Category', $prefix = 'JEMModel', $config = array('ignore_request' => true))
	{
		$model = parent::getModel($name, $prefix, $config);
		return $model;
	}

	/**
	 * Rebuild the nested set tree.
	 *
	 * @return	bool	False on failure or error, true on success.
	 */
	public function rebuild()
	{
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$this->setRedirect(JRoute::_('index.php?option=com_jem&view=categories', false));

		// Initialise variables.
		$model = $this->getModel();

		if ($model->rebuild()) {
			// Rebuild succeeded.
			$this->setMessage(JText::_('COM_JEM_CATEGORIES_REBUILD_SUCCESS'));
			return true;
		} else {
			// Rebuild failed.
			$this->setMessage(JText::_('COM_JEM_CATEGORIES_REBUILD_FAILURE'));
			return false;
		}
	}

	/**
	 * Save the manual order inputs from the categories list page.
	 *
	 * @return	void
	 */
	public function saveorderDisabled()
	{
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		// Get the arrays from the Request
		$order = JFactory::getApplication()->input->post->get('order', array(), 'array');
		$originalOrder = explode(',', JFactory::getApplication()->input->getString('original_order_values', ''));

		// Make sure something has changed
		if (!($order === $originalOrder)) {
			parent::saveorder();
		} else {
			// Nothing to reorder
			$this->setRedirect(JRoute::_('index.php?option='.$this->option.'&view='.$this->view_list, false));
			return true;
		}
	}

	/** Deletes and returns correctly.
 	 *
 	 * @return	void
 	 */
 	public function deleteDisabled()
 	{
 		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

 		// Get items to remove from the request.
 		$cid = JFactory::getApplication()->input->get('cid', array(), 'array');
 		$extension = JFactory::getApplication()->input->get('extension', '');

 		if (!is_array($cid) || count($cid) < 1)
 		{
 			JError::raiseWarning(500, JText::_($this->text_prefix . '_NO_ITEM_SELECTED'));
 		}
 		else
 		{
 			// Get the model.
 			$model = $this->getModel();

 			// Make sure the item ids are integers
 			jimport('joomla.utilities.arrayhelper');
 			JArrayHelper::toInteger($cid);

 			// Remove the items.
 			if ($model->delete($cid))
 			{
 				$this->setMessage(JText::plural($this->text_prefix . '_N_ITEMS_DELETED', count($cid)));
 			}
 			else
 			{
 				$this->setMessage($model->getError());
 			}
 		}

 		$this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&extension=' . $extension, false));
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
 		$cid= JFactory::getApplication()->input->post->get('cid', array(), 'array');

 		if (!is_array($cid) || count($cid) < 1) {
 			JError::raiseWarning(500, JText::_('COM_JEM_SELECT_ITEM_TO_DELETE'));
 		}

 		$model = $this->getModel('category');

 		$msg = $model->delete($cid);

 		$cache = JFactory::getCache('com_jem');
 		$cache->clean();

 		$this->setRedirect('index.php?option=com_jem&view=categories', $msg);
 	}

}