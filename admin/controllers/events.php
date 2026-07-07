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
use Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

/**
 * Events Controller
 */
class JemControllerEvents extends AdminController
{
    /**
     * @var    string  The prefix to use with controller messages.
     *
     */
    protected $text_prefix = 'COM_JEM_EVENTS';

    /**
     * Constructor.
     *
     * @param  array  $config  An optional associative array of configuration settings.
     * @see    AdminController
     */
    public function __construct($config = array()) {
        parent::__construct($config);

        $this->registerTask('unfeatured', 'featured');
    }

    /**
     * Method to toggle the featured setting of a list of events.
     *
     * @return void
     * @since  1.6
     */
    public function featured() {
        // Check for request forgeries
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Initialise variables.
        $user   = JemFactory::getUser();
        $ids    = Factory::getApplication()->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($ids);
        $ids = array_filter($ids);
        $values = array('featured' => 1, 'unfeatured' => 0);
        $task   = $this->getTask();
        $value  = ArrayHelper::getValue($values, $task, 0, 'int');

        $glob_auth = $user->can('publish', 'event'); // general permission for all events

        // Access checks.
        foreach ($ids as $i => $id) {
            if (!$glob_auth && !$user->can('publish', 'event', (int)$id)) {
                // Prune items that you can't change.
                unset($ids[$i]);
                Factory::getApplication()->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'notice');
            }
        }

        if (empty($ids)) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
        } else {
            // Get the model.
            $model = $this->getModel();

            // Publish the items.
            if (!$model->featured($ids, $value)) {
                Factory::getApplication()->enqueueMessage($model->getError(), 'warning');
            }
        }

        $this->setRedirect('index.php?option=com_jem&view=events');
    }

    /**
     * Batch process selected events.
     */
    public function batch()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $user = JemFactory::getUser();
        $ids = $app->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($ids);
        $ids = array_values(array_filter($ids));
        $batch = $app->input->get('batch', array(), 'array');

        $globAuth = $user->can('edit', 'event');

        foreach ($ids as $i => $id) {
            if (!$globAuth && !$user->can('edit', 'event', (int) $id)) {
                unset($ids[$i]);
                $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'notice');
            }
        }

        if (empty($ids)) {
            $app->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
        } else {
            $model = $this->getModel();
            $operations = array(
                'assetgroup_id' => array('method' => 'changeAccess', 'message' => 'COM_JEM_EVENTS_BATCH_ACCESS_CHANGED'),
                'category_id'   => array('method' => 'moveToCategory', 'message' => 'COM_JEM_EVENTS_MOVED_TO_CATEGORY'),
                'venue_id'      => array('method' => 'moveToVenue', 'message' => 'COM_JEM_EVENTS_MOVED_TO_VENUE'),
                'type_id'       => array('method' => 'moveToType', 'message' => 'COM_JEM_EVENTS_MOVED_TO_TYPE'),
            );
            $processed = false;

            foreach ($operations as $field => $operation) {
                if (!array_key_exists($field, $batch) || $batch[$field] === '') {
                    continue;
                }

                if (!$model->{$operation['method']}($ids, (int) $batch[$field])) {
                    $app->enqueueMessage($model->getError(), 'warning');
                    $this->setRedirect('index.php?option=com_jem&view=events');
                    return;
                }

                $processed = true;
                $app->enqueueMessage(Text::plural($operation['message'], count($ids)));
            }

            if (!$processed) {
                $app->enqueueMessage(Text::_('COM_JEM_EVENTS_BATCH_NO_CHANGE'), 'warning');
            }
        }

        $this->setRedirect('index.php?option=com_jem&view=events');
    }

    /**
     * Proxy for getModel.
     *
     */
    public function getModel($name = 'Event', $prefix = 'JemModel', $config = array('ignore_request' => true)) {
        $model = parent::getModel($name, $prefix, $config);
        return $model;
    }

}
?>
