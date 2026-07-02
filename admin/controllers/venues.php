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
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * Controller: Venues
 */
class JemControllerVenues extends AdminController
{
    /**
     * @var    string  The prefix to use with controller messages.
     */
    protected $text_prefix = 'COM_JEM_VENUES';


    /**
     * Proxy for getModel.
     */
    public function getModel($name = 'Venue', $prefix = 'JemModel', $config = array('ignore_request' => true)) {
        $model = parent::getModel($name, $prefix, $config);
        return $model;
    }

    public function saveOrderAjax()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.edit.state', 'com_jem') && !$user->authorise('core.admin', 'com_jem')) {
            echo '0';
            $app->close();
        }

        $cid = $app->input->get('cid', array(), 'array');
        $order = $app->input->get('order', array(), 'array');
        ArrayHelper::toInteger($cid);
        ArrayHelper::toInteger($order);

        if (empty($cid) || count($cid) !== count($order)) {
            echo '0';
            $app->close();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        foreach ($cid as $index => $id) {
            if ($id <= 0) {
                continue;
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_venues'))
                ->set($db->quoteName('ordering') . ' = ' . (int) ($order[$index] ?? ($index + 1)))
                ->where($db->quoteName('id') . ' = ' . (int) $id);
            $db->setQuery($query);
            $db->execute();
        }

        echo '1';
        $app->close();
    }

    /**
     * logic for remove venues
     *
     * @access public
     */
    public function remove() {
        // Check for token
        Session::checkToken() or jexit(Text::_('COM_JEM_GLOBAL_INVALID_TOKEN'));

        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.delete', 'com_jem')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $jinput = $app->input;
        $cid = $jinput->get('cid',array(),'array');

        if (!is_array( $cid ) || count( $cid ) < 1) {
            throw new Exception(Text::_('COM_JEM_SELECT_AN_ITEM_TO_DELETE'), 500);
        } else {
            $model = $this->getModel('venue');

            ArrayHelper::toInteger($cid);
            $cid = array_filter($cid);

            if (empty($cid)) {
                throw new Exception(Text::_('COM_JEM_SELECT_AN_ITEM_TO_DELETE'), 500);
            }

            // trigger delete function in the model
            $result = $model->delete($cid);
            if ($result['removed']) {
                $app->enqueueMessage(Text::plural($this->text_prefix.'_N_ITEMS_DELETED',$result['removedCount']));
            }
            if ($result['error']) {
                $app->enqueueMessage(Text::_('COM_JEM_VENUES_UNABLETODELETE'),'warning');

                foreach ($result['error'] AS $error) {
                    $html = array();
                    $html[] = '<span class="label label-info">'.$error[0].'</span>';
                    $html[] = '<br>';
                    unset($error[0]);
                    $html[] = implode('<br>', $error);
                    $app->enqueueMessage(implode("\n",$html),'warning');
                }
            }

            $this->postDeleteHook($model,$cid);
        }

        $cache = Factory::getCache('com_jem');
        $cache->clean();

        $this->setRedirect( 'index.php?option=com_jem&view=venues');
    }
}
