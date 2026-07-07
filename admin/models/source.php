<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * Source Model
 */
class JemModelSource extends AdminModel
{
    /**
     * Constructor
     */
    public function __construct($config = array(), $factory = null)
    {
        parent::__construct($config, $factory);
        
        // Set the dispatcher for Joomla 6 compatibility
        if (method_exists($this, 'setDispatcher')) {
            $this->setDispatcher(Factory::getApplication()->getDispatcher());
        }
    }

    /**
     * Cache for the template information.
     *
     * @var        object
     */
    private $_template = null;

    protected $jemVersion = null;

    protected function logCssOperation($message, $level = Log::INFO)
    {
        if (class_exists('JemHelper')) {
            JemHelper::addLogEntry($message, __METHOD__, $level);
        }
    }

    /**
     * Resolve and validate a CSS source path inside the allowed JEM media folders.
     *
     * @param   string  $fileName  Stored source identifier.
     *
     * @return  object|false
     */
    protected function resolveSourceFile($fileName)
    {
        if (empty($fileName) || !is_string($fileName)) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            return false;
        }

        $custom = stripos($fileName, 'custom#:') === 0;
        $file   = $custom ? substr($fileName, strlen('custom#:')) : $fileName;

        if ($file === '' || $file !== InputFilter::getInstance()->clean($file, 'path')) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            return false;
        }

        $basePath = Path::clean(JPATH_ROOT . '/media/com_jem/css' . ($custom ? '/custom' : ''));
        $filePath = Path::clean($basePath . '/' . $file);
        $baseCheck = rtrim(strtolower($basePath), '\\/') . DIRECTORY_SEPARATOR;
        $fileCheck = strtolower($filePath);

        if (strpos($fileCheck, $baseCheck) !== 0 || preg_match('#(^|[\\\\/])\\.\\.([\\\\/]|$)#', $file)) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            return false;
        }

        return (object) array(
            'custom' => $custom,
            'file'   => $file,
            'path'   => $filePath,
        );
    }

    protected function getJemVersion()
    {
        if ($this->jemVersion !== null) {
            return $this->jemVersion;
        }

        $this->jemVersion = '';
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));

        $db->setQuery($query);
        $manifest = json_decode((string) $db->loadResult(), true);

        if (is_array($manifest) && !empty($manifest['version'])) {
            $this->jemVersion = (string) $manifest['version'];
        }

        return $this->jemVersion;
    }

    protected function getCssDefinitions()
    {
        return array(
            'backend.css' => array('use' => 'css_backend_usecustom', 'file' => 'css_backend_customfile', 'label' => 'backend.css'),
            'backend-responsive.css' => array('use' => 'css_backend_responsive_usecustom', 'file' => 'css_backend_responsive_customfile', 'fallbackUse' => 'css_backend_usecustom', 'label' => 'backend-responsive.css'),
            'calendar.css' => array('use' => 'css_calendar_usecustom', 'file' => 'css_calendar_customfile', 'label' => 'calendar.css'),
            'calendar-responsive.css' => array('use' => 'css_calendar_responsive_usecustom', 'file' => 'css_calendar_responsive_customfile', 'fallbackUse' => 'css_calendar_usecustom', 'label' => 'calendar-responsive.css'),
            'colorpicker.css' => array('use' => 'css_colorpicker_usecustom', 'file' => 'css_colorpicker_customfile', 'label' => 'colorpicker.css'),
            'colorpicker-responsive.css' => array('use' => 'css_colorpicker_responsive_usecustom', 'file' => 'css_colorpicker_responsive_customfile', 'fallbackUse' => 'css_colorpicker_usecustom', 'label' => 'colorpicker-responsive.css'),
            'geostyle.css' => array('use' => 'css_geostyle_usecustom', 'file' => 'css_geostyle_customfile', 'label' => 'geostyle.css'),
            'geostyle-responsive.css' => array('use' => 'css_geostyle_responsive_usecustom', 'file' => 'css_geostyle_responsive_customfile', 'fallbackUse' => 'css_geostyle_usecustom', 'label' => 'geostyle-responsive.css'),
            'googlemap.css' => array('use' => 'css_googlemap_usecustom', 'file' => 'css_googlemap_customfile', 'label' => 'googlemap.css'),
            'googlemap-responsive.css' => array('use' => 'css_googlemap_responsive_usecustom', 'file' => 'css_googlemap_responsive_customfile', 'fallbackUse' => 'css_googlemap_usecustom', 'label' => 'googlemap-responsive.css'),
            'jem.css' => array('use' => 'css_jem_usecustom', 'file' => 'css_jem_customfile', 'label' => 'jem.css'),
            'jem-responsive.css' => array('use' => 'css_jem_responsive_usecustom', 'file' => 'css_jem_responsive_customfile', 'fallbackUse' => 'css_jem_usecustom', 'label' => 'jem-responsive.css'),
            'print.css' => array('use' => 'css_print_usecustom', 'file' => 'css_print_customfile', 'label' => 'print.css'),
            'print-responsive.css' => array('use' => 'css_print_responsive_usecustom', 'file' => 'css_print_responsive_customfile', 'fallbackUse' => 'css_print_usecustom', 'label' => 'print-responsive.css'),
        );
    }

    protected function getUserCssDefinitions()
    {
        return array(
            'jem-user-front.css' => array(
                'scope' => Text::_('COM_JEM_CSSMANAGER_USER_FILE_SCOPE_FRONT'),
            ),
            'jem-user-module.css' => array(
                'scope' => Text::_('COM_JEM_CSSMANAGER_USER_FILE_SCOPE_MODULE'),
            ),
        );
    }

    protected function getActiveCustomCssMap($settings)
    {
        $active = array();

        foreach ($this->getCssDefinitions() as $defaultFile => $definition) {
            $useCustom = (int) $settings->get($definition['use'], 0) === 1;

            if (!$useCustom && !empty($definition['fallbackUse'])) {
                $useCustom = (int) $settings->get($definition['fallbackUse'], 0) === 1;
            }

            if (!$useCustom) {
                continue;
            }

            $customFile = trim((string) $settings->get($definition['file'], ''));
            $customFile = $customFile ?: $defaultFile;

            if (!isset($active[$customFile])) {
                $active[$customFile] = array();
            }

            $active[$customFile][] = $definition['label'];
        }

        return $active;
    }

    protected function readCustomHeaderValue($contents, $field)
    {
        if ($contents === false || $contents === '') {
            return '';
        }

        if (preg_match('/JEM custom ' . preg_quote($field, '/') . ':\s*([^\r\n*]+)/', $contents, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    public function getSourceDetails()
    {
        $fileName = $this->getState('filename');
        $source = $this->resolveSourceFile($fileName);

        if (!$source || !is_file($source->path)) {
            return false;
        }

        $details = new stdClass;
        $details->filename = $source->file;
        $details->custom = $source->custom;
        $details->size = filesize($source->path);
        $details->modified = filemtime($source->path);
        $details->created = $source->custom ? filectime($source->path) : 0;
        $details->version = $source->custom ? '' : $this->getJemVersion();
        $details->sourceFile = '';
        $details->sourceVersion = '';
        $details->sourceSize = 0;
        $details->active = false;
        $details->usedBy = array();
        $details->userOverride = isset($this->getUserCssDefinitions()[$source->file]);
        $details->scope = $details->userOverride ? $this->getUserCssDefinitions()[$source->file]['scope'] : '';
        $details->id = base64_encode(($source->custom ? 'custom#:' : '') . $source->file);

        if ($source->custom) {
            $contents = file_get_contents($source->path);
            $details->sourceFile = $this->readCustomHeaderValue($contents, 'source');
            $details->sourceVersion = $this->readCustomHeaderValue($contents, 'source version');
            $sourceSize = $this->readCustomHeaderValue($contents, 'source size');

            if (preg_match('/^\d+/', $sourceSize, $matches)) {
                $details->sourceSize = (int) $matches[0];
            }

            $activeCustom = $this->getActiveCustomCssMap(JemHelper::retrieveCss());
            if (isset($activeCustom[$source->file])) {
                $details->active = true;
                $details->usedBy = $activeCustom[$source->file];
            }
        }

        return $details;
    }

    /**
     * Method to auto-populate the model state.
     *
     * @Note Calling getState in this method will result in recursion.
     */
    protected function populateState()
    {
        $app = Factory::getApplication('administrator');

        // Load the User state.
        $id = $app->getUserState('com_jem.edit.source.id');

        // Parse the template id out of the compound reference.
        $temp = $id ? base64_decode($id, true) : $id;
        $fileName = $temp;

        if(!empty($fileName))
        {
            $this->setState('filename', $fileName);

            // Save the syntax for later use
            $app->setUserState('editor.source.syntax', File::getExt($fileName));
        }
        // Load the parameters.
        $params    = ComponentHelper::getParams('com_jem');
        $this->setState('params', $params);
    }

    /**
     * Method to get the record form.
     *
     * @param  array   $data     Data for the form.
     * @param  boolean $loadData True if the form is to load its own data (default case), false if not.
     * @return JForm   A JForm object on success, false on failure
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Initialise variables.
        $app = Factory::getApplication();

        // Codemirror or Editor None should be enabled
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('COUNT(*)');
        $query->from('#__extensions as a');
        $query->where('((a.name ='.$db->quote('plg_editors_codemirror').' AND a.enabled = 1) OR (a.name ='.$db->quote('plg_editors_none').' AND a.enabled = 1))');
        $db->setQuery($query);
        $state = $db->loadResult();
        if ((int)$state < 1 ) {
            $app->enqueueMessage(Text::_('COM_JEM_CSSMANAGER_ERROR_EDITOR_DISABLED'), 'warning');
        }

        // Get the form.
        $form = $this->loadForm('com_jem.source', 'source', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed The data for the form.
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jem.edit.source.data', array());

        if (empty($data)) {
            $data = $this->getSource();
        }

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @return mixed Object on success, false on failure.
     */
    public function getSource()
    {
        $fileName = $this->getState('filename');
        $source = $this->resolveSourceFile($fileName);

        $item = new stdClass;
        if($source && file_exists($source->path)){
            if ($source->file) {
                $item->custom   = $source->custom;
                $item->filename = $source->file;
                $item->source   = file_get_contents($source->path);
            } else {
                $item->custom   = false;
                $item->filename = false;
                $item->source   = false;
            }
        }else{
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
        }

        return $item;
    }

    /**
     * Method to store the source file contents.
     *
     * @param  array   The souce data to save.
     *
     * @return boolean True on success, false otherwise and internal error set.
     */
    public function save($data)
    {
        $dispatcher = JemFactory::getDispatcher();
        $fileName   = $this->getState('filename');
        $source     = $this->resolveSourceFile($fileName);

        if (!$source) {
            return false;
        }

        $file = $source->file;
        $filePath = $source->path;

        // Include the extension plugins for the save events.
        PluginHelper::importPlugin('extension');

        // Set FTP credentials, if given.
        ClientHelper::setCredentialsFromRequest('ftp');
        $ftp = ClientHelper::getCredentials('ftp');

        // Trigger the onExtensionBeforeSave event.
        $result = $dispatcher->triggerEvent('onExtensionBeforeSave', array('com_jem.source', (object)$data, false));
        if (in_array(false, $result, true)) {
            $this->setError(Text::sprintf('COM_JEM_CSSMANAGER_ERROR_FAILED_TO_SAVE_FILENAME', $file));
            return false;
        }

        // Try to make the template file writeable.
        if (!$ftp['enabled'] && Path::isOwner($filePath) && !Path::setPermissions($filePath, '0644')) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_WRITABLE'));
            return false;
        }

        $return = File::write($filePath, $data['source']);
        // But report save error with higher priority
        if (!$return) {
            $this->setError(Text::sprintf('COM_JEM_CSSMANAGER_ERROR_FAILED_TO_SAVE_FILENAME', $file));
            $this->logCssOperation('CSS file save failed: "' . $file . '"', Log::WARNING);
            return false;
        }

        // Try to make the custom template file read-only again.
        if (!$ftp['enabled'] && Path::isOwner($filePath) && !Path::setPermissions($filePath, '0444')) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_UNWRITABLE'));
            $this->logCssOperation('CSS file saved but permissions could not be restored: "' . $file . '"', Log::WARNING);
            return false;
        }

        $user = Factory::getApplication()->getIdentity();
        $userInfo = $user && (int) $user->id > 0 ? $user->name . ' (#' . (int) $user->id . ')' : 'Unknown';
        $this->logCssOperation('CSS file saved: "' . $file . '", custom "' . ($source->custom ? 'yes' : 'no') . '", user "' . $userInfo . '"', Log::INFO);

        return true;
    }
}
