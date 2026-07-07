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


    public function __construct(& $db)
    {
        parent::__construct('#__jem_attachments', 'id', $db);
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

        if (empty($this->created)) {
            $this->created = Factory::getDate()->toSql();
        }

        return true;
    }
}
?>
