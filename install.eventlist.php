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
 
 * Some of the code based on the special installer addon created by Andrew Eddie and the team of jXtended.
 * We thank for this cool idea of extending the installation process easily
 * copyright	2005-2008 New Life in IT Pty Ltd.  All rights reserved.
 */


defined('_JEXEC') or die;

//load libraries
$db =  JFactory::getDBO();
jimport('joomla.filesystem.folder');

$status = new JObject();
$status->modules = array ();
$status->plugins = array ();
$status->updates = array ();
$status->install = array ();

/***********************************************************************************************
 * ---------------------------------------------------------------------------------------------
 * MODULE INSTALLATION SECTION
 * ---------------------------------------------------------------------------------------------
 ***********************************************************************************************/

$modules = & $this->manifest->getAttribute('modules');
if (is_a($modules, 'JXMLElement') && count($modules->children()))
{

    foreach ($modules->children() as $module)
    {
        $mname = $module->attributes('module');
        $mclient = JApplicationHelper::getClientInfo($module->attributes('client'), true);

        // Set the installation path
        if (! empty($mname))
        {
            $this->parent->setPath('extension_root', $mclient->path.DS.'modules'.DS.$mname);
        } else
        {
            $this->parent->abort(JText::_('Module').' '.JText::_('Install').': '.JText::_('No module file specified'));
            return false;
        }

        /*
         * If the module directory already exists, then we will assume that the
         * module is already installed or another module is using that directory.
         */
        if (file_exists($this->parent->getPath('extension_root')) && !$this->parent->getOverwrite())
        {
            $this->parent->abort(JText::_('Module').' '.JText::_('Install').': '.JText::_('Another module is already using directory').': "'.$this->parent->getPath('extension_root').'"');
            return false;
        }

        // If the module directory does not exist, lets create it
        $created = false;
        if (!file_exists($this->parent->getPath('extension_root')))
        {
            if (!$created = JFolder::create($this->parent->getPath('extension_root')))
            {
                $this->parent->abort(JText::_('Module').' '.JText::_('Install').': '.JText::_('Failed to create directory').': "'.$this->parent->getPath('extension_root').'"');
                return false;
            }
        }

        /*
         * Since we created the module directory and will want to remove it if
         * we have to roll back the installation, lets add it to the
         * installation step stack
         */
        if ($created)
        {
            $this->parent->pushStep( array ('type'=>'folder', 'path'=>$this->parent->getPath('extension_root')));
        }

        // Copy all necessary files
        $element = & $module->getElementByPath('files');
        if ($this->parent->parseFiles($element, -1) === false)
        {
            // Install failed, roll back changes
            $this->parent->abort();
            return false;
        }

        // Copy language files
        $element = & $module->getAttribute('languages');
        if ($this->parent->parseLanguages($element, $mclient->id) === false)
        {
            // Install failed, roll back changes
            $this->parent->abort();
            return false;
        }

        // Copy media files
        $element = & $module->getAttribute('media');
        if ($this->parent->parseMedia($element, $mclient->id) === false)
        {
            // Install failed, roll back changes
            $this->parent->abort();
            return false;
        }

        $mtitle = $module->attributes('title');
        $mposition = $module->attributes('position');
        $morder = $module->attributes('order');

        if ($mtitle && $mposition)
        {
            // if module already installed do not create a new instance
            $query = 'SELECT `id` FROM `#__modules` WHERE module = '.$db->Quote($mname);
            $db->setQuery($query);
            if (!$db->Query())
            {
                // Install failed, roll back changes
                $this->parent->abort(JText::_('Module').' '.JText::_('Install').': '.$db->stderr(true));
                return false;
            }
            $id = $db->loadResult();

            if (!$id)
            {
                $row = & JTable::getInstance('module');
                $row->title = $mtitle;
                $row->ordering = $morder;
                $row->position = $mposition;
                $row->showtitle = 0;
                $row->iscore = 0;
                $row->access = ($mclient->id) == 1?2:0;
                $row->client_id = $mclient->id;
                $row->module = $mname;
                $row->published = 1;
                $row->params = '';

                if (!$row->store())
                {
                    // Install failed, roll back changes
                    $this->parent->abort(JText::_('Module').' '.JText::_('Install').': '.$db->stderr(true));
                    return false;
                }

                // Make visible evertywhere if site module
                if ($mclient->id == 0)
                {
                    $query = 'REPLACE INTO `#__modules_menu` (moduleid,menuid) values ('.$db->Quote($row->id).',0)';
                    $db->setQuery($query);
                    if (!$db->query())
                    {
                        // Install failed, roll back changes
                        $this->parent->abort(JText::_('Module').' '.JText::_('Install').': '.$db->stderr(true));
                        return false;
                    }
                }


            }


        }

        $status->modules[] = array ('name'=>$mname, 'client'=>$mclient->name);
    }
}

