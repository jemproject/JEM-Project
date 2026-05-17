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
use Joomla\Registry\Registry;

class jem_types extends Table
{
    public $id             = null;
    public $name           = '';
    public $alias          = '';
    public $description    = null;
    public $entity         = 1;
    public $icon           = null;
    public $color          = null;
    public $published      = 1;
    public $ordering       = 0;
    public $access         = 1;
    public $language       = '*';
    public $checked_out    = null;
    public $checked_out_time = null;
    public $created        = null;
    public $created_by     = 0;
    public $modified       = null;
    public $modified_by    = 0;
    public $attribs        = null;

    public function __construct(&$db)
    {
        parent::__construct('#__jem_types', 'id', $db);
    }

    public function bind($array, $ignore = '')
    {
        if (isset($array['attribs']) && is_array($array['attribs'])) {
            $registry = new Registry;
            $registry->loadArray($array['attribs']);
            $array['attribs'] = (string) $registry;
        }

        return parent::bind($array, $ignore);
    }

    public function check()
    {
        if (!trim($this->name)) {
            $this->setError(Text::_('COM_JEM_TYPE_ERROR_NAME_REQUIRED'));
            return false;
        }

        $alias = OutputFilter::stringURLSafe($this->name);
        if (empty($this->alias) || $this->alias === $alias) {
            $this->alias = $alias;
        }

        if (empty($this->created)) {
            $this->created = Factory::getDate()->toSql();
        }

        return true;
    }
}
