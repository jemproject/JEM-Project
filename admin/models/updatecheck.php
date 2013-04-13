<?php
/**
 * @version $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
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

// no direct access
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Updatecheck Model
 *
 * @package JEM
 * @since 0.9
 */
class JEMModelUpdatecheck extends JModelLegacy
{
	/**
	 * Events data in array
	 *
	 * @var array
	 */
	var $_updatedata = null;

	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Logic for the Update Check
	 *
	 * @access public
	 * @return object
	 * @since 0.9
	 */
	function getUpdatedata()
	{

		$elsettings = ELAdmin::config();

		include_once(JPATH_COMPONENT_ADMINISTRATOR.DS.'classes'.DS.'Snoopy.class.php');

		$snoopy = new Snoopy();

		//set the source file
		$file = 'http://www.joomlaeventmanager.net/update.csv';

		$snoopy->read_timeout 	= 30;
		$snoopy->agent 			= "Mozilla/5.0 (compatible; Konqueror/3.2; Linux 2.6.2) (KHTML, like Gecko)";

		$snoopy->fetch($file);

		$_updatedata = null;

		if ($snoopy->status != 200 || $snoopy->error) {

			$_updatedata->failed = 1;

		} else {

			$data = explode('|', $snoopy->results);

			$_updatedata->version 		= $data[0];
			$_updatedata->versiondetail	= $data[1];
			$_updatedata->date			= strftime( $elsettings->formatdate, strtotime( $data[2] ) );
			$_updatedata->info 			= $data[3];
			$_updatedata->download 		= $data[4];
			$_updatedata->notes			= $data[5];
			$_updatedata->changes 		= explode(';', $data[6]);
			$_updatedata->failed 		= 0;

			$_updatedata->current = version_compare( '2.0.0.1', $_updatedata->version );

		}

		return $_updatedata;
	}

}
?>