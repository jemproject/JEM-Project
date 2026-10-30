<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * JEM Component Myevents Controller
 *
 * @package JEM
 *
 */
class JemControllerMyevents extends BaseController
{
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Logic to publish events
     *
     * @access public
     * @return void
     */
    public function publish() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');

        $app = Factory::getApplication();
        $input = $app->input;

        $cid = $input->get('cid', array(), 'array');

        if (empty($cid)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_SELECT_ITEM_TO_PUBLISH'), 'notice');
            $this->setRedirect(JemHelperRoute::getMyEventsRoute());
            return;
        }

        $model = $this->getModel('myevents');
        if (!$model->publish($cid, 1)) {
            $this->setRedirect(JemHelperRoute::getMyEventsRoute(), $model->getError(), 'error');
            return;
        }

        $total = (int) $model->getState('publish.updated', 0);
        $skipped = (int) $model->getState('publish.skipped', 0);
        $msg = $total > 0
            ? $total . ' ' . Text::_('COM_JEM_EVENT_PUBLISHED')
            : Text::_('COM_JEM_STATUS_NO_ITEMS_CHANGED');

        if ($skipped > 0) {
            $msg .= ' ' . Text::sprintf('COM_JEM_STATUS_ITEMS_SKIPPED', $skipped);
        }

        $this->setRedirect(
            JemHelperRoute::getMyEventsRoute(),
            $msg,
            $total === 0 || $skipped > 0 ? 'warning' : 'message'
        );
    }

    /**
     * Logic for canceling an event and proceed to add a venue
     */
    public function unpublish() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');

        $app = Factory::getApplication();
        $input = $app->input;

        $cid = $input->get('cid', array(), 'array');

        if (empty($cid)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_SELECT_ITEM_TO_UNPUBLISH'), 'notice');
            $this->setRedirect(JemHelperRoute::getMyEventsRoute());
            return;
        }

        $model = $this->getModel('myevents');
        if (!$model->publish($cid, 0)) {
            $this->setRedirect(JemHelperRoute::getMyEventsRoute(), $model->getError(), 'error');
            return;
        }

        $total = (int) $model->getState('publish.updated', 0);
        $skipped = (int) $model->getState('publish.skipped', 0);
        $msg = $total > 0
            ? $total . ' ' . Text::_('COM_JEM_EVENT_UNPUBLISHED')
            : Text::_('COM_JEM_STATUS_NO_ITEMS_CHANGED');

        if ($skipped > 0) {
            $msg .= ' ' . Text::sprintf('COM_JEM_STATUS_ITEMS_SKIPPED', $skipped);
        }

        $this->setRedirect(
            JemHelperRoute::getMyEventsRoute(),
            $msg,
            $total === 0 || $skipped > 0 ? 'warning' : 'message'
        );
    }

    /**
     * Logic to trash events
     *
     * @access public
     * @return void
     */
    public function trash() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');

        $app = Factory::getApplication();
        $input = $app->input;

        $cid = $input->get('cid', array(), 'array');

        if (empty($cid)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_SELECT_ITEM_TO_TRASH'), 'notice');
            $this->setRedirect(JemHelperRoute::getMyEventsRoute());
            return;
        }

        $model = $this->getModel('myevents');
        if (!$model->publish($cid, -2)) {
            $this->setRedirect(JemHelperRoute::getMyEventsRoute(), $model->getError(), 'error');
            return;
        }

        $total = (int) $model->getState('publish.updated', 0);
        $skipped = (int) $model->getState('publish.skipped', 0);
        $msg = $total > 0
            ? $total . ' ' . Text::_('COM_JEM_EVENT_TRASHED')
            : Text::_('COM_JEM_STATUS_NO_ITEMS_CHANGED');

        if ($skipped > 0) {
            $msg .= ' ' . Text::sprintf('COM_JEM_STATUS_ITEMS_SKIPPED', $skipped);
        }

        $this->setRedirect(
            JemHelperRoute::getMyEventsRoute(),
            $msg,
            $total === 0 || $skipped > 0 ? 'warning' : 'message'
        );
    }
}
?>