/***********************************************************************************************
 * ---------------------------------------------------------------------------------------------
 * PLUGIN INSTALLATION SECTION
 * ---------------------------------------------------------------------------------------------
 ***********************************************************************************************/

$plugins = & $this->manifest->getAttribute('plugins');
if (is_a($plugins, 'JXMLElement') && count($plugins->children()))
{

    foreach ($plugins->children() as $plugin)
    {
        $pname = $plugin->attributes('plugin');
        $pgroup = $plugin->attributes('group');
        $porder = $plugin->attributes('order');

        // Set the installation path
        if (! empty($pname) && ! empty($pgroup))
        {
            $this->parent->setPath('extension_root', JPATH_ROOT.DS.'plugins'.DS.$pgroup);
        } else
        {
            $this->parent->abort(JText::_('Plugin').' '.JText::_('Install').': '.JText::_('No plugin file specified'));
            return false;
        }

        /**
         * ---------------------------------------------------------------------------------------------
         * Filesystem Processing Section
         * ---------------------------------------------------------------------------------------------
         */

        // If the plugin directory does not exist, lets create it
        $created = false;
        if (!file_exists($this->parent->getPath('extension_root')))
        {
            if (!$created = JFolder::create($this->parent->getPath('extension_root')))
            {
                $this->parent->abort(JText::_('Plugin').' '.JText::_('Install').': '.JText::_('Failed to create directory').': "'.$this->parent->getPath('extension_root').'"');
                return false;
            }
        }

        /*
         * If we created the plugin directory and will want to remove it if we
         * have to roll back the installation, lets add it to the installation
         * step stack
         */
        if ($created)
        {
            $this->parent->pushStep( array ('type'=>'folder', 'path'=>$this->parent->getPath('extension_root')));
        }

        // Copy all necessary files
        $element = & $plugin->getAttribute('files');
        if ($this->parent->parseFiles($element, -1) === false)
        {
            // Install failed, roll back changes
            $this->parent->abort();
            return false;
        }

        // Copy all necessary files
        $element = & $plugin->getAttribute('languages');
        if ($this->parent->parseLanguages($element, 1) === false)
        {
            // Install failed, roll back changes
            $this->parent->abort();
            return false;
        }

        // Copy media files
        $element = & $plugin->getAttribute('media');
        if ($this->parent->parseMedia($element, 1) === false)
        {
            // Install failed, roll back changes
            $this->parent->abort();
            return false;
        }

        /**
         * ---------------------------------------------------------------------------------------------
         * Database Processing Section
         * ---------------------------------------------------------------------------------------------
         */
        // Check to see if a plugin by the same name is already installed
        $query = 'SELECT `id`'.
        ' FROM `#__extensions`'.
        ' WHERE folder = '.$db->Quote($pgroup).
        ' AND element = '.$db->Quote($pname);
        $db->setQuery($query);
        if (!$db->Query())
        {
            // Install failed, roll back changes
            $this->parent->abort(JText::_('Plugin').' '.JText::_('Install').': '.$db->stderr(true));
            return false;
        }
        $id = $db->loadResult();

        // Was there a plugin already installed with the same name?
        if ($id)
        {

            if (!$this->parent->getOverwrite())
            {
                // Install failed, roll back changes
                $this->parent->abort(JText::_('Plugin').' '.JText::_('Install').': '.JText::_('Plugin').' "'.$pname.'" '.JText::_('already exists!'));
                return false;
            }

        } else
        {
            $row = & JTable::getInstance('plugin');
            $row->name = JText::_(ucfirst($pgroup)).' - '.JText::_(ucfirst($pname));
            $row->ordering = $porder;
            $row->folder = $pgroup;
            $row->iscore = 0;
            $row->access = 0;
            $row->client_id = 0;
            $row->element = $pname;
            $row->published = 1;
            $row->params = '';

            if (!$row->store())
            {
                // Install failed, roll back changes
                $this->parent->abort(JText::_('Plugin').' '.JText::_('Install').': '.$db->stderr(true));
                return false;
            }
        }

        $status->plugins[] = array ('name'=>$pname, 'group'=>$pgroup);
    }
}

