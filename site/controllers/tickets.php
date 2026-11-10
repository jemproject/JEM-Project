<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

require_once JPATH_SITE . '/components/com_jem/classes/ticketservice.class.php';

/** POST-only operations shared by the frontend and administrator clients. */
class JemControllerTickets extends BaseController
{
    /** @return void Read a locator without changing payment or admission. */
    public function lookup(): void
    {
        $this->respond('lookup');
    }

    /** @return void Confirm an actual external collection. */
    public function payment(): void
    {
        $this->respond('payment');
    }

    /** @return void Record explicitly selected places entering the event. */
    public function admission(): void
    {
        $this->respond('admission');
    }

    /**
     * Validate POST/CSRF once and serialize only authorised service results.
     * @param string $action Public controller operation.
     * @return void
     */
    private function respond(string $action): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->sendHeaders();
        try {
            $this->requirePost();
            $input = [];
            foreach (['reference', 'version', 'operation', 'quantity', 'pool', 'amount', 'currency', 'method', 'receipt'] as $name) {
                $input[$name] = $app->input->post->getString($name, '');
            }
            $input['reference'] = strtoupper(trim($input['reference']));
            $input['event_id'] = $app->input->post->getInt('event_id');
            $service = new JemTicketService(Factory::getContainer()->get('DatabaseDriver'));
            $data = $action === 'lookup'
                ? $service->lookup($input['reference'], $input['event_id'], $app->getIdentity())
                : $service->execute($action, $input, $app->getIdentity());
            echo json_encode(['success' => true, 'data' => $data], JSON_THROW_ON_ERROR);
        } catch (DomainException | InvalidArgumentException $error) {
            http_response_code($error->getMessage() === 'JERROR_ALERTNOAUTHOR' ? 403
                : ($error->getMessage() === 'COM_JEM_TK_STALE' ? 409 : 400));
            echo json_encode(['success' => false, 'message' => Text::_($error->getMessage())]);
        } catch (Throwable $error) {
            http_response_code(500);
            JemHelper::addLogEntry('Tickets operation failed: ' . get_class($error), __METHOD__, \Joomla\CMS\Log\Log::ERROR);
            echo json_encode(['success' => false, 'message' => Text::_('COM_JEM_TK_ERROR')]);
        }
        $app->close();
    }

    /** @return void Reject GET writes and invalid/expired sessions. */
    private function requirePost(): void
    {
        $app = Factory::getApplication();
        if ($app->input->getMethod() !== 'POST' || !Session::checkToken('post') || !(int) $app->getIdentity()->id) {
            throw new DomainException('JERROR_ALERTNOAUTHOR');
        }
    }

    /** @return void Download the current owner's or authorised staff's private PDF. */
    public function download(): void
    {
        $app = Factory::getApplication();
        try {
            $this->requirePost();
            $service = new JemTicketService(Factory::getContainer()->get('DatabaseDriver'));
            $ticket = $service->document(strtoupper(trim($app->input->post->getString('reference'))),
                $app->input->post->getInt('event_id'), $app->getIdentity());
        } catch (DomainException $error) {
            throw new RuntimeException(Text::_($error->getMessage()),
                $error->getMessage() === 'JERROR_ALERTNOAUTHOR' ? 403 : 404);
        }
        require_once JPATH_SITE . '/components/com_jem/classes/ticketpdf.class.php';
        $bytes = JemTicketPdf::render($ticket);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $app->setHeader('Content-Type', 'application/pdf', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="reservation-' . $ticket['reference'] . '.pdf"', true);
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->sendHeaders();
        echo $bytes;
        $app->close();
    }
}
