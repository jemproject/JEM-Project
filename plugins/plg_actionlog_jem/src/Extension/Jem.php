<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

namespace Joomla\Plugin\Actionlog\Jem\Extension;

use Joomla\CMS\Event\Model\AfterChangeStateEvent;
use Joomla\CMS\Event\Model\AfterDeleteEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Actionlogs\Administrator\Plugin\ActionLogPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use RuntimeException;
use Throwable;

defined('_JEXEC') or die;

/**
 * JEM user action log integration.
 */
final class Jem extends ActionLogPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $autoloadLanguage = true;

    private const CONTEXTS = array(
        'com_jem.event' => array(
            'type' => 'PLG_ACTIONLOG_JEM_TYPE_EVENT',
            'title' => 'title',
            'id' => 'id',
            'link' => 'index.php?option=com_jem&task=event.edit&id=%d',
            'param' => 'log_events',
        ),
        'com_jem.venue' => array(
            'type' => 'PLG_ACTIONLOG_JEM_TYPE_VENUE',
            'title' => 'venue',
            'id' => 'id',
            'link' => 'index.php?option=com_jem&task=venue.edit&id=%d',
            'param' => 'log_venues',
        ),
        'com_jem.category' => array(
            'type' => 'PLG_ACTIONLOG_JEM_TYPE_CATEGORY',
            'title' => 'catname',
            'id' => 'id',
            'link' => 'index.php?option=com_jem&task=category.edit&id=%d',
            'param' => 'log_categories',
        ),
        'com_jem.type' => array(
            'type' => 'PLG_ACTIONLOG_JEM_TYPE_TYPE',
            'title' => 'name',
            'id' => 'id',
            'link' => 'index.php?option=com_jem&task=type.edit&id=%d',
            'param' => 'log_types',
        ),
        'com_jem.group' => array(
            'type' => 'PLG_ACTIONLOG_JEM_TYPE_GROUP',
            'title' => 'name',
            'id' => 'id',
            'link' => 'index.php?option=com_jem&task=group.edit&id=%d',
            'param' => 'log_groups',
        ),
        'com_jem.attachment' => array(
            'type' => 'PLG_ACTIONLOG_JEM_TYPE_ATTACHMENT',
            'title' => 'name',
            'id' => 'id',
            'link' => 'index.php?option=com_jem&task=attachment.edit&id=%d',
            'param' => 'log_attachments',
        ),
    );

    public static function getSubscribedEvents(): array
    {
        return array(
            'onContentAfterSave' => 'onContentAfterSave',
            'onContentAfterDelete' => 'onContentAfterDelete',
            'onContentChangeState' => 'onContentChangeState',
            'onJemAfterAttendeeSave' => 'onJemAfterAttendeeSave',
            'onJemAfterAttendeeDelete' => 'onJemAfterAttendeeDelete',
            'onJemAfterAttendeeStatusChange' => 'onJemAfterAttendeeStatusChange',
            'onJemAfterAttachmentSave' => 'onJemAfterAttachmentSave',
            'onJemAfterAttachmentDelete' => 'onJemAfterAttachmentDelete',
        );
    }

    public function onContentAfterSave(AfterSaveEvent $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $context = $event->getContext();

        if (!isset(self::CONTEXTS[$context]) || !$this->isContextEnabled($context)) {
            return;
        }

        $item = $event->getItem();
        $isNew = $event->getIsNew();
        $message = $this->createMessage($context, $item, $isNew ? 'add' : 'update');

        $this->addLog(array($message), $isNew ? 'PLG_ACTIONLOG_JEM_ITEM_ADDED' : 'PLG_ACTIONLOG_JEM_ITEM_UPDATED', $context);
    }

    public function onContentAfterDelete(AfterDeleteEvent $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $context = $event->getContext();

        if (!isset(self::CONTEXTS[$context]) || !$this->isContextEnabled($context)) {
            return;
        }

        $message = $this->createMessage($context, $event->getItem(), 'delete', false);

        $this->addLog(array($message), 'PLG_ACTIONLOG_JEM_ITEM_DELETED', $context);
    }

    public function onContentChangeState(AfterChangeStateEvent $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $context = $event->getContext();

        if (!isset(self::CONTEXTS[$context]) || !$this->isContextEnabled($context)) {
            return;
        }

        $state = (int) $event->getValue();
        $action = $this->stateToAction($state);

        if ($action === '') {
            return;
        }

        $items = $this->loadItems($context, $event->getPks());
        $messages = array();

        foreach ($event->getPks() as $pk) {
            $item = $items[(int) $pk] ?? (object) array(self::CONTEXTS[$context]['id'] => (int) $pk);
            $messages[] = $this->createMessage($context, $item, $action);
        }

        $this->addLog($messages, 'PLG_ACTIONLOG_JEM_ITEM_STATE_CHANGED', $context);
    }

    public function onJemAfterAttendeeSave(Event $event): void
    {
        if (!$this->isEnabled() || !$this->isFeatureEnabled('log_attendees')) {
            return;
        }

        $attendee = $this->eventArgument($event, 0);
        $isNew = (bool) $this->eventArgument($event, 1, false);

        if (!is_object($attendee)) {
            return;
        }

        $attendees = $this->loadAttendees(array((int) ($attendee->id ?? 0)));
        $attendee = $attendees[(int) ($attendee->id ?? 0)] ?? $attendee;

        $this->addLog(
            array($this->createAttendeeMessage($attendee)),
            $isNew ? 'PLG_ACTIONLOG_JEM_ATTENDEE_ADDED' : 'PLG_ACTIONLOG_JEM_ATTENDEE_UPDATED',
            'com_jem.attendee'
        );
    }

    public function onJemAfterAttendeeDelete(Event $event): void
    {
        if (!$this->isEnabled() || !$this->isFeatureEnabled('log_attendees')) {
            return;
        }

        $attendee = $this->eventArgument($event, 0);

        if (!is_object($attendee)) {
            return;
        }

        $this->addLog(
            array($this->createAttendeeMessage($attendee)),
            'PLG_ACTIONLOG_JEM_ATTENDEE_DELETED',
            'com_jem.attendee'
        );
    }

    public function onJemAfterAttendeeStatusChange(Event $event): void
    {
        if (!$this->isEnabled() || !$this->isFeatureEnabled('log_attendees')) {
            return;
        }

        $subject = $this->eventArgument($event, 0);
        $status = (int) $this->eventArgument($event, 1, 0);
        $eventId = (int) $this->eventArgument($event, 2, 0);
        $attendees = array();

        if (is_array($subject)) {
            $attendees = $this->loadAttendees($subject, $eventId);
        } elseif (is_object($subject)) {
            $attendees[] = $subject;
        }

        $messages = array();

        foreach ($attendees as $attendee) {
            $messages[] = $this->createAttendeeMessage($attendee, $status);
        }

        if (!$messages) {
            return;
        }

        $this->addLog($messages, 'PLG_ACTIONLOG_JEM_ATTENDEE_STATUS_CHANGED', 'com_jem.attendee');
    }

    public function onJemAfterAttachmentSave(Event $event): void
    {
        if (!$this->isEnabled() || !$this->isFeatureEnabled('log_attachments')) {
            return;
        }

        $attachment = $this->eventArgument($event, 0);
        $isNew = (bool) $this->eventArgument($event, 1, false);

        if (!is_object($attachment)) {
            return;
        }

        $attachments = $this->loadAttachments(array((int) ($attachment->id ?? 0)));
        $attachment = $attachments[(int) ($attachment->id ?? 0)] ?? $attachment;

        $this->addLog(
            array($this->createAttachmentMessage($attachment)),
            $isNew ? 'PLG_ACTIONLOG_JEM_ATTACHMENT_ADDED' : 'PLG_ACTIONLOG_JEM_ATTACHMENT_UPDATED',
            'com_jem.attachment'
        );
    }

    public function onJemAfterAttachmentDelete(Event $event): void
    {
        if (!$this->isEnabled() || !$this->isFeatureEnabled('log_attachments')) {
            return;
        }

        $attachment = $this->eventArgument($event, 0);

        if (!is_object($attachment)) {
            return;
        }

        $this->addLog(
            array($this->createAttachmentMessage($attachment, false)),
            'PLG_ACTIONLOG_JEM_ATTACHMENT_DELETED',
            'com_jem.attachment'
        );
    }

    private function isEnabled(): bool
    {
        if (!$this->getApplication()->isClient('administrator')) {
            return false;
        }

        try {
            if (!class_exists('JemConfig')) {
                require_once JPATH_SITE . '/components/com_jem/classes/config.class.php';
            }

            $config = \JemConfig::getInstance()->toRegistry();
            $global = $config->get('globalattribs', null);

            if ($global instanceof Registry) {
                return (int) $global->get('actionlog_enabled', 0) === 1;
            }

            if (is_object($global)) {
                return (int) ($global->actionlog_enabled ?? 0) === 1;
            }

            return (int) $config->get('globalattribs.actionlog_enabled', 0) === 1;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function createMessage(string $context, object $item, string $action, bool $includeLink = true): array
    {
        $meta = self::CONTEXTS[$context];
        $idField = $meta['id'];
        $titleField = $meta['title'];
        $id = (int) ($item->{$idField} ?? 0);
        $title = trim((string) ($item->{$titleField} ?? ''));

        if ($title === '' && $context === 'com_jem.attachment') {
            $title = trim((string) ($item->file ?? ''));
        }

        if ($title === '') {
            $title = Text::_('PLG_ACTIONLOG_JEM_UNKNOWN_TITLE');
        }

        $message = array(
            'action' => $action,
            'type' => $meta['type'],
            'id' => $id,
            'title' => $title,
            'extension' => 'COM_JEM',
        );

        if ($includeLink && $id > 0) {
            $message['itemlink'] = sprintf($meta['link'], $id);
        }

        return $message;
    }

    private function createAttendeeMessage(object $attendee, ?int $status = null): array
    {
        $user = $this->getApplication()->getIdentity();
        $id = (int) ($attendee->id ?? 0);
        $eventId = (int) ($attendee->event ?? 0);
        $eventTitle = trim((string) ($attendee->eventtitle ?? ''));
        $attendeeName = trim((string) ($attendee->username ?? $attendee->name ?? ''));

        if ($eventTitle === '') {
            $eventTitle = Text::_('PLG_ACTIONLOG_JEM_UNKNOWN_TITLE');
        }

        if ($attendeeName === '') {
            $attendeeName = Text::_('PLG_ACTIONLOG_JEM_UNKNOWN_ATTENDEE');
        }

        $message = array(
            'action' => $status === null ? 'update' : 'change_status',
            'type' => 'PLG_ACTIONLOG_JEM_TYPE_ATTENDEE',
            'id' => $id,
            'attendee' => $attendeeName,
            'eventtitle' => $eventTitle,
            'extension' => 'COM_JEM',
            'userid' => $user->id,
            'username' => $user->username,
            'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . (int) $user->id,
        );

        if ($eventId > 0) {
            $message['eventlink'] = 'index.php?option=com_jem&task=event.edit&id=' . $eventId;
        }

        if ($status !== null) {
            $message['status'] = $this->statusToText($status);
        }

        return $message;
    }

    private function createAttachmentMessage(object $attachment, bool $includeLink = true): array
    {
        $user = $this->getApplication()->getIdentity();
        $id = (int) ($attachment->id ?? 0);
        $title = trim((string) ($attachment->name ?? ''));

        if ($title === '') {
            $title = trim((string) ($attachment->file ?? ''));
        }

        if ($title === '') {
            $title = Text::_('PLG_ACTIONLOG_JEM_UNKNOWN_TITLE');
        }

        $message = array(
            'action' => 'update',
            'type' => 'PLG_ACTIONLOG_JEM_TYPE_ATTACHMENT',
            'id' => $id,
            'title' => $title,
            'object' => (string) ($attachment->object ?? ''),
            'extension' => 'COM_JEM',
            'userid' => $user->id,
            'username' => $user->username,
            'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . (int) $user->id,
        );

        if ($includeLink && $id > 0) {
            $message['itemlink'] = 'index.php?option=com_jem&task=attachment.edit&id=' . $id;
        }

        return $message;
    }

    private function statusToText(int $status): string
    {
        switch ($status) {
            case -1:
                return Text::_('PLG_ACTIONLOG_JEM_STATUS_NOT_ATTENDING');
            case 0:
                return Text::_('PLG_ACTIONLOG_JEM_STATUS_INVITED');
            case 1:
                return Text::_('PLG_ACTIONLOG_JEM_STATUS_ATTENDING');
            case 2:
                return Text::_('PLG_ACTIONLOG_JEM_STATUS_WAITINGLIST');
            default:
                return (string) $status;
        }
    }

    private function isContextEnabled(string $context): bool
    {
        $param = self::CONTEXTS[$context]['param'] ?? '';

        return $param === '' || $this->isFeatureEnabled($param);
    }

    private function isFeatureEnabled(string $param): bool
    {
        return (int) $this->params->get($param, 1) === 1;
    }

    private function eventArgument(Event $event, $key, $default = null)
    {
        $arguments = $event->getArguments();

        if (array_key_exists($key, $arguments)) {
            return $arguments[$key];
        }

        $stringKey = (string) $key;

        return array_key_exists($stringKey, $arguments) ? $arguments[$stringKey] : $default;
    }

    private function stateToAction(int $state): string
    {
        switch ($state) {
            case 1:
                return 'publish';
            case 0:
                return 'unpublish';
            case 2:
                return 'archive';
            case -2:
                return 'trash';
            default:
                return '';
        }
    }

    private function loadItems(string $context, array $pks): array
    {
        $meta = self::CONTEXTS[$context];
        $tables = array(
            'com_jem.event' => '#__jem_events',
            'com_jem.venue' => '#__jem_venues',
            'com_jem.category' => '#__jem_categories',
            'com_jem.type' => '#__jem_types',
            'com_jem.group' => '#__jem_groups',
            'com_jem.attachment' => '#__jem_attachments',
        );

        $ids = array_values(array_unique(array_filter(array_map('intval', $pks))));

        if (!$ids || empty($tables[$context])) {
            return array();
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(array($meta['id'], $meta['title'])))
            ->from($db->quoteName($tables[$context]))
            ->whereIn($db->quoteName($meta['id']), $ids);

        $db->setQuery($query);

        try {
            return (array) $db->loadObjectList($meta['id']);
        } catch (RuntimeException $e) {
            return array();
        }
    }

    private function loadAttendees(array $ids, int $eventId = 0): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids) {
            return array();
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(
                array(
                    'r.*',
                    $db->quoteName('u.name', 'username'),
                    $db->quoteName('e.title', 'eventtitle'),
                )
            )
            ->from($db->quoteName('#__jem_register', 'r'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('r.uid'))
            ->join('LEFT', $db->quoteName('#__jem_events', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('r.event'))
            ->whereIn($db->quoteName('r.id'), $ids);

        if ($eventId > 0) {
            $query->where($db->quoteName('r.event') . ' = ' . $eventId);
        }

        $db->setQuery($query);

        try {
            return (array) $db->loadObjectList('id');
        } catch (RuntimeException $e) {
            return array();
        }
    }

    private function loadAttachments(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids) {
            return array();
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jem_attachments'))
            ->whereIn($db->quoteName('id'), $ids);

        $db->setQuery($query);

        try {
            return (array) $db->loadObjectList('id');
        } catch (RuntimeException $e) {
            return array();
        }
    }
}
