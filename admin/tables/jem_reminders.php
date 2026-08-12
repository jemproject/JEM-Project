<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;

class jem_reminders extends Table
{
    public function __construct(&$db)
    {
        parent::__construct('#__jem_reminders', 'id', $db);
    }

    public function check()
    {
        $this->title = trim((string) $this->title);
        if ($this->title === '') {
            $this->setError(Text::_('COM_JEM_REMINDER_ERROR_TITLE_REQUIRED'));

            return false;
        }
        $this->minutes = (int) $this->minutes;
        if ($this->minutes < 1) {
            $this->setError(Text::_('COM_JEM_REMINDER_ERROR_MINUTES_INVALID'));

            return false;
        }
        $this->event_id = 0;
        $this->source_id = null;
        $this->published = (int) (bool) $this->published;
        $this->default_new_event = (int) (bool) $this->default_new_event;
        $this->ordering = (int) $this->ordering;
        $now = Factory::getDate()->toSql();
        if (empty($this->created)) {
            $this->created = $now;
        }
        if (empty($this->created_by)) {
            $this->created_by = (int) Factory::getApplication()->getIdentity()->id;
        }
        $this->modified = $now;
        if (empty($this->code)) {
            $base = OutputFilter::stringURLSafe($this->title) ?: 'reminder';
            $this->code = substr('custom_' . str_replace('-', '_', $base) . '_' . substr(sha1($now . microtime(true)), 0, 8), 0, 64);
        }

        return true;
    }
}
