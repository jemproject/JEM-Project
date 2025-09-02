<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * JEM Component Help Model
 *
 * @package JEM
 *
 */
class JemModelHelp extends BaseDatabaseModel
{
    /**
     * Constructor
     */
    public function __construct($config = array(), $factory = null)
    {
        parent::__construct($config, $factory);
        
        // Set the dispatcher for Joomla 5/6 compatibility
        if (method_exists($this, 'setDispatcher')) {
            $this->setDispatcher(Factory::getApplication()->getDispatcher());
        }
    }
}
?>