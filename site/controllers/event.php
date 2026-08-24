<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

require_once (JPATH_COMPONENT_SITE.'/classes/controller.form.class.php');

/**
 * Event Controller
 */
class JemControllerEvent extends JemControllerForm
{
    protected $view_item = 'editevent';
    protected $view_list = 'eventslist';
    protected $_id = 0;

    /**
     * Method to add a new record.
     *
     * @return boolean True if the event can be added, false if not.
     */
    public function add() {
        if (!$this->requireFrontendUser()) {
            return false;
        }

        $this->assertFrontendCanAdd('event', Factory::getApplication()->input->getInt('catid', 0));

        return parent::add();
    }

    /**
     * Method override to check if you can add a new record.
     *
     * @param  array An array of input data.
     *
     * @return boolean
     */
    protected function allowAdd($data = array()) {
        // Initialise variables.
        $user       = JemFactory::getUser();
        $inputCatId = Factory::getApplication()->input->getInt('catid', 0);
        $categoryIds = ArrayHelper::getValue($data, 'cats', $inputCatId ? array($inputCatId) : array(), 'array');

        return JemFrontendAccess::canAdd($user, 'event', $categoryIds);
    }

    /**
     * Method override to check if you can edit an existing record.
     *
     * @param  array  $data An array of input data.
     * @param  string $key  The name of the key for the primary key.
     *
     * @return boolean
     */
    protected function allowEdit($data = array(), $key = 'id') {
        // Initialise variables.
        $recordId = (int) ($data[$key] ?? 0);
        $user     = JemFactory::getUser();

        if ($recordId < 1) {
            return false;
        }

        // Never authorise with submitted ownership or access fields.
        $record = $this->getModel()->getItem($recordId);

        return JemFrontendAccess::canEdit($user, 'event', $record);
    }

