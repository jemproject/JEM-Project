<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

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

}
