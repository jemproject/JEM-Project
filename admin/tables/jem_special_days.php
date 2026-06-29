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

class jem_special_days extends Table
{
    public $id = null;
    public $title = '';
    public $alias = '';
    public $day_type = '';
    public $start_date = null;
    public $end_date = null;
    public $weekdays = '';
    public $country = '';
    public $region = '';
    public $city = '';
    public $description = null;
    public $show_dates = 1;
    public $published = 1;
    public $access = 1;
    public $ordering = 0;
    public $created = null;
    public $created_by = 0;
    public $modified = null;
    public $modified_by = 0;
    public $checked_out = null;
    public $checked_out_time = null;

    public function __construct(&$db)
    {
        parent::__construct('#__jem_special_days', 'id', $db);
    }

    public function bind($array, $ignore = '')
    {
        if (isset($array['weekdays']) && is_array($array['weekdays'])) {
            $array['weekdays'] = implode(',', array_filter(array_map('intval', $array['weekdays']), static function ($weekday) {
                return $weekday >= 0 && $weekday <= 6;
            }));
        }

        if (isset($array['country']) && is_array($array['country'])) {
            $array['country'] = implode(',', array_values(array_unique(array_filter(array_map(static function ($country) {
                return strtoupper(substr(preg_replace('/[^A-Z]/i', '', (string) $country), 0, 2));
            }, $array['country'])))));
        }

        return parent::bind($array, $ignore);
    }

    public function check()
    {
        $this->title = trim((string) $this->title);

        if ($this->title === '') {
            $this->setError(Text::_('COM_JEM_SPECIAL_DAY_ERROR_TITLE_REQUIRED'));
            return false;
        }

        $this->alias = trim((string) $this->alias);

        if ($this->alias === '') {
            $this->alias = OutputFilter::stringURLSafe($this->title);
        }

        $this->day_type = trim((string) $this->day_type);
        if ($this->day_type === '') {
            $this->setError(Text::_('COM_JEM_SPECIAL_DAY_ERROR_TYPE_REQUIRED'));
            return false;
        }

        $this->weekdays = implode(',', array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $this->weekdays)), static function ($weekday) {
            return $weekday >= 0 && $weekday <= 6;
        }))));

        $this->start_date = $this->normaliseDate($this->start_date);
        $this->end_date = $this->normaliseDate($this->end_date);

        if ($this->start_date === null || $this->end_date === null) {
            $this->setError(Text::_('COM_JEM_SPECIAL_DAY_ERROR_DATE_RANGE_REQUIRED'));
            return false;
        }

        if ($this->start_date !== null && $this->end_date !== null && $this->end_date < $this->start_date) {
            $tmp = $this->start_date;
            $this->start_date = $this->end_date;
            $this->end_date = $tmp;
        }

        $this->published = (int) $this->published;
        if (!in_array($this->published, array(-2, 0, 1), true)) {
            $this->published = 0;
        }
        $this->show_dates = (int) $this->show_dates === 0 ? 0 : 1;
        $this->access = max(1, (int) $this->access);
        $this->ordering = (int) $this->ordering;
        $this->created_by = (int) $this->created_by;
        $this->modified_by = (int) $this->modified_by;
        $this->country = implode(',', array_values(array_unique(array_filter(array_map(static function ($country) {
            return strtoupper(substr(preg_replace('/[^A-Z]/i', '', trim($country)), 0, 2));
        }, explode(',', (string) $this->country))))));

        if (empty($this->created)) {
            $this->created = Factory::getDate()->toSql();
        }

        if (empty($this->created_by)) {
            $user = Factory::getApplication()->getIdentity();
            $this->created_by = (int) ($user->id ?? 0);
        }

        return true;
    }

    private function normaliseDate($date)
    {
        $date = trim((string) $date);

        if ($date === '' || $date === '0000-00-00') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }
}
