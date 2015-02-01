<?php
/**
* @version 2.1.1
* @package JEM
* @subpackage JEM Banner Module
* @copyright (C) 2014-2015 joomlaeventmanager.net
* @copyright (C) 2005-2009 Christoph Lukes
* @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
*/
defined('_JEXEC') or die;
if ($params->get('use_modal', 0)) {
JHtml::_('behavior.modal', 'a.flyermodal');
$modal = 'flyermodal';
} else {
$modal = 'notmodal';
}
?>
<div id="jemmodulebanner">
<?php ?>
	<div class="eventset" summary="mod_jem_banner">
		<?php foreach ($list as $item) : ?>

		<?php if ($item->eventlink) : ?>
			<h2 class="event-title">
			<a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->title; ?>"><?php echo $item->title; ?></a>
			</h2>
		<?php else : ?>
			<h2 class="event-title">
			<?php echo $item->title; ?>
			</h2>
		<?php endif; ?>
		
			<div>

			<?php if ($params->get('showcalendar', 1) == 1) :?>
				<div>
					<div class="calendar">
						<div class="monthbanner">
							<?php echo $item->month; ?>
						</div>
						<div class="daybanner">
							<?php echo $item->daynamefull; ?>
						</div>
						<div class="daynumbanner">
							<?php echo $item->daynum; ?>
						</div>
					</div>
     
				</div>
 			<?php endif; ?>
				<?php if ($params->get('showflyer', 1) == 1) :?>			
				<div>
					<div class="banner-jem">
						<?php if ($params->get('showcalendar', 1) == 1) :?>
							<div>
								<?php if(($item->eventimage)!=str_replace("jpg","",($item->eventimage)) OR
								($item->eventimage)!=str_replace("gif","",($item->eventimage)) OR
								($item->eventimage)!=str_replace("png","",($item->eventimage))) : ?>
								<a href="<?php echo $item->eventimageorig; ?>" class="<?php echo $modal;?>" title="<?php echo $item->title; ?> ">
								<img class="float_right image-preview" src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->title; ?>" /></a>
								<?php else : ?>
									</br></br></br></br>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if ($params->get('showcalendar', 0) == 0) :?>
							<div>
								<?php if(($item->eventimage)!=str_replace("jpg","",($item->eventimage)) OR
								($item->eventimage)!=str_replace("gif","",($item->eventimage)) OR
								($item->eventimage)!=str_replace("png","",($item->eventimage))) : ?>
								<a href="<?php echo $item->eventimageorig; ?>" class="<?php echo $modal;?>" title="<?php echo $item->title; ?> ">
								<img class="float_right image-preview2" src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->title; ?>" /></a>
								<?php else : ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>			

					</div>
				</div>
				<?php endif; ?>	
				<?php if ($params->get('showflyer', 0) == 0) :?>			
				<div>
					<div class="banner-jem">
						<?php if ($params->get('showcalendar', 1) == 1) :?>
							<div>

									</br></br></br></br></br>

							</div>
						<?php endif; ?>
		

					</div>
				</div>
				<?php endif; ?>	
			</div>
		

			<div class="desc">

				<?php echo $item->eventdescription; ?>
				<?php if (isset($item->link) && $item->readmore != 0 && $params->get('readmore')) :
				echo '</br><a class="readmore" href="'.$item->link.'">'.$item->linkText.'</a>';
				endif;?>
			</div>



			<?php
/*Datum und Zeitangabe*/
?>
			</br>

								<?php			
				/*wenn kein Kalenderblatt angezeigt wird*/?>
				<?php if ($params->get('showcalendar', 0) == 0) :?>
			
					<?php if ($item->date && $params->get('datemethod', 1) == 2) :?>
						<div class="date">
						<?php echo $item->date; ?>

						</div>
					<?php endif; ?>
				
					<?php if ($item->date && $params->get('datemethod', 1) == 1) :?>
						<div class="date">

						<?php echo $item->daynamefull;?>
						<?php echo ', ';?>						
						<?php echo $item->daynum; ?>
						<?php echo '.';?>							
				        <?php echo $item->month; ?>
				        <?php echo $item->year; ?>						
						<?php if ($item->time && $params->get('datemethod', 1) == 1) :?>
						<?php echo ', um '?><?php echo $item->time; ?>

						<?php echo JText::_('MOD_JEM_BANNER_TIMEEXPRESSION') ?>
						<?php endif; ?>
						</div>
					<?php endif; ?>

				
				<?php endif; ?>
					<?php			
				/*wenn Kalenderblatt angezeigt wird*/?>			
				<?php if ($params->get('showcalendar', 1) == 1) :?>
								<?php			
				/*wenn Zeitdifferenz angezeigt werden soll*/?>
					<?php if ($item->date && $params->get('datemethod', 1) == 2) :?>
						<div class="date">
						<?php echo $item->date; ?>

						

						</div>
					<?php endif; ?>
									<?php			
				/*wenn Datum angezeigt werden soll*/?>			
					<?php if ($item->time && $params->get('datemethod', 1) == 1) :?>
					<?php			
				/*es muss nur noch die Zeit angezeigt werden (da Datum auf Kalenderblatt schon angezeigt) */?>
						<div class="time">
						<?php echo $item->time; ?>
						<?php echo JText::_('MOD_JEM_BANNER_TIMEEXPRESSION') ?>
						</div>
					<?php endif; ?>

				
				<?php endif; ?>	
		

			<?php if ($params->get('hidevenue', 0) == 0) :?>			
				<div class="venue-title">
				<?php if ($item->venuelink) : ?>
					<a href="<?php echo $item->venuelink; ?>" title="<?php echo $item->venue; ?>"><?php echo $item->venue; ?></a>
				<?php else : ?>
				<?php echo $item->venue; ?>
				<?php endif; ?>
				
				</div>	
			<?php endif; ?>	
			<?php
/*category*/?>			
			<?php if ($params->get('hidecategory', 0) == 0) :?>	
				<div class="category">
				<?php echo $item->catname; ?>
				</div>
			<?php endif; ?>			
		

			<div class="hr"><hr /></div>
			</br>
		<?php endforeach; ?>
	</div>	
</div>