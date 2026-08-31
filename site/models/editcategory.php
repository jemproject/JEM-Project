<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/category.php';

/**
 * Frontend model for creating and editing JEM categories.
 */
class JemModelEditcategory extends JemModelCategory
{
    public $typeAlias = 'com_jem.category';

    /**
     * Populate site editor state without accepting an unrelated menu item id.
     */
    protected function populateState()
    {
        $app = Factory::getApplication();
        $id = $app->input->getInt('a_id', 0);
        $parentId = $app->input->getInt('parent_id', 1);
        $return = $app->input->get('return', '', 'base64');
        $decodedReturn = $return ? base64_decode($return, true) : false;

        $this->setState('category.id', $id);
        $this->setState('editcategory.id', $id);
        $this->setState('category.parent_id', $parentId > 0 ? $parentId : 1);
        $this->setState('return_page', ($decodedReturn && Uri::isInternal($decodedReturn)) ? $decodedReturn : '');
        $this->setState('params', $app->getParams('com_jem'));
        $this->setState('layout', $app->input->getCmd('layout', 'edit'));
    }

    /**
     * Load the dedicated, deliberately restricted frontend form.
     */
    public function getForm($data = array(), $loadData = true)
    {
        return $this->loadForm(
            'com_jem.category',
            'category',
            array('control' => 'jform', 'load_data' => $loadData)
        );
    }

    /**
     * Add frontend view parameters to the backend category row.
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item) {
            return false;
        }

        if (empty($item->parent_id)) {
            $item->parent_id = 1;
        }

        $item->params = new Registry();

        return $item;
    }

    /**
     * Return the validated internal return URL in base64 form for the template.
     */
    public function getReturnPage()
    {
        return base64_encode((string) $this->getState('return_page'));
    }

