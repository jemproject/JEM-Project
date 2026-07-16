<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Filesystem\Path;
use Joomla\CMS\Filter\InputFilter;

// ensure JemFactory is loaded (because this class is used by modules or plugins too)
require_once(JPATH_SITE.'/components/com_jem/factory.php');

/**
 * Holds the logic for attachments manipulation
 *
 * @package JEM
 */
class JemAttachment
{
    /**
     * Attachment identifiers are stored as type + numeric id, e.g. event12 or venue7.
     */
    static protected function isValidObject($object)
    {
        return is_string($object) && preg_match('/^[a-z]+[0-9]+$/i', $object);
    }

    /**
     * Resolve an attachment path and ensure it remains inside the configured base directory.
     */
    static protected function getSafeAttachmentPath($object, $file)
    {
        if (!self::isValidObject($object)) {
            return false;
        }

        $jemsettings = JemHelper::config();
        $basePath = Path::clean(JPATH_SITE.'/'.$jemsettings->attachments_path);
        $path = Path::clean($basePath.'/'.$object.'/'.$file);
        $baseCheck = rtrim(strtolower($basePath), '\\/') . DIRECTORY_SEPARATOR;

        if (strpos(strtolower($path), $baseCheck) !== 0) {
            return false;
        }

        return $path;
    }

    /**
     * Clean text stored with an attachment to reduce stored XSS risk.
     */
    static protected function cleanText($value, $maxLength = 255)
    {
        $value = trim((string) $value);
        $value = InputFilter::getInstance()->clean($value, 'string');

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }

    /**
     * Return the configured extension matched against the complete filename suffix.
     */
    static protected function getAllowedExtension($filename, array $allowed)
    {
        $filename = strtolower((string) $filename);
        usort($allowed, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($allowed as $extension) {
            $extension = strtolower(trim((string) $extension));

            if ($extension !== '' && substr($filename, -strlen('.' . $extension)) === '.' . $extension) {
                return $extension;
            }
        }

        return '';
    }

    /**
     * Reject file names that contain executable or browser-active extensions.
     */
    static protected function hasUnsafeExtension($filename)
    {
        $unsafe = array(
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
            'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp',
            'sh', 'bash', 'cmd', 'bat', 'exe', 'dll', 'so',
            'js', 'mjs', 'html', 'htm', 'xhtml', 'svg',
        );

        $parts = explode('.', strtolower((string) $filename));
        array_shift($parts);

        return (bool) array_intersect($parts, $unsafe);
    }

    /**
     * Verify common MIME types for configured attachment extensions.
     */
    static protected function hasAllowedMime($tmpFile, $extension)
    {
        if (!is_file($tmpFile) || !function_exists('finfo_open')) {
            return true;
        }

        $map = array(
            'txt' => array('text/plain'),
            'csv' => array('text/plain', 'text/csv', 'application/csv'),
            'pdf' => array('application/pdf'),
            'jpg' => array('image/jpeg'),
            'jpeg' => array('image/jpeg'),
            'png' => array('image/png'),
            'gif' => array('image/gif'),
            'webp' => array('image/webp'),
            'zip' => array('application/zip', 'application/x-zip-compressed'),
            'gz' => array('application/gzip', 'application/x-gzip'),
            'tar.gz' => array('application/gzip', 'application/x-gzip'),
        );

        $extension = strtolower((string) $extension);

        if (empty($map[$extension])) {
            return true;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if (!$finfo) {
            return true;
        }

        $mime = finfo_file($finfo, $tmpFile);
        finfo_close($finfo);

        return in_array($mime, $map[$extension], true);
    }

    /**
     * Return a Font Awesome class that represents the attachment extension.
     */
    static public function getIconClass($filename)
    {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

        if (in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'), true)) {
            return 'fa fa-file-image';
        }

        if (in_array($extension, array('zip', 'tar', 'gz', 'rar', '7z'), true)) {
            return 'fa fa-file-archive';
        }

        if (in_array($extension, array('doc', 'docx', 'odt', 'rtf'), true)) {
            return 'fa fa-file-word';
        }

        if (in_array($extension, array('xls', 'xlsx', 'ods', 'csv'), true)) {
            return 'fa fa-file-excel';
        }

        if (in_array($extension, array('ppt', 'pptx', 'odp'), true)) {
            return 'fa fa-file-powerpoint';
        }

        if ($extension === 'pdf') {
            return 'fa fa-file-pdf';
        }

        if (in_array($extension, array('txt', 'md', 'log'), true)) {
            return 'fa fa-file-alt';
        }

        return 'fa fa-file';
    }

    /**
     * upload files for the specified object
     *
     * @param  array  data from JInputFiles (as array of n arrays of params, [n][params])
     * @param  string object identification (should be event<eventid>, category<categoryid>, etc...)
     */
    static public function postUpload($post_files, $object)
    {
        require_once JPATH_SITE.'/components/com_jem/classes/image.class.php';

        $user = JemFactory::getUser();
        $jemsettings = JemHelper::config();

        $path = self::getSafeAttachmentPath($object, '');

        if (!$path) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_COULD_NOT_CREATE_FOLDER').': '.$object, 'warning');
            return false;
        }

        if (!(is_array($post_files) && count($post_files))) {
            return false;
        }

        $allowed = explode(",", $jemsettings->attachments_types);
        foreach ($allowed as $k => $v) {
            $allowed[$k] = strtolower($v ? trim($v) : $v);
        }

        $maxsizeinput = max(1, (int) $jemsettings->attachments_maxsize) * 1024; // size in kb

        foreach ($post_files as $k => $rec)
        {
            $file = array_key_exists('name', $rec) ? $rec['name'] : '';
            if (empty($file)) {
                continue;
            }

            if (!isset($rec['error']) || $rec['error'] !== UPLOAD_ERR_OK || empty($rec['tmp_name']) || !is_uploaded_file($rec['tmp_name'])) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_ATTACHMENT_SAVING_TO_DB').': '.$file, 'warning');
                continue;
            }

            // check if the filetype is valid
            $fileext = self::getAllowedExtension($file, $allowed);
            if (!$fileext || self::hasUnsafeExtension($file)) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_ATTACHEMENT_EXTENSION_NOT_ALLOWED').': '.$file, 'warning');
                continue;
            }

            if (!self::hasAllowedMime($rec['tmp_name'], $fileext)) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_ATTACHEMENT_EXTENSION_NOT_ALLOWED').': '.$file, 'warning');
                continue;
            }

