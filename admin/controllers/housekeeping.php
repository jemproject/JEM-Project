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

require_once JPATH_COMPONENT_ADMINISTRATOR . '/classes/housekeepingdefaults.class.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/classes/housekeepingdiagnostics.class.php';
require_once JPATH_COMPONENT_ADMINISTRATOR . '/classes/housekeepingreports.class.php';

/**
 * Housekeeping-Controller
 */
class JemControllerHousekeeping extends BaseController
{
    /**
     * Run the full read-only audit and retain its result in the private log.
     */
    public function runDiagnostics(): void
    {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();
        $app = Factory::getApplication();

        try {
            $report = (new JemHousekeepingDiagnostics())->run();
            $id = (new JemHousekeepingReports())->save($report, (int) $app->getIdentity()->id);
            $this->setRedirect(
                'index.php?option=com_jem&view=housekeeping&tool=diagnostics&report=' . $id,
                Text::sprintf('COM_JEM_TOOLS_REPORT_SAVED', $id)
            );
        } catch (\Throwable $error) {
            $this->setRedirect(
                'index.php?option=com_jem&view=housekeeping&tool=diagnostics',
                Text::sprintf('COM_JEM_TOOLS_REPORT_FAILED', $error->getMessage()),
                'warning'
            );
        }
    }

