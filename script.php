<?php



// No direct access to this file
defined('_JEXEC') or die;
$db =  JFactory::getDBO();
jimport('joomla.filesystem.folder');


/**
 * Script file of HelloWorld component
 */
class com_jemInstallerScript
{
	/**
	 * method to install the component
	 *
	 * @return void
	 */
	function install($parent) 
	{
		// $parent is the class calling this method
	//	$parent->getParent()->setRedirectURL('index.php?option=com_jem');
		
		// Check for existing /images/jem directory
    if (!$direxists = JFolder::exists(JPATH_SITE.'/images/jem'))
    {?>
	    
	    <table cellpadding="4" cellspacing="0" border="0" width="100%" class="adminlist">
	<tr>
		<td valign="top">
    		<img src="<?php echo '../media/com_jem/images/jemlogo.png'; ?>" height="100" width="250" alt="jem Logo" align="left">
		</td>
		<td valign="top" width="100%">
       	 	<strong>JEM</strong><br/>
        	<font class="small">by <a href="http://www.joomlaeventmanager.net" target="_blank">joomlaeventmanager.net </a><br/>
        	Released under the terms and conditions of the <a href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GNU General Public License</a>.
        	</font>
		</td>
	</tr>
	<tr>
		<td colspan="2">
	    
			<code>Installation Status:<br />
			<?php
			// Check for existing /images/jem directory
			if ($direxists = JFolder::exists( JPATH_SITE.'/images/jem' )) {
				echo "<font color='green'>FINISHED:</font> Directory /images/jem exists. Skipping creation.<br />";
			} else {
				echo "<font color='orange'>Note:</font> The Directory /images/jem does NOT exist. jem will try to create them.<br />";

				//Image folder creation 
				if ($makedir = JFolder::create( JPATH_SITE.'/images/jem')) {
					echo "<font color='green'>FINISHED:</font> Directory /images/jem created.<br />";
				} else {
					echo "<font color='red'>ERROR:</font> Directory /images/jem NOT created.<br />";
				}
                if (JFolder::create(JPATH_SITE.'/images/jem/categories')) {
					echo "<font color='green'>FINISHED:</font> Directory /images/jem/categories created.<br />";
				} else {
					echo "<font color='red'>ERROR:</font> Directory /images/jem/categories NOT created.<br />";
				}
				if (JFolder::create( JPATH_SITE.'/images/jem/categories/small')) {
					echo "<font color='green'>FINISHED:</font> Directory /images/jem/categories/small created.<br />";
				} else {
					echo "<font color='red'>ERROR:</font> Directory /images/jem/categories/small NOT created.<br />";
				}
				if (JFolder::create(JPATH_SITE.'/images/jem/events')) {
					echo "<font color='green'>FINISHED:</font> Directory /images/jem/events created.<br />";
				} else {
					echo "<font color='red'>ERROR:</font> Directory /images/jem/events NOT created.<br />";
				}
				if (JFolder::create( JPATH_SITE.'/images/jem/events/small')) {
					echo "<font color='green'>FINISHED:</font> Directory /images/jem/events/small created.<br />";
				} else {
					echo "<font color='red'>ERROR:</font> Directory /images/jem/events/small NOT created.<br />";
				}
				if (JFolder::create( JPATH_SITE.'/images/jem/venues')) {
					echo "<font color='green'>FINISHED:</font> Directory /images/jem/venues created.<br />";
				} else {
					echo "<font color='red'>ERROR:</font> Directory /images/jem/venues NOT created.<br />";
				}
				if (JFolder::create( JPATH_SITE.'/images/jem/venues/small')) {
					echo "<font color='green'>FINISHED:</font> Directory /images/jem/venues/small created.<br />";
				} else {
					echo "<font color='red'>ERROR:</font> Directory /images/jem/venues/small NOT created.<br />";
				}
			}
        	?>
        	<?php
		$db =  JFactory::getDBO();
		  $query = "INSERT INTO #__jem_settings VALUES (1, 2, 1, 1, 1, 1, 1, 1, '1', '1', '100%', '20%', '40%', '20%', '', 'Datum', 'Activiteit', 'Locatie', 'city', '%d.%m.%Y', '%H.%M', 'h', 1, 1, 1, 1, 1, 1, 1, 1, -2, 0, 'example@example.com', 0, '1000', -2, -2, -2, 1, '', 'Type', 1, 1, 1, 1, '100', '100', '100', 1, 1, 0, 0, 1, 2, 2, -2, 1, 0, -2, 1, 0, 1, '[title], [a_name], [categories], [times]', 'The event titled [title] starts on [dates]!', 1, 0, 'State', '0', 0, 1, 0, '1364604520', '', '', 'NL', 'NL', '100', '10%', 0, 'evimage', '0', 0, 1, 1, 1, 1, 1, 1, 1, 0, 0, 'attendee', '10%', 1, 30, 1, 1, 'media/com_jem/attachments', '1000', 'txt,csv,htm,html,xml,css,doc,xls,zip,rtf,ppt,pdf,swf,flv,avi,wmv,mov,jpg,jpeg,gif,png,tar.gz', 0, '30', 100, 1)";
        $db->setQuery($query);
        if (!$db->query())
        {
            echo "<font color='red'>ERROR:</font> Insert of default settings failed.<br />";
        } else
        {
            echo "<font color='green'>FINISHED:</font> Insert of default settings was a succes.<br />";
        }	
        ?>
			<br />

			<?php
			if (($direxists) || ($makedir)) {
			?>
				<font color="green"><b>JEM Installed Successfully!</b></font><br />
				Ensure that JEM has write access to the directories shown above! Have Fun.
				</code>
			<?php
			} else {
			?>
				<font color="red">
				<b>Unfortunately JEM could NOT be installed successfully!</b>
				</font>
				<br /><br />
				Please check following directories:<br />
				</code>
				<ul>
					<li>/images/jem</li>
					<li>/images/jem/categories</li>
					<li>/images/jem/categories/small</li>
					<li>/images/jem/events</li>
					<li>/images/jem/events/small</li>
					<li>/images/jem/venues</li>
					<li>/images/jem/venues/small</li>
				</ul>
				<br />

				<code>
					If they do not exist, create them and ensure JEM has write access to these directories.<br />
					If you don't so, you prevent JEM from functioning correctly. (You can't upload images).
				</code>
				
			<?php
			}
			
			
		?>	
		</td>
	</tr>
</table>
	  <?php  
	    
    }
    
  

	}  // end of fuction install

	/**
	 * method to uninstall the component
	 *
	 * @return void
	 */
	function uninstall($parent) 
	{
		// $parent is the class calling this method
		echo '<p>' . JText::_('COM_JEM_UNINSTALL_TEXT') . '</p>';
	}

	/**
	 * method to update the component
	 *
	 * @return void
	 */
	function update($parent) 
	{
		 // $parent is the class calling this method
                echo '<p>' . JText::sprintf('COM_JEM_UPDATE_TEXT', $parent->get('manifest')->version) . '</p>';
	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent) 
	{
		// $parent is the class calling this method
		// $type is the type of change (install, update or discover_install)
		echo '<p>' . JText::_('COM_JEM_PREFLIGHT_' . $type . '_TEXT') . '</p>';
	
		
		
		
	}

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * @return void
	 */
	function postflight($type, $parent) 
	{
		// $parent is the class calling this method
		// $type is the type of change (install, update or discover_install)
		echo '<p>' . JText::_('COM_JEM_POSTFLIGHT_' . $type . '_TEXT') . '</p>';
	}
}
