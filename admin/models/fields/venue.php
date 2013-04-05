<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// Check to ensure this file is included in Joomla!
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined('_JEXEC') or die();

jimport('joomla.form.formfield');
jimport('joomla.html.parameter.element');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');


/**
 * Renders an venue element
 *
 * @package Joomla
 * @subpackage EventList
 * @since 1.5
 */
/**
 * HelloWorld Form Field class for the HelloWorld component
 */
class JFormFieldVenue extends JFormFieldList
{
        /**
         * The field type.
         *
         * @var         string
         */
        protected $type = 'Venue';
 
        /**
         * Method to get a list of options for a list input.
         *
         * @return      array           An array of JHtml options.
         */
        protected function getOptions() 
        {
                $db = JFactory::getDBO();
                $query = $db->getQuery(true);
                $query->select('id,venue');
                $query->from('#__eventlist_venues');
                $db->setQuery((string)$query);
                $venues = $db->loadObjectList();
                $options = array();
                if ($venues)
                {
                        foreach($venues as $venue) 
                        {
                                $options[] = JHtml::_('select.option', $venue->id, $venue->venue);
                        }
                }
                $options = array_merge(parent::getOptions(), $options);
                return $options;
        }
}

?>