    /** Delete exactly one saved audit after administrator confirmation. */
    public function deleteDiagnosticReport(): void
    {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.admin')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $nonce = $app->input->post->getString('delete_report_nonce', '');
        if (!JemHelper::consumeActionNonce('housekeeping.deleteDiagnosticReport', $nonce)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $id = max(0, $app->input->post->getInt('report_id', 0));
        $returnId = max(0, $app->input->post->getInt('return_report', 0));
        $reports = new JemHousekeepingReports();
        try {
            if (!$reports->delete($id)) {
                throw new \RuntimeException(Text::_('COM_JEM_TOOLS_REPORT_NOT_FOUND'));
            }
            if ($returnId === $id) {
                $recent = $reports->recent(1);
                $returnId = $recent ? (int) $recent[0]->id : 0;
            }
            $this->setRedirect(
                'index.php?option=com_jem&view=housekeeping&tool=diagnostics'
                    . ($returnId > 0 ? '&report=' . $returnId : ''),
                Text::sprintf('COM_JEM_TOOLS_REPORT_DELETED', $id)
            );
        } catch (\Throwable $error) {
            $this->setRedirect(
                'index.php?option=com_jem&view=housekeeping&tool=diagnostics'
                    . ($returnId > 0 ? '&report=' . $returnId : ''),
                $error->getMessage(),
                'warning'
            );
        }
    }

    /** Remove selected JEM override files or JEM entries in shared language INIs. */
    public function deleteOverrides(): void
    {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();
        $app = Factory::getApplication();
        if (!$app->getIdentity()->authorise('core.admin')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $stored = $this->diagnosticReportFromPost();
        $url = 'index.php?option=com_jem&view=housekeeping&tool=diagnostics&report=' . (int) $stored['id'];
        $nonce = $app->input->post->getString('delete_override_nonce', '');
        if (!JemHelper::consumeActionNonce('housekeeping.deleteOverrides', $nonce)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        try {
            $selected = $app->input->post->get('override_items', array(), 'array');
            $shown = array();
            foreach ($stored['data']['report']['overrides']['templates'] ?? array() as $item) {
                if (!empty($item['winner'])) {
                    $shown[JemHousekeepingOverrides::selectionId('template', $item['winner'])] = true;
                }
            }
            foreach ($stored['data']['report']['overrides']['languages'] ?? array() as $item) {
                $shown[JemHousekeepingOverrides::selectionId('language', $item['path'], $item['keys'])] = true;
            }
            foreach ((array) $selected as $id) {
                if (!is_string($id) || !isset($shown[$id])) {
                    throw new \InvalidArgumentException('Select an override shown in this diagnostic report.');
                }
            }
            $removed = (new JemHousekeepingOverrides())->deleteSelected((array) $selected);
            $report = (new JemHousekeepingDiagnostics())->run();
            $id = (new JemHousekeepingReports())->save($report, (int) $app->getIdentity()->id);
            $this->setRedirect(
                'index.php?option=com_jem&view=housekeeping&tool=diagnostics&report=' . $id,
                Text::sprintf(
                    'COM_JEM_TOOLS_OVERRIDES_DELETED',
                    (int) $removed['template_files'],
                    (int) $removed['language_keys']
                )
            );
        } catch (\Throwable $error) {
            $this->setRedirect(
                $url,
                Text::sprintf('COM_JEM_TOOLS_OVERRIDES_DELETE_FAILED', $error->getMessage()),
                'warning'
            );
        }
    }

    /**
     * Download the reviewed report as structured JSON.
     */
    public function downloadDiagnostics(): void
    {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();
        $app = Factory::getApplication();
        $stored = $this->diagnosticReportFromPost();
        $reports = new JemHousekeepingReports();
        $selection = $this->diagnosticSelection();
        $json = $reports->export(
            $stored,
            $app->input->post->getInt('include_names', 0) === 1,
            $this->diagnosticObservations(),
            $selection
        );

        $files = $reports->languageFiles($stored, $selection);
        if ($files) {
            $zip = new \ZipArchive();
            $temporary = tempnam(sys_get_temp_dir(), 'jem-report-');
            if ($temporary === false) {
                throw new \RuntimeException('Unable to create the diagnostic archive.');
            }
            try {
                if ($zip->open($temporary, \ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('Unable to create the diagnostic archive.');
                }
                $zip->addFromString($this->diagnosticFilename(), $json);
                foreach ($files as $file) {
                    $zip->addFromString($file['path'], $file['content']);
                }
                $zip->close();
                $payload = file_get_contents($temporary);
                if ($payload === false) {
                    throw new \RuntimeException('Unable to read the diagnostic archive.');
                }
            } finally {
                @unlink($temporary);
            }
            $filename = str_replace('.json', '.zip', $this->diagnosticFilename());
            $contentType = 'application/zip';
        } else {
            $payload = $json;
            $filename = $this->diagnosticFilename();
            $contentType = 'application/json; charset=utf-8';
        }

        $app->setHeader('Content-Type', $contentType, true);
        $app->setHeader(
            'Content-Disposition',
            'attachment; filename="' . $filename . '"',
            true
        );
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $app->sendHeaders();
        echo $payload;
        $app->close();
    }

    /**
     * Send the selected report only after explicit administrator consent.
     */
    public function sendDiagnostics(): void
    {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();
        $app = Factory::getApplication();
        $stored = $this->diagnosticReportFromPost();
        $id = (int) $stored['id'];
        $url = 'index.php?option=com_jem&view=housekeeping&tool=diagnostics&report=' . $id;

        if ($app->input->post->getInt('send_consent', 0) !== 1) {
            $this->setRedirect($url, Text::_('COM_JEM_TOOLS_SEND_CONSENT_REQUIRED'), 'warning');
            return;
        }

        try {
            $reports = new JemHousekeepingReports();
            $selection = $this->diagnosticSelection();
            $json = $reports->export(
                $stored,
                $app->input->post->getInt('include_names', 0) === 1,
                $this->diagnosticObservations(),
                $selection
            );
            $files = $reports->languageFiles($stored, $selection);
            $size = strlen($json) + array_sum(array_map(static fn($file): int => strlen($file['content']), $files));
            if ($size > 8388608) {
                throw new \RuntimeException('The report is too large for email. Download the JSON file instead.');
            }

            $config = Factory::getConfig();
            $mailer = Factory::getMailer();
            if ($mailer->setSender(array($config->get('mailfrom'), $config->get('fromname'))) === false
                || $mailer->addRecipient('info@joomlaeventmanager.net') === false) {
                throw new \RuntimeException('Joomla rejected the mail envelope.');
            }
            $mailer->setSubject('JEM diagnostic report #' . $id);
            $mailer->setBody(Text::sprintf('COM_JEM_TOOLS_MAIL_BODY', $id));
            $mailer->isHtml(false);
            $mailer->addStringAttachment($json, $this->diagnosticFilename(), 'base64', 'application/json');
            foreach ($files as $file) {
                $mailer->addStringAttachment($file['content'], $file['attachment_name'], 'base64', 'text/plain');
            }
            if (!$mailer->send()) {
                throw new \RuntimeException('Joomla could not deliver the diagnostic report.');
            }

            $this->setRedirect($url, Text::_('COM_JEM_TOOLS_REPORT_SENT'));
        } catch (\Throwable $error) {
            $this->setRedirect($url, Text::_('COM_JEM_TOOLS_SEND_FAILED'), 'warning');
        }
    }

    private function diagnosticReportFromPost(): array
    {
        $id = max(0, Factory::getApplication()->input->post->getInt('report_id', 0));
        $stored = (new JemHousekeepingReports())->load($id);
        if (!$stored) {
            throw new \RuntimeException(Text::_('COM_JEM_TOOLS_REPORT_NOT_FOUND'), 404);
        }

        return $stored;
    }

    private function diagnosticFilename(): string
    {
        $timezone = new \DateTimeZone((string) Factory::getConfig()->get('offset', 'UTC'));
        $timestamp = (new \DateTimeImmutable('now', $timezone))->format('Ymd-His');

        return 'jem-diagnostics-' . $timestamp . '.json';
    }

    private function diagnosticSelection(): array
    {
        $post = Factory::getApplication()->input->post;

        return array(
            'settings' => $post->getInt('include_selected_settings', 0) === 1,
            'component' => $post->getInt('include_component_settings', 0) === 1,
            'inventory' => $post->getInt('include_inventory', 0) === 1,
            'types' => $post->get('include_type_items', array(), 'array'),
            'override_templates' => $post->getInt('include_template_overrides', 0) === 1,
            'override_languages' => $post->getInt('include_language_overrides', 0) === 1,
            'override_css' => $post->getInt('include_css_overrides', 0) === 1,
            'view_types' => $post->get('include_view_types', array(), 'array'),
            'modules' => $post->get('include_module_settings', array(), 'array'),
            'plugins' => $post->get('include_plugin_settings', array(), 'array'),
            'languages' => $post->get('include_language_ini', array(), 'array'),
        );
    }

    private function diagnosticObservations(): string
    {
        $value = trim(Factory::getApplication()->input->post->getString('observations', ''));
        if (mb_strlen($value, 'UTF-8') > 2000) {
            throw new \InvalidArgumentException(Text::_('COM_JEM_TOOLS_OBSERVATIONS_TOO_LONG'));
        }

        return $value;
    }

    /**
     * Restoring settings requires component options permission; other Joomla
     * extensions additionally require global administrator privileges.
     */
    private function allowSettingsReset(string $scope): void
    {
        if (!JemHelperBackend::canManage('core.options')
            || ($scope !== 'component' && !Factory::getApplication()->getIdentity()->authorise('core.admin'))) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * Preview selected defaults without changing stored settings.
     */
    public function previewDefaults(): void
    {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();
        $app = Factory::getApplication();
        $scope = $app->input->post->getCmd('restore_scope', '');
        $this->allowSettingsReset($scope);
        $id = max(0, $app->input->post->getInt('restore_target', 0));
        $selected = $app->input->post->get('restore_fields', array(), 'array');
        $url = 'index.php?option=com_jem&view=housekeeping&tool=restore&scope=' . urlencode($scope)
            . '&target=' . $id;

        try {
            $preview = (new JemHousekeepingDefaults())->preview($scope, $id, (array) $selected);
            $app->setUserState('com_jem.housekeeping.restore_preview', $preview);
            $app->setUserState('com_jem.housekeeping.restore_run', null);
            $message = Text::sprintf('COM_JEM_TOOLS_RESTORE_PREVIEW_READY', count($preview['changes']));
            $type = count($preview['changes']) > 0 ? 'message' : 'warning';
        } catch (\Throwable $error) {
            $app->setUserState('com_jem.housekeeping.restore_preview', null);
            $message = Text::sprintf('COM_JEM_TOOLS_RESTORE_FAILED', $error->getMessage());
            $type = 'warning';
        }

        $this->setRedirect($url, $message, $type);
    }

    /**
     * Apply a fresh session-bound preview after a one-time confirmation.
     */
    public function applyDefaults(): void
    {
        JemHelper::requirePostToken();
        $this->allowHousekeeping();
        $app = Factory::getApplication();
        $preview = $app->getUserState('com_jem.housekeeping.restore_preview', null);
        $scope = is_array($preview) ? (string) ($preview['scope'] ?? '') : '';
        $this->allowSettingsReset($scope);
        $nonce = $app->input->post->getString('restore_nonce', '');
        if (!JemHelper::consumeActionNonce('housekeeping.applyDefaults', $nonce) || !is_array($preview)) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $id = (int) ($preview['id'] ?? 0);
        $url = 'index.php?option=com_jem&view=housekeeping&tool=restore&scope=' . urlencode($scope)
            . '&target=' . $id;
        $app->setUserState('com_jem.housekeeping.restore_preview', null);

        try {
            $changed = (new JemHousekeepingDefaults())->apply($preview);
            $app->setUserState('com_jem.housekeeping.restore_run', array(
                'scope' => $scope,
                'id' => $id,
                'lines' => array_map(static function ($field) {
                    return array('status' => 'notice', 'message' => Text::sprintf(
                        'COM_JEM_TOOLS_RESTORE_LINE',
                        (string) $field['label']
                    ));
                }, $changed),
            ));
            $this->setRedirect(
                $url,
                Text::sprintf('COM_JEM_TOOLS_RESTORE_APPLIED', count($changed))
            );
        } catch (\Throwable $error) {
            $app->setUserState('com_jem.housekeeping.restore_run', array(
                'scope' => $scope,
                'id' => $id,
                'lines' => array(array(
                    'status' => 'error',
                    'message' => Text::sprintf('COM_JEM_TOOLS_RESTORE_FAILED', $error->getMessage()),
                )),
            ));
            $this->setRedirect(
                $url,
                Text::sprintf('COM_JEM_TOOLS_RESTORE_FAILED', $error->getMessage()),
                'warning'
            );
        }
    }

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

        Factory::getApplication()->setUserState('com_jem.housekeeping.image_run', array(
            array('status' => 'notice', 'file' => Text::sprintf('COM_JEM_HOUSEKEEPING_RESIZE_THUMBNAILS_DONE', $total)),
        ));

        $link = 'index.php?option=com_jem&view=housekeeping&tool=images';
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
            'index.php?option=com_jem&view=housekeeping&tool=images',
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
            $app->setUserState('com_jem.housekeeping.image_run', $run['lines']);
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
            'index.php?option=com_jem&view=housekeeping&tool=images',
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