    /**
     * Apply an explicit frontend allowlist and validate every security-sensitive value.
     */
    public function save($submittedData)
    {
        $app = Factory::getApplication();
        $user = JemFactory::getUser();
        $recordId = (int) $this->getState('editcategory.id');
        $submittedId = (int) ($submittedData['id'] ?? 0);

        if ($submittedId !== $recordId) {
            $this->setError(Text::_('COM_JEM_ERROR_INVALID_RECORD_ID'));

            return false;
        }

        $stored = null;

        if ($recordId > 0) {
            $stored = parent::getItem($recordId);

            if (!$stored || (int) $stored->id !== $recordId
                || !JemFrontendCategoryAccess::canEdit($user, $stored)) {
                $this->setError(Text::_('JERROR_ALERTNOAUTHOR'));

                return false;
            }
        } elseif (!JemFrontendCategoryAccess::canCreate($user, (int) ($submittedData['parent_id'] ?? 1))) {
            $this->setError(Text::_('JERROR_ALERTNOAUTHOR'));

            return false;
        }

        $allowed = array_flip(array(
            'id', 'catname', 'alias', 'parent_id', 'type_id', 'color', 'description',
            'image', 'image_as_default', 'event_image_default_storage', 'published',
            'access', 'language', 'meta_keywords', 'meta_description',
        ));
        $data = array_intersect_key((array) $submittedData, $allowed);
        $data['id'] = $recordId;
        $data['catname'] = trim(strip_tags((string) ($data['catname'] ?? '')));
        $data['alias'] = trim(strip_tags((string) ($data['alias'] ?? '')));
        $data['description'] = ComponentHelper::filterText((string) ($data['description'] ?? ''));
        $data['meta_keywords'] = trim(strip_tags((string) ($data['meta_keywords'] ?? '')));
        $data['meta_description'] = trim(strip_tags((string) ($data['meta_description'] ?? '')));
        $data['color'] = trim((string) ($data['color'] ?? ''));

        if (strlen($data['catname']) > 100 || strlen($data['alias']) > 100) {
            $this->setError(Text::_('COM_JEM_CATEGORY_ERROR_INVALID_TEXT'));

            return false;
        }

        if ($data['color'] !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'])) {
            $this->setError(Text::_('COM_JEM_CATEGORY_ERROR_INVALID_COLOR'));

            return false;
        }

        $parentId = max(1, (int) ($data['parent_id'] ?? 1));

        if (!$this->validateParent($parentId, $recordId, $stored, $user)) {
            return false;
        }

        $data['parent_id'] = $parentId;

        if (!$this->validateType((int) ($data['type_id'] ?? 0), $user)) {
            return false;
        }

        $data['type_id'] = (int) ($data['type_id'] ?? 0) ?: null;
        $data['access'] = (int) ($data['access'] ?? ($stored->access ?? 1));

        if (!JemFrontendCategoryAccess::canAssignAccess($user, $data['access'])) {
            $this->setError(Text::_('JERROR_ALERTNOAUTHOR'));

            return false;
        }

        $data['language'] = $this->validateLanguage((string) ($data['language'] ?? ($stored->language ?? '*')));

        if ($data['language'] === false) {
            $this->setError(Text::_('COM_JEM_CATEGORY_ERROR_INVALID_LANGUAGE'));

            return false;
        }

        if (JemFrontendCategoryAccess::canEditState($user, $stored, $parentId)) {
            $data['published'] = (int) ($data['published'] ?? 0) === 1 ? 1 : 0;
        } else {
            $data['published'] = $stored ? (int) $stored->published : 0;
        }

        $data['image_as_default'] = !empty($data['image_as_default']) ? 1 : 0;
        $data['event_image_default_storage'] = in_array(
            (string) ($data['event_image_default_storage'] ?? ''),
            array('shared_root', 'event_folder'),
            true
        ) ? (string) $data['event_image_default_storage'] : 'shared_root';

        // Preserve backend-only values. They are never accepted from the frontend request.
        $data['groupid'] = (int) ($stored->groupid ?? 0);
        $data['title'] = $data['catname'];
        $data['note'] = (string) ($stored->note ?? '');
        $data['path'] = (string) ($stored->path ?? '');
        $data['metadata'] = $stored->metadata ?? '';
        $data['article_category_id'] = (int) ($stored->article_category_id ?? 0);
        $data['article_create_mode'] = (int) ($stored->article_create_mode ?? 0);
        $data['email'] = (string) ($stored->email ?? '');
        $data['emailacljl'] = (int) ($stored->emailacljl ?? 0);
        $data['created_user_id'] = $stored
            ? (int) $stored->created_user_id
            : (int) $user->id;

        $uploadedPaths = array();

        if (!$this->prepareImage($data, $uploadedPaths)) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->transactionStart();

        try {
            if (!parent::save($data)) {
                $db->transactionRollback();
                $this->removeUploadedPaths($uploadedPaths);

                return false;
            }

            $db->transactionCommit();

            return true;
        } catch (Throwable $exception) {
            $db->transactionRollback();
            $this->removeUploadedPaths($uploadedPaths);
            throw $exception;
        }
    }