/***********************************************************************************************
 * ---------------------------------------------------------------------------------------------
 * EVENTLIST CHECK MODE UPDATE
 * ---------------------------------------------------------------------------------------------
 ***********************************************************************************************/
//detect if catsid field is available in events table. If yes, 1.0 is installed
$query = 'DESCRIBE #__eventlist_events catsid';
$db->setQuery($query);
$update11 = $db->loadResult();

$i = 0;

if ($update11)
{
    $status->updates[] = array ('oldversion'=>'1.0', 'newversion'=>'1.1 Beta');

    #############################################################################
    #																			#
    #		Database Update Logic for EventList 1.0 to EventList 1.1 Beta		#
    #																			#
    #############################################################################
    
    /* Get the current columns */
		$q = "SHOW COLUMNS FROM #__eventlist_events";
		$db->setQuery($q);
		$events_cols = $db->loadObjectList('Field');

    //update current settings
    $query = 'ALTER TABLE #__eventlist_settings'
    .' CHANGE imagehight imageheight VARCHAR( 20 ) NOT NULL,'
    .' ADD reg_access tinyint(4) NOT NULL AFTER regname,'
    .' ADD ownedvenuesonly tinyint(4) NOT NULL AFTER locpubrec,'
	.' DROP mailinform,'
	.' DROP mailinformrec,'
	.' DROP mailinformuser,'
	.' DROP commentsystem'
    ;
    $db->setQuery($query);
    if (!$db->query())
    {
        $status->updates[$i][] = array ('message'=>'Updating settings table: Step 1', 'result'=>'failed');
    } else
    {
        $status->updates[$i][] = array ('message'=>'Updating settings table: Step 1', 'result'=>'success');
    }

	$query = 'UPDATE #__eventlist_settings'
    .' SET meta_keywords = REPLACE(meta_keywords, "[catsid]","[categories]"),'
	.' meta_description = REPLACE(meta_description, "[catsid]","[categories]")'
    ;
    $db->setQuery($query);
    if (!$db->query())
    {
        $status->updates[$i][] = array ('message'=>'Updating settings table: Step 2', 'result'=>'failed');
    } else
    {
        $status->updates[$i][] = array ('message'=>'Updating settings table: Step 2', 'result'=>'success');
    }

    //update events table
    //add new fields
    $query = 'ALTER TABLE #__eventlist_events'
    .( !array_key_exists('recurrence_limit', $events_cols) ? ' ADD recurrence_limit INT NOT NULL AFTER recurrence_counter,' : '')
    .( !array_key_exists('recurrence_limit_date', $events_cols) ? ' ADD recurrence_limit_date DATE NULL DEFAULT NULL AFTER recurrence_limit,' : '')
    .( !array_key_exists('recurrence_first_id', $events_cols) ? ' ADD recurrence_first_id int(11) NOT NULL default \'0\' AFTER meta_description,' : '')
    .( !array_key_exists('recurrence_byday', $events_cols) ? ' ADD recurrence_byday VARCHAR( 20 ) NULL DEFAULT NULL AFTER recurrence_limit_date,' : '')
    .( !array_key_exists('version', $events_cols) ? ' ADD version int(11) unsigned NOT NULL default \'0\' AFTER modified_by,' : '')
    .( !array_key_exists('hits', $events_cols) ? ' ADD hits int(11) unsigned NOT NULL default \'0\' AFTER unregistra' : '')
    ;
    $db->setQuery($query);
    if (!$db->query())
    {
        $status->updates[$i][] = array ('message'=>'Adding new fields to event table', 'result'=>'failed, skipping recurrence convert');
    } else
    {
        $status->updates[$i][] = array ('message'=>'Adding new fields to event table', 'result'=>'success');

        //converting fields to new schema
		    $query = 'UPDATE #__eventlist_events'
		    .' SET recurrence_limit_date = recurrence_counter,'
			.' meta_keywords = REPLACE(meta_keywords, "[catsid]","[categories]"),'
			.' meta_description = REPLACE(meta_description, "[catsid]","[categories]")'
		    ;
		    $db->setQuery($query);
		    if (!$db->query())
		    {
		        $status->updates[$i][] = array ('message'=>'Converting recurrence: Step 1', 'result'=>'failed');
		    } else
		    {
		        $status->updates[$i][] = array ('message'=>'Converting recurrence: Step 1', 'result'=>'success');
		
		        $query = 'ALTER TABLE #__eventlist_events'
				    .' CHANGE recurrence_counter recurrence_counter INT NOT NULL DEFAULT \'0\''
				    ;
				    $db->setQuery($query);
				    if (!$db->query())
				    {
				        $status->updates[$i][] = array ('message'=>'Converting recurrence: Step 2', 'result'=>'failed');
				    } else
				    {
				        $status->updates[$i][] = array ('message'=>'Converting recurrence: Step 2', 'result'=>'success');
				    }
		    }
		    
		    
				$query = ' UPDATE #__eventlist_events '
				       . ' SET recurrence_number = 0,'
				       . ' recurrence_type = 0,'
				       . ' recurrence_counter = 0,'
				       . ' recurrence_limit = 0,'
				       . ' recurrence_limit_date = NULL,'
				       . ' recurrence_byday = ""'
				            ;
				$db->setQuery($query);
				if (!$db->query())
				{
					$status->updates[$i][] = array ('message'=>'reset recurrences', 'result'=>'failed');
				} else
				{
					$status->updates[$i][] = array ('message'=>'reset recurrences', 'result'=>'success');
				}
    }



    //convert category structure
    $query = 'SELECT id, catsid FROM #__eventlist_events';
    $db->setQuery($query);
    $categories = $db->loadObjectList();

    $err = 0;
    foreach ($categories AS $category)
    {
        $query = 'INSERT INTO #__eventlist_cats_event_relations VALUES ('.$category->catsid.', '.$category->id.', \'\')';
        $db->setQuery($query);
        if (!$db->query())
        {
            $err++;
        }
    }

    if ($err)
    {
        $status->updates[$i][] = array ('message'=>'Converting to new category structure', 'result'=>'failed');
    } else
    {
        $status->updates[$i][] = array ('message'=>'Converting to new category structure', 'result'=>'success');

        //remove catsid field from events table
		    $query = 'ALTER TABLE #__eventlist_events DROP catsid';
		    $db->setQuery($query);
		    if (!$db->query())
		    {
		        $status->updates[$i][] = array ('message'=>'Removing outdated fields from events table', 'result'=>'failed');
		    } else
		    {
		        $status->updates[$i][] = array ('message'=>'Removing outdated fields from events table', 'result'=>'success');
		    }
    }


    //update venues table
    $query = 'ALTER TABLE #__eventlist_venues'
    .' ADD latitude float default NULL,'
    .' ADD longitude float default NULL,'
    .' ADD version int(11) unsigned NOT NULL default \'0\''
    ;
    $db->setQuery($query);
    if (!$db->query())
    {
        $status->updates[$i][] = array ('message'=>'Adding new fields to venues table', 'result'=>'failed');
    } else
    {
        $status->updates[$i][] = array ('message'=>'Adding new fields to venues table', 'result'=>'success');
    }

    /* Get the current columns */
		$q = "SHOW COLUMNS FROM #__eventlist_categories";
		$db->setQuery($q);
		$events_cats = $db->loadObjectList('Field');
		
		if (!array_key_exists('color', $events_cats))
		{
	    //update categories table
	    $query = 'ALTER IGNORE TABLE #__eventlist_categories'
	    .' ADD color varchar(20) NOT NULL default \'\''
	    ;
	
	    $db->setQuery($query);
	    if (!$db->query())
	    {
	        $status->updates[$i][] = array ('message'=>'Adding new fields to categories table', 'result'=>'failed');
	    } else
	    {
	        $status->updates[$i][] = array ('message'=>'Adding new fields to categories table', 'result'=>'success');
	    }
		}

    //increase counter
    $i++;

    #############################################################################
    #																			#
    #	END: Database Update Logic for EventList 1.0 to EventList 1.1 Beta		#
    #																			#
    #############################################################################
}

