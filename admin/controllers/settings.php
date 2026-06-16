<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

require_once JPATH_SITE . '/components/com_jem/classes/log.class.php';

/**
 * JEM Component Settings Controller
 */
class JemControllerSettings extends BaseController
{

    public function __construct($config = array()) {
        parent::__construct($config);

        // Map the apply task to the save method.
        $this->registerTask('apply', 'save');
    }

    /**
     * Method to check if you can add a new record.
     *
     * @return boolean
     */
    protected function allowEdit() {
        return JemFactory::getUser()->authorise('core.manage', 'com_jem');
    }

    /**
     * Method to check if you can save a new or existing record.
     *
     * @return boolean
     */
    protected function allowSave() {
        return $this->allowEdit();
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param  string  The model name. Optional.
     * @param  string  The class prefix. Optional.
     * @param  array   Configuration data for model. Optional.
     *
     * @return object  The model.
     */
    public function getModel($name = 'Settings', $prefix = 'JemModel', $config = array()) {
        $model = parent::getModel($name, $prefix, $config);
        return $model;
    }

    /**
     * Method to save the configuration data.
     *
     * @param  array  An array containing all global config data.
     * @return bool   True on success, false on failure.
     * @since 1.6
     */
    public function save() {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Initialise variables.
        $app = Factory::getApplication();
        $data = $app->input->get('jform', array(), 'array');

        $task = $this->getTask();
        $model = $this->getModel();
        $context = 'com_jem.edit.settings';

        // Access check.
        if (!$this->allowSave()) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_SAVE_NOT_PERMITTED'), 'warning');
        }

        // Validate the posted data.
        $form = $model->getForm();
        if (!$form) {
            Factory::getApplication()->enqueueMessage($model->getError(), 'error');
            return false;
        }

        // Validate the posted data.
        $form = $model->getForm();
        $data = $model->validate($form, $data);

        // Check for validation errors.
        if ($data === false) {
            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof Exception) {
                    $app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $app->enqueueMessage($errors[$i], 'warning');
                }
            }

            // Save the data in the session.
            $app->setUserState($context . '.data', $data);

            // Redirect back to the edit screen.
            $this->setRedirect(Route::_('index.php?option=com_jem&view=settings', false));
            return false;
        }

        // Attempt to save the data.
        if (!$model->store($data)) {
            // Save the data in the session.
            $app->setUserState($context . '.data', $data);

            // Redirect back to the edit screen.
            $this->setMessage(Text::sprintf('JERROR_SAVE_FAILED', $model->getError()), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jem&view=settings', false));
            return false;
        }

        $this->setMessage(Text::_('COM_JEM_SETTINGS_SAVED'));

        // Redirect the user and adjust session state based on the chosen task.
        switch ($task) {
            case 'apply':
                // Reset the record data in the session.
                $app->setUserState($context . '.data', null);

                // Redirect back to the edit screen.
                $this->setRedirect(Route::_('index.php?option=com_jem&view=settings', false));
                break;

            default:
                // Clear the record id and data from the session.
                $app->setUserState($context . '.id', null);
                $app->setUserState($context . '.data', null);

                // Redirect to the list screen.
                $this->setRedirect(Route::_('index.php?option=com_jem&view=main', false));
                break;
        }
    }

    /**
     * Cancel operation
     */
    public function cancel() {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Check if the user is authorized to do this.
        if (!JemFactory::getUser()->authorise('core.admin', 'com_jem')) {
            Factory::getApplication()->redirect('index.php', Text::_('JERROR_ALERTNOAUTHOR'));
            return;
        }

        $this->setRedirect('index.php?option=com_jem');
    }

    /**
     * Load the built-in example custom field configuration.
     */
    public function loadExampleCustomFields() {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        if (!$this->allowSave()) {
            $this->setMessage(Text::_('JERROR_SAVE_NOT_PERMITTED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jem&view=settings', false));

            return false;
        }

        $model = $this->getModel();

        if (!$model->loadExampleCustomFields()) {
            $this->setMessage(Text::sprintf('JERROR_SAVE_FAILED', $model->getError()), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jem&view=settings', false));

            return false;
        }

        $this->setMessage(Text::_('COM_JEM_CUSTOM_FIELDS_EXAMPLE_LOADED'));
        $this->setRedirect(Route::_('index.php?option=com_jem&view=settings', false));

        return true;
    }

    /**
     * Display a known JEM log file.
     *
     * @return bool
     */
    public function viewLog()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $log = $this->getKnownLogFile();
        $content = $this->readLogTail($log['path']);

        if ($content === '') {
            $content = Text::_('COM_JEM_CONFIGINFO_LOG_EMPTY');
        }

        $app->setHeader('Content-Type', 'text/html; charset=utf-8', true);

        echo '<!doctype html><html><head><meta charset="utf-8"><title>'
            . htmlspecialchars($log['name'], ENT_QUOTES, 'UTF-8')
            . '</title><style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:1rem;}h1{font-size:1.25rem;margin:0 0 1rem;}pre{white-space:pre-wrap;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:.9rem;line-height:1.45;background:#f6f8fa;border:1px solid #d8dee4;padding:1rem;}</style></head><body><h1>'
            . htmlspecialchars($log['name'], ENT_QUOTES, 'UTF-8')
            . '</h1><pre>'
            . htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
            . '</pre></body></html>';

        $app->close();

        return true;
    }

    /**
     * Download a known JEM log file.
     *
     * @return bool
     */
    public function downloadLog()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        $log = $this->getKnownLogFile();
        $app = Factory::getApplication();

        if (!is_file($log['path']) || !is_readable($log['path'])) {
            throw new \Exception(Text::_('COM_JEM_CONFIGINFO_LOG_EMPTY'), 404);
        }

        $app->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . basename($log['name']) . '"', true);
        $app->setHeader('Content-Length', (string) filesize($log['path']), true);

        readfile($log['path']);
        $app->close();

        return true;
    }

    /**
     * Resolve a request key to a known JEM log file.
     *
     * @return array
     */
    protected function getKnownLogFile()
    {
        if (!$this->allowEdit()) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $app = Factory::getApplication();
        $key = $app->input->getCmd('log', '');
        $files = JemLog::getLogFiles();

        if (!isset($files[$key])) {
            throw new \Exception(Text::_('COM_JEM_CONFIGINFO_LOG_INVALID'), 400);
        }

        $logPath = rtrim($app->get('log_path', JPATH_ADMINISTRATOR . '/logs'), '/\\');

        return array(
            'name' => $files[$key],
            'path' => $logPath . DIRECTORY_SEPARATOR . $files[$key],
        );
    }

    /**
     * Read the last part of a log file for backend preview.
     *
     * @param   string  $file  Absolute log file path.
     *
     * @return string
     */
    protected function readLogTail($file)
    {
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }

        $maxBytes = 250000;
        $size = filesize($file);
        $handle = fopen($file, 'rb');

        if (!$handle) {
            return '';
        }

        if ($size > $maxBytes) {
            fseek($handle, -$maxBytes, SEEK_END);
            fgets($handle);
        }

        $content = stream_get_contents($handle);
        fclose($handle);

        return $content ?: '';
    }

}
