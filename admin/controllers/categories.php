<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Categories Controller
 */
class JemControllerCategories extends AdminController
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
	public function getModel($name = 'Category', $prefix = 'JemModel', $config = array('ignore_request' => true))
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
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		$this->setRedirect(Route::_('index.php?option=com_jem&view=categories', false));

		// Initialise variables.
		$model = $this->getModel();

		if ($model->rebuild()) {
			// Rebuild succeeded.
			$this->setMessage(Text::_('COM_JEM_CATEGORIES_REBUILD_SUCCESS'));
			return true;
		} else {
			// Rebuild failed.
			$this->setMessage(Text::_('COM_JEM_CATEGORIES_REBUILD_FAILURE'));
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
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		// Get the arrays from the Request
		$order = Factory::getApplication()->input->post->get('order', array(), 'array');
		$originalOrder = explode(',', Factory::getApplication()->input->getString('original_order_values', ''));

		// Make sure something has changed
		if ($order !== $originalOrder) {
			parent::saveorder();
		} else {
			// Nothing to reorder
			$this->setRedirect(Route::_('index.php?option='.$this->option.'&view='.$this->view_list, false));
			return true;
		}
	}

	/** Deletes and returns correctly.
 	 *
 	 * @return	void
 	 */
 	public function deleteDisabled()
 	{
 		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

 		// Get items to remove from the request.
 		$cid = Factory::getApplication()->input->get('cid', array(), 'array');
 		$extension = Factory::getApplication()->input->get('extension', '');

 		if (!is_array($cid) || count($cid) < 1)
 		{
			 Factory::getApplication()->enqueueMessage(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), 'warning');
 		}
 		else
 		{
 			// Get the model.
 			$model = $this->getModel();

 			// Make sure the item ids are integers
 			jimport('joomla.utilities.arrayhelper');
 			\Joomla\Utilities\ArrayHelper::toInteger($cid);

 			// Remove the items.
 			if ($model->delete($cid))
 			{
 				$this->setMessage(Text::plural($this->text_prefix . '_N_ITEMS_DELETED', count($cid)));
 			}
 			else
 			{
 				$this->setMessage($model->getError());
 			}
 		}

 		$this->setRedirect(Route::_('index.php?option=' . $this->option . '&extension=' . $extension, false));
 	}

 	/**
 	 * Logic to delete categories
 	 *
 	 * @access public
 	 * @return void
 	 *
 	 */
 	public function remove()
 	{
		// Check for request forgeries
		Session::checkToken() or jexit('Invalid Token');

 		$cid= Factory::getApplication()->input->post->get('cid', array(), 'array');

 		if (!is_array($cid) || count($cid) < 1) {
			 Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_SELECT_ITEM_TO_DELETE'), 'warning');
 		}

 		$model = $this->getModel('category');

 		$msg = $model->delete($cid);

 		$cache = Factory::getCache('com_jem');
 		$cache->clean();

 		$this->setRedirect('index.php?option=com_jem&view=categories', $msg);
 	}

}
