<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;

/**
 * Holds helpfull administration related stuff
 *
 * @package JEM
 * @since 0.9
 */
class JEMAdmin {

	/**
	* Writes footer. 
	*
	* @since 0.9
	*/
static	function footer( )
	{
        $params =  JComponentHelper::getParams('com_jem');
     
       /* if ($params->get('copyright') == 1) { */
		echo '<font color="grey">Powered by <a href="http://www.joomlaeventmanager.net" target="_blank">JEM</a></font>';
       /*  
        } else {
	     echo '';   
        } 
        */

	}

static	function config()
	{
		$db = JFactory::getDBO();

		$sql = 'SELECT * FROM #__jem_settings WHERE id = 1';
		$db->setQuery($sql);
		$config = $db->loadObject();

		return $config;
	}
	
static	function buildtimeselect($max, $name, $selected, $class = 'class="inputbox"')
	{
		$timelist 	= array();

		foreach(range(0, $max) as $wert) {
		    if(strlen($wert) == 2) {
				$timelist[] = JHTML::_( 'select.option', $wert, $wert);
    		}else{
      			$timelist[] = JHTML::_( 'select.option', '0'.$wert, '0'.$wert);
    		}
		}
		return JHTML::_('select.genericlist', $timelist, $name, $class, 'value', 'text', $selected );
	}
}

?>