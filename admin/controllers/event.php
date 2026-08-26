<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

require_once (JPATH_COMPONENT_SITE.'/classes/controller.form.class.php');

/**
 * JEM Component Event Controller
 *
*/
class JemControllerEvent extends JemControllerForm
{
    /**
     * @var    string  The prefix to use with controller messages.
     *
     */
    protected $text_prefix = 'COM_JEM_EVENT';


    /**
     * Constructor.
     *
     * @param  array $config  An optional associative array of configuration settings.
     * @see    FormController
     *
     */
    public function __construct($config = array()) {
        parent::__construct($config);
    }

    /**
     * Require the dedicated backend event-create permission.
     */
    protected function allowAdd($data = array())
    {
        return JemHelperBackend::can('event', 'create');
    }

    /**
     * Authorise editing from the event owner stored in the database.
     */
    protected function allowEdit($data = array(), $key = 'id')
    {
        $recordId = isset($data[$key]) ? (int) $data[$key] : 0;

        if ($recordId <= 0) {
            return false;
        }

        $record = $this->getModel()->getItem($recordId);

        return is_object($record)
            && (int) ($record->id ?? 0) === $recordId
            && JemHelperBackend::can('event', 'edit', $record);
    }

    /**
     * Refresh the venue configuration choices after the modal venue changes.
     */
    public function venueConfigurations()
    {
        $app = Factory::getApplication();
        JemHelper::setNoStoreHeaders();
        $app->sendHeaders();
        require_once JPATH_SITE . '/components/com_jem/classes/featurepolicy.class.php';
        if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_VENUE_CAPACITY)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $eventId = $app->input->getInt('id', 0);
        $venueId = $app->input->getInt('venue_id', 0);
        $authorised = $eventId > 0
            ? $this->allowEdit(array('id' => $eventId), 'id')
            : $this->allowAdd();
        if (!$authorised) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        require_once JPATH_ADMINISTRATOR . '/components/com_jem/classes/eventpricingcapacity.class.php';
        try {
            echo new JsonResponse(JemEventPricingCapacityService::getVenueConfigurationPayload($venueId));
        } catch (Throwable $e) {
            echo new JsonResponse($e);
        }
        $app->close();
    }

    /**
     * Method to save a record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable.
     *
     * @return  boolean
     */
    public function save($key = null, $urlVar = 'id')
    {
        $result = parent::save($key, $urlVar);
        $model = $this->getModel();

        if ($result && $model) {
            $this->handleCreatedArticleContentRedirect($model);
            $this->handleAssociatedArticleSyncNotice($model);
        }

        return $result;
    }

    /**
     * Update the associated Joomla article from the current event data.
     *
     * @return  void
     */
    public function updateAssociatedArticle()
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $app = Factory::getApplication();
        $input = $app->input->post;
        $id = $input->getInt('id', 0);
        $fields = $input->getString('fields', '');
        $model = $this->getModel();
        $redirect = Route::_('index.php?option=com_jem&view=event&layout=edit&id=' . $id, false);

        if (!$id || !$this->allowEdit(array('id' => $id), 'id')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        if ($model && $model->updateAssociatedArticleFromEvent($id, $fields)) {
            $this->setRedirect($redirect, Text::_('COM_JEM_EVENT_ARTICLE_SYNC_UPDATED'), 'message');

            return;
        }

        $this->setRedirect(
            $redirect,
            $model && $model->getError() ? $model->getError() : Text::_('COM_JEM_EVENT_ARTICLE_SYNC_UPDATE_FAILED'),
            'warning'
        );
    }

    /**
     * Notify or redirect after an empty event-content article is created.
     *
     * @param   object  $model  Event model.
     *
     * @return  void
     */
    protected function handleCreatedArticleContentRedirect($model)
    {
        $articleId = (int) $model->getState('event.article_content_article_id', 0);

        if (!$articleId || !(bool) $model->getState('event.article_content_empty', false)) {
            return;
        }

        $editUrl = Route::_('index.php?option=com_content&task=article.edit&id=' . $articleId, false);
        $action = (string) $model->getState('event.article_content_create_action', 'copy_description');

        if ($action === 'empty_edit') {
            $this->setRedirect($editUrl, Text::_('COM_JEM_EVENT_ARTICLE_CONTENT_EMPTY_EDIT'), 'notice');

            return;
        }

        Factory::getApplication()->enqueueMessage(
            Text::_('COM_JEM_EVENT_ARTICLE_CONTENT_EMPTY_EDIT') . ' <a href="' . $this->escapeHtmlAttribute($editUrl) . '">' . Text::_('COM_JEM_EVENT_ARTICLE_CONTENT_EDIT_LINK') . '</a>',
            'notice'
        );
    }

    /**
     * Show article sync choices when event content changed after save.
     *
     * @param   object  $model  Event model.
     *
     * @return  void
     */
    protected function handleAssociatedArticleSyncNotice($model)
    {
        $eventId = (int) $model->getState('event.article_sync_event_id', 0);
        $fields = (string) $model->getState('event.article_sync_fields', '');
        $labels = (string) $model->getState('event.article_sync_labels', '');

        if (!$eventId || $fields === '') {
            return;
        }

        $token = Session::getFormToken();
        $updateUrl = Route::_('index.php?option=com_jem', false);
        $dismissUrl = Route::_('index.php?option=com_jem&view=event&layout=edit&id=' . $eventId, false);
        $message = Text::sprintf('COM_JEM_EVENT_ARTICLE_SYNC_NOTICE', htmlspecialchars($labels, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false))
            . ' <form class="d-inline" method="post" action="' . $this->escapeHtmlAttribute($updateUrl) . '">'
            . '<input type="hidden" name="task" value="event.updateAssociatedArticle">'
            . '<input type="hidden" name="id" value="' . $eventId . '">'
            . '<input type="hidden" name="fields" value="' . $this->escapeHtmlAttribute($fields) . '">'
            . '<input type="hidden" name="' . $this->escapeHtmlAttribute($token) . '" value="1">'
            . '<button class="btn btn-sm btn-primary" type="submit">' . Text::_('COM_JEM_EVENT_ARTICLE_SYNC_UPDATE') . '</button>'
            . '</form>'
            . ' <a class="btn btn-sm btn-secondary" href="' . $this->escapeHtmlAttribute($dismissUrl) . '">' . Text::_('COM_JEM_EVENT_ARTICLE_SYNC_DISMISS') . '</a>';

        Factory::getApplication()->enqueueMessage($message, 'notice');
    }

    /**
     * Function that allows child controller access to model data
     * after the data has been saved.
     * Here used to trigger the jem plugins, mainly the mailer.
     *
     * @param   object  $model      The data model object.
     * @param   array           $validData  The validated data.
     *
     * @return  void
     *
     */
    protected function _postSaveHook($model, $validData = array()) {
        $modelName = method_exists($model, 'getName') ? $model->getName() : 'event';
        $isNew     = $model->getState('event.new');
        $id        = (int) $model->getState('event.id');

        if ($isNew === null) {
            $isNew = $model->getState($modelName . '.new');
        }

        if (!$id) {
            $id = (int) $model->getState($modelName . '.id');
        }

        if (!$id && !empty($validData['id'])) {
            $id = (int) $validData['id'];
        }

        if (!$id) {
            return;
        }

        $isNew = (bool) $isNew;

        JemHelper::reconcileWaitingList($id, array('source' => 'administrator.event.save'));

        // trigger all jem plugins
        PluginHelper::importPlugin('jem');
        $dispatcher = JemFactory::getDispatcher();
        $dispatcher->triggerEvent('onEventEdited', array($id, $isNew));

        // but show warning if mailer is disabled
        if (!PluginHelper::isEnabled('jem', 'mailer')) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'notice');
        }
    }
}
