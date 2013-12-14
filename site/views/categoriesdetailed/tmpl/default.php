<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<div id="jem" class="jem_categories_detailed">
<div class="buttons">
	<?php
		echo JEMOutput::submitbutton( $this->dellink, $this->params );
		echo JEMOutput::archivebutton( $this->params, $this->task );
		echo JEMOutput::printbutton( $this->print_link, $this->params );
	?>
</div>



<h1 class="componentheading">
<?php echo $this->escape($this->pagetitle); ?>
</h1>

<?php 

foreach($this->categories as $category) :
?>
	<h2 class="jem cat<?php echo $category->id; ?>">
		<?php echo JHtml::_('link', JRoute::_($category->linktarget), $this->escape($category->catname)); ?>
	</h2>

<div class="cat<?php echo $category->id; ?> floattext">



<div class="catimg">
	  	<?php //flyer

	if (empty($category->image)) {

    $jemsettings =  JEMHelper::config();
    $imgattribs['width'] = $jemsettings->imagewidth;
    $imgattribs['height'] = $jemsettings->imagehight;

	echo  JHtml::_('image','com_jem/noimage.png', $category->catname, $imgattribs,true);
	}else{

	$cimage = JEMImage::flyercreator($category->image, 'category');
	echo JEMOutput::flyer( $category, $cimage, 'category' );

	}
	?>
</div>



	<div class="description"><?php echo $category->description; ?>
		<p>
			<?php
				echo JHtml::_('link', JRoute::_($category->linktarget), $category->linktext);
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
		<strong><a href="<?php echo JRoute::_(JEMHelperRoute::getCategoryRoute($sub->slug)); ?>"><?php echo $this->escape($sub->catname); ?></a></strong> (<?php echo $sub->assignedevents != null ? $sub->assignedevents : 0; ?>)
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

<div class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</div>
</div>