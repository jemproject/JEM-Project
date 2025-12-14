<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterBase;
use Joomla\CMS\Application\CMSApplication;

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
// use Joomla\CMS\Component\Router\Rules\NomenuRules;
// use Joomla\Component\Jem\Site\Service\JemNomenuRules as NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;

require_once (JPATH_SITE.'/components/com_jem/services/JemNomenuRules.php');

class JemRouter extends RouterView
{
    /**
     * Router segments.
     *
     * @var  array
     *
     * @since  1.0.0
     */
    protected $_segments = array();

    /**
     * Router ids.
     *
     * @var  array
     *
     * @since  1.0.0
     */
    protected $_ids = array();

    /**
     * Router constructor.
     *
     * @param   CMSApplication  $app   The application object.
     * @param   AbstractMenu    $menu  The menu object to work with.
     *
     * @since  1.0.0
     */
    public function __construct($app = null, $menu = null)
    {

       
        $viewsWithId = [
            'calendar',
            'eventslist',
            'event',
            'categories',
            'category',
            'attendees',
            'day',
            'editevent',
            'editvenue',
            'myattendances',
            'myevents',
            'myvenues',
            'search',
            'venue',
            'venueslist',
            'venues',
            'weekcal'
        ];

        // Registro masivo de vistas (DRY: Don't Repeat Yourself)
        foreach ($viewsWithId as $viewName) {
            $viewConfig = new RouterViewConfiguration($viewName);
            $viewConfig->setKey('id');
            $this->registerView($viewConfig);
        }

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new JemNomenuRules($this));
    }
}
function jemBuildRoute(&$query)
{
    $app    = Factory::getApplication();

    $router = new JemRouter($app, $app->getMenu());

    return $router->build($query);
}

function jemParseRoute($segments)
{
    $app    = Factory::getApplication();
    $router = new JemRouter($app, $app->getMenu());
    return $router->parse($segments);
}
?>