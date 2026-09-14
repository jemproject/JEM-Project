<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\RulesInterface;

/**
 * Rule to process URLs without a menu item
 *
 * @since  3.4
 */
class JemNomenuRules implements RulesInterface
{
    /**
     * Router this rule belongs to
     *
     * @var RouterView
     * @since 3.4
     */
    protected $router;

    /**
     * Class constructor.
     *
     * @param   RouterView  $router  Router this rule belongs to
     *
     * @since   3.4
     */
    public function __construct(RouterView $router)
    {
        $this->router = $router;
    }

    /**
     * Normalize query values before a menu-less URL is built.
     *
     * @param   array  &$query  The query array to process
     *
     * @return  void
     *
     * @since   3.4
     * @codeCoverageIgnore
     */
    public function preprocess(&$query)
    {
        if (isset($query['Itemid'])) {
            $itmid = is_array($query['Itemid']) ? array_values($query['Itemid']) : $query['Itemid'];
            $query['Itemid'] = is_array($itmid) ? $itmid[0] : $itmid;
        }
    }

    /**
     * Parse a menu-less URL
     *
     * @param   array  &$segments  The URL segments to parse
     * @param   array  &$vars      The vars that result from the segments
     *
     * @return  void
     *
     * @since   3.4
     */
    public function parse(&$segments, &$vars)
    {
        if (empty($segments)) {
            return;
        }

        // Count segments
        $count = count($segments);

        switch ($segments[0])
        {
            case 'category':
                {
                    if ($count == 2) {
                        $id = explode(':', $segments[1]);
                        $vars['view'] = 'category';
                        $vars['id'] = $id[0];
                    } else {
                        $vars['view'] = 'category';
                    }
                }
                break;

            case 'event':
                {

                    if ($count == 2) {
                        $id = explode(':', $segments[1]);
                        $vars['id'] = $id[0];
                        $vars['view'] = 'event';
                    } else {
                        $vars['view'] = 'event';
                    }

                }
                break;

            case 'venue':
                {
                    if ($count == 2) {
                        $id = explode(':', $segments[1]);
                        $vars['id'] = $id[0];
                        $vars['view'] = 'venue';
                    } else {
                        $vars['view'] = 'venue';
                    }
                }
                break;

            case 'editvenue':
                {
                    $vars['view'] = 'editvenue';
                    if ($count == 2) {
                        $vars['a_id'] = $segments[1];
                    }
                }
                break;

            case 'editevent':
                {
                    $vars['view'] = 'editevent';
                    if ($count == 2) {
                        $vars['a_id'] = $segments[1];
                    }
                }
                break;

            case 'eventslist':
                {
                    $vars['view'] = 'eventslist';
                }
                break;

            case 'search':
                {
                    $vars['view'] = 'search';
                }
                break;

            case 'categoriesdetailed':
                {
                    $vars['view'] = 'categoriesdetailed';
                }
                break;

            case 'categories':
                {
                    if ($count == 2) {
                        $id = explode(':', $segments[1]);
                        $vars['id'] = $id[0];
                    }
                    $vars['view'] = 'categories';
                }
                break;

            case 'calendar':
                {
                    $vars['view'] = 'calendar';
                }
                break;

            case 'venues':
                {
                    $vars['view'] = 'venues';
                }
                break;

            case 'venueslist':
                {
                    $vars['view'] = 'venueslist';
                }
                break;
            case 'day':
                {
                    $vars['view'] = 'day';
                    if ($count == 2) {
                        $vars['id'] = $segments[1];
                    }
                }
                break;

            case 'myattendances':
                {
                    $vars['view'] = 'myattendances';
                }
                break;

            case 'myevents':
                {
                    $vars['view'] = 'myevents';
                }
                break;

            case 'mytimeline':
                {
                    $vars['view'] = 'mytimeline';
                }
                break;

            case 'myvenues':
                {
                    $vars['view'] = 'myvenues';
                }
                break;

            case 'attendees':
                {
                    if(isset($segments[1])){
                        $id = explode(':', $segments[1]);
                        $vars['id'] = $id[0];
                    }
                    $vars['view'] = 'attendees';
                }
                break;

            case 'typeevents':
                {
                    if (isset($segments[1])) {
                        $id = explode(':', $segments[1]);
                        $vars['id'] = $id[0];
                    }
                    $vars['view'] = 'typeevents';
                }
                break;

            case 'typevenues':
                {
                    if (isset($segments[1])) {
                        $id = explode(':', $segments[1]);
                        $vars['id'] = $id[0];
                    }
                    $vars['view'] = 'typevenues';
                }
                break;

            default:
                {
                    $vars['view'] = $segments[0];
                }
                break;
        }
        array_shift($segments);

        array_shift($segments);
    }

    /**
     * Build a menu-less URL
     *
     * @param   array  &$query     The vars that should be converted
     * @param   array  &$segments  The URL segments to create
     *
     * @return  void
     *
     * @since   3.4
     */
    public function build(&$query, &$segments)
    {
        if (isset($query['view'], $query['a_id'])
            && in_array($query['view'], array('editevent', 'editvenue'), true)) {
            $segments[] = $query['view'];
            $segments[] = $query['a_id'];
            unset($query['view'], $query['tmpl'], $query['a_id'], $query['Itemid']);

            return;
        }

        if(isset($query['view'],$query['id'])){
            $segments[] =$query['view'];
            $segments[] =$query['id'];
            unset($query['view'],$query['tmpl'],$query['id'],$query['Itemid']);
        }else
            if (isset($query['view'])) {
                $segments[] = $query['view'];
                unset($query['view']);
            }else

                if (isset($query['id'])) {
                    $segments[] = $query['id'];
                    unset($query['id']);
                };
    }
}
