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
    public $day_type_id = 0;
    public $day_type = '';
    public $start_date = null;
    public $end_date = null;
    public $weekdays = '';
    public $country = '';
    public $region = '';
    public $city = '';
    public $description = null;
    public $article_id = 0;
    public $url = '';
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
        if (isset($array['link'])) {
            $link = $this->normaliseLink((string) $array['link']);
            $array['article_id'] = $link['article_id'];
            $array['url'] = $link['url'];
            unset($array['link']);
        }

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

        $this->day_type_id = (int) $this->day_type_id;
        $this->day_type = trim((string) $this->day_type);

        if ($this->day_type_id > 0 && $this->day_type === '') {
            $this->day_type = $this->getDayTypeName($this->day_type_id);
        }

        if ($this->day_type_id <= 0 && $this->day_type !== '') {
            $this->day_type_id = $this->getDayTypeId($this->day_type);
        }

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
        if (!in_array($this->published, array(-2, 0, 1, 2), true)) {
            $this->published = 0;
        }
        $this->article_id = max(0, (int) $this->article_id);
        $this->url = trim((string) $this->url);
        if ($this->url !== '' && !$this->isAllowedLink($this->url)) {
            $this->url = '';
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

    private function getDayTypeName($typeId)
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('id') . ' = ' . (int) $typeId)
            ->where($db->quoteName('entity') . ' = 4');

        try {
            $db->setQuery($query);
            return trim((string) $db->loadResult());
        } catch (RuntimeException $e) {
            return '';
        }
    }

    private function getDayTypeId($name)
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 0;
        }

        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('entity') . ' = 4')
            ->where($db->quoteName('name') . ' = ' . $db->quote($name));

        try {
            $db->setQuery($query);
            return (int) $db->loadResult();
        } catch (RuntimeException $e) {
            return 0;
        }
    }

    private function normaliseDate($date)
    {
        $date = trim((string) $date);

        if ($date === '' || $date === '0000-00-00') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    private function normaliseLink($link)
    {
        $link = trim(html_entity_decode((string) $link, ENT_QUOTES, 'UTF-8'));

        if ($link === '') {
            return array('article_id' => 0, 'url' => '');
        }

        if (ctype_digit($link)) {
            return array('article_id' => (int) $link, 'url' => '');
        }

        $parts = parse_url($link);
        $query = array();

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        } elseif (strpos($link, 'index.php?') === 0) {
            parse_str(substr($link, strlen('index.php?')), $query);
        }

        if (($query['option'] ?? '') === 'com_content' && ($query['view'] ?? '') === 'article' && !empty($query['id'])) {
            return array('article_id' => max(0, (int) $query['id']), 'url' => '');
        }

        if ($this->isAllowedLink($link)) {
            return array('article_id' => 0, 'url' => $link);
        }

        return array('article_id' => 0, 'url' => '');
    }

    private function isAllowedLink($link)
    {
        $link = trim((string) $link);

        if ($link === '') {
            return false;
        }

        if (filter_var($link, FILTER_VALIDATE_URL)) {
            return true;
        }

        if (strpos($link, 'index.php?') === 0) {
            return true;
        }

        return strpos($link, '/') === 0 && strpos($link, '//') !== 0;
    }
}
