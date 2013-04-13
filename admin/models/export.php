<?php
/**
* @version 1.0.0
* @package JEM
* @copyright Copyright (C) 2012. All rights reserved.
* @license GNU General Public License version 2 or later; see LICENSE.txt
*
* Based on:  https://gist.github.com/dongilbert/4195504
*  
*
/  
*/
 
// No direct access.
defined('_JEXEC') or die;
 
jimport('joomla.application.component.modellist');
 
class JEMModelExport extends JModelList
{
 
/**
* Constructor.
*
* @param array An optional associative array of configuration settings.
* @see JController
* @since 1.6
*/
public function __construct($config = array())
{
if (empty($config['filter_fields']))
{
$config['filter_fields'] = array(
'id', 'a.id',
);
}
 
parent::__construct($config);
}
 
 
/**
* Method to auto-populate the model state.
*
* Note. Calling getState in this method will result in recursion.
*/
protected function populateState($ordering = null, $direction = null)
{
// Initialise variables.
$app = JApplication::getInstance('administrator');
 
// Load the filter state.
$filter_form_type = $app->getUserStateFromRequest($this->context.'.filter.form_type', 'filter_form_type');
$this->setState('filter.form_type', $filter_form_type);
 
$filter_start_date = $app->getUserStateFromRequest($this->context.'.filter.start_date', 'filter_start_date');
$this->setState('filter.start_date', $filter_start_date);
 
$filter_end_date = $app->getUserStateFromRequest($this->context.'.filter.end_date', 'filter_end_date');
$this->setState('filter.end_date', $filter_end_date);
 
// Load the parameters.
$params = JComponentHelper::getParams('com_jem');
$this->setState('params', $params);
 
// List state information.
parent::populateState('a.first_name', 'asc');
}
 


		/********
		*
		*
		*   Events
		*
		*
		********/




/**
* Build an SQL query to load the list data.
*
* @return JDatabaseQuery
* @since 1.6
*/
protected function getListQuery()
{
 // Create a new query object.
$db = $this->getDbo();
$query = $db->getQuery(true);
 
 // Select the required fields from the table.
$query->select('a.*');
$query->from('`#__jem_events` AS a');
 
// Filtering form_type
 $filter_form_type = $this->getState("filter.form_type");
 
if ($filter_form_type)
{
$query->where("a.form_type = '{$filter_form_type}'");
}
 
$filter_start_date = (string) $this->getState("filter.start_date");
$filter_end_date = (string) $this->getState("filter.end_date");
 
// Filtering start_date
if ($filter_start_date !== '')
 { 
 $query->where("a.created_time >= '{$filter_start_date} 00:00:00'");
 }
 
// Filtering end_date
 if ($filter_end_date !== '')
 {
 $query->where("a.created_time <= '{$filter_end_date} 23:59:59'");
 }
 
 return $query;
}
 

public function getCsv()
{
$this->populateState();
 
$csv = fopen('php://output', 'w');
$db = $this->getDbo();
$header = array();
$header = array_keys($db->getTableColumns('#__jem_events'));
fputcsv($csv, $header, ';');


$items = $db->setQuery($this->getListQuery())->loadObjectList();


foreach ($items as $lines) {
    foreach ($lines as &$line) {
        $line = mb_convert_encoding($line, 'Windows-1252', 'auto');
    }
    fputcsv($csv, (array) $lines, ';', '"');
}

 
return fclose($csv);
}




