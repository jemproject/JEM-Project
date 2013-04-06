<?php
/**
 * $Id$
 * @package Joomla
 * @subpackage Eventlist
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * Eventlist is maintained by the community located at
 * http://www.joomlaeventmanager.net
 *
 * Eventlist is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * Eventlist is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined('_JEXEC') or die;
// ELHelper::headerDeclarations();
?>


<form action="index.php" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">
  <fieldset>
    <legend><?php echo JText::_('COM_JEM_EXPORT_EVENTS'); ?></legend>
    </br />    
      <table>
        <tr>
          <td>    
            <label for="file">
                <?php echo JText::_( '' ).':'; ?>
            </label>
          </td>
         
        </tr>
        
        <td>
			    
          </td>
        
        
        <tr>
          <td>    
            <label for="replace_events">
                <?php echo JText::_( '' ).':'; ?>
            </label>
          </td>
          <td>
                 <div class="button2-left"><div class="blank"><a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=export&controller=export')"><?php echo JText::_('COM_JEM_EXPORT_EVENTS'); ?></a></div></div>
          </td>
        </tr>
      </table>
  </fieldset>
  
  <fieldset>
    <legend><?php echo JText::_('COM_JEM_EXPORT_CATEGORIES'); ?></legend>
      
      <table>
        <tr>
          <td>    
            <label for="file">
                <?php echo JText::_( '' ).':'; ?>
            </label>
          </td>
          <td>
            
          </td>
        </tr>
        <tr>
          <td>    
            <label for="replace_cats">
                <?php echo JText::_( '' ).':'; ?>
            </label>
          </td>
          <td>
            <div class="button2-left"><div class="blank"><a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=exportcats&controller=export')"><?php echo JText::_('COM_JEM_EXPORT_CATEGORIES'); ?></a></div></div>  
          </td>
        </tr>
      </table>
  </fieldset>
  
  <fieldset>
    <legend><?php echo JText::_('COM_JEM_EXPORT_VENUES'); ?></legend>
    </br />    
      <table>
        <tr>
          <td>    
            <label for="file">
                <?php echo JText::_( '' ).':'; ?>
            </label>
          </td>
         
        </tr>
        
        <td>
			    
          </td>
        
        
        <tr>
          <td>    
            <label for="replace_venues">
                <?php echo JText::_( '' ).':'; ?>
            </label>
          </td>
          <td>
                 <div class="button2-left"><div class="blank"><a title="<?php echo JText::_('COM_JEM_CSV_EXPORT'); ?>" onclick="window.open('index.php?option=com_jem&task=exportvenues&controller=export')"><?php echo JText::_('COM_JEM_EXPORT_VENUES'); ?></a></div></div>
          </td>
        </tr>
      </table>
  </fieldset>
  
  
  
  
  
	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="view" value="export" />
	<input type="hidden" name="controller" value="export" />
	<input type="hidden" name="task" value="" />
</form>

<p class="copyright">
  <?php echo ELAdmin::footer( ); ?>
</p>