<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;

/**
 * Housekeeping-View
 */
class JemViewHousekeeping extends JemAdminView
{

    public function display($tpl = null) {

        $app = Factory::getApplication();

        if (!JemHelperBackend::canManage('jem.tools.manage')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->imagesReview = $app->input->getCmd('tool', '') === 'images';
        if ($app->input->getCmd('tool', '') === 'attachmentrecovery') {
            $this->prepareDeletedResources();
        } elseif ($this->imagesReview) {
            $this->imagesType = $app->input->getInt('type', 1);
            if (!in_array($this->imagesType, array(1, 2, 3), true)) {
                throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'), 404);
            }
            $this->imagesCandidates = array();
            $this->imagesError = '';
            try {
                $this->imagesCandidates = $this->getModel()->getImageCleanupCandidates($this->imagesType);
            } catch (JemHousekeepingImageReferenceException $error) {
                $this->imagesError = $error->getMessage();
            }
            $this->imagesBatchLimit = JemModelHousekeeping::IMAGE_CLEANUP_BATCH_LIMIT;
            $this->imagesNonce = $this->imagesCandidates
                ? JemHelper::issueActionNonce('housekeeping.images.' . $this->imagesType) : '';
            $this->setLayout('images');
        } else {
            $this->totalcats = $this->get('Countcats');
        }
        $this->truncateNonce = JemHelper::issueActionNonce('housekeeping.truncateAllData');

        // add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }


    /**
     * Build a paginated, read-only preview and bind its confirmation to this administrator.
     * @return void
     */
    protected function prepareDeletedResources(): void
    {
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.admin')) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        require_once JPATH_SITE . '/components/com_jem/classes/deletedobjectcleanup.class.php';
        $this->cleanupPage = max(1, $app->input->getInt('page', 1));
        $preview = JemDeletedObjectCleanup::preview(Factory::getContainer()->get('DatabaseDriver'), $this->cleanupPage);
        $this->cleanupEntries = $preview['entries'];
        $this->cleanupMore = $preview['more'];
        // Store only ownership fingerprints in the session, never filesystem paths.
        $entries = array_map(static fn (array $entry): array => [
            'status' => $entry['status'], 'snapshot' => $entry['snapshot'],
        ], $preview['entries']);
        $app->setUserState('com_jem.deleted_resources_preview', [
            'user' => (int) $app->getIdentity()->id, 'time' => time(), 'entries' => $entries,
        ]);
        $this->cleanupNonce = JemHelper::issueActionNonce('housekeeping.recoverDeletedResources');
        $this->cleanupRun = (array) $app->getUserState('com_jem.deleted_resources_run', []);
        $app->setUserState('com_jem.deleted_resources_run', null);
        $this->setLayout('attachmentrecovery');
    }


    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_HOUSEKEEPING'), 'housekeeping');

        ToolbarHelper::back('JTOOLBAR_BACK', 'index.php?option=com_jem&view=' . (($this->imagesReview || $this->getLayout() === 'attachmentrecovery') ? 'housekeeping' : 'main'));
        ToolbarHelper::divider();
        ToolBarHelper::help('housekeeping', true, 'https://www.joomlaeventmanager.net/documentation/backend/control-panel/housekeeping');
    }
}
?>
