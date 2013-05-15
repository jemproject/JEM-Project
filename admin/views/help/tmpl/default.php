<?php
/**
 * @version 1.9 $Id$
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
<form action="index.php" method="post" name="adminForm" id="adminForm">
<table border="1" class="adminform">
	<tr>
		<td colspan="2">
			<table width="100%">
				<tr>
					<td>
						<strong><?php echo JText::_( 'COM_JEM_SEARCH' ); ?></strong>
						<input class="text_area" type="hidden" name="option" value="com_jem" />
						<input type="text" name="search" id="search" value="<?php echo $this->helpsearch;?>" class="inputbox" />
						<input type="submit" value="<?php echo JText::_( 'COM_JEM_GO' ); ?>" class="button" />
						<input type="button" value="<?php echo JText::_( 'COM_JEM_RESET' ); ?>" class="button" onclick="f=document.adminForm;f.search.value='';f.submit()" />
					</td>
					<td style="text-align:right">
						<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/el.intro.html'; ?>" target='helpFrame'><?php echo JText::_( 'COM_JEM_HOME' ); ?></a>
						|
						<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/helpsite/el.gethelp.html'; ?>" target='helpFrame'><?php echo JText::_( 'COM_JEM_GET_HELP' ); ?></a>
						|
						<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/helpsite/el.givehelp.html'; ?>" target='helpFrame'><?php echo JText::_( 'COM_JEM_GIVE_HELP' ); ?></a>
						|
						<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/helpsite/el.credits.html'; ?>" target='helpFrame'><?php echo JText::_( 'COM_JEM_CREDITS' ); ?></a>
						|
						<?php echo JHTML::_('link', 'http://www.gnu.org/licenses/gpl-2.0.html', JText::_( 'COM_JEM_LICENSE' ), array('target' => 'helpFrame')) ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<table style="width:100%" class="adminlist">
	<tr valign="top">
		<td align="left">

			<?php
			echo JHtml::_('sliders.start', 'det-pane', $options);
			
			$title2 = JText::_( 'COM_JEM_SCREEN_HELP' );
			echo JHtml::_('sliders.panel', $title2, 'help');
			?>
			<table class="adminlist">
				<?php
				foreach ($this->toc as $k=>$v) {
					echo '<tr>';
					echo '<td>';
					echo JHTML::Link('components/com_jem/help/'.$this->langTag.'/'.$k, $v, array('target' => 'helpFrame'));
					echo '</td>';
					echo '</tr>';
				}
				?>
			</table>
			<?php 
			$title3 = JText::_( 'COM_JEM_CONFIG_INFO' );
			echo JHtml::_('sliders.panel', $title3, 'registra');
			?>
			<table class="adminlist">
				<tr>
				<td><label>Version:</label></td>
				<td><?php echo '1.9'; ?></td>
				</tr>
			</table>
			

			<?php
			echo JHtml::_('sliders.end');
		  	?>
		</td>
		<td width="75%">
			<iframe name="helpFrame" src="<?php echo 'components/com_jem/help/'.$this->langTag.'/el.intro.html'; ?>" class="helpFrame"></iframe>
		</td>
	</tr>
</table>
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="view" value="help" />
<input type="hidden" name="task" value="" />

</form>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>

<?php
//keep session alive
JHTML::_('behavior.keepalive');
?>