#############################################################################
#																			#
#	BEGIN: Database Update Logic for EventList 1.1 Beta to Current EventList development version
#																			#
#############################################################################
//is this a fresh install ? in that case, there should be no settings yet
$query = 'SELECT id FROM #__eventlist_settings WHERE id = 1';
$db->setQuery($query);
$freshinstall = !$db->loadResult();

if (!$freshinstall) // update only if not fresh install
{	
  $status->updates[] = array ('oldversion'=>'1.1 Beta', 'newversion'=>'1.1 Beta development');
    
	// first get tables to be checked for update
	$tables = array( '#__eventlist_events',
	                 '#__eventlist_register', 
               );
	$tables = $db->getTableFields($tables, false);
	
	// update events table
	$cols = $tables['#__eventlist_events'];
	
	if (!array_key_exists('maxplaces', $cols)) // remove the '&& 0'...
	{
    //update table
    $query = ' ALTER TABLE #__eventlist_events'
           . ' ADD `maxplaces` INT( 11 ) NOT NULL DEFAULT "0", '
           . ' ADD `waitinglist` TINYINT( 1 ) NOT NULL DEFAULT "0" '
           ;

    $db->setQuery($query);
    if (!$db->query())
    {
        $status->updates[$i][] = array ('message'=>'Adding new fields to events table', 'result'=>'failed');
    } else
    {
        $status->updates[$i][] = array ('message'=>'Adding new fields to events table', 'result'=>'success');
    }
	}	
	// update events table
	$cols = $tables['#__eventlist_register'];
	
	if (!array_key_exists('waiting', $cols)) // remove the '&& 0'...
	{
    //update table
    $query = ' ALTER TABLE #__eventlist_register'
           . ' ADD `waiting` TINYINT( 1 ) NOT NULL DEFAULT "0" '
           ;

    $db->setQuery($query);
    if (!$db->query())
    {
        $status->updates[$i][] = array ('message'=>'Adding new fields to register table', 'result'=>'failed');
    } else
    {
        $status->updates[$i][] = array ('message'=>'Adding new fields to register table', 'result'=>'success');
    }
	}	
	
	//increase counter
	$i++;
}
#############################################################################
#																			#
#	END: Database Update Logic for EventList 1.1 Beta to Current EventList development version
#																			#
#############################################################################

