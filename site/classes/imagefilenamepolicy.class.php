<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;

/**
 * Resolve names for JEM-managed image uploads. Attachment names are not affected.
 */
class JemImageFilenamePolicy
{
    public const UNIQUE = 'unique';
    public const ORIGINAL = 'original';

    public static function mode($settings): string
    {
        return (string) ($settings->image_filename_policy ?? self::UNIQUE) === self::ORIGINAL
            ? self::ORIGINAL
            : self::UNIQUE;
    }

    public static function canReplace(string $resource, int $parentId = 1): bool
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        if ($user->guest) {
            return false;
        }

        if ($app->isClient('administrator')) {
            if ($resource === 'event') {
                return JemHelperBackend::canCreateEvent();
            }
            if ($resource === 'venue') {
                return JemHelperBackend::can('venue', 'create');
            }

            if ($resource === 'category') {
                $asset = $parentId > 1 ? 'com_jem.category.' . $parentId : 'com_jem';

                return $user->authorise('core.create', $asset);
            }

            return false;
        }

        $jemUser = JemFactory::getUser();

        if ($resource === 'event') {
            return JemFrontendAccess::canAddEventSomewhere($jemUser);
        }
        if ($resource === 'venue') {
            return JemFrontendAccess::canAdd($jemUser, 'venue');
        }

