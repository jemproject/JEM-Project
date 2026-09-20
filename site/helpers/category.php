<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\CMS\Categories\CategoryNode;

/**
 * Empty category adapter for Joomla Custom Fields contexts.
 *
 * JEM event categories are managed in #__jem_categories and their field
 * selection is stored by JEM. Venue fields are global. Returning an empty
 * modern category service prevents com_fields from falling back to the
 * unrelated legacy JemCategories class or #__categories assignments.
 */
class JemFieldsCategories implements CategoryInterface
{
    /**
     * Empty root node expected by Joomla's Fields edit form.
     *
     * @var CategoryNode
     */
    protected $root;

    /**
     * @param  array  $options  Unused Joomla category options.
     */
    public function __construct($options = array())
    {
        $this->root = new CategoryNode(array(
            'id'        => 0,
            'title'     => 'ROOT',
            'alias'     => 'root',
            'extension' => 'com_jem',
            'level'     => 0,
        ));
    }

    /**
     * JEM Custom Fields do not use Joomla category assignments.
     *
     * @param   mixed    $id         Requested category id.
     * @param   boolean  $forceload  Whether to force loading.
     *
     * @return CategoryNode|null
     */
    public function get($id = 'root', $forceload = false)
    {
        if ($id === 'root') {
            return $this->root;
        }

        return null;
    }
}

/**
 * Category adapter for the com_jem.event Custom Fields context.
 */
class JemEventCategories extends JemFieldsCategories
{
}

/**
 * Category adapter for the com_jem.venue Custom Fields context.
 */
class JemVenueCategories extends JemFieldsCategories
{
}

/**
 * Content Component Category Tree
 */
class JEM2Categories extends Categories
{
    public function __construct($options = array())
    {
        $options['table'] = '#__jem_categories';
        $options['extension'] = 'com_jem';
        parent::__construct($options);
    }
}
