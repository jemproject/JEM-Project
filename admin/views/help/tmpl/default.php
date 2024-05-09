<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;


$options = array(
    'onActive' => 'function(title, description){
        description.setStyle("display", "block");
        title.addClass("open").removeClass("closed");
    }',
    'onBackground' => 'function(title, description){
        description.setStyle("display", "none");
        title.addClass("closed").removeClass("open");
    }',
	'opacityTransition' => true,
    'startOffset' => 0,  // 0 starts on the first tab, 1 starts the second, etc...
    'useCookie' => true, // this must not be a string. Don't use quotes.
);
?>
<form action="<?php echo Route::_('index.php?option=com_jem&view=help'); ?>" method="post" name="adminForm" id="adminForm">

	<div id="j-main-container" class="j-main-container">
	    <div class="row mb-3">
		   <div class="col-md-12">
				<fieldset id="filter-bar" class=" mb-3">
					<div class="row mb-3">
						<div class="col-md-4">
							<div class="input-group">  
								<input type="text" name="filter_search" id="filter_search" class="form-control" aria-describedby="filter_search-desc" placeholder="<?php echo Text::_('COM_JEM_SEARCH');?>" value="<?php echo $this->helpsearch;?>"  inputmode="search" onChange="document.adminForm.submit();" >											
								
								<button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="Search">
									<span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
								</button>
								<button type="button" class="btn btn-primary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
							</div>
						</div>
						<div class="col-md-8">
							<div class="filter-select fltrt">
									<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/intro.html'; ?>" target='helpFrame'><?php echo Text::_('COM_JEM_HOME'); ?></a>
									|
									<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/helpsite/gethelp.html'; ?>" target='helpFrame'><?php echo Text::_('COM_JEM_GET_HELP'); ?></a>
									|
									<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/helpsite/contribute.html'; ?>" target='helpFrame'><?php echo Text::_('COM_JEM_CONTRIBUTE'); ?></a>
									|
									<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/helpsite/credits.html'; ?>" target='helpFrame'><?php echo Text::_('COM_JEM_CREDITS'); ?></a>
									|
									<?php echo HTMLHelper::_('link', 'https://www.gnu.org/licenses/gpl-3.0', Text::_('COM_JEM_LICENSE'), array('target' => '_blank')) ?>
								
							</div>
						</div>
					</div>
				</fieldset>
		   </div>
		</div>
		<div class="row">
			<div class="col-md-4">
				<div id="treecellhelp" class="w-100">
					<div class="accordion" id="accordionHelpForm">
						<div class="accordion-item">
							<h2 class="accordion-header" id="det-pane-header">
							<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#det-pane-details" aria-expanded="true" aria-controls="det-pane-details">
								<?php echo Text::_('COM_JEM_SCREEN_HELP'); ?>
							</button>
							</h2>
							<div id="det-pane-details" class="accordion-collapse collapse show" aria-labelledby="det-pane-header" data-bs-parent="#accordionHelpForm">
								<div class="accordion-body">
									<fieldset class="panelform">
									<table class="adminlist help-toc">
										<?php
										foreach ($this->toc as $k=>$v) {
											echo '<tr>';
											echo '<td>';
											echo HTMLHelper::Link('components/com_jem/help/'.$this->langTag.'/'.$k, $v, array('target' => 'helpFrame'));
											echo '</td>';
											echo '</tr>';
										}
										?>
									</table>
									</fieldset>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-8">
				<div id="datacellhelp" class="w-100">
					<iframe name="helpFrame" src="<?php echo 'components/com_jem/help/'.$this->langTag.'/intro.html'; ?>" class="helpFrame w-100" height="600px"></iframe>
				</div>
			</div>
		</div>
		<div class="clr"> </div>
	</div>

	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="view" value="help" />
	<input type="hidden" name="task" value="" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php 
$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive');

?>