		/********
		*
		*
		*   Categories
		*
		*
		********/





/**
* Build an SQL query to load the list data.
*
* @return JDatabaseQuery
* @since 1.6
*/
protected function getListQuerycats()
{
 // Create a new query object.
$db = $this->getDbo();
$query = $db->getQuery(true);
 
 // Select the required fields from the table.
$query->select('a.*');
$query->from('#__jem_categories AS a');
 
// Filtering form_type
 $filter_form_type = $this->getState("filter.form_type");
 
if ($filter_form_type)
{
$query->where("a.form_type = '{$filter_form_type}'");
}
 
$filter_start_date = (string) $this->getState("filter.start_date");
$filter_end_date = (string) $this->getState("filter.end_date");
 
// Filtering start_date
if ($filter_start_date !== '')
 { 
 $query->where("a.created_time >= '{$filter_start_date} 00:00:00'");
 }
 
// Filtering end_date
 if ($filter_end_date !== '')
 {
 $query->where("a.created_time <= '{$filter_end_date} 23:59:59'");
 }
 
 return $query;
}


public function getCsvcats()
{
$this->populateState();
 
$csv = fopen('php://output', 'w');
$db = $this->getDbo();
$header = array();
$header = array_keys($db->getTableColumns('#__jem_categories'));
fputcsv($csv, $header, ';');


$items = $db->setQuery($this->getListQuerycats())->loadObjectList();


foreach ($items as $lines) {
    foreach ($lines as &$line) {
        $line = mb_convert_encoding($line, 'Windows-1252', 'auto');
    }
    fputcsv($csv, (array) $lines, ';', '"');
}

 
return fclose($csv);
}




		/********
		*
		*
		*   Venues
		*
		*
		********/


/**
* Build an SQL query to load the list data.
*
* @return JDatabaseQuery
* @since 1.6
*/
protected function getListQueryvenues()
{
 // Create a new query object.
$db = $this->getDbo();
$query = $db->getQuery(true);
 
 // Select the required fields from the table.
$query->select('a.*');
$query->from('#__jem_venues AS a');
 
// Filtering form_type
 $filter_form_type = $this->getState("filter.form_type");
 
if ($filter_form_type)
{
$query->where("a.form_type = '{$filter_form_type}'");
}
 
$filter_start_date = (string) $this->getState("filter.start_date");
$filter_end_date = (string) $this->getState("filter.end_date");
 
// Filtering start_date
if ($filter_start_date !== '')
 { 
 $query->where("a.created_time >= '{$filter_start_date} 00:00:00'");
 }
 
// Filtering end_date
 if ($filter_end_date !== '')
 {
 $query->where("a.created_time <= '{$filter_end_date} 23:59:59'");
 }
 
 return $query;
}


public function getCsvvenues()
{
$this->populateState();
 
$csv = fopen('php://output', 'w');
$db = $this->getDbo();
$header = array();
$header = array_keys($db->getTableColumns('#__jem_venues'));
fputcsv($csv, $header, ';');


$items = $db->setQuery($this->getListQueryvenues())->loadObjectList();


foreach ($items as $lines) {
    foreach ($lines as &$line) {
        $line = mb_convert_encoding($line, 'Windows-1252', 'auto');
    }
    fputcsv($csv, (array) $lines, ';', '"');
}

 
return fclose($csv);
}




		/********
		*
		*
		*   CATS_EVENTS
		*
		*
		********/




/**
* Build an SQL query to load the list data.
*
* @return JDatabaseQuery
* @since 1.6
*/
protected function getListQuerycatsevents()
{
 // Create a new query object.
$db = $this->getDbo();
$query = $db->getQuery(true);
 
 // Select the required fields from the table.
$query->select('a.*');
$query->from('#__jem_cats_event_relations AS a');
 
// Filtering form_type
 $filter_form_type = $this->getState("filter.form_type");
 
if ($filter_form_type)
{
$query->where("a.form_type = '{$filter_form_type}'");
}
 
$filter_start_date = (string) $this->getState("filter.start_date");
$filter_end_date = (string) $this->getState("filter.end_date");
 
// Filtering start_date
if ($filter_start_date !== '')
 { 
 $query->where("a.created_time >= '{$filter_start_date} 00:00:00'");
 }
 
// Filtering end_date
 if ($filter_end_date !== '')
 {
 $query->where("a.created_time <= '{$filter_end_date} 23:59:59'");
 }
 
 return $query;
}


public function getCsvcatsevents()
{
$this->populateState();
 
$csv = fopen('php://output', 'w');
$db = $this->getDbo();
$header = array();
$header = array_keys($db->getTableColumns('#__jem_cats_event_relations'));
fputcsv($csv, $header, ';');


$items = $db->setQuery($this->getListQuerycatsevents())->loadObjectList();


foreach ($items as $lines) {
    foreach ($lines as &$line) {
        $line = mb_convert_encoding($line, 'Windows-1252', 'auto');
    }
    fputcsv($csv, (array) $lines, ';', '"');
}

 
return fclose($csv);
}





















}