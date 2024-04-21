<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
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
		
		// calendar route
		$calendar = new RouterViewConfiguration('calendar');
		$calendar->setKey('id');
		$this->registerView($calendar);

		// eventslist route
		$eventslist = new RouterViewConfiguration('eventslist');
		$eventslist->setKey('id');
		$this->registerView($eventslist);
	
		// event route
		$event = new RouterViewConfiguration('event');
		$event->setKey('id');
		$this->registerView($event);

		// categories route
		$categories = new RouterViewConfiguration('categories');
		$categories->setKey('id');
		$this->registerView($categories);

		// category route
		$category = new RouterViewConfiguration('category');
		$category->setKey('id');
		$this->registerView($category);

		// attendees route
		$attendees = new RouterViewConfiguration('attendees');
		$attendees->setKey('id');
		$this->registerView($attendees);

		// day route
		$day = new RouterViewConfiguration('day');
		$day->setKey('id');
		$this->registerView($day);

		// editevent route
		$editevent = new RouterViewConfiguration('editevent');
		$editevent->setKey('id');
		$this->registerView($editevent);

		// editvenue route
		$editvenue = new RouterViewConfiguration('editvenue');
		$editvenue->setKey('id');
		$this->registerView($editvenue);

		// myattendances route
		$myattendances = new RouterViewConfiguration('myattendances');
		$myattendances->setKey('id');
		$this->registerView($myattendances);

		// myevents route
		$myevents = new RouterViewConfiguration('myevents');
		$myevents->setKey('id');
		$this->registerView($myevents);

		// myvenues route
		$myvenues = new RouterViewConfiguration('myvenues');
		$myvenues->setKey('id');
		$this->registerView($myvenues);

		// search route
		$search = new RouterViewConfiguration('search');
		$search->setKey('id');
		$this->registerView($search);

		// venue route
		$venue = new RouterViewConfiguration('venue');
		$venue->setKey('id');
		$this->registerView($venue);

		// venueslist route
		$venueslist = new RouterViewConfiguration('venueslist');
		$venueslist->setKey('id');
		$this->registerView($venueslist);

		// venues route
		$venues = new RouterViewConfiguration('venues');
		$venues->setKey('id');
		$this->registerView($venues);

		// weekcal route
		$weekcal = new RouterViewConfiguration('weekcal');
		$weekcal->setKey('id');
		$this->registerView($weekcal);
		
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