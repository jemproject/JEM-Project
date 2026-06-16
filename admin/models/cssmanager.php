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
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\Path;

/**
 * Model-CSSManager
 */
class JemModelCssmanager extends BaseDatabaseModel
{
    protected $jemVersion = null;

    protected function logCssOperation($message, $level = Log::INFO)
    {
        if (class_exists('JemHelper')) {
            JemHelper::addLogEntry($message, __METHOD__, $level);
        }
    }

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
     * Internal method to get file properties.
     *
     * @param  string The base path.
     * @param  string The file name.
     * @return object
     */
    protected function getFile($path, $name)
    {
        $temp = new stdClass;

        $temp->name = $name;
        $temp->exists = file_exists($path.$name);
        $temp->id = base64_encode($name);
        $temp->custom = false;
        $temp->usedBy = array();
        $temp->active = false;
        $temp->size = 0;
        $temp->version = $this->getJemVersion();

        if ($temp->exists) {
            $temp->size = filesize($path . $name);
            $ext =  File::getExt($path.$name);
                if ($ext != 'css') {
                    # the file is valid but the extension not so let's return false
                    $temp->ext = false;
                } else {
                    $temp->ext = true;
                }
        }

        return $temp;
    }

    /**
     * Internal method to get file properties.
     *
     * @param  string The base path.
     * @param  string The file name.
     * @return object
     */
    protected function getCustomFile($path, $name)
    {
        $temp = new stdClass;
        $temp->name = $name;
        $temp->exists = file_exists($path.$name);
        $temp->custom = true;
        $temp->usedBy = array();
        $temp->active = false;
        $temp->sourceFile = '';
        $temp->sourceVersion = '';
        $temp->sourceSize = 0;
        $temp->userVersion = '';
        $temp->size = 0;
        $temp->created = 0;
        $temp->modified = 0;

        $filename = 'custom#:'.$name;
        $temp->id = base64_encode($filename);

        if ($temp->exists) {
            $temp->size = filesize($path . $name);
            $temp->created = filectime($path . $name);
            $temp->modified = filemtime($path . $name);
            $ext =  File::getExt($path.$name);
            if ($ext != 'css') {
                # the file is valid but the extension not so let's return false
                $temp->ext = false;
            } else {
                $temp->ext = true;
                $contents = file_get_contents($path . $name);

                if ($contents !== false && preg_match('/JEM custom source:\s*([A-Za-z0-9._-]+\.css)/', $contents, $matches)) {
                    $temp->sourceFile = $matches[1];
                }

                if ($contents !== false && preg_match('/JEM custom source version:\s*([^\r\n*]+)/', $contents, $matches)) {
                    $temp->sourceVersion = trim($matches[1]);
                }

                if ($contents !== false && preg_match('/JEM custom source size:\s*(\d+)/', $contents, $matches)) {
                    $temp->sourceSize = (int) $matches[1];
                }

                if ($contents !== false && preg_match('/JEM user override version:\s*([^\r\n*]+)/', $contents, $matches)) {
                    $temp->userVersion = trim($matches[1]);
                }
            }
        }

        return $temp;
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
                'version' => '0.0',
                'userVersion' => '1.0',
                'scope' => Text::_('COM_JEM_CSSMANAGER_USER_FILE_SCOPE_FRONT'),
                'description' => Text::_('COM_JEM_CSSMANAGER_USER_FILE_FRONT_DESC'),
            ),
            'jem-user-module.css' => array(
                'version' => '0.0',
                'userVersion' => '1.0',
                'scope' => Text::_('COM_JEM_CSSMANAGER_USER_FILE_SCOPE_MODULE'),
                'description' => Text::_('COM_JEM_CSSMANAGER_USER_FILE_MODULE_DESC'),
            ),
        );
    }

    protected function getUserCssHeader($file, $definition)
    {
        return "/*\n"
            . " * JEM user override stylesheet: " . $file . "\n"
            . " * JEM user override version: " . ($definition['userVersion'] ?? '1.0') . "\n"
            . " * JEM custom source version: " . $definition['version'] . "\n"
            . " * Scope: " . $definition['scope'] . "\n"
            . " * This file is loaded after the normal JEM stylesheets for its scope.\n"
            . " * Add small site-specific CSS overrides here.\n"
            . " */\n\n";
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

    public function copyCustomFile($file, $targetFile = '')
    {
        $file = (string) $file;
        $targetFile = trim((string) $targetFile);
        $targetFile = $targetFile !== '' ? $targetFile : $file;

        if ($file === '' || $file !== \Joomla\CMS\Filter\InputFilter::getInstance()->clean($file, 'path') || File::getExt($file) !== 'css') {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS custom copy rejected: invalid source file "' . $file . '"', Log::WARNING);
            return false;
        }

        if ($targetFile === '' || $targetFile !== \Joomla\CMS\Filter\InputFilter::getInstance()->clean($targetFile, 'path') || File::getExt($targetFile) !== 'css') {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS custom copy rejected: invalid target file "' . $targetFile . '" for source "' . $file . '"', Log::WARNING);
            return false;
        }

        $basePath = Path::clean(JPATH_ROOT . '/media/com_jem/css');
        $source = Path::clean($basePath . '/' . $file);
        $targetDir = Path::clean($basePath . '/custom');
        $target = Path::clean($targetDir . '/' . $targetFile);

        if (!is_file($source)) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS custom copy failed: source file not found "' . $file . '"', Log::WARNING);
            return false;
        }

        if (!is_dir($targetDir) && !Folder::create($targetDir)) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_CSS_FOLDER_NOT_FOUND'));
            $this->logCssOperation('CSS custom copy failed: custom CSS folder not available for "' . $targetFile . '"', Log::WARNING);
            return false;
        }

        if (is_file($target)) {
            $this->setError(Text::sprintf('COM_JEM_CSSMANAGER_CUSTOM_FILE_EXISTS', $targetFile));
            $this->logCssOperation('CSS custom copy skipped: target already exists "' . $targetFile . '" from source "' . $file . '"', Log::WARNING);
            return false;
        }

        $contents = file_get_contents($source);

        if ($contents === false) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS custom copy failed: unable to read source "' . $file . '"', Log::WARNING);
            return false;
        }

        $user = Factory::getApplication()->getIdentity();
        $userInfo = $user && (int) $user->id > 0 ? $user->name . ' (#' . (int) $user->id . ')' : 'Unknown';

        $header = "/*\n"
            . " * JEM custom source: " . $file . "\n"
            . " * JEM custom source version: " . $this->getJemVersion() . "\n"
            . " * JEM custom source size: " . filesize($source) . " bytes\n"
            . " * Created as a custom stylesheet. Edit this file instead of the standard CSS file.\n"
            . " */\n\n";

        $written = File::write($target, $header . $contents);

        if ($written) {
            $this->logCssOperation('CSS custom file created: source "' . $file . '", target "' . $targetFile . '", user "' . $userInfo . '"', Log::INFO);
        } else {
            $this->logCssOperation('CSS custom copy failed while writing target "' . $targetFile . '" from source "' . $file . '"', Log::WARNING);
        }

        return $written;
    }

    public function deleteCustomFile($file)
    {
        $file = (string) $file;

        if ($file === '' || $file !== \Joomla\CMS\Filter\InputFilter::getInstance()->clean($file, 'path') || File::getExt($file) !== 'css') {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS custom delete rejected: invalid custom file "' . $file . '"', Log::WARNING);
            return false;
        }

        $customDir = Path::clean(JPATH_ROOT . '/media/com_jem/css/custom');
        $target = Path::clean($customDir . '/' . $file);
        $activeCustom = $this->getActiveCustomCssMap(JemHelper::retrieveCss());

        if (isset($activeCustom[$file])) {
            $this->setError(Text::sprintf('COM_JEM_CSSMANAGER_CUSTOM_FILE_ASSIGNED', $file));
            $this->logCssOperation('CSS custom delete blocked: assigned custom file "' . $file . '"', Log::WARNING);
            return false;
        }

        if (!is_file($target)) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS custom delete failed: file not found "' . $file . '"', Log::WARNING);
            return false;
        }

        if (strpos($target, $customDir . DIRECTORY_SEPARATOR) !== 0 && strpos($target, $customDir . '/') !== 0) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS custom delete rejected: unsafe path "' . $file . '"', Log::WARNING);
            return false;
        }

        $deleted = File::delete($target);
        $user = Factory::getApplication()->getIdentity();
        $userInfo = $user && (int) $user->id > 0 ? $user->name . ' (#' . (int) $user->id . ')' : 'Unknown';

        if ($deleted) {
            $this->logCssOperation('CSS custom file deleted: "' . $file . '", user "' . $userInfo . '"', Log::INFO);
        } else {
            $this->logCssOperation('CSS custom delete failed while removing "' . $file . '", user "' . $userInfo . '"', Log::WARNING);
        }

        return $deleted;
    }

    public function getCustomDownloadFile($file)
    {
        $file = (string) $file;

        if ($file === '' || $file !== \Joomla\CMS\Filter\InputFilter::getInstance()->clean($file, 'path') || File::getExt($file) !== 'css') {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS custom download rejected: invalid file "' . $file . '"', Log::WARNING);
            return false;
        }

        $customDir = Path::clean(JPATH_ROOT . '/media/com_jem/css/custom');
        $path = Path::clean($customDir . '/' . $file);
        $baseCheck = rtrim(strtolower($customDir), '\\/') . DIRECTORY_SEPARATOR;
        $pathCheck = strtolower($path);

        if (strpos($pathCheck, $baseCheck) !== 0 || preg_match('#(^|[\\\\/])\\.\\.([\\\\/]|$)#', $file) || !is_file($path)) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS custom download failed: file not found "' . $file . '"', Log::WARNING);
            return false;
        }

        return (object) array(
            'name' => basename($file),
            'path' => $path,
            'size' => filesize($path),
        );
    }

    public function createUserCssFile($file)
    {
        $file = (string) $file;
        $definitions = $this->getUserCssDefinitions();

        if (!isset($definitions[$file])) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_SOURCE_FILE_NOT_FOUND'));
            $this->logCssOperation('CSS user override create rejected: invalid file "' . $file . '"', Log::WARNING);
            return false;
        }

        $customDir = Path::clean(JPATH_ROOT . '/media/com_jem/css/custom');
        $target = Path::clean($customDir . '/' . $file);

        if (!is_dir($customDir) && !Folder::create($customDir)) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_CSS_FOLDER_NOT_FOUND'));
            $this->logCssOperation('CSS user override create failed: custom CSS folder not available for "' . $file . '"', Log::WARNING);
            return false;
        }

        if (is_file($target)) {
            $this->setError(Text::sprintf('COM_JEM_CSSMANAGER_CUSTOM_FILE_EXISTS', $file));
            $this->logCssOperation('CSS user override create skipped: target already exists "' . $file . '"', Log::WARNING);
            return false;
        }

        $header = $this->getUserCssHeader($file, $definitions[$file]);

        $written = File::write($target, $header);
        $user = Factory::getApplication()->getIdentity();
        $userInfo = $user && (int) $user->id > 0 ? $user->name . ' (#' . (int) $user->id . ')' : 'Unknown';

        if ($written) {
            $this->logCssOperation('CSS user override file created: "' . $file . '", user "' . $userInfo . '"', Log::INFO);
        } else {
            $this->logCssOperation('CSS user override create failed while writing "' . $file . '", user "' . $userInfo . '"', Log::WARNING);
        }

        return $written;
    }

    /**
     * Method to get a list of all the files to edit in a template.
     *
     * @return array A nested array of relevant files.
     */
    public function getFiles()
    {
        // Initialise variables.
        $result = array();

        $path = Path::clean(JPATH_ROOT.'/media/com_jem/');

        // Check if the template path exists.
        if (!is_dir($path)) {
            $this->setError(Text::_('COM_JEM_CSSMANAGER_ERROR_CSS_FOLDER_NOT_FOUND'));
            return false;
        }

        $settings = JemHelper::retrieveCss();
        $activeCustom = $this->getActiveCustomCssMap($settings);

        // Handle the standard CSS files.
        $files = Folder::files($path . '/css', '\.css$', false, false);
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $file) {
            $item = $this->getFile($path . '/css/', $file);
            $item->usedBy = array($file);
            $result['css'][] = $item;
        }

        $customFiles = is_dir($path . 'css/custom') ? Folder::files($path . 'css/custom', '\.css$', false, false) : array();
        foreach (array_keys($activeCustom) as $activeFile) {
            if ($activeFile && !in_array($activeFile, $customFiles, true)) {
                $customFiles[] = $activeFile;
            }
        }
        sort($customFiles, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($customFiles as $cfile) {
            $rf = $this->getCustomFile($path.'css/custom/', $cfile);
            $rf->active = isset($activeCustom[$cfile]) && $rf->exists;
            $rf->usedBy = $activeCustom[$cfile] ?? array();

            if (isset($this->getUserCssDefinitions()[$cfile])) {
                continue;
            }

            $result['custom'][] = $rf;
        }

        foreach ($this->getUserCssDefinitions() as $userFile => $definition) {
            $uf = $this->getCustomFile($path . 'css/custom/', $userFile);
            $uf->definitionVersion = $definition['version'];
            $uf->definitionSize = strlen($this->getUserCssHeader($userFile, $definition));
            $uf->scope = $definition['scope'];
            $uf->description = $definition['description'];
            $uf->active = $uf->exists;
            if ($uf->exists && $uf->sourceVersion === '') {
                $uf->sourceVersion = $definition['version'];
            }
            if ($uf->exists && $uf->userVersion === '') {
                $uf->userVersion = $definition['userVersion'] ?? '1.0';
            }
            $result['usercss'][] = $uf;
        }

        return $result;
    }

    /**
     * Method to auto-populate the model state.
     *
     * @Note  Calling getState in this method will result in recursion.
     */
    protected function populateState()
    {
        $app = Factory::getApplication('administrator');

        // Load the parameters.
        $params = ComponentHelper::getParams('com_jem');
        $this->setState('params', $params);
    }

    /**
     * Detect if option linenumbers is enabled
     * plugin: codemirror
     */
    public function getStatusLinenumber()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('params');
        $query->from('#__extensions');
        $query->where(array("type = 'plugin'", "element = 'codemirror'"));
        $db->setQuery($query);
        $manifest = json_decode($db->loadResult(), true);
        return array_key_exists('linenumbers', $manifest) ? $manifest['linenumbers'] : false;
    }

    /**
     * Sets parameter values in the component's row of the extension table
     *
     * @param $param_array An array holding the params to store
     */
    public function setStatusLinenumber($status)
    {
        // read the existing component value(s)
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('params')
              ->from('#__extensions')
              ->where(array("type = 'plugin'", "element = 'codemirror'"));

        $db->setQuery($query);
        $params = json_decode($db->loadResult(), true);
        $params['linenumbers'] = $status;

        // store the combined new and existing values back as a JSON string
        $paramsString = json_encode($params);
        $query = $db->getQuery(true);
        $query->update('#__extensions')
              ->set('params = '.$db->quote($paramsString))
              ->where(array("type = 'plugin'", "element = 'codemirror'"));

        $db->setQuery($query);
        $db->execute();
    }
}
?>