    /**
     * Method to cancel an edit.
     *
     * @param  string $key The name of the primary key of the URL variable.
     *
     * @return boolean True if access level checks pass, false otherwise.
     */
    public function cancel($key = 'a_id') {
        $this->checkToken();

        if (!$this->requireFrontendUser()) {
            return false;
        }

        $recordId = $this->getFrontendRecordId();

        if ($recordId > 0) {
            $item = $this->getFrontendItemOrFail($recordId, 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND');
            $this->assertFrontendCanEdit('event', $item);
        } else {
            $data = Factory::getApplication()->input->post->get('jform', array(), 'array');
            $categories = !empty($data['cats'])
                ? (array) $data['cats']
                : array_filter(array(Factory::getApplication()->input->getInt('catid', 0)));
            $this->assertFrontendCanAdd('event', $categories);
        }

        $result = parent::cancel($key);

        // Redirect to the return page.
        $this->setRedirect($this->getReturnPage());

        return $result;
    }

    /**
     * Method to edit an existing record.
     *
     * @param  string $key    The name of the primary key of the URL variable.
     * @param  string $urlVar The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
     *
     * @return boolean True if access level check and checkout passes, false otherwise.
     */
    public function edit($key = null, $urlVar = 'a_id') {
        if (!$this->requireFrontendUser()) {
            return false;
        }

        $recordId = $this->getFrontendRecordId(true);
        $item = $this->getFrontendItemOrFail($recordId, 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND');
        $this->assertFrontendCanEdit('event', $item);

        $result = parent::edit($key, $urlVar);

        if (!$result) {
            // A checkout conflict must not redirect into an unheld editor form.
            $this->_id = $recordId;
            $this->setRedirect($this->getReturnPage());
        }

        return $result;
    }

    /**
     * Method to add a new record based on existing record.
     *
     * @return boolean True if the event can be added, false if not.
     */
    public function copy() {
        if (!$this->requireFrontendUser()) {
            return false;
        }

        $this->assertFrontendCanAdd('event', Factory::getApplication()->input->getInt('catid', 0));
        $sourceId = $this->getFrontendRecordId(true);
        $source = $this->getFrontendItemOrFail($sourceId, 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND');
        $this->assertFrontendCanEdit('event', $source);

        return parent::add();
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param  string $name   The model name. Optional.
     * @param  string $prefix The class prefix. Optional.
     * @param  array  $config Configuration array for model. Optional.
     *
     * @return object The model.
     */
    public function getModel($name = 'editevent', $prefix = '', $config = array('ignore_request' => true)) {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Gets the URL arguments to append to an item redirect.
     *
     * @param  int    $recordId The primary key id for the item.
     * @param  string $urlVar   The name of the URL variable for the id.
     *
     * @return string The arguments to append to the redirect URL.
     */
    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'a_id') {
        // Need to override the parent method completely.
        $jinput = Factory::getApplication()->input;
        $tmpl   = $jinput->getCmd('tmpl', '');
        $layout = $jinput->getCmd('layout', 'edit'); 
        $task   = $jinput->getCmd('task', '');
        $append = '';

        // Setup redirect info.
        if ($tmpl) {
            $append .= '&tmpl=' . $tmpl;
        }

        $append .= '&layout=edit';

        if ($recordId) {
            $append .= '&' . $urlVar . '=' . $recordId;
        } elseif (($task === 'copy') && ($fromId = $jinput->getInt('a_id', 0))) {
            $append .= '&from_id=' . $fromId;
        }

        $itemId = $jinput->getInt('Itemid', 0);
        $catId  = $jinput->getInt('catid', 0);
        $locId  = $jinput->getInt('locid', 0);
        $date   = $jinput->getCmd('date', '');
        $return = $this->getReturnPage();

        if ($itemId) {
            $append .= '&Itemid=' . $itemId;
        }

        if ($catId) {
            $append .= '&catid=' . $catId;
        }

        if ($locId) {
            $append .= '&locid=' . $locId;
        }

        if ($date) {
            $append .= '&date=' . $date;
        }

        if ($return) {
            $append .= '&return=' . base64_encode($return);
        }

        return $append;
    }

    /**
     * Get the return URL.
     *
     * If a "return" variable has been passed in the request
     *
     * @return string The return URL.
     */
    protected function getReturnPage() {
        $uri    = Uri::getInstance();
        $return = Factory::getApplication()->input->get('return', null, 'base64');
        $decodedReturn = $return ? base64_decode($return, true) : false;

        if (empty($decodedReturn) || !Uri::isInternal($decodedReturn)) {
            if (!empty($this->_id)) {
                return Route::_(JemHelperRoute::getEventRoute($this->_id));
            }
            return $uri->base();
        } else {
            return $decodedReturn;
        }
    }

    /**
     * Function that allows child controller access to model data
     * after the data has been saved.
     * Here used to trigger the jem plugins, mainly the mailer.
     *
     * @param  object          $model      The data model object.
     * @param  array           $validData  The validated data.
     *
     * @return void
     */
    protected function _postSaveHook($model, $validData = array()) {
        $modelName = method_exists($model, 'getName') ? $model->getName() : 'editevent';
        $eventId   = (int) $model->getState('event.id', 0);

        if (!$eventId) {
            $eventId = (int) $model->getState($modelName . '.id', 0);
        }

        if (!$eventId && !empty($validData['id'])) {
            $eventId = (int) $validData['id'];
        }

        if ($eventId > 0) {
            JemHelper::reconcileWaitingList($eventId, array('source' => 'site.event.save'));
        }

        $task = $this->getTask();
        if ($task == 'save') {
            $isNew     = $model->getState('editevent.new');
            $this->_id = $eventId;

            // trigger all jem plugins
            PluginHelper::importPlugin('jem');
            $dispatcher = JemFactory::getDispatcher();
            $dispatcher->triggerEvent('onEventEdited', array($this->_id, $isNew));

            // but show warning if mailer is disabled
            if (!PluginHelper::isEnabled('jem', 'mailer')) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_GLOBAL_MAILERPLUGIN_DISABLED'), 'notice');
            }
        }
    }

    /**
     * Method to save a record.
     *
     * @param  string $key    The name of the primary key of the URL variable.
     * @param  string $urlVar The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
     *
     * @return boolean True if successful, false otherwise.
     */
    public function save($key = null, $urlVar = 'a_id') {
        // Use Joomla's translated token failure and safe referrer redirect.
        $this->checkToken();

        if (!$this->requireFrontendUser()) {
            return false;
        }

        $recordId = $this->getFrontendRecordId();

        if ($recordId > 0) {
            $item = $this->getFrontendItemOrFail($recordId, 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND');
            $this->assertFrontendCanEdit('event', $item);
        } else {
            $data = Factory::getApplication()->input->post->get('jform', array(), 'array');
            $categories = !empty($data['cats'])
                ? (array) $data['cats']
                : array_filter(array(Factory::getApplication()->input->getInt('catid', 0)));
            $this->assertFrontendCanAdd('event', $categories);
        }

        $result = parent::save($key, $urlVar);

        // If ok, redirect to the return page.
        if ($result) {
            $model = $this->getModel();

            if ($this->handleCreatedArticleContentRedirect($model)) {
                return $result;
            }

            $this->handleAssociatedArticleSyncNotice($model);
            $this->setRedirect($this->getReturnPage());
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
        $this->checkToken();

        if (!$this->requireFrontendUser()) {
            return false;
        }

        $app = Factory::getApplication();
        $input = $app->input->post;
        $id = JemFrontendAccess::normaliseRecordId($input, true);
        $item = $this->getFrontendItemOrFail($id, 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND');
        $this->assertFrontendCanEdit('event', $item);
        $fields = $input->getString('fields', '');
        $return = $input->getBase64('return', '');
        $model = $this->getModel();
        $redirect = $this->getReturnPage();

        if ($return) {
            $decodedReturn = base64_decode($return, true);

            if ($decodedReturn && Uri::isInternal($decodedReturn)) {
                $redirect = $decodedReturn;
            }
        }

        if ($id && $model && $model->updateAssociatedArticleFromEvent($id, $fields)) {
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
     * Create a Joomla article from the front-end selector modal and return it to
     * the event form as the selected associated article.
     *
     * @return  void
     */
    public function createAssociatedArticle()
    {
        $this->checkToken();

        if (!$this->requireFrontendUser()) {
            return false;
        }

        $app      = Factory::getApplication();
        $input    = $app->input;
        $function = preg_replace('/[^A-Za-z0-9_]/', '', $input->getCmd('function', 'jSelectArticle'));
        $title    = $input->getString('article_title', '');
        $targetId = $input->getInt('article_catid', 0);
        $jemcats  = array_values(array_filter(array_map('intval', explode(',', (string) $input->getString('jemcats', '')))));
        $model    = $this->getModel();

        if (!$model || !JemFrontendAccess::canUseEventSelectors(
            $app,
            JemFactory::getUser(),
            $model,
            $this->getFrontendRecordId()
        )) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $article  = $model ? $model->createAssociatedArticlePlaceholder($title, $targetId, $jemcats) : array();

        $app->setHeader('Content-Type', 'text/html; charset=utf-8', true);

        if (!empty($article['id']) && $function !== '') {
            echo '<!doctype html><html><body><script>';
            echo 'var fn = ' . json_encode($function) . ';';
            echo 'if (window.parent && typeof window.parent[fn] === "function") {';
            echo 'window.parent[fn](' . (int) $article['id'] . ', ' . json_encode((string) $article['title']) . ');';
            echo '}';
            echo '</script></body></html>';
            $app->close();
        }

        $message = $model && $model->getError() ? $model->getError() : Text::_('COM_JEM_EVENT_ARTICLE_CREATE_FAILED');
        echo '<!doctype html><html><body style="font-family: sans-serif; padding: 1rem;">';
        echo '<div class="alert alert-danger" style="border:1px solid #c52827;color:#842029;background:#f8d7da;padding:.75rem 1rem;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
        echo '<button type="button" onclick="history.back();" style="padding:.45rem .75rem;">' . Text::_('JPREVIOUS') . '</button>';
        echo '</body></html>';
        $app->close();
    }

    /**
     * Notify or redirect after an empty event-content article is created.
     *
     * @param   object  $model  Event model.
     *
     * @return  boolean  True when a redirect was set.
     */
    protected function handleCreatedArticleContentRedirect($model)
    {
        $articleId = $model ? (int) $model->getState('event.article_content_article_id', 0) : 0;

        if (!$articleId || !(bool) $model->getState('event.article_content_empty', false)) {
            return false;
        }

        $return = base64_encode($this->getReturnPage());
        $editUrl = Route::_('index.php?option=com_content&task=article.edit&a_id=' . $articleId . '&return=' . $return . '&' . Session::getFormToken() . '=1', false);
        $action = (string) $model->getState('event.article_content_create_action', 'copy_description');

        if ($action === 'empty_edit') {
            $this->setRedirect($editUrl, Text::_('COM_JEM_EVENT_ARTICLE_CONTENT_EMPTY_EDIT'), 'notice');

            return true;
        }

        Factory::getApplication()->enqueueMessage(
            Text::_('COM_JEM_EVENT_ARTICLE_CONTENT_EMPTY_EDIT') . ' <a href="' . $this->escapeHtmlAttribute($editUrl) . '">' . Text::_('COM_JEM_EVENT_ARTICLE_CONTENT_EDIT_LINK') . '</a>',
            'notice'
        );

        return false;
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
        $eventId = $model ? (int) $model->getState('event.article_sync_event_id', 0) : 0;
        $fields = $model ? (string) $model->getState('event.article_sync_fields', '') : '';
        $labels = $model ? (string) $model->getState('event.article_sync_labels', '') : '';

        if (!$eventId || $fields === '') {
            return;
        }

        $return = base64_encode($this->getReturnPage());
        $token = Session::getFormToken();
        $updateUrl = Route::_('index.php?option=com_jem', false);
        $dismissUrl = $this->getReturnPage();
        $message = Text::sprintf('COM_JEM_EVENT_ARTICLE_SYNC_NOTICE', htmlspecialchars($labels, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false))
            . ' <form class="d-inline" method="post" action="' . $this->escapeHtmlAttribute($updateUrl) . '">'
            . '<input type="hidden" name="task" value="event.updateAssociatedArticle">'
            . '<input type="hidden" name="a_id" value="' . $eventId . '">'
            . '<input type="hidden" name="fields" value="' . $this->escapeHtmlAttribute($fields) . '">'
            . '<input type="hidden" name="return" value="' . $this->escapeHtmlAttribute($return) . '">'
            . '<input type="hidden" name="' . $this->escapeHtmlAttribute($token) . '" value="1">'
            . '<button class="btn btn-sm btn-primary" type="submit">' . Text::_('COM_JEM_EVENT_ARTICLE_SYNC_UPDATE') . '</button>'
            . '</form>'
            . ' <a class="btn btn-sm btn-secondary" href="' . $this->escapeHtmlAttribute($dismissUrl) . '">' . Text::_('COM_JEM_EVENT_ARTICLE_SYNC_DISMISS') . '</a>';

        Factory::getApplication()->enqueueMessage($message, 'notice');
    }

    /**
     * Saves the registration to the database
     */
    public function userregister() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');

        $app = Factory::getApplication();
        $input = $app->getInput();
        $id    = $input->getInt('rdid', 0);
        $rid   = $input->getInt('regid', 0);

        // Get the model
        $model = $this->getModel('Event', 'JemModel');

        $reg = $model->getUserRegistration($id);
        if ($reg !== false && isset($reg->id) && $reg->id != $rid) {
            $msg = Text::_('COM_JEM_ALREADY_REGISTERED') . ' [id: ' . $reg->id . ']';
            $this->setRedirect(Route::_(JemHelperRoute::getEventRoute($id), false), $msg, 'error');
            $this->redirect();
            return;
        }

        $model->setId($id);
        $register_id = $model->userregister();

        if (!$register_id) {
            $msg = $model->getError();
            $this->setRedirect(Route::_(JemHelperRoute::getEventRoute($id), false), $msg, 'error');
            $this->redirect();
            return;
        }

        JemHelper::updateWaitingList($id);

        PluginHelper::importPlugin('jem');
        PluginHelper::importPlugin('actionlog', 'jem');
        $dispatcher = JemFactory::getDispatcher();
        $updatedRegistration = $model->getUserRegistration($id);
        $transition = JemRegistrationTransition::create(
            $reg ?: null,
            $updatedRegistration,
            (int) Factory::getApplication()->getIdentity()->id,
            'site.event.registration_response'
        );
        JemRegistrationTransition::dispatchStatusMail($dispatcher, $updatedRegistration, $transition, false, true);
        JemRegistrationTransition::dispatchAudit($dispatcher, array($transition));

        $cache = Factory::getCache('com_jem');
        $cache->clean();

        $msg = Text::_('COM_JEM_REGISTRATION_THANKS_FOR_RESPONSE');

        $this->setRedirect(Route::_(JemHelperRoute::getEventRoute($id), false), $msg);
    }

    /**
     * Deletes a registered user
     */
    public function delreguser() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid Token');

        $id = Factory::getApplication()->input->getInt('rdid', 0);

        // Get/Create the model
        $model = $this->getModel('Event', 'JemModel');

        $model->setId($id);
        $registration = $model->getUserRegistration($id);

        if (!$registration || !$model->delreguser()) {
            $msg = $model->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED');
            $this->setRedirect(Route::_(JemHelperRoute::getEventRoute($id), false), $msg, 'error');
            return;
        }

        JemHelper::updateWaitingList($id);

        PluginHelper::importPlugin('jem');
        PluginHelper::importPlugin('actionlog', 'jem');
        $dispatcher = JemFactory::getDispatcher();
        JemRegistrationTransition::dispatchDeletionMail($dispatcher, $registration);
        $dispatcher->triggerEvent('onJemAfterAttendeeDelete', array($registration));

        $cache = Factory::getCache('com_jem');
        $cache->clean();

        $msg = Text::_('COM_JEM_UNREGISTERED_SUCCESSFULL');
        $this->setRedirect(Route::_(JemHelperRoute::getEventRoute($id), false), $msg);
    }
}
