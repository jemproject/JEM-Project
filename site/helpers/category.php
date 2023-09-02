<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Categories\Categories;

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
