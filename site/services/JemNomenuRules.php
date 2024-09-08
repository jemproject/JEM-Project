<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */


defined('JPATH_PLATFORM') or die;

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
     * Dummymethod to fullfill the interface requirements
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
        $itmid = is_array($query['Itemid']) ? array_values($query['Itemid']) : $query['Itemid'] ;

        $query['Itemid']= is_array($itmid) ?  $itmid[0] : $itmid;
        // echo "preprocess <pre/>";print_R($query);die;
        $test = 'Test';
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

        // Count segments
        $count = count($segments);


        // echo "<pre/>";print_r($segments);die;
        //with this url: https://localhost/j4x/my-walks/mywalk-n/walk-title.html
        // segments: [[0] => mywalk-n, [1] => walk-title]
        // vars: [[option] => com_mywalks, [view] => mywalks, [id] => 0]

        // $vars['view'] = 'mywalk';
        // $vars['id'] = substr($segments[0], strpos($segments[0], '-') + 1);
        // array_shift($segments);

        // array_shift($segments);
        // $vars['option']='com_jem';
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
                        $vars['id'] = $segments[1];
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
                    // $id = explode(':', $segments[1]);
                    // $vars['id'] = $id[0];
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
        // $itmid = is_array($query['Itemid']) ? array_values($query['Itemid']) : $query['Itemid'] ;

        // $query['Itemid']= is_array($itmid) ?  $itmid[0] : $itmid;
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