    /**
     * Validate the selected parent and prevent self/descendant moves.
     */
    protected function validateParent($parentId, $recordId, $stored, $user)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'lft', 'rgt', 'access')))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('id') . ' = ' . (int) $parentId);
        $db->setQuery($query, 0, 1);
        $parent = $db->loadObject();

        if (!$parent || !JemFrontendCategoryAccess::canView($user, $parent)) {
            $this->setError(Text::_('COM_JEM_CATEGORY_ERROR_INVALID_PARENT'));

            return false;
        }

        if ($recordId > 0) {
            if ($parentId === $recordId
                || ((int) $parent->lft > (int) $stored->lft && (int) $parent->rgt < (int) $stored->rgt)) {
                $this->setError(Text::_('COM_JEM_CATEGORY_ERROR_INVALID_PARENT'));

                return false;
            }

            if ($parentId === (int) $stored->parent_id) {
                return true;
            }
        }

        if (!JemFrontendCategoryAccess::canCreate($user, $parentId)) {
            $this->setError(Text::_('JERROR_ALERTNOAUTHOR'));

            return false;
        }

        return true;
    }

    /**
     * Validate the optional JEM Category Type against entity and view level.
     */
    protected function validateType($typeId, $user)
    {
        if ($typeId < 1) {
            return true;
        }

        $levels = array_map('intval', (array) $user->getAuthorisedViewLevels());
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('id') . ' = ' . (int) $typeId)
            ->where($db->quoteName('entity') . ' = 2')
            ->where($db->quoteName('published') . ' = 1');

        if ($levels) {
            $query->where($db->quoteName('access') . ' IN (' . implode(',', $levels) . ')');
        } else {
            $query->where('1 = 0');
        }

        $db->setQuery($query);

        if ((int) $db->loadResult() !== 1) {
            $this->setError(Text::_('COM_JEM_CATEGORY_ERROR_INVALID_TYPE'));

            return false;
        }

        return true;
    }

    /**
     * Accept all-languages or one installed Joomla content language.
     */
    protected function validateLanguage($language)
    {
        $language = trim($language);

        if ($language === '*') {
            return '*';
        }

        if (!preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $language)) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('lang_code') . ' = ' . $db->quote($language))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);

        return (int) $db->loadResult() === 1 ? $language : false;
    }

    /**
     * Validate a server image or publish a new profile-checked upload.
     */
    protected function prepareImage(array &$data, array &$uploadedPaths)
    {
        $app = Factory::getApplication();
        $settings = JemHelper::config();
        $files = $app->input->files;
        $file = $files->get('userfile', array(), 'array');
        $nestedFiles = $files->get('jform', array(), 'array');

        if (!empty($nestedFiles['userfile'])) {
            $file = $nestedFiles['userfile'];
        }

        $hasUpload = !empty($file['name']);
        $removeImage = $app->input->post->getInt('removeimage', 0) === 1;
        $directory = Path::clean(JPATH_SITE . '/images/jem/categories');
        $thumbnailDirectory = Path::clean($directory . '/small');

        if ($hasUpload) {
            $filename = JemImage::sanitize($directory . DIRECTORY_SEPARATOR, (string) $file['name']);
            $target = Path::clean($directory . DIRECTORY_SEPARATOR . $filename);
            $thumbnail = Path::clean($thumbnailDirectory . DIRECTORY_SEPARATOR . $filename);
            $requestedDimension = $app->input->post->getInt(
                'image_max_dimension',
                JemImageProfilePolicy::defaultUploadMaxDimension($settings, JemImageProfilePolicy::CATEGORY)
            );
            $requestedRatio = $app->input->post->getCmd('image_ratio', '');

            if (!JemImage::uploadProfileImage(
                $file,
                $target,
                $thumbnail,
                $settings,
                JemImageProfilePolicy::CATEGORY,
                $requestedDimension,
                $requestedRatio
            )) {
                $this->setError(Text::_('COM_JEM_UPLOAD_FAILED'));

                return false;
            }

            $uploadedPaths = array($target, $thumbnail);
            $data['image'] = $filename;

            return true;
        }

        if ($removeImage) {
            $data['image'] = '';

            return true;
        }

        $image = trim((string) ($data['image'] ?? ''));

        if ($image === '') {
            $data['image'] = '';

            return true;
        }

        $safeImage = File::makeSafe(basename($image));
        $extension = strtolower(File::getExt($safeImage));

        if ($safeImage !== $image
            || !in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)
            || !is_file(Path::clean($directory . DIRECTORY_SEPARATOR . $safeImage))) {
            $this->setError(Text::_('COM_JEM_CATEGORY_ERROR_INVALID_IMAGE'));

            return false;
        }

        $data['image'] = $safeImage;

        return true;
    }

    /**
     * Remove only the unique files created by the failed request.
     */
    protected function removeUploadedPaths(array $paths)
    {
        foreach ($paths as $path) {
            if ($path !== '' && File::exists($path)) {
                File::delete($path);
            }
        }
    }
}
