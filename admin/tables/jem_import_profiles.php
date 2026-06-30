<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;

class jem_import_profiles extends Table
{
    public $id               = null;
    public $title            = '';
    public $context          = 'events';
    public $source_format    = 'csv';
    public $source_signature = null;
    public $mapping          = '';
    public $options          = null;
    public $published        = 1;
    public $access           = 1;
    public $ordering         = 0;
    public $created          = null;
    public $created_by       = 0;
    public $modified         = null;
    public $modified_by      = 0;

    public function __construct(&$db)
    {
        parent::__construct('#__jem_import_profiles', 'id', $db);
    }

    public function check()
    {
        $this->title = trim((string) $this->title);

        if ($this->title === '') {
            $this->setError(Text::_('COM_JEM_IMPORT_PROFILE_ERROR_TITLE_REQUIRED'));
            return false;
        }

        $this->context = strtolower(trim((string) $this->context));
        if (!in_array($this->context, array('events', 'venues', 'specialdays'), true)) {
            $this->context = 'events';
        }

        $this->source_format = strtolower(trim((string) $this->source_format));
        if (!in_array($this->source_format, array('csv', 'json', 'xml', 'ics'), true)) {
            $this->source_format = 'csv';
        }

        json_decode((string) $this->mapping);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->setError(Text::_('COM_JEM_IMPORT_PROFILE_ERROR_MAPPING_INVALID'));
            return false;
        }

        $this->published = (int) (bool) $this->published;
        $this->access    = max(1, (int) $this->access);
        $this->ordering  = (int) $this->ordering;

        return true;
    }
}
