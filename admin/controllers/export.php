<?php
/**
* @version 1.0.0
* @package Eventlist
* @copyright Copyright (C) 2012. All rights reserved.
* @license GNU General Public License version 2 or later; see LICENSE.txt
*
* Based on:  https://gist.github.com/dongilbert/4195504
*
*
*/
 
// No direct access.
defined('_JEXEC') or die;
 
jimport('joomla.application.component.controlleradmin');
 
class EventlistControllerExport extends JControllerAdmin
{
/**
* Proxy for getModel.
* @since 1.6
*/
public function getModel($name = 'Export', $prefix = 'EventlistModel',$config=array())
{
$model = parent::getModel($name, $prefix, array('ignore_request' => true));
return $model;
}


public function export()
{
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=events.csv");
header("Pragma: no-cache");
header("Expires: 0");
$this->getModel()->getCsv();
jexit();
}

public function exportcats()
{
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=categories.csv");
header("Pragma: no-cache");
header("Expires: 0");
$this->getModel()->getCsvcats();
jexit();
}

public function exportvenues()
{
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=venues.csv");
header("Pragma: no-cache");
header("Expires: 0");
$this->getModel()->getCsvvenues();
jexit();
}

public function exportcatevents()
{
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=catevents.csv");
header("Pragma: no-cache");
header("Expires: 0");
$this->getModel()->getCsvcatsevents();
jexit();
}




}