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
        }

        return $result;
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
            Text::_('COM_JEM_EVENT_ARTICLE_CONTENT_EMPTY_EDIT') . ' <a href="' . $editUrl . '">' . Text::_('COM_JEM_EVENT_ARTICLE_CONTENT_EDIT_LINK') . '</a>',
            'notice'
        );
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
