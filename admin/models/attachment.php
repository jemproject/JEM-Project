<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Table\Table;

require_once __DIR__ . '/admin.php';

class JemModelAttachment extends JemModelAdmin
{
    protected function canDelete($record)
    {
        return !empty($record->id) && JemFactory::getUser()->authorise('core.delete', 'com_jem');
    }

    public function getTable($name = 'jem_attachments', $prefix = '', $options = array())
    {
        return Table::getInstance($name, '', $options);
    }

    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm('com_jem.attachment', 'attachment', array('control' => 'jform', 'load_data' => $loadData));

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jem.edit.attachment.data', array());

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    protected function prepareTable($table)
    {
        if (empty($table->created)) {
            $table->created = Factory::getDate()->toSql();
        }

        if (empty($table->created_by)) {
            $table->created_by = (int) Factory::getApplication()->getIdentity()->id;
        }
    }

    public function save($data)
    {
        if (!empty($data['id'])) {
            $table = $this->getTable();

            if ($table->load((int) $data['id'])) {
                $data['object'] = (string) $table->object;
                $data['file'] = (string) $table->file;
            }
        }

        return parent::save($data);
    }

    public function getLinkedItem($id = null)
    {
        $id = $id ?: (int) Factory::getApplication()->input->getInt('id', 0);
        $table = $this->getTable();

        if (!$id || !$table->load((int) $id) || !preg_match('/^([a-z]+)([0-9]+)$/i', (string) $table->object, $matches)) {
            return null;
        }

        $type = strtolower($matches[1]);
        $itemId = (int) $matches[2];
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $editLink = '';

        switch ($type) {
            case 'event':
                $query->select($db->quoteName(array('title', 'published')))
                    ->from($db->quoteName('#__jem_events'))
                    ->where($db->quoteName('id') . ' = ' . $itemId);
                $editLink = 'index.php?option=com_jem&task=event.edit&id=' . $itemId . '&active_tab=attachments#attachments';
                break;

            case 'venue':
                $query->select($db->quoteName(array('venue', 'published')))
                    ->from($db->quoteName('#__jem_venues'))
                    ->where($db->quoteName('id') . ' = ' . $itemId);
                $editLink = 'index.php?option=com_jem&task=venue.edit&id=' . $itemId . '&active_tab=attachments#attachments';
                break;

            case 'category':
                $query->select($db->quoteName(array('catname', 'published')))
                    ->from($db->quoteName('#__jem_categories'))
                    ->where($db->quoteName('id') . ' = ' . $itemId);
                $editLink = 'index.php?option=com_jem&task=category.edit&id=' . $itemId;
                break;

            default:
                return (object) array(
                    'type' => $type,
                    'id' => $itemId,
                    'title' => (string) $table->object,
                    'published' => null,
                    'edit_link' => '',
                );
        }

        $db->setQuery($query);
        $linked = $db->loadObject();

        if (!$linked) {
            return (object) array(
                'type' => $type,
                'id' => $itemId,
                'title' => (string) $table->object,
                'published' => null,
                'edit_link' => '',
            );
        }

        return (object) array(
            'type' => $type,
            'id' => $itemId,
            'title' => isset($linked->title) ? $linked->title : (isset($linked->venue) ? $linked->venue : $linked->catname),
            'published' => $linked->published,
            'edit_link' => $editLink,
        );
    }

    public function getFileInfo($id = null)
    {
        $id = $id ?: (int) Factory::getApplication()->input->getInt('id', 0);
        $table = $this->getTable();

        if (!$id || !$table->load((int) $id)) {
            return null;
        }

        $extension = strtolower(pathinfo((string) $table->file, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? preg_replace('/[^a-z0-9]/', '', $extension) : 'file';
        $path = $this->resolveAttachmentPath($table->object, $table->file);
        $exists = $path && is_file($path);
        $size = $exists ? filesize($path) : null;
        $modified = $exists ? filemtime($path) : null;

        return (object) array(
            'extension' => $extension,
            'type' => $this->getFileType($extension),
            'path_safe' => (bool) $path,
            'exists' => (bool) $exists,
            'size' => $size === false ? null : $size,
            'modified' => $modified === false ? null : $modified,
        );
    }

    public function deleteWithFiles(array $pks)
    {
        foreach ($pks as $pk) {
            $path = $this->getAttachmentPath((int) $pk);

            if ($path && is_file($path) && !File::delete($path)) {
                return false;
            }
        }

        return $this->delete($pks);
    }

    public function getAttachmentPath($id)
    {
        $table = $this->getTable();

        if (!$id || !$table->load((int) $id)) {
            return false;
        }

        return $this->resolveAttachmentPath($table->object, $table->file);
    }

    private function resolveAttachmentPath($object, $file)
    {
        if (!is_string($object) || !preg_match('/^[a-z]+[0-9]+$/i', $object)) {
            return false;
        }

        if ((string) $file === '' || basename((string) $file) !== (string) $file) {
            return false;
        }

        $jemsettings = JemHelper::config();
        $basePath = Path::clean(JPATH_SITE . '/' . trim((string) $jemsettings->attachments_path));
        $path = Path::clean($basePath . '/' . $object . '/' . $file);
        $baseCheck = rtrim(strtolower($basePath), '\\/') . DIRECTORY_SEPARATOR;

        return strpos(strtolower($path), $baseCheck) === 0 ? $path : false;
    }

    private function getFileType($extension)
    {
        if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'))) {
            return 'image';
        }

        if (in_array($extension, array('doc', 'docx', 'odt', 'rtf'))) {
            return 'document';
        }

        if (in_array($extension, array('xls', 'xlsx', 'ods', 'csv'))) {
            return 'spreadsheet';
        }

        if (in_array($extension, array('zip', 'rar', '7z', 'tar', 'gz'))) {
            return 'archive';
        }

        if ($extension === 'pdf') {
            return 'pdf';
        }

        if ($extension === 'txt') {
            return 'text';
        }

        return 'generic';
    }

}
