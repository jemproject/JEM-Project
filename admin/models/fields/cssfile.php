<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\Path;

FormHelper::loadFieldClass('list');

/**
 * Custom CSS file selector.
 */
class JFormFieldCssFile extends JFormFieldList
{
    protected $type = 'CssFile';
    protected $hasCompatibleFiles = false;

    protected function getInput()
    {
        $this->size = 1;
        $this->multiple = false;
        $this->class = trim($this->class . ' form-select');

        if (!$this->hasCompatibleFiles()) {
            $this->disabled = true;
        }

        return parent::getInput();
    }

    protected function hasCompatibleFiles()
    {
        $path = Path::clean(JPATH_ROOT . '/media/com_jem/css/custom');
        $files = is_dir($path) ? Folder::files($path, '\.css$', false, false) : array();
        $sourceFile = $this->getSourceCssFile();

        foreach ($files as $file) {
            if ($this->isCompatibleFile($path, $file, $sourceFile)) {
                return true;
            }
        }

        return (bool) $this->value;
    }

    protected function getSourceCssFile()
    {
        $fieldName = (string) $this->fieldname;

        if (strpos($fieldName, 'css_') !== 0 || substr($fieldName, -11) !== '_customfile') {
            return '';
        }

        $name = substr($fieldName, 4, -11);

        return str_replace('_', '-', $name) . '.css';
    }

    protected function getCustomSourceCssFile($path, $file)
    {
        $contents = is_file($path . '/' . $file) ? file_get_contents($path . '/' . $file, false, null, 0, 512) : false;

        if ($contents !== false && preg_match('/JEM custom source:\s*([A-Za-z0-9._-]+\.css)/', $contents, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function isCompatibleFile($path, $file, $sourceFile)
    {
        if ($sourceFile === '') {
            return true;
        }

        $customSource = $this->getCustomSourceCssFile($path, $file);

        if ($customSource !== '') {
            return $customSource === $sourceFile;
        }

        $sourceStem = preg_replace('/\.css$/', '', $sourceFile);
        $fileStem   = preg_replace('/\.css$/', '', $file);

        return $file === $sourceFile || strpos($fileStem, $sourceStem . '-') === 0 || strpos($fileStem, $sourceStem . '_') === 0;
    }

    protected function getOptions()
    {
        $options = array();
        $options[] = HTMLHelper::_('select.option', '', Text::_('COM_JEM_SETTINGS_CSS_FILE_SELECT'));

        $path = Path::clean(JPATH_ROOT . '/media/com_jem/css/custom');
        $files = is_dir($path) ? Folder::files($path, '\.css$', false, false) : array();
        $sourceFile = $this->getSourceCssFile();
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $file) {
            if (!$this->isCompatibleFile($path, $file, $sourceFile)) {
                continue;
            }

            $options[] = HTMLHelper::_('select.option', $file, $file);
        }

        if ($this->value && !in_array($this->value, $files, true)) {
            $options[] = HTMLHelper::_('select.option', $this->value, Text::sprintf('COM_JEM_SETTINGS_CSS_FILE_MISSING_OPTION', $this->value));
        }

        return array_merge(parent::getOptions(), $options);
    }
}
