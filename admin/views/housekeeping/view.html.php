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
use Joomla\CMS\Pagination\Pagination;

require_once JPATH_COMPONENT_ADMINISTRATOR . '/classes/housekeepingdiagnostics.class.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/classes/housekeepingreports.class.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/classes/housekeepingdefaults.class.php';

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

        $this->totalcats = $this->get('Countcats');
        $this->tool = $app->input->getCmd('tool', '');
        if ($this->tool === '' && $app->input->getInt('imageaudit', 0) === 1) {
            $this->tool = 'images';
        }

        if ($this->tool === 'diagnostics') {
            $reports = new JemHousekeepingReports();
            $this->diagnosticReportId = max(0, $app->input->getInt('report', 0));
            $this->diagnosticStored = $reports->load($this->diagnosticReportId);
            $this->diagnosticRecent = $reports->recent();
            $this->diagnosticReport = $this->diagnosticStored['data']['report'] ?? null;
            $this->diagnosticDeleteAllowed = $app->getIdentity()->authorise('core.admin');
            $this->diagnosticDeleteNonce = $this->diagnosticDeleteAllowed && $this->diagnosticRecent
                ? JemHelper::issueActionNonce('housekeeping.deleteDiagnosticReport') : '';
            $this->diagnosticPage = max(1, $app->input->getInt('page', 1));
            $severity = $app->input->getCmd('severity', '');
            $this->diagnosticSeverity = in_array($severity, array('notice', 'warning', 'error'), true)
                ? $severity
                : '';
            $this->overrideDeleteAllowed = $app->getIdentity()->authorise('core.admin');
            $this->overrideDeleteNonce = $this->overrideDeleteAllowed && $this->diagnosticReport
                ? JemHelper::issueActionNonce('housekeeping.deleteOverrides') : '';
            $this->setLayout('diagnostics');
        } elseif ($this->tool === 'restore') {
            if (!JemHelperBackend::canManage('core.options')) {
                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
            $this->restoreScope = $app->input->getCmd('scope', 'component');
            if (!in_array($this->restoreScope, array('component', 'view', 'module', 'plugin'), true)) {
                $this->restoreScope = 'component';
            }
            if ($this->restoreScope !== 'component' && !$app->getIdentity()->authorise('core.admin')) {
                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
            $this->restoreTargetId = max(0, $app->input->getInt('target', 0));
            $defaults = new JemHousekeepingDefaults();
            $this->restoreTargets = $defaults->targets($this->restoreScope);
            $this->restoreCatalog = null;
            $this->restoreError = '';
            if ($this->restoreScope === 'component' || $this->restoreTargetId > 0) {
                try {
                    $this->restoreCatalog = $defaults->catalog($this->restoreScope, $this->restoreTargetId);
                } catch (\Throwable $error) {
                    $this->restoreError = $error->getMessage();
                }
            }
            $this->restorePreview = $app->getUserState('com_jem.housekeeping.restore_preview', null);
            $this->restoreRun = $app->getUserState('com_jem.housekeeping.restore_run', null);
            $this->restoreNonce = JemHelper::issueActionNonce('housekeeping.applyDefaults');
            $this->setLayout('restore');
        } elseif ($this->tool === 'images') {
            $this->imageRun = $app->getUserState('com_jem.housekeeping.image_run', null);
            $this->setLayout('images');
        } elseif ($this->tool !== '') {
            throw new \Exception(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'), 404);
        }

        $legacyReport = $app->getUserState('com_jem.housekeeping.image_profile_report', null);
        $auditActive = $this->tool === 'images' || is_array($legacyReport);
        $this->imageProfileReport = null;
        $this->imagePagination = null;
        $this->imageBatchLimit = 25;
        $this->truncateNonce = JemHelper::issueActionNonce('housekeeping.truncateAllData');

        if ($auditActive) {
            $model = $this->getModel();
            $ordering = $app->getUserStateFromRequest(
                'com_jem.housekeeping.image_ordering',
                'filter_order',
                'file',
                'cmd'
            );
            $direction = $app->getUserStateFromRequest(
                'com_jem.housekeeping.image_direction',
                'filter_order_Dir',
                'asc',
                'cmd'
            );
            $limitstart = $app->input->getInt('limitstart', 0);
            $this->imageBatchLimit = $model::IMAGE_NORMALISE_BATCH_LIMIT;
            $this->imageProfileReport = $model->auditImageProfiles(
                $ordering,
                $direction,
                $limitstart,
                $model::IMAGE_CANDIDATE_PAGE_LIMIT
            );
            $this->imagePagination = new Pagination(
                (int) $this->imageProfileReport['candidate_total'],
                (int) $this->imageProfileReport['limitstart'],
                (int) $this->imageProfileReport['limit']
            );
            $app->setUserState('com_jem.housekeeping.image_profile_report', null);
        }

        // add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }


    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_HOUSEKEEPING'), 'housekeeping');

        ToolbarHelper::back(
            'JTOOLBAR_BACK',
            $this->tool === '' ? 'index.php?option=com_jem&view=main' : 'index.php?option=com_jem&view=housekeeping'
        );
        ToolbarHelper::divider();
        ToolBarHelper::help('housekeeping', true, 'https://www.joomlaeventmanager.net/documentation/backend/control-panel/housekeeping');
    }
}
?>
