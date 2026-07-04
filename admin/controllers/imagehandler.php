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
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Session\Session;
use Joomla\Filesystem\Path;
require_once JPATH_SITE . '/components/com_jem/classes/eventimagepath.class.php';

/**
 * JEM Component Imagehandler Controller
 *
 * @package JEM
 *
 */
class JemControllerImagehandler extends BaseController
{
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
     * logic for uploading an image
     *
     * @access public
     * @return void
     */
    public function uploadimage() {
        // Check for request forgeries
        Session::checkToken() or jexit('Invalid token');

        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_jem')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $jemsettings = JemAdmin::config();

        $file = $app->input->files->get('userfile', array(), 'array');
        $task = $app->input->getCmd('task', '');
        $imagePath = JemEventImagePath::normaliseRelativeFolder($app->input->getString('image_path', ''));
        $redirectPath = $imagePath !== '' ? '&image_path=' . rawurlencode($imagePath) : '';

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

        if ($task === 'eventimgup') {
            if (!JemEventImagePath::ensureEventFolders($imagePath)) {
                $app->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'error');
                $app->redirect('index.php?option=com_jem&view=imagehandler&task=' . $task . '&tmpl=component' . $redirectPath);
                return;
            }

            $directories[$task] = JemEventImagePath::absoluteImageFolder($imagePath);
        }

        $base_Dir = Path::clean($directories[$task]) . DIRECTORY_SEPARATOR;
        $baseCheck = rtrim(strtolower($base_Dir), '\\/') . DIRECTORY_SEPARATOR;

        //do we have an upload?
        if (empty($file['name'])) {
            $app->enqueueMessage(Text::_('COM_JEM_IMAGE_EMPTY'), 'warning');
            $app->redirect('index.php?option=com_jem&view=imagehandler&task=' . $task . '&tmpl=component' . $redirectPath);
            return;
        }

        if (!empty($file['error']) || !is_uploaded_file($file['tmp_name'])) {
            $app->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'error');
            $app->redirect('index.php?option=com_jem&view=imagehandler&task=' . $task . '&tmpl=component' . $redirectPath);
            return;
        }

        //check the image
        $check = JemImage::check($file, $jemsettings);

        if ($check === false) {
            $app->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'error');
            $app->redirect('index.php?option=com_jem&view=imagehandler&task=' . $task . '&tmpl=component' . $redirectPath);
            return;
        }

        //sanitize the image filename
        $filename = JemImage::sanitize($base_Dir, $file['name']);
        $filepath = Path::clean($base_Dir . $filename);

        if (strpos(strtolower($filepath), $baseCheck) !== 0) {
            $app->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'error');
            $app->redirect('index.php?option=com_jem&view=imagehandler&task=' . $task . '&tmpl=component' . $redirectPath);
            return;
        }

        //upload the image
        if (!File::upload($file['tmp_name'], $filepath)) {
            $app->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'error');
            $app->redirect('index.php?option=com_jem&view=imagehandler&task=' . $task . '&tmpl=component' . $redirectPath);
            return;
        }

        if ($task === 'eventimgup') {
            JemEventImagePath::createThumbnail($imagePath, $filename, $filepath, $jemsettings);
        }

        echo '<script> alert(' . json_encode(Text::_('COM_JEM_UPLOAD_COMPLETE')) . '); window.parent.SelectImage(' . json_encode($filename) . ', ' . json_encode($filename) . ', null, ' . json_encode($task === 'eventimgup' ? $imagePath : '') . '); </script>' . "\n";
        $app->close();
    }
    /**
     * logic to mass delete images
     *
     * @access public
     * @return void
     */
    public function delete() {
        // Check for request forgeries
        Session::checkToken('get') or jexit('Invalid Token');

        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.manage', 'com_jem')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }


        // Get some data from the request
        $images = Factory::getApplication()->input->get('rm', array(), 'array');
        $folder = Factory::getApplication()->input->getCmd('folder', '');
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
