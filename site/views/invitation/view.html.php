<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once JPATH_SITE . '/components/com_jem/classes/invitationservice.class.php';

/** Restricted invitation portal; never grants Joomla login or general event access. */
class JemViewInvitation extends JemView
{
    public array $invitationRows = [];
    public string $invitationToken = '';
    public int $invitationId = 0;
    public bool $answered = false;
    public bool $canAccept = true;

    /** @param string|null $tpl Layout suffix. @return void */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');
        // The Joomla Page Cache plugin has its own storage policy; HTTP headers alone
        // do not exclude a guest's session-scoped confirmation page from that cache.
        $app->getDispatcher()->addListener('onPageCacheIsExcluded', static function ($event): void {
            $event->addResult(true);
        });
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->setHeader('Referrer-Policy', 'no-referrer', true);
        $app->getDocument()->setMetadata('robots', 'noindex, nofollow');
        $this->invitationToken = $app->input->getString('invitation_token', '');
        $this->invitationId = $app->input->getInt('invitation_id', 0);
        try {
            if ($this->invitationToken !== '' || $this->invitationId > 0) {
                $service = new JemInvitationService($db);
                $owner = $this->invitationToken === '';
                $rows = $owner ? $service->resolveOwned($this->invitationId, $app->getIdentity()) : $service->resolve($this->invitationToken);
                try {
                    $preview = $owner ? $service->respondOwned($this->invitationId, $app->getIdentity(), true, true)
                        : $service->respond($this->invitationToken, true, true);
                    $app->getSession()->set('com_jem.invitation.response', [
                        'digest' => $owner ? 'owner:' . (int) $app->getIdentity()->id . ':' . $this->invitationId : JemInvitationPolicy::digest($this->invitationToken),
                        'fingerprint' => JemInvitationService::responseFingerprint($preview),
                    ]);
                    foreach ($rows as $index => $row) {
                        $row->preview_total = $preview[$index]->grand_total ?? '0.00';
                        $row->preview_currency = $preview[$index]->currency ?? '';
                        $row->preview_waiting = (bool) $preview[$index]->waiting;
                    }
                } catch (Throwable $error) {
                    $this->canAccept = false;
                    $app->enqueueMessage(Text::_($error instanceof DomainException ? $error->getMessage() : 'COM_JEM_INV_ERROR'), 'warning');
                }
            } else {
                $holder = $app->getSession()->get('com_jem.invitation.holder', []);
                $ids = array_map('intval', $holder['ids'] ?? []);
                if (!$ids || ($holder['until'] ?? 0) < time()) {
                    throw new DomainException('COM_JEM_INV_INVALID_LINK');
                }
                $rows = $db->setQuery('SELECT * FROM #__jem_register WHERE id IN (' . implode(',', $ids) . ') ORDER BY event')->loadObjectList();
                $this->answered = true;
            }
            foreach ($rows as $row) {
                $event = $db->setQuery('SELECT title,dates,times FROM #__jem_events WHERE id=' . (int) $row->event)->loadObject();
                if ($event) {
                    $this->invitationRows[] = ['registration' => $row, 'event' => $event];
                }
            }
        } catch (DomainException $error) {
            $app->enqueueMessage(Text::_('COM_JEM_INV_INVALID_LINK'), 'warning');
        }
        parent::display($tpl);
    }
}
