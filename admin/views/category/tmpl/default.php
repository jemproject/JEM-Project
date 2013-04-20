<?php
/**
 * @version 1.0 $Id$
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

defined('_JEXEC') or die;
JHTML::_('behavior.formvalidation');

$options = array(
		'onActive' => 'function(title, description){
		description.setStyle("display", "block");
		title.addClass("open").removeClass("closed");
}',
		'onBackground' => 'function(title, description){
		description.setStyle("display", "none");
		title.addClass("closed").removeClass("open");
}',
		'startOffset' => 0,  // 0 starts on the first tab, 1 starts the second, etc...
		'useCookie' => true, // this must not be a string. Don't use quotes.
);



?>

<script type="text/javascript">
     Joomla.submitbutton = function(task)
	{
    	 var form = document.adminForm;

 		if (task == 'cancel') {
 			submitform( task );
 		} else if (form.catname.value == ""){
 			alert( "<?php echo JText::_ ( 'COM_JEM_ADD_NAME_CATEGORY' ); ?>" );
 			form.catname.focus();
   		} else {
   			<?php echo $this->editor->save( 'catdescription' ); ?>
   			submitform( task );
   		}

	}
</script>


<form
	action="<?php echo JRoute::_('index.php?option=com_jem&view=category'); ?>"
	method="post" name="adminForm" id="adminForm" class="form-validate"
	enctype="multipart/form-data">

	<table style="width:100%">
		<tr>
			<td valign="top"><?php echo JHtml::_('tabs.start','det-pane', $options); ?>

				<?php	echo JHtml::_('tabs.panel',JText::_('COM_JEM_CATEGORY_INFO_TAB'), 'info' ); ?>
				&nbsp;<!-- this is a trick for IE7... otherwise the first table inside the tab is shifted right ! -->
				<table class="adminform">
					<tr>
						<td><label for="catname"> <?php echo JText::_( 'COM_JEM_CATEGORY' ).':'; ?>
						</label>
						</td>
						<td><input name="catname" class="inputbox required" id="catname"
							value="<?php echo $this->row->catname; ?>" size="50"
							maxlength="100" />
						</td>
						<td><label for="published"> <?php echo JText::_( 'COM_JEM_PUBLISHED' ).':'; ?>
						</label>
						</td>
						<td><?php
						$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"', $this->row->published );
						echo $html;
						?>
						</td>
					</tr>
					<tr>
						<td><label for="alias"> <?php echo JText::_( 'COM_JEM_ALIAS' ).':'; ?>
						</label>
						</td>
						<td><input class="inputbox" type="text" name="alias" id="alias"
							size="50" maxlength="100"
							value="<?php echo $this->row->alias; ?>" />
						</td>
						<td><?php echo JText::_( 'COM_JEM_PARENT_CATEGORY' ).':'; ?>
						</td>
						<td><?php echo $this->Lists['parent_id']; ?>
						</td>
					</tr>
				</table>

				<table class="adminform">
					<tr>
						<td><?php
						// parameters : areaname, content, hidden field, width, height, rows, cols
						echo $this->editor->display( 'catdescription',  $this->row->catdescription, '100%;', '350', '75', '20', array('pagebreak', 'readmore') ) ;
						?>
						</td>
					</tr>
				</table> <?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'attachments' ); ?>
				<?php echo $this->loadTemplate('attachments'); ?> <?php echo JHtml::_('tabs.end'); ?>
			</td>
			<td valign="top" width="320px" style="padding: 7px 0 0 5px"><?php
			$title2 = JText::_( 'COM_JEM_ACCESS' );
			echo JHtml::_('sliders.start', 'det-pane', $options);
			echo JHtml::_('sliders.panel', $title2, 'access');
			?>
				<table>
					<tr>
						<td><label for="access"> <?php echo JText::_( 'COM_JEM_ACCESS' ).':'; ?>
						</label>
						</td>
						<td><?php
						echo $this->Lists['access'];
						?>
						</td>
					</tr>
				</table> <?php
				$title2 = JText::_( 'COM_JEM_GROUP' );
				echo JHtml::_('sliders.panel', $title2, 'group');
				?>
				<table>
					<tr>
						<td><label for="groups"> <?php echo JText::_( 'COM_JEM_GROUP' ).':'; ?>
						</label>
						</td>
						<td><?php echo $this->Lists['groups']; ?>
						</td>
					</tr>
				</table> <?php
				$title2 = JText::_( 'COM_JEM_IMAGE' );
				echo JHtml::_('sliders.panel', $title2, 'image');
				?>
				<table>
					<tr>
						<td><label for="catimage"> <?php echo JText::_( 'COM_JEM_CHOOSE_IMAGE' ).':'; ?>
						</label>
						</td>
						<td><?php echo $this->imageselect; ?>
						</td>
					</tr>
					<tr>
						<td></td>
						<td><img src="../media/system/images/blank.png" name="imagelib"
							id="imagelib" width="80" height="80" border="2" alt="Preview" />
							<script type="text/javascript">
						if (document.forms[0].a_imagename.value!=''){
							var imname = document.forms[0].a_imagename.value;
							jsimg='../images/jem/categories/' + imname;
							document.getElementById('imagelib').src= jsimg;
						}
						</script> <br />
						</td>
					</tr>
				</table> <?php
				$title3 = JText::_( 'COM_JEM_COLOR' );
				echo JHtml::_('sliders.panel', $title3, 'color2');
				?>
				<table>
					<tr>
						<td><label for="color"> <?php echo JText::_( 'COM_JEM_CHOOSE_COLOR' ).':'; ?>
						</label>
						</td>
						<td><input class="inputbox" type="text" style="background: <?php echo ( $this->row->color == '' )?"transparent":$this->row->color; ?>;"
                   name="color" id="color" size="10" maxlength="20" value="<?php echo $this->row->color; ?>" />
							<input type="button" class="button"
							value="<?php echo JText::_( 'COM_JEM_PICK' ); ?>"
							onclick="openPicker('color', -200, 20);" />
						</td>
					</tr>
				</table> <?php
				$title4 = JText::_( 'COM_JEM_METADATA_INFORMATION' );
				echo JHtml::_('sliders.panel', $title4, 'metadata');
				?>
				<table>
					<tr>
						<td><label for="metadesc"> <?php echo JText::_( 'COM_JEM_META_DESCRIPTION' ); ?>:
						</label> <br /> <textarea class="inputbox" cols="40" rows="5"
								name="meta_description" id="metadesc" style="width: 300px;">
								<?php echo str_replace('&','&amp;',$this->row->meta_description); ?>
							</textarea>
						</td>
					</tr>
					<tr>
						<td><label for="metakey"> <?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?>:
						</label> <br /> <textarea class="inputbox" cols="40" rows="5"
								name="meta_keywords" id="metakey" style="width: 300px;">
								<?php echo str_replace('&','&amp;',$this->row->meta_keywords); ?>
							</textarea>
						</td>
					</tr>
					<tr>
						<td><input type="button" class="button"
							value="<?php echo JText::_( 'COM_JEM_ADD_CATNAME' ); ?>"
							onclick="f=document.adminForm;f.metakey.value=f.catname.value;" />
						</td>
					</tr>
				</table> <?php
				echo JHtml::_('sliders.end');

				?>
			</td>
		</tr>
	</table>

	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
	<input type="hidden" name="controller" value="categories" /> <input
		type="hidden" name="task" value="" />
</form>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>