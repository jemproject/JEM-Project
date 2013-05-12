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

defined( '_JEXEC' ) or die;
?>

<div id="jem" class="jem_categories_detailed">
<p class="buttons">
	<?php
		if ( !$this->params->get( 'popup' ) ) : //don't show in printpopup
			echo JEMOutput::submitbutton( $this->dellink, $this->params );
			echo JEMOutput::archivebutton( $this->params, $this->task );
		endif;
		echo JEMOutput::printbutton( $this->print_link, $this->params );
	?>
</p>

<?php if ($this->params->get('show_page_title')) : ?>

<h1 class="componentheading">
<?php echo $this->escape($this->pagetitle); ?>
</h1>

<?php endif;

foreach($this->categories as $category) :
?>
	<h2 class="jem cat<?php echo $category->id; ?>">
		<?php echo JHTML::_('link', JRoute::_($category->linktarget), $this->escape($category->catname)); ?>
	</h2>

<div class="cat<?php echo $category->id; ?> floattext">



<div class="catimg">
	  	<?php //flyer
	
	if (empty($category->image)) {

    $jemsettings =  JEMHelper::config();
    $imgattribs['width'] = $jemsettings->imagewidth;
    $imgattribs['height'] = $jemsettings->imagehight;

	echo  JHTML::image('media/com_jem/images/noimage.png', $category->catname, $imgattribs);
	}else{
	
	$cimage = JEMImage::flyercreator($category->image, 'category');
	echo JEMOutput::flyer( $category, $cimage, 'category' );
	
	}
	?>
</div>



	<div class="catdescription"><?php echo $category->catdescription; ?>
		<p>
			<?php
				echo JHTML::_('link', JRoute::_($category->linktarget), $category->linktext);
			?>
			(<?php echo $category->assignedevents ? $category->assignedevents : '0';?>)
		</p>
	</div>
	<br class="clear" />

</div>

<?php 
//only show this part if subcategries are available
if (count($category->subcats)) :
?>

<div class="subcategories">
<?php echo JText::_('COM_JEM_SUBCATEGORIES'); ?>
</div>
<?php
$n = count($category->subcats);
$i = 0;
?>
<div class="subcategorieslist">
	<?php foreach ($category->subcats as $sub) : ?>
		<strong><a href="<?php echo JRoute::_( 'index.php?view=categoryevents&id='. $sub->slug ); ?>"><?php echo $this->escape($sub->catname); ?></a></strong> (<?php echo $sub->assignedevents != null ? $sub->assignedevents : 0; ?>)
		<?php 
		$i++;
		if ($i != $n) :
			echo ',';
		endif;
	endforeach; ?>
</div>

<?php endif; ?>

<!--table-->
<?php
//TODO: move out of template
$this->rows		= $this->model->getEventdata( $category->id );
$this->categoryid = $category->id;

echo $this->loadTemplate('table');

endforeach;
?>

<!--pagination-->

<div class="pagination">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>



<!--copyright-->

<p class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</p>
</div>