        return $resource === 'category'
            && JemFrontendCategoryAccess::canCreate($jemUser, $parentId);
    }

    /**
     * Show the same collision choices in direct forms and the image popup.
     */
    public static function renderControls(
        string $field,
        string $resource,
        string $fileId,
        string $imagePath = '',
        int $recordId = 0,
        int $parentId = 1
    ): string
    {
        static $scriptLoaded = false;

        if (!$scriptLoaded) {
            $mediaRoot = JPATH_SITE . '/media/com_jem/';
            $stylePath = $mediaRoot . 'css/image-filename-policy.css';
            $scriptPath = $mediaRoot . 'js/image-filename-policy.js';
            Factory::getApplication()->getDocument()->getWebAssetManager()
                ->registerAndUseStyle(
                    'jem.image-filename-policy',
                    'com_jem/image-filename-policy.css',
                    array('version' => is_file($stylePath) ? (string) filemtime($stylePath) : 'auto')
                )
                ->registerScript(
                    'jem.image-filename-policy',
                    'com_jem/image-filename-policy.js',
                    array('version' => is_file($scriptPath) ? (string) filemtime($scriptPath) : 'auto')
                )
                ->useScript('jem.image-filename-policy');
            $scriptLoaded = true;
        }

        $app = Factory::getApplication();
        $mayReplace = self::canReplace($resource, $parentId);
        $key = htmlspecialchars($field, ENT_QUOTES, 'UTF-8');
        $endpoint = $app->isClient('administrator')
            ? 'index.php?option=com_jem&task=imagehandler.checkFilename&format=json'
            : Uri::root() . 'index.php?option=com_jem&task=imagefilename.check&format=json';
        $html = '<div class="jem-image-filename-policy mt-2"'
            . ' data-jem-image-file-id="' . htmlspecialchars($fileId, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-jem-image-resource="' . htmlspecialchars($resource, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-jem-image-path="' . htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-jem-image-record-id="' . $recordId . '"'
            . ' data-jem-image-check-url="' . htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-jem-image-token="' . Session::getFormToken() . '"'
            . ' data-jem-image-checking="' . htmlspecialchars(Text::_('COM_JEM_IMAGE_NAME_CHECKING'), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-jem-image-available="' . htmlspecialchars(Text::_('COM_JEM_IMAGE_NAME_AVAILABLE'), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-jem-image-conflict="' . htmlspecialchars(Text::_('COM_JEM_IMAGE_NAME_CONFLICT'), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-jem-image-check-failed="' . htmlspecialchars(Text::_('COM_JEM_IMAGE_NAME_CHECK_FAILED'), ENT_QUOTES, 'UTF-8') . '">'
            . '<label class="form-label" for="jem-image-name-' . $key . '">'
            . Text::_('COM_JEM_IMAGE_UPLOAD_NAME') . '</label>'
            . '<input class="form-control" id="jem-image-name-' . $key . '" name="jem_image_name[' . $key . ']"'
            . ' type="text" maxlength="95" placeholder="' . Text::_('COM_JEM_IMAGE_UPLOAD_NAME_HINT') . '">'
            . '<div class="jem-image-name-status alert mt-2" role="status" hidden></div>'
            . '<dialog class="jem-image-collision-dialog" aria-labelledby="jem-image-collision-title-' . $key . '"'
            . ' aria-describedby="jem-image-collision-message-' . $key . '">'
            . '<h3 id="jem-image-collision-title-' . $key . '">'
            . Text::_('COM_JEM_IMAGE_COLLISION_TITLE') . '</h3>'
            . '<p id="jem-image-collision-message-' . $key . '" class="jem-image-collision-message"></p>'
            . '<label class="form-label mt-2" for="jem-image-conflict-' . $key . '">'
            . Text::_('COM_JEM_IMAGE_NAME_COLLISION_LABEL') . '</label>'
            . '<select class="form-select" id="jem-image-conflict-' . $key . '" name="jem_image_conflict[' . $key . ']">'
            . '<option value="">' . Text::_('COM_JEM_IMAGE_UPLOAD_CHOOSE') . '</option>'
            . '<option value="reuse">' . Text::_('COM_JEM_IMAGE_UPLOAD_REUSE') . '</option>'
            . '<option value="copy">' . Text::_('COM_JEM_IMAGE_UPLOAD_COPY') . '</option>';

        if ($mayReplace) {
            $html .= '<option value="replace">' . Text::_('COM_JEM_IMAGE_UPLOAD_REPLACE') . '</option>';
        }

        $html .= '</select>';

        if ($mayReplace) {
            $html .= '<label class="form-check mt-2 jem-image-replace-confirm" hidden>'
                . '<input class="form-check-input" type="checkbox" value="1"'
                . ' name="jem_image_replace_confirm[' . $key . ']">'
                . '<span class="form-check-label">' . Text::_('COM_JEM_IMAGE_UPLOAD_REPLACE_CONFIRM') . '</span>'
                . '</label>';
        }

        return $html
            . '<div class="jem-image-collision-actions">'
            . '<button type="button" class="btn btn-secondary" data-jem-image-rename>'
            . Text::_('COM_JEM_IMAGE_COLLISION_RENAME') . '</button>'
            . '<button type="button" class="btn btn-secondary" data-jem-image-cancel>'
            . Text::_('JCANCEL') . '</button>'
            . '<button type="button" class="btn btn-primary" data-jem-image-accept>'
            . Text::_('COM_JEM_IMAGE_COLLISION_CONTINUE') . '</button>'
            . '</div></dialog></div>';
    }

    /**
     * Check the same destination folder and safe filename used by the upload.
     *
     * @return array{filename: string, exists: bool}
     */
    public static function checkExisting(
        string $resource,
        string $imagePath,
        string $uploadedName,
        string $requestedStem,
        array $context = array()
    ): array {
        $classes = array(
            'event' => 'JemEventImagePath',
            'venue' => 'JemVenueImagePath',
            'category' => 'JemCategoryImagePath',
        );

        if (!isset($classes[$resource])) {
            throw new RuntimeException(Text::_('COM_JEM_IMAGE_FILENAME_INVALID'));
        }

        require_once __DIR__ . '/' . $resource . 'imagepath.class.php';
        $class = $classes[$resource];
        if ($resource === 'event' && $imagePath === '' && $context !== array()) {
            $imagePath = JemEventImagePath::configuredFolderFromEvent((object) $context);
        }
        $folder = $class::absoluteImageFolder($class::normaliseRelativeFolder($imagePath));
        $filename = self::originalName($uploadedName, $requestedStem);
        $target = rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($target) && !is_file($target)) {
            throw new RuntimeException(Text::_('COM_JEM_IMAGE_FILENAME_INVALID'));
        }

        return array('filename' => $filename, 'exists' => is_file($target));
    }

    /**
     * Return a safe filename with the original extension and no random suffix.
     */
    public static function originalName(string $filename, string $requestedStem = ''): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $stem = $requestedStem !== '' ? $requestedStem : (string) pathinfo($filename, PATHINFO_FILENAME);
        $stem = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '_', $stem));
        $stem = trim($stem, '_');

        if ($stem === '' || $extension === '' || !preg_match('/\A[a-z0-9]+\z/D', $extension)) {
            throw new RuntimeException(Text::_('COM_JEM_IMAGE_FILENAME_INVALID'));
        }

        // Event and venue image columns have a 100-character limit.
        $stem = substr($stem, 0, 99 - strlen($extension));

        return $stem . '.' . $extension;
    }

    /**
     * @return array{filename: string, action: string}
     */
    public static function resolve(string $folder, string $uploadedName, $settings, string $field): array
    {
        if (self::mode($settings) === self::UNIQUE) {
            if (!class_exists('JemImage', false)) {
                require_once JPATH_SITE . '/components/com_jem/classes/image.class.php';
            }

            return array('filename' => JemImage::sanitize($folder, $uploadedName), 'action' => 'upload');
        }

        $input = Factory::getApplication()->input;
        $names = $input->post->get('jem_image_name', array(), 'array');
        $actions = $input->post->get('jem_image_conflict', array(), 'array');
        $requestedStem = is_array($names) ? trim((string) ($names[$field] ?? '')) : '';
        $action = is_array($actions) ? (string) ($actions[$field] ?? '') : '';
        $filename = self::originalName($uploadedName, $requestedStem);
        $target = rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($target)) {
            return array('filename' => $filename, 'action' => 'upload');
        }

        if (!is_file($target)) {
            throw new RuntimeException(Text::_('COM_JEM_IMAGE_FILENAME_INVALID'));
        }

        if ($action === 'reuse') {
            return array('filename' => $filename, 'action' => 'reuse');
        }

        if ($action === 'copy') {
            if (!class_exists('JemImage', false)) {
                require_once JPATH_SITE . '/components/com_jem/classes/image.class.php';
            }

            return array('filename' => JemImage::sanitize($folder, $filename), 'action' => 'upload');
        }

        $confirmations = $input->post->get('jem_image_replace_confirm', array(), 'array');
        $form = $input->post->get('jform', array(), 'array');
        $parentId = is_array($form)
            ? (int) ($form['parent_id'] ?? $input->post->getInt('parent_id', 1))
            : $input->post->getInt('parent_id', 1);
        if ($action === 'replace'
            && self::canReplace(self::resourceForFolder($folder), $parentId)
            && is_array($confirmations)
            && (int) ($confirmations[$field] ?? 0) === 1) {
            return array('filename' => $filename, 'action' => 'replace');
        }

        throw new RuntimeException(Text::_('COM_JEM_IMAGE_FILENAME_EXISTS'));
    }

    /**
     * Resolve the resource from the server-selected JEM image folder.
     */
    private static function resourceForFolder(string $folder): string
    {
        $folder = str_replace('\\', '/', rtrim(Path::clean($folder), '/\\'));

        foreach (array('events' => 'event', 'venues' => 'venue', 'categories' => 'category') as $directory => $resource) {
            $base = str_replace('\\', '/', Path::clean(JPATH_SITE . '/images/jem/' . $directory));

            if ($folder === $base || str_starts_with($folder, $base . '/')) {
                return $resource;
            }
        }

        return '';
    }

    /**
     * Process one profile image without losing an existing file if the record save fails.
     * Newly created files and replacement backups are returned separately for rollback.
     *
     * @return string|false
     */
    public static function uploadProfile(
        array $file,
        string $folder,
        string $thumbnailFolder,
        $settings,
        string $profile,
        $dimension,
        $ratio,
        string $field,
        array &$newFiles,
        array &$replacements
    ) {
        if (!class_exists('JemImage', false)) {
            require_once JPATH_SITE . '/components/com_jem/classes/image.class.php';
        }

        if (!JemImageProfilePolicy::isProfile($profile)
            || JemImage::check($file, $settings, $profile) === false) {
            return false;
        }

        try {
            $choice = self::resolve($folder, (string) $file['name'], $settings, $field);
        } catch (RuntimeException $exception) {
            Factory::getApplication()->enqueueMessage($exception->getMessage(), 'warning');

            return false;
        }

        $filename = $choice['filename'];
        $target = rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $thumbnail = rtrim($thumbnailFolder, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if ($choice['action'] === 'reuse') {
            return $filename;
        }

        foreach ($newFiles as $path) {
            if ($path === $target) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_IMAGE_FILENAME_EXISTS'), 'warning');

                return false;
            }
        }

        $backup = array();
        if ($choice['action'] === 'replace') {
            $backup = JemImage::backupReplacement($target, $thumbnail);

            if (!$backup) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_UPLOAD_FAILED'), 'warning');

                return false;
            }
        }

        if (!JemImage::uploadProfileImage(
            $file,
            $target,
            $thumbnail,
            $settings,
            $profile,
            $dimension,
            $ratio,
            $choice['action'] === 'replace'
        )) {
            if ($backup) {
                JemImage::finishReplacement($backup, false);
            }

            return false;
        }

        if ($backup) {
            $replacements[] = $backup;
        } else {
            $newFiles[] = $target;
            $newFiles[] = $thumbnail;
        }

        return $filename;
    }

    /**
     * Complete an upload batch after the database save, or restore its old files.
     */
    public static function finish(array $newFiles, array $replacements, bool $committed): void
    {
        if (!$committed) {
            foreach ($newFiles as $path) {
                if (File::exists($path)) {
                    File::delete($path);
                }
            }
        }

        foreach ($replacements as $backup) {
            JemImage::finishReplacement($backup, $committed);
        }
    }
}
