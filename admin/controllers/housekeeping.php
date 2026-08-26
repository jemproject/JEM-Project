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
     * Audit assigned originals against the current image profiles without changing files.
     */
    public function auditImages() {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();

        $app = Factory::getApplication();
        $app->setUserState('com_jem.housekeeping.image_profile_report', null);

        $this->setRedirect(
            'index.php?option=com_jem&view=housekeeping&imageaudit=1',
            Text::_('COM_JEM_HOUSEKEEPING_IMAGE_AUDIT_DONE')
        );
    }

    /**
     * Normalise one controlled batch of assigned originals after explicit confirmation.
     */
    public function normaliseImages() {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();

        $app = Factory::getApplication();
        $model = $this->getModel('housekeeping');
        $batchLimit = $model::IMAGE_NORMALISE_BATCH_LIMIT;
        $selected = $app->input->post->get('image_candidates', array(), 'array');
        $selected = is_array($selected) ? array_values($selected) : array();

        if (!$selected) {
            $this->setRedirect(
                'index.php?option=com_jem&view=housekeeping&imageaudit=1',
                Text::_('COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_NONE_SELECTED'),
                'warning'
            );
            return;
        }

        if (count($selected) > $batchLimit) {
            $this->setRedirect(
                'index.php?option=com_jem&view=housekeeping&imageaudit=1',
                Text::sprintf(
                    'COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_LIMIT_EXCEEDED',
                    $batchLimit
                ),
                'warning'
            );
            return;
        }

        try {
            $run = $model->normaliseImageProfiles($selected);
        } catch (InvalidArgumentException $exception) {
            $this->setRedirect(
                'index.php?option=com_jem&view=housekeeping&imageaudit=1',
                Text::_('COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_INVALID_SELECTION'),
                'warning'
            );
            return;
        }

        $type = (int) $run['failed'] > 0 ? 'warning' : 'message';
        if ((int) $run['failed'] > 0) {
            $app->enqueueMessage(
                Text::sprintf('COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_ERRORS', (int) $run['failed']),
                'warning'
            );
        }

        $this->setRedirect(
            'index.php?option=com_jem&view=housekeeping&imageaudit=1',
            Text::sprintf(
                'COM_JEM_HOUSEKEEPING_IMAGE_NORMALISE_DONE',
                (int) $run['completed'],
                (int) $run['skipped']
            ),
            $type
        );
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