/***********************************************************************************************
 * ---------------------------------------------------------------------------------------------
 * EVENTLIST FRESH INSTALL
 * ---------------------------------------------------------------------------------------------
 ***********************************************************************************************/

if ($freshinstall)
{	
    // Check for existing /images/eventlist directory
    if (!$direxists = JFolder::exists(JPATH_SITE.'/images/eventlist'))
    {
        //Image folder creation
        if ($makedir = JFolder::create(JPATH_SITE.'/images/eventlist'))
        {
			$status->install[] = array ('message'=>'Try to create directory /images/eventlist', 'result'=>'success');
        } else
        {
            $status->install[] = array ('message'=>'Try to create directory /images/eventlist', 'result'=>'failed');
        }
        if (JFolder::create(JPATH_SITE.'/images/eventlist/events'))
        {
			$status->install[] = array ('message'=>'Try to create directory /images/eventlist/events', 'result'=>'success');
        } else
        {
           $status->install[] = array ('message'=>'Try to create directory /images/eventlist/events', 'result'=>'failed');
        }
        if (JFolder::create(JPATH_SITE.'/images/eventlist/events/small'))
        {
			$status->install[] = array ('message'=>'Try to create directory /images/eventlist/events/small', 'result'=>'success');
        } else
        {
            $status->install[] = array ('message'=>'Try to create directory /images/eventlist/events/small', 'result'=>'failed');
        }
        if (JFolder::create(JPATH_SITE.'/images/eventlist/categories'))
        {
			$status->install[] = array ('message'=>'Try to create directory /images/eventlist/categories', 'result'=>'success');
        } else
        {
           $status->install[] = array ('message'=>'Try to create directory /images/eventlist/categories', 'result'=>'failed');
        }
        if (JFolder::create(JPATH_SITE.'/images/eventlist/categories/small'))
        {
			$status->install[] = array ('message'=>'Try to create directory /images/eventlist/categories/small', 'result'=>'success');
        } else
        {
            $status->install[] = array ('message'=>'Try to create directory /images/eventlist/categories/small', 'result'=>'failed');
        }
        if (JFolder::create(JPATH_SITE.'/images/eventlist/venues'))
        {
			$status->install[] = array ('message'=>'Try to create directory /images/eventlist/venues', 'result'=>'success');
        } else
        {
            $status->install[] = array ('message'=>'Try to create directory /images/eventlist/venues', 'result'=>'failed');
        }
        if (JFolder::create(JPATH_SITE.'/images/eventlist/venues/small'))
        {
			$status->install[] = array ('message'=>'Try to create directory /images/eventlist/venues/small', 'result'=>'success');
        } else
        {
			$status->install[] = array ('message'=>'Try to create directory /images/eventlist/venues', 'result'=>'failed');
        }
    }

    //check if default values are available
    $query = 'SELECT * FROM #__eventlist_settings WHERE id = 1';
    $db->setQuery($query);
    $settingsresult = $db->loadResult();
    
    if (!$settingsresult)
    {
        //Set the default setting values -> fresh install
        $query = "INSERT INTO #__eventlist_settings VALUES (1, 0, 1, 0, 1, 1, 1, 0, '', '', '100%', '15%', '25%', '20%', '20%', 'Date', 'Title', 'Venue', 'City', '%d.%m.%Y', '%H.%M', 'h', 1, 0, 1, 1, 1, 1, 1, 2, -2, '1000', -2, -2, -2, 1, '20%', 'Type', 1, 1, 1, 1, 0, '100', '100', '100', 0, 1, 0, 0, 1, 2, 2, -2, 1, 0, -2, 1, 0, 0, '[title], [a_name], [categories], [times]', 'The event titled [title] starts on [dates]!', 0, 'State', 0, '', 0, 1, '1174491851', '', '')";
        $db->setQuery($query);
        if (!$db->query())
        {
            echo "<font color='red'>Error loading default setting values. Please apply changes manually!</font><br />";
			$status->install[] = array ('message'=>'Try to load default setting values', 'result'=>'failed');
        } else
        {
            $status->install[] = array ('message'=>'Try to load default setting values', 'result'=>'success');
        }
    }
}



