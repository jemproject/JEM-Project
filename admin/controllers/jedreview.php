<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;

require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/jedreview.php';

class JemControllerJedreview extends BaseController
{
    public function save(): void
    {
        JemHelper::requirePostToken();
        $app = Factory::getApplication();
        if (!JemJedReview::allowed($app, JemAclMigrationStatus::pending())) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $action = $app->input->post->getString('review_action', '');
        $card = $app->input->post->getString('community_card', 'jed');
        if ($action !== 'dismiss' || !isset(JemJedReview::cards()[$card])) {
            throw new InvalidArgumentException(Text::_('JERROR_INVALID_REQUEST'), 400);
        }

        $error = false;
        try {
            JemJedReview::save(Factory::getContainer()->get('DatabaseDriver'), (int) $app->getIdentity()->id, $action, $card);
        } catch (Throwable $exception) {
            $error = true;
            Log::add('Community card preference could not be saved.', Log::WARNING, 'com_jem');
        }

        $message = Text::_($error ? 'COM_JEM_COMMUNITY_ERROR' : 'COM_JEM_COMMUNITY_SAVED');
        if ($app->input->getCmd('format') === 'json') {
            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            $app->setHeader('Cache-Control', 'no-store', true);
            echo new JsonResponse($error ? null : ['dismissed' => true, 'card' => $card], $message, $error, true);
            $app->close();
            return;
        }

        $this->setRedirect('index.php?option=com_jem', $message, $error ? 'error' : 'message');
    }
}
