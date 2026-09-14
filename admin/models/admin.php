<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\String\StringHelper;


abstract class JemModelAdmin extends AdminModel
{
    /**
     * Generate a unique title and alias when an item is saved as a copy.
     *
     * @param  string  $title       Current item title
     * @param  string  $alias       Current item alias
     * @param  string  $titleField  Table field containing the title
     *
     * @return array<string>
     */
    protected function generateCopyTitleAndAlias($title, $alias, $titleField = 'title')
    {
        $table = $this->getTable();
        $title = trim((string) $title);
        $alias = OutputFilter::stringURLSafe(trim((string) $alias) ?: $title);
        $firstCollision = true;

        while ($alias !== '' && $table->load(array('alias' => $alias))) {
            if ($firstCollision || $title === (string) $table->$titleField) {
                $title = StringHelper::increment($title);
            }

            $alias = StringHelper::increment($alias, 'dash');
            $firstCollision = false;
        }

        return array($title, $alias);
    }

    protected function _prepareTable($table)
    {
        // Derived class will provide its own implementation if required.
    }
    protected function prepareTable($table)
    {
        $this->_prepareTable($table);
    }
}