/***********************************************************************************************
 * ---------------------------------------------------------------------------------------------
 * OUTPUT TO SCREEN
 * ---------------------------------------------------------------------------------------------
 ***********************************************************************************************/
$rows = 0;
?>
<img src="components/com_eventlist/assets/images/evlogo.png" width="250" height="108" alt="EventList Logo" align="right" /><h2>EventList installation</h2>
<table class="adminlist">
    <thead>
        <tr>
            <th class="title" colspan="2">
                <?php
                echo JText::_('Extension');
                ?>
            </th>
            <th width="30%">
                <?php
                echo JText::_('Status');
                ?>
            </th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td colspan="3">
            </td>
        </tr>
    </tfoot>
    <tbody>
        <tr class="row0">
            <td class="key" colspan="2">
                <?php
                echo 'EventList '.JText::_('Component');
                ?>
            </td>
            <td>
                <strong>
                    <?php
                    echo JText::_('Installed');
                    ?>
                </strong>
            </td>
        </tr>
        <?php
        if (count($status->modules)):
        ?>
        <tr>
            <th>
                <?php
                echo JText::_('Module');
                ?>
            </th>
            <th>
                <?php
                echo JText::_('Client');
                ?>
            </th>
            <th>
            </th>
        </tr>
        <?php
        foreach ($status->modules as $module):
        ?>
        <tr class="row<?php echo (++ $rows % 2); ?>">
            <td class="key">
                <?php
                echo $module['name'];
                ?>
            </td>
            <td class="key">
                <?php
                echo ucfirst($module['client']);
                ?>
            </td>
            <td>
                <strong>
                    <?php
                    echo JText::_('Installed');
                    ?>
                </strong>
            </td>
        </tr>
        <?php
        endforeach;
        endif;
        
        if (count($status->plugins)):
        ?>
        <tr>
            <th>
                <?php
                echo JText::_('Plugin');
                ?>
            </th>
            <th>
                <?php
                echo JText::_('Group');
                ?>
            </th>
            <th>
            </th>
        </tr>
        <?php
        foreach ($status->plugins as $plugin):
        ?>
        <tr class="row<?php echo (++ $rows % 2); ?>">
            <td class="key">
                <?php
                echo ucfirst($plugin['name']);
                ?>
            </td>
            <td class="key">
                <?php
                echo ucfirst($plugin['group']);
                ?>
            </td>
            <td>
                <strong>
                    <?php
                    echo JText::_('Installed');
                    ?>
                </strong>
            </td>
        </tr>
        <?php
        endforeach;
        endif;
        ?>
    </tbody>
