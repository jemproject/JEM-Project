<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;

/**
 * JEM attachments table class
 *
 * @package JEM
 *
 */
class jem_attachments extends Table
{
    /**
     * Primary Key
     * @var int
     */
    public $id = null;
    /** @var int */
    public $file = '';
    /** @var int */
    public $object = '';
    /** @var string */
    public $name = null;
    /** @var string */
    public $description = null;
    /** @var string */
    public $icon = null;
    /** @var int */
    public $frontend = 1;
    /** @var int */
    public $access = 1;
    /** @var int */
    public $ordering = 0;
    /** @var string */
    public $created = '';
    /** @var int */
    public $created_by = 0;
    /** @var string|null */
    public $modified = null;
    /** @var int */
    public $modified_by = 0;
    /** @var bool Internal CSV context; never populated by Table::bind(). */
    protected $_importAudit = false;
    /** @var int */
    public $downloads = 0;
    /** @var string|null */
    public $last_download = null;


    public function __construct(& $db)
    {
        parent::__construct('#__jem_attachments', 'id', $db);
    }

    /**
     * Preserve metadata supplied by the trusted importer, not a bound form field.
     *
     * @return void
     */
    public function useImportAudit(): void
    {
        $this->_importAudit = true;
    }

    /**
     * Track the last editor without changing creation data or recording no-op saves.
     *
     * @param bool $updateNulls Whether nullable values should be written on update.
     * @return bool Whether the attachment was persisted successfully.
     */
    public function store($updateNulls = false)
    {
        $db = $this->getDatabase();
        $stored = null;
        if ((int) $this->id > 0) {
            $stored = $db->setQuery($db->getQuery(true)
                ->select('*')->from($db->quoteName($this->getTableName()))
                ->where($db->quoteName('id') . ' = ' . (int) $this->id))->loadObject();
        }

        $actor = (int) Factory::getApplication()->getIdentity()->id;
        $now = Factory::getDate()->toSql();
        if ($stored) {
            // Neither a form nor an unchanged event save may rewrite this history.
            $this->created = $stored->created;
            $this->created_by = (int) $stored->created_by;
            $changed = false;
            foreach (array('object', 'file', 'name', 'description', 'icon', 'frontend', 'access', 'ordering') as $field) {
                if ((string) $this->$field !== (string) $stored->$field) {
                    $changed = true;
                    break;
                }
            }
            $this->modified = $changed || $this->_importAudit ? $now : $stored->modified;
            $this->modified_by = $changed || $this->_importAudit ? $actor : (int) $stored->modified_by;
        } elseif ($this->_importAudit) {
            $this->modified = null;
            $this->modified_by = 0;
        } else {
            $this->created = $now;
            $this->created_by = $actor;
            $this->modified = null;
            $this->modified_by = 0;
        }

        return parent::store($updateNulls);
    }

    // overloaded check function
    public function check()
    {
        $this->object = trim((string) $this->object);
        $this->file   = trim((string) $this->file);

        if ($this->object === '' || !preg_match('/^[a-z]+[0-9]+$/i', $this->object)) {
            $this->setError(Text::_('COM_JEM_ATTACHMENT_ERROR_INVALID_OBJECT'));
            return false;
        }

        if ($this->file === '' || basename($this->file) !== $this->file || strpos($this->file, '..') !== false) {
            $this->setError(Text::_('COM_JEM_ATTACHMENT_ERROR_INVALID_FILE'));
            return false;
        }

        $filter = InputFilter::getInstance();
        $this->name        = $filter->clean((string) $this->name, 'string');
        $this->description = $filter->clean((string) $this->description, 'string');
        $this->icon        = $filter->clean((string) $this->icon, 'string');
        $this->frontend    = (int) (bool) $this->frontend;
        $this->access      = max(1, (int) $this->access);
        $this->ordering    = (int) $this->ordering;
        $this->created_by  = (int) $this->created_by;
        $this->downloads   = max(0, (int) $this->downloads);

        if (empty($this->created)) {
            $this->created = Factory::getDate()->toSql();
        }

        return true;
    }
}
?>
