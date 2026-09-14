<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;

/**
 * Housekeeping-Controller
 */
class JemControllerHousekeeping extends BaseController
{
    /**
     * Check whether the current user can run housekeeping tasks.
     */
    protected function allowHousekeeping() {
        if (!JemHelperBackend::canManage('jem.tools.manage')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        // Register Extra task
        $this->registerTask('cleaneventimg',     'delete');
        $this->registerTask('cleanvenueimg',     'delete');
        $this->registerTask('cleancategoryimg',    'delete');
    }

    /**
     * logic to massdelete unassigned images
     *
     * @access public
     * @return void
     *
     */
    public function delete() {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();

        $task = Factory::getApplication()->input->post->getCmd('task', '');
        $task = strpos($task, '.') !== false ? substr($task, strrpos($task, '.') + 1) : $task;
        $model = $this->getModel('housekeeping');
        $total = 0;

        if ($task == 'cleaneventimg') {
            $total = $model->delete($model::EVENTS);
        } elseif ($task == 'cleanvenueimg') {
            $total = $model->delete($model::VENUES);
        } elseif ($task == 'cleancategoryimg') {
            $total = $model->delete($model::CATEGORIES);
        }

        $link = 'index.php?option=com_jem&view=housekeeping';
        $msg = Text::sprintf('COM_JEM_HOUSEKEEPING_IMAGES_DELETED', $total);

        $this->setRedirect($link, $msg);
    }

    /**
     * logic to truncate table cats_relations
     *
     * @access public
     * @return void
     *
     */
    public function cleanupCatsEventRelations() {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();

        $model = $this->getModel('housekeeping');
        $model->cleanupCatsEventRelations();

        $link = 'index.php?option=com_jem&view=housekeeping';
        $msg = Text::_('COM_JEM_HOUSEKEEPING_CLEANUP_CATSEVENT_RELS_DONE');

        $this->setRedirect($link, $msg);
    }

    /**
     * Deletes physical attachment files that have no matching database record.
     */
    public function cleanupUnusedAttachmentFiles() {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();

        $model = $this->getModel('housekeeping');
        $result = $model->cleanupUnusedAttachmentFiles();

        $link = 'index.php?option=com_jem&view=housekeeping';
        $msg = Text::sprintf(
            'COM_JEM_HOUSEKEEPING_UNUSED_ATTACHMENT_FILES_DONE',
            (int) $result->files,
            (int) $result->folders,
            (int) $result->failed
        );
        $type = $result->failed ? 'warning' : 'message';

        $this->setRedirect($link, $msg, $type);
    }

    /**
     * Regenerates event, venue and category thumbnails using current image settings.
     */
    public function resizethumbs() {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();

        $model = $this->getModel('housekeeping');
        $total = $model->resizeThumbnails();

        $link = 'index.php?option=com_jem&view=housekeeping';
        $msg = Text::sprintf('COM_JEM_HOUSEKEEPING_RESIZE_THUMBNAILS_DONE', $total);

        $this->setRedirect($link, $msg);
    }

    /**
     * Truncates JEM tables with exception of settings table
     */
    public function truncateAllData() {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();

        $app = Factory::getApplication();
        $nonce = $app->input->post->getString('truncate_nonce', '');

        if (!JemHelper::consumeActionNonce('housekeeping.truncateAllData', $nonce)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $model = $this->getModel('housekeeping');
        $deleteAttachmentFiles = (bool) $app->input->post->getInt('deleteattachments', 0);
        $deleteImageFiles = (bool) $app->input->post->getInt('deleteimages', 0);
        $model->truncateAllData($deleteAttachmentFiles, $deleteImageFiles);

        $link = 'index.php?option=com_jem&view=housekeeping';
        $msg = ($deleteAttachmentFiles || $deleteImageFiles)
            ? Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_AND_FILES_DONE')
            : Text::_('COM_JEM_HOUSEKEEPING_TRUNCATE_ALL_DATA_DONE');

        $this->setRedirect($link, $msg);
    }

    /**
     * Triggerarchive + Recurrences
     *
     * @access public
     * @return void
     *
     */
    public function triggerarchive() {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();

        JemHelper::cleanup(1);

        $link = 'index.php?option=com_jem&view=housekeeping';
        $msg = Text::_('COM_JEM_HOUSEKEEPING_AUTOARCHIVE_DONE');

        $this->setRedirect($link, $msg);
    }
}
?>