</table>
<?php
if (count($status->updates)):
?>
<?php
foreach ($status->updates as $update):
?>
<h3>
    <?php
    echo JText::_('Detected Version').': '.$update['oldversion'].'. Update to '.$update['newversion'];
    ?>
</h3>
<table class="adminlist">
    <thead>
        <tr>
            <th class="title" colspan="2">
                <?php
                echo JText::_('Action');
                ?>
            </th>
            <th width="30%">
                <?php
                echo JText::_('Status');
                ?>
            </th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td colspan="3">
            </td>
        </tr>
    </tfoot>
    <tbody>
        <?php
        //TODO: temp quick fix only
        array_shift($update);
        array_shift($update);
        
        foreach ($update as $step):
        ?>
        <tr class="row<?php echo (++ $rows % 2); ?>">
            <td class="key" colspan="2">
                <?php
                echo $step['message'];
                ?>
            </td>
            <td class="key">
                <?php
                echo $step['result'];
                ?>
            </td>
        </tr>
        <?php
        endforeach;
        ?>
    </tbody>
</table>
<?php
endforeach;
endif;
?>

<?php
if (count($status->install)):
?>
<h3>
    Pre install actions
</h3>
<table class="adminlist">
    <thead>
        <tr>
            <th class="title" colspan="2">
                <?php
                echo JText::_('Action');
                ?>
            </th>
            <th width="30%">
                <?php
                echo JText::_('Status');
                ?>
            </th>
        </tr>
    </thead>
    <tfoot>
        <tr>
            <td colspan="3">
            </td>
        </tr>
    </tfoot>
    <tbody>
        <?php        
        foreach ($status->install as $install):
        ?>
        <tr class="row<?php echo (++ $rows % 2); ?>">
            <td class="key" colspan="2">
                <?php
                echo $install['message'];
                ?>
            </td>
            <td class="key">
                <?php
                echo $install['result'];
                ?>
            </td>
        </tr>
        <?php
        endforeach;
        ?>
    </tbody>
</table>
<?php
endif;
?>
