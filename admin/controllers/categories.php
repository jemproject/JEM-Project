<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

/**
 * Categories Controller
 */
class JemControllerCategories extends AdminController
{

    protected $text_prefix = 'COM_JEM_CATEGORIES';


    /**
     * Proxy for getModel
     *
     * @param    string    $name    The model name. Optional.
     * @param    string    $prefix    The class prefix. Optional.
     *
     * @return    object    The model.
     */
    public function getModel($name = 'Category', $prefix = 'JemModel', $config = array('ignore_request' => true)) {
        $model = parent::getModel($name, $prefix, $config);
        return $model;
    }

    /**
     * Rebuild the nested set tree.
     *
     * @return    bool    False on failure or error, true on success.
     */
    public function rebuild() {
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
      * Logic to delete categories
      *
      * @access public
      * @return void
      *
      */
     public function remove() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');

         $app = Factory::getApplication();
         if (!$app->getIdentity()->authorise('core.delete', 'com_jem')) {
             throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
         }

         $cid = $app->input->post->get('cid', array(), 'array');

         if (!is_array($cid) || count($cid) < 1) {
             $app->enqueueMessage(Text::_('COM_JEM_SELECT_ITEM_TO_DELETE'), 'warning');
             $this->setRedirect('index.php?option=com_jem&view=categories');
             return;
         }

         ArrayHelper::toInteger($cid);
         $cid = array_filter($cid);

         if (empty($cid)) {
             $app->enqueueMessage(Text::_('COM_JEM_SELECT_ITEM_TO_DELETE'), 'warning');
             $this->setRedirect('index.php?option=com_jem&view=categories');
             return;
         }

         $model = $this->getModel('category');

         $msg = $model->delete($cid);

         $cache = Factory::getCache('com_jem');
         $cache->clean();

         $this->setRedirect('index.php?option=com_jem&view=categories', $msg);
     }

}
