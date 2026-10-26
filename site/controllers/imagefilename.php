<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

require_once JPATH_SITE . '/components/com_jem/classes/imagefilenamepolicy.class.php';

/**
 * Read-only name check for frontend image upload forms.
 */
class JemControllerImagefilename extends BaseController
{
    public function check()
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
            if ($app->getIdentity()->guest) {
                throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
            if (JemImageFilenamePolicy::mode(JemHelper::config()) !== JemImageFilenamePolicy::ORIGINAL) {
                throw new RuntimeException(Text::_('COM_JEM_IMAGE_FILENAME_INVALID'), 400);
            }

            // Only a boolean for a JEM-managed public image folder is returned.
            // The subsequent form save still enforces record ACL and rechecks the file.
            echo new JsonResponse(JemImageFilenamePolicy::checkExisting(
                $app->input->post->getCmd('resource', ''),
                $app->input->post->getString('image_path', ''),
                $app->input->post->getString('filename', ''),
                $app->input->post->getString('stem', ''),
                $app->input->post->get('context', array(), 'array')
            ));
        } catch (Throwable $exception) {
            echo new JsonResponse($exception);
        }

        $app->close();
    }
}
