<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.categories');

/**
 * Content Component Category Tree
 */
class JEM2Categories extends JCategories
{
	public function __construct($options = array())
	{
		$options['table'] = '#__jem_categories';
		$options['extension'] = 'com_jem';
		parent::__construct($options);
	}
}
