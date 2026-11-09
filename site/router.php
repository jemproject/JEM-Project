<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Multilanguage;

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;

require_once __DIR__ . '/services/JemNomenuRules.php';
require_once __DIR__ . '/helpers/calendardaylink.php';

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
            'annualcalendar',
            'calendar',
            'eventslist',
            'event',
            'eventsmap',
            'categories',
            'category',
            'attendees',
            'myattendances',
            'myevents',
            'mytimeline',
            'myvenues',
            'registration',
            'search',
            'specialday',
            'venue',
            'venueslist',
            'venuesmap',
            'venues',
            'typeevents',
            'typevenues',
            'weekcal'
        ];

        // Registro masivo de vistas (DRY: Don't Repeat Yourself)
        foreach ($viewsWithId as $viewName) {
            $viewConfig = new RouterViewConfiguration($viewName);
            $viewConfig->setKey('id');
            $this->registerView($viewConfig);
        }

        // Frontend form controllers use a_id to avoid collisions with routed item ids.
        foreach (array('editcategory', 'editevent', 'editvenue') as $viewName) {
            $viewConfig = new RouterViewConfiguration($viewName);
            $viewConfig->setKey('a_id');
            $this->registerView($viewConfig);
        }

        // A Day date is navigation state, not a content id used to select a menu item.
        $this->registerView(new RouterViewConfiguration('day'));
        $this->registerView(new RouterViewConfiguration('specialdays'));
        $this->registerView(new RouterViewConfiguration('attendeeregistrations'));
        $this->registerView(new RouterViewConfiguration('eventsblog'));
        $this->registerView(new RouterViewConfiguration('modulepreview'));

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new JemNomenuRules($this));
    }

    /**
     * Select an eligible detail menu and preserve explicit Day/Calendar navigation.
     *
     * @param   array  $query  Component URL parameters.
     *
     * @return  array  Parameters with the selected menu context preserved when applicable.
     */
    public function preprocess($query)
    {
        // These keyed views need record-aware selection, not MenuRules' whole-view lookup.
        if (in_array($query['view'] ?? '', ['event', 'venue', 'category'], true)) {
            $language = $query['lang'] ?? '*';
            if ($language === '*' && Multilanguage::isEnabled()) {
                $language = $this->app->get('language');
            }
            $items = $this->menu->getItems(
                ['component_id', 'language'],
                [(int) ComponentHelper::getComponent('com_jem')->id, [$language, '*']]
            );
            $itemId = JemDetailMenu::select($items ?: [], $query, (int) ($this->menu->getActive()->id ?? 0));
            unset($query['Itemid']);
            if ($itemId !== null) {
                $query['Itemid'] = $itemId;
                $item = $this->menu->getItem($itemId);
                if (!isset($query['layout']) && !empty($item->query['layout'])
                    && $item->query['layout'] !== 'default') {
                    // A source list/calendar layout is not the target detail layout.
                    $query['layout'] = 'default';
                }
            }

            return $query;
        }

        if (($query['view'] ?? '') === 'day'
            && isset($query['Itemid'])
            && is_numeric($query['Itemid'])) {
            $item = $this->menu->getItem((int) $query['Itemid']);
            $language = $query['lang'] ?? '*';

            if ($item
                && ($item->query['option'] ?? '') === 'com_jem'
                && ($language === '*' || $item->language === '*' || $item->language === $language)
                && (($item->query['view'] ?? '') === 'day'
                    || (in_array($query['jemcal'] ?? null, array(1, '1'), true)
                        && JemCalendarDayLink::sourceView((array) $item->query) !== ''))) {
                return $query;
            }
        }

        return parent::preprocess($query);
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
