<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Category Controller
 */
class JemControllerCategory extends FormController
{
    /**
     * The extension for which the categories apply.
     *
     * @var    string
     */
    protected $text_prefix = 'COM_JEM_CATEGORY';

    /**
     * Constructor.
     *
     * @param  array  $config  An optional associative array of configuration settings.
     *
     * @see    FormController
     */
    public function __construct($config = array()) {
        parent::__construct($config);
    }

    protected function allowAdd($data = array())
    {
        $parentId = (int) ($data['parent_id'] ?? Factory::getApplication()->input->getInt('parent_id', 1));

        return JemHelperBackend::canCategory('create', null, $parentId);
    }

    protected function allowEdit($data = array(), $key = 'id')
    {
        $recordId = (int) ($data[$key] ?? 0);

        if ($recordId < 1) {
            return false;
        }

        $record = $this->getModel()->getItem($recordId);

        return is_object($record) && JemHelperBackend::canCategory('edit', $record);
    }

    /**
     * Moving a category requires create permission on the destination parent.
     */
    public function save($key = null, $urlVar = 'id')
    {
        $this->checkToken();

        $data = Factory::getApplication()->input->post->get('jform', array(), 'array');
        $recordId = (int) ($data[$urlVar] ?? $data['id'] ?? 0);

        if ($recordId > 0) {
            $record = $this->getModel()->getItem($recordId);
            $newParentId = (int) ($data['parent_id'] ?? 1);

            if (is_object($record)
                && $newParentId !== (int) ($record->parent_id ?? 1)
                && !JemHelperBackend::canCategory('create', null, $newParentId)) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
        }

        return parent::save($key, $urlVar);
    }

}
