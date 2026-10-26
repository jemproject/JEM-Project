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
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Session\Session;
use Joomla\Filesystem\Path;
require_once JPATH_SITE . '/components/com_jem/classes/imagefilenamepolicy.class.php';

/**
 * JEM Component Imagehandler Controller
 *
 * @package JEM
 *
 */
class JemControllerImagehandler extends BaseController
{
    /**
     * Check whether the current user may add images for the requested resource.
     *
     * Existing event and venue uploads carry the record id so edit-own can be
     * evaluated against the owner loaded from storage. New records require the
     * corresponding create permission.
     */
    private function canUploadForTask($task, $recordId)
    {
        if ($task === 'eventimgup') {
            return $this->canCreateOrEditResource('event', $recordId);
        }

        if ($task === 'venueimgup') {
            return $this->canCreateOrEditResource('venue', $recordId);
        }

        if ($task === 'categoriesimgup') {
            $user = Factory::getApplication()->getIdentity();

            return $user->authorise('core.create', 'com_jem') || $user->authorise('core.edit', 'com_jem');
        }

        return false;
    }

    private function canCreateOrEditResource($type, $recordId)
    {
        if ((int) $recordId <= 0) {
            return JemHelperBackend::can($type, 'create');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'created_by')))
            ->from($db->quoteName($type === 'event' ? '#__jem_events' : '#__jem_venues'))
            ->where($db->quoteName('id') . ' = ' . (int) $recordId);
        $db->setQuery($query);
        $record = $db->loadObject();

        return is_object($record) && JemHelperBackend::can($type, 'edit', $record);
    }

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        // Register Extra task
        $this->registerTask('eventimgup', 'uploadimage');
        $this->registerTask('venueimgup', 'uploadimage');
        $this->registerTask('categoriesimgup', 'uploadimage');
    }

    /**
     * Check an upload name when the file is selected, without changing files.
     */
    public function checkFilename()
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        JemHelper::setNoStoreHeaders();
        $app->sendHeaders();

        try {
            if (strtoupper($app->input->server->getString('REQUEST_METHOD', '')) !== 'POST') {
                throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
            }
            if (!Session::checkToken('post')) {
                throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
            }

            $resource = $app->input->post->getCmd('resource', '');
            $tasks = array(
                'event' => 'eventimgup',
                'venue' => 'venueimgup',
                'category' => 'categoriesimgup',
            );

            if (!isset($tasks[$resource])
                || !$this->canUploadForTask($tasks[$resource], $app->input->post->getInt('record_id', 0))) {
                throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
            if (JemImageFilenamePolicy::mode(JemAdmin::config()) !== JemImageFilenamePolicy::ORIGINAL) {
                throw new RuntimeException(Text::_('COM_JEM_IMAGE_FILENAME_INVALID'), 400);
            }

            echo new JsonResponse(JemImageFilenamePolicy::checkExisting(
                $resource,
                $app->input->post->getString('filename', ''),
                $app->input->post->getString('stem', '')
            ));
        } catch (Throwable $exception) {
            echo new JsonResponse($exception);
        }

        $app->close();
    }

    /**
     * logic for uploading an image
     *
     * @access public
     * @return void
     */
    public function uploadimage() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid token');

        $app = Factory::getApplication();
        $task = $app->input->getCmd('task', '');
        $recordId = $app->input->getInt('record_id', 0);

        if (!$this->canUploadForTask($task, $recordId)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $jemsettings = JemAdmin::config();

        $file = $app->input->files->get('userfile', array(), 'array');
        $directories = array(
            'venueimgup'      => JPATH_SITE . '/images/jem/venues/',
            'eventimgup'      => JPATH_SITE . '/images/jem/events/',
            'categoriesimgup' => JPATH_SITE . '/images/jem/categories/',
        );

        if (!isset($directories[$task])) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect('index.php?option=com_jem&view=imagehandler&tmpl=component');
            return;
        }

        $base_Dir = Path::clean($directories[$task]) . DIRECTORY_SEPARATOR;

        //do we have an upload?
        if (empty($file['name'])) {
            $app->enqueueMessage(Text::_('COM_JEM_IMAGE_EMPTY'), 'warning');
            $app->redirect('index.php?option=com_jem&view=imagehandler&task=' . $task . '&tmpl=component');
            return;
        }

        if (!empty($file['error']) || !is_uploaded_file($file['tmp_name'])) {
            $app->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'error');
            $app->redirect('index.php?option=com_jem&view=imagehandler&task=' . $task . '&tmpl=component');
            return;
        }

        $newFiles = array();
        $replacements = array();
        $filename = JemImageFilenamePolicy::upload(
            $file, $base_Dir, $jemsettings, 'userfile', $newFiles, $replacements
        );
        if ($filename === false) {
            $app->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'error');
            $app->redirect('index.php?option=com_jem&view=imagehandler&task=' . $task . '&tmpl=component');
            return;
        } else {
            JemImageFilenamePolicy::finish(array(), $replacements, true);
            echo "<script> alert(" . json_encode(Text::_('COM_JEM_UPLOAD_COMPLETE')) . "); window.parent.SelectImage(" . json_encode($filename) . ", " . json_encode($filename) . "); </script>\n";
            $app->close();
        }
    }

    /**
     * logic to mass delete images
     *
     * @access public
     * @return void
     */
    public function delete() {
        JemHelper::requirePostToken();

        $app = Factory::getApplication();
        if (!JemHelperBackend::canManage('jem.tools.manage')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }


        // Get some data from the request
        $images = $app->input->post->get('rm', array(), 'array');
        $folder = $app->input->post->getCmd('folder', '');
        $allowedFolders = array('events', 'venues', 'categories');

        if (!in_array($folder, $allowedFolders, true)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UNABLE_TO_DELETE'), 'warning');
            $app->redirect('index.php?option=com_jem&view=imagehandler&tmpl=component');
            return;
        }

        $basePath = Path::clean(JPATH_SITE . '/images/jem/' . $folder);
        $baseCheck = rtrim(strtolower($basePath), '\\/') . DIRECTORY_SEPARATOR;

        if (count($images)) {
            foreach ($images as $image) {
                if ($image !== InputFilter::getInstance()->clean($image, 'path')) {
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UNABLE_TO_DELETE').' '.htmlspecialchars($image, ENT_COMPAT, 'UTF-8'), 'warning');
                    continue;
                }

                $fullPath = Path::clean($basePath . '/' . $image);
                $fullPaththumb = Path::clean($basePath . '/small/' . $image);

                if (strpos(strtolower($fullPath), $baseCheck) !== 0 || strpos(strtolower($fullPaththumb), $baseCheck) !== 0) {
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UNABLE_TO_DELETE').' '.htmlspecialchars($image, ENT_COMPAT, 'UTF-8'), 'warning');
                    continue;
                }

                if (is_file($fullPath)) {
                    File::delete($fullPath);
                    if (is_file($fullPaththumb)) {
                        File::delete($fullPaththumb);
                    }
                }
            }
        }

        if ($folder == 'events') {
            $task = 'selecteventimg';
        } elseif ($folder == 'venues') {
            $task = 'selectvenueimg';
        } elseif ($folder == 'categories') {
            $task = 'selectcategoriesimg';
        }

        $app->redirect('index.php?option=com_jem&view=imagehandler&task='.$task.'&tmpl=component');
    }

}
?>
