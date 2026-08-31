<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Client\ClientHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Factory;
require_once JPATH_SITE . '/components/com_jem/classes/eventimagepath.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/venueimagepath.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/categoryimagepath.class.php';
use Joomla\String\StringHelper;

/**
 * View class for the JEM imageselect screen
 * Based on the Joomla! media component
 *
 * @package JEM
 *
 */
class JemViewImagehandler extends HtmlView
{
    public $canDeleteImages = false;

    /**
     * The browser may expose only the image collection belonging to a resource
     * the user is allowed to access. Category images remain an administrative
     * tool until categories receive a dedicated resource policy.
     */
    private function canAccessTask($task)
    {
        if (in_array($task, array('selecteventimg', 'eventimg', 'eventimgup'), true)) {
            return JemHelperBackend::can('event', 'access');
        }

        if (in_array($task, array('selectvenueimg', 'venueimg', 'venueimgup'), true)) {
            return JemHelperBackend::can('venue', 'access');
        }

        return in_array($task, array('selectcategoriesimg', 'categoriesimg', 'categoriesimgup'), true)
            && Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jem');
    }


    /**
     * Image selection List
     */
    public function display($tpl = null)
    {
        $app    = Factory::getApplication();
        $option = $app->input->getString('option', 'com_jem');
        $task   = $app->input->getCmd('task', '');

        if (!$this->canAccessTask($task)) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        if ($this->getLayout() == 'uploadimage') {
            $this->_displayuploadimage($tpl);
            return;
        }

        //get vars
        $search = $app->getUserStateFromRequest($option.'.filter_search', 'filter_search', '', 'string');
        $search = trim(StringHelper::strtolower($search));

        //set variables
        $imageTasks = array(
            'selecteventimg'      => array('folder' => 'events',     'task' => 'eventimg',      'redi' => 'selecteventimg'),
            'eventimg'            => array('folder' => 'events',     'task' => 'eventimg',      'redi' => 'selecteventimg'),
            'eventimgup'          => array('folder' => 'events',     'task' => 'eventimg',      'redi' => 'selecteventimg'),
            'selectvenueimg'      => array('folder' => 'venues',     'task' => 'venueimg',      'redi' => 'selectvenueimg'),
            'venueimg'            => array('folder' => 'venues',     'task' => 'venueimg',      'redi' => 'selectvenueimg'),
            'venueimgup'          => array('folder' => 'venues',     'task' => 'venueimg',      'redi' => 'selectvenueimg'),
            'selectcategoriesimg' => array('folder' => 'categories', 'task' => 'categoriesimg', 'redi' => 'selectcategoriesimg'),
            'categoriesimg'       => array('folder' => 'categories', 'task' => 'categoriesimg', 'redi' => 'selectcategoriesimg'),
            'categoriesimgup'     => array('folder' => 'categories', 'task' => 'categoriesimg', 'redi' => 'selectcategoriesimg'),
        );

        if (empty($imageTasks[$task])) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'warning');
            $this->setLayout('uploadimage');
            $this->_displayuploadimage($tpl);
            return;
        }

        $folder = $imageTasks[$task]['folder'];
        $task   = $imageTasks[$task]['task'];
        $redi   = $imageTasks[$task]['redi'];

        $baseFolder = $folder;
        if ($folder === 'events') {
            $imagePath = JemEventImagePath::normaliseRelativeFolder($app->input->getString('image_path', ''));
        } elseif ($folder === 'venues') {
            $imagePath = JemVenueImagePath::normaliseRelativeFolder($app->input->getString('image_path', ''));
        } else {
            $imagePath = JemCategoryImagePath::normaliseRelativeFolder($app->input->getString('image_path', ''));
        }
        if ($imagePath !== '') {
            $folder .= '/' . $imagePath;
        }

        $app->input->set('folder', $folder);
        $this->canDeleteImages = JemHelperBackend::canManage('jem.tools.manage');

        // Do not allow cache
        $app->allowCache(false);

        // Get images
        $images = $this->get('images');
        $pagination = $this->get('Pagination');

        if ($search || (is_array($images) && (count($images) > 0))) {
            $this->images     = $images;
            $this->folder     = $folder;
            $this->task       = $redi;
            $this->search     = $search;
            $this->baseFolder = $baseFolder;
            $this->imagePath  = $imagePath;
            $this->state      = $this->get('state');
            $this->pagination = $pagination;
            parent::display($tpl);
        } else {
            //no images in the folder, redirect to uploadscreen and raise notice
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_NO_IMAGES_AVAILABLE'), 'notice');
            $this->setLayout('uploadimage');
            $app->input->set('task', $task);
            $this->_displayuploadimage($tpl);
            return;
        }
    }

    public function setImage($index = 0)
    {
        if (isset($this->images[$index])) {
            $this->_tmp_img = $this->images[$index];
        } else {
            $this->_tmp_img = new stdClass();
        }
    }

    /**
     * Prepares the upload image screen
     *
     * @param  $tpl
     *
     */
    protected function _displayuploadimage($tpl = null)
    {
        //initialise variables
        $uri         =Uri::getInstance();
        $uri         = $uri->toString();
        $jemsettings = JemAdmin::config();

        //get vars
        $task = Factory::getApplication()->input->get('task', '');
        $requestedProfile = Factory::getApplication()->input->getCmd('image_profile', '');
        $defaultProfiles = array(
            'eventimg' => 'event_intro',
            'venueimg' => 'venue',
            'categoriesimg' => 'category',
        );
        $allowedProfiles = array(
            'eventimg' => array('event_intro', 'event_full'),
            'venueimg' => array('venue'),
            'categoriesimg' => array('category'),
        );
        $baseTask = preg_replace('/up$/', '', (string) $task);
        $imageProfile = isset($allowedProfiles[$baseTask]) && in_array($requestedProfile, $allowedProfiles[$baseTask], true)
            ? $requestedProfile
            : ($defaultProfiles[$baseTask] ?? 'event_intro');
        if (strpos((string) $task, 'venue') === 0) {
            $imagePath = JemVenueImagePath::normaliseRelativeFolder(
                Factory::getApplication()->input->getString('image_path', '')
            );
        } elseif (strpos((string) $task, 'categories') === 0) {
            $imagePath = JemCategoryImagePath::normaliseRelativeFolder(
                Factory::getApplication()->input->getString('image_path', '')
            );
        } else {
            $imagePath = JemEventImagePath::normaliseRelativeFolder(
                Factory::getApplication()->input->getString('image_path', '')
            );
        }

        $ftp = ClientHelper::setCredentialsFromRequest('ftp');

        //assign data to template
        $this->task        = $task;
        $this->jemsettings = $jemsettings;
        $this->request_url = $uri;
        $this->ftp         = $ftp;
        $this->imagePath   = $imagePath;
        $this->imageProfile = $imageProfile;

        parent::display($tpl);
    }
}
?>