            // check size
            if (empty($rec['size']) || $rec['size'] > $maxsizeinput) {
                Factory::getApplication()->enqueueMessage(Text::sprintf('COM_JEM_ERROR_ATTACHEMENT_FILE_TOO_BIG', $file, $rec['size'], $maxsizeinput), 'warning');
                continue;
            }

            if (!is_dir($path)) {
                // try to create it
                $res = Folder::create($path);
                if (!$res) {
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_COULD_NOT_CREATE_FOLDER').': '.$path, 'warning');
                    return false;
                }
            }

            // TODO: Probably move this to a helper class

            $sanitizedFilename = JemImage::sanitize($path, $file);

            // Make sure that the full file path is safe.
            $filepath = self::getSafeAttachmentPath($object, $sanitizedFilename);
            if (!$filepath) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_COULD_NOT_CREATE_FOLDER').': '.$object, 'warning');
                continue;
            }
            // File::upload has additional params to control security parsing.
            // switch off parsing archives for byte sequences looking like a script file extension
            // but keep all other checks running
            if (!File::upload($rec['tmp_name'], $filepath, false, false, array('forbidden_ext_in_content' => true))) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_COULD_NOT_CREATE_FOLDER').': '.$object, 'warning');
                continue;
            }

            $table = Table::getInstance('jem_attachments', '');
            $table->file = $sanitizedFilename;
            $table->object = $object;
            if (isset($rec['customname']) && !empty($rec['customname'])) {
                $table->name = self::cleanText($rec['customname']);
            }
            if (isset($rec['description']) && !empty($rec['description'])) {
                $table->description = self::cleanText($rec['description'], 1000);
            }
            if (isset($rec['access'])) {
                $table->access = max(1, intval($rec['access']));
            }
            if (isset($rec['ordering'])) {
                $table->ordering = max(0, (int) $rec['ordering']);
            }
            if (isset($rec['frontend'])) {
                $table->frontend = (int) ((int) $rec['frontend'] === 1);
            }

            $table->created = date('Y-m-d H:i:s');
            $table->created_by = $user->get('id');

            if (!($table->check() && $table->store())) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_ATTACHMENT_SAVING_TO_DB').': '.$table->getError(), 'warning');
            } else {
                self::triggerActionLog('onJemAfterAttachmentSave', array((object) $table->getProperties(), true));
            }
        } // foreach

        return true;
    }

    /**
     * update attachment record in db
     * @param  array (id, name, description, access)
     */
    static public function update($attach, $object = null)
    {
        if (!is_array($attach) || !isset($attach['id']) || !(intval($attach['id']))) {
            return false;
        }

        $table = Table::getInstance('jem_attachments', '');
        if (!$table->load((int) $attach['id'])) {
            return false;
        }

        if ($object !== null && (!self::isValidObject($object) || $table->object !== $object)) {
            return false;
        }

        $attach['id'] = (int) $attach['id'];

        if (array_key_exists('name', $attach)) {
            $attach['name'] = self::cleanText($attach['name']);
        }

        if (array_key_exists('description', $attach)) {
            $attach['description'] = self::cleanText($attach['description'], 1000);
        }

        if (array_key_exists('access', $attach)) {
            $attach['access'] = max(1, (int) $attach['access']);
        }

        if (array_key_exists('ordering', $attach)) {
            $attach['ordering'] = max(0, (int) $attach['ordering']);
        }
        if (array_key_exists('frontend', $attach)) {
            $attach['frontend'] = (int) ((int) $attach['frontend'] === 1);
        }

        $table->bind($attach);

        if (!($table->check() && $table->store())) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_ERROR_ATTACHMENT_UPDATING_RECORD').': '.$table->getError(), 'warning');
            return false;
        }

        self::triggerActionLog('onJemAfterAttachmentSave', array((object) $table->getProperties(), false));

        return true;
    }

    /**
     * return attachments for objects
     * @param  string object identification (should be event<eventid>, category<categoryid>, etc...)
     * @return array
     */
    static public function getAttachments($object, $includeUnpublished = null)
    {
        $jemsettings = JemHelper::config();

            $path = self::getSafeAttachmentPath($object, '');

            if (!$path) {
                return false;
            }

        if (!file_exists($path)) {
            return array();
        }

        // first list files in the folder
        $files = Folder::files($path, null, false, false);

        // then get info for files from db
        $db = Factory::getContainer()->get('DatabaseDriver');
        $fnames = array();
        foreach ($files as $f) {
            $fnames[] = $db->Quote($f);
        }

        if (!count($fnames)) {
            return array();
        }

        $app = Factory::getApplication();
        if ($includeUnpublished === null) {
            $includeUnpublished = $app->isClient('administrator');
        }

        // Check access level if not a Super User on Backend.
        $user = JemFactory::getUser();
        if ($app->isClient('administrator') && $user->authorise('core.manage')) {
            $qAccess = '';
        } else {
            $levels = $user->getAuthorisedViewLevels();
            $qAccess = '   AND access IN (' . implode(',', $levels) . ')';
        }

        $qPublished = $includeUnpublished ? '' : '   AND frontend = 1';

        $query = 'SELECT * '
               . ' FROM #__jem_attachments '
               . ' WHERE file IN ('. implode(',', $fnames) .')'
               . '   AND object = '. $db->Quote($object)
               . $qAccess
               . $qPublished
               . ' ORDER BY ordering ASC ';

        $db->setQuery($query);
        $res = $db->loadObjectList();

        return $res;
    }

    /**
     * get the file
     *
     * @param  int $id
     */
    static public function getAttachmentPath($id)
    {
        $user = JemFactory::getUser();
        // Support Joomla access levels instead of single group id
        $levels = $user->getAuthorisedViewLevels();

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = 'SELECT * '
               . ' FROM #__jem_attachments '
               . ' WHERE id = '. $db->Quote(intval($id));

        $db->setQuery($query);
        $res = $db->loadObject();

        if (!$res) {
            throw new Exception(Text::_('COM_JEM_FILE_NOT_FOUND'), 404);
        }

        if (!Factory::getApplication()->isClient('administrator') && !(int) $res->frontend) {
            throw new Exception(Text::_('COM_JEM_NO_ACCESS'), 403);
        }

        if (!in_array($res->access, $levels)) {
            throw new Exception(Text::_('COM_JEM_NO_ACCESS'), 403);
        }

        $path = self::getSafeAttachmentPath($res->object, $res->file);
        if (!$path) {
            throw new Exception(Text::_('COM_JEM_FILE_NOT_FOUND'), 404);
        }

        if (!file_exists($path)) {
            throw new Exception(Text::_('COM_JEM_FILE_NOT_FOUND'), 404);
        }

        return $path;
    }

    /**
     * Record a successfully delivered attachment download.
     *
     * The counter update is atomic so concurrent downloads are not lost. A
     * statistics failure must not invalidate a file that was already sent.
     *
     * @param  int $id Attachment id
     * @return boolean
     */
    static public function recordDownload($id)
    {
        $id = (int) $id;

        if ($id < 1) {
            return false;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_attachments'))
                ->set($db->quoteName('downloads') . ' = ' . $db->quoteName('downloads') . ' + 1')
                ->set($db->quoteName('last_download') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->where($db->quoteName('id') . ' = ' . $id);
            $db->setQuery($query);

            return (bool) $db->execute();
        } catch (\RuntimeException $e) {
            JemHelper::addLogEntry(
                'Unable to record attachment download for id ' . $id . ': ' . $e->getMessage(),
                __METHOD__,
                Log::ERROR
            );

            return false;
        }
    }

    /**
     * Write a failed attachment delivery to JEM's log without exposing paths.
     *
     * @param int    $id      Attachment id
     * @param string $channel Download channel (frontend or backend)
     * @param string $reason  Failure reason
     * @return void
     */
    static public function logDownloadError($id, $channel, $reason)
    {
        $userId = (int) JemFactory::getUser()->get('id');
        $reason = trim(preg_replace('/\s+/', ' ', (string) $reason));

        JemHelper::addLogEntry(
            'Attachment download failed; id=' . (int) $id
            . '; user=' . $userId
            . '; channel=' . preg_replace('/[^a-z]/i', '', (string) $channel)
            . '; reason=' . $reason,
            __METHOD__,
            Log::WARNING
        );
    }

    /**
     * remove attachment for objects
     *
     * @param  id from db
     * @param  string object identification (should be event<eventid>, category<categoryid>, etc...)
     * @return boolean
     */
    static public function remove($id)
    {
        $user = JemFactory::getUser();
        // Support Joomla access levels instead of single group id
        $levels = $user->getAuthorisedViewLevels();
        $userid = $user->get('id');

        // then get info for files from db
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = 'SELECT * '
               . ' FROM #__jem_attachments '
               . ' WHERE id = ' . $db->Quote($id) . ' AND access IN (0,' . implode(',', $levels) . ')';

        $db->setQuery($query);
        $res = $db->loadObject();

        if (!$res) {
            return false;
        }

        $attachment = $res;

        // check permission
        if (empty($userid) || ($userid != $res->created_by)) {
            if (strncasecmp($res->object, 'event', 5) == 0) {
                $type = 'event';
                $itemid = (int)substr($res->object, 5);
                $table = '#__jem_events';
            } elseif (strncasecmp($res->object, 'venue', 5) == 0) {
                $type = 'venue';
                $itemid = (int)substr($res->object, 5);
                $table = '#__jem_venues';
            } else {
                return false;
            }

            // get item owner
            $query = 'SELECT created_by FROM ' . $table . ' WHERE id = ' . $db->Quote($itemid);
            $db->setQuery($query);
            $created_by = $db->loadResult();

            if (!$user->can('edit', $type, $itemid, $created_by)) {
                JemHelper::addLogEntry("User {$userid} is not permitted to remove attachment " . $res->object, __METHOD__);
                return false;
            }
        }

        JemHelper::addLogEntry("User {$userid} removes attachment " . $res->object.'/'.$res->file, __METHOD__);
        $path = self::getSafeAttachmentPath($res->object, $res->file);
        if (!$path) {
            return false;
        }

        if (file_exists($path)) {
            File::delete($path);
        }

        $query = 'DELETE FROM #__jem_attachments '
               . ' WHERE id = '. $db->Quote($id);

        $db->setQuery($query);
        $deleted = $db->execute();

        if (!$deleted) {
            return false;
        }

        self::triggerActionLog('onJemAfterAttachmentDelete', array($attachment));

        return true;
    }

    /**
     * Trigger optional Joomla action log plugin events.
     *
     * @param string $event
     * @param array  $arguments
     *
     * @return void
     */
    static protected function triggerActionLog($event, array $arguments)
    {
        try {
            PluginHelper::importPlugin('actionlog', 'jem');
            JemFactory::getDispatcher()->triggerEvent($event, $arguments);
        } catch (Throwable $e) {
            // Attachment operations must not fail because optional action logging failed.
        }
    }
}
