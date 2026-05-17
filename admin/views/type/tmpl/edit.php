<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

HTMLHelper::_('behavior.formvalidator');
?>

<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id=' . (int) $this->item->id); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate">

    <div class="row">
        <div class="col-md-9">
            <div class="card mb-3">
                <div class="card-body">

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('name'); ?>
                        <?php echo $this->form->getInput('name'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('alias'); ?>
                        <?php echo $this->form->getInput('alias'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('description'); ?>
                        <?php echo $this->form->getInput('description'); ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?php echo $this->form->getLabel('icon'); ?>
                            <div class="input-group">
                                <select id="jem-icon-library" class="form-select flex-grow-0" style="width:auto" title="<?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_LIBRARY'); ?>">
                                    <option value="fa-solid">Font Awesome 6 Solid</option>
                                    <option value="fa-regular">Font Awesome 6 Regular</option>
                                    <option value="fa-brands">Font Awesome 6 Brands</option>
                                </select>
                                <?php echo $this->form->getInput('icon'); ?>
                            </div>
                            <datalist id="jem-icon-list-fa-solid">
                                <option value="fa-solid fa-address-book">
                                <option value="fa-solid fa-address-card">
                                <option value="fa-solid fa-arrow-down">
                                <option value="fa-solid fa-arrow-left">
                                <option value="fa-solid fa-arrow-right">
                                <option value="fa-solid fa-arrow-up">
                                <option value="fa-solid fa-asterisk">
                                <option value="fa-solid fa-ban">
                                <option value="fa-solid fa-bars">
                                <option value="fa-solid fa-bell">
                                <option value="fa-solid fa-bolt">
                                <option value="fa-solid fa-bookmark">
                                <option value="fa-solid fa-briefcase">
                                <option value="fa-solid fa-building">
                                <option value="fa-solid fa-bullhorn">
                                <option value="fa-solid fa-calendar">
                                <option value="fa-solid fa-calendar-check">
                                <option value="fa-solid fa-calendar-days">
                                <option value="fa-solid fa-calendar-plus">
                                <option value="fa-solid fa-camera">
                                <option value="fa-solid fa-cart-shopping">
                                <option value="fa-solid fa-certificate">
                                <option value="fa-solid fa-chart-bar">
                                <option value="fa-solid fa-chart-pie">
                                <option value="fa-solid fa-check">
                                <option value="fa-solid fa-circle-check">
                                <option value="fa-solid fa-circle-info">
                                <option value="fa-solid fa-circle-minus">
                                <option value="fa-solid fa-circle-plus">
                                <option value="fa-solid fa-circle-question">
                                <option value="fa-solid fa-circle-xmark">
                                <option value="fa-solid fa-clock">
                                <option value="fa-solid fa-cloud">
                                <option value="fa-solid fa-code">
                                <option value="fa-solid fa-comment">
                                <option value="fa-solid fa-compass">
                                <option value="fa-solid fa-copy">
                                <option value="fa-solid fa-database">
                                <option value="fa-solid fa-download">
                                <option value="fa-solid fa-earth-americas">
                                <option value="fa-solid fa-envelope">
                                <option value="fa-solid fa-eye">
                                <option value="fa-solid fa-eye-slash">
                                <option value="fa-solid fa-file">
                                <option value="fa-solid fa-file-lines">
                                <option value="fa-solid fa-filter">
                                <option value="fa-solid fa-flag">
                                <option value="fa-solid fa-floppy-disk">
                                <option value="fa-solid fa-folder">
                                <option value="fa-solid fa-folder-open">
                                <option value="fa-solid fa-forward">
                                <option value="fa-solid fa-gear">
                                <option value="fa-solid fa-gears">
                                <option value="fa-solid fa-gift">
                                <option value="fa-solid fa-globe">
                                <option value="fa-solid fa-graduation-cap">
                                <option value="fa-solid fa-heart">
                                <option value="fa-solid fa-house">
                                <option value="fa-solid fa-image">
                                <option value="fa-solid fa-inbox">
                                <option value="fa-solid fa-info">
                                <option value="fa-solid fa-key">
                                <option value="fa-solid fa-laptop">
                                <option value="fa-solid fa-layer-group">
                                <option value="fa-solid fa-leaf">
                                <option value="fa-solid fa-link">
                                <option value="fa-solid fa-list">
                                <option value="fa-solid fa-location-dot">
                                <option value="fa-solid fa-location-pin">
                                <option value="fa-solid fa-lock">
                                <option value="fa-solid fa-magnifying-glass">
                                <option value="fa-solid fa-map">
                                <option value="fa-solid fa-map-pin">
                                <option value="fa-solid fa-minus">
                                <option value="fa-solid fa-mobile">
                                <option value="fa-solid fa-music">
                                <option value="fa-solid fa-paperclip">
                                <option value="fa-solid fa-pen">
                                <option value="fa-solid fa-pen-to-square">
                                <option value="fa-solid fa-phone">
                                <option value="fa-solid fa-play">
                                <option value="fa-solid fa-plus">
                                <option value="fa-solid fa-power-off">
                                <option value="fa-solid fa-print">
                                <option value="fa-solid fa-puzzle-piece">
                                <option value="fa-solid fa-recycle">
                                <option value="fa-solid fa-rotate">
                                <option value="fa-solid fa-share">
                                <option value="fa-solid fa-shield">
                                <option value="fa-solid fa-signal">
                                <option value="fa-solid fa-sitemap">
                                <option value="fa-solid fa-sliders">
                                <option value="fa-solid fa-square-check">
                                <option value="fa-solid fa-star">
                                <option value="fa-solid fa-stop">
                                <option value="fa-solid fa-tag">
                                <option value="fa-solid fa-tags">
                                <option value="fa-solid fa-thumbs-down">
                                <option value="fa-solid fa-thumbs-up">
                                <option value="fa-solid fa-ticket">
                                <option value="fa-solid fa-trash">
                                <option value="fa-solid fa-trophy">
                                <option value="fa-solid fa-triangle-exclamation">
                                <option value="fa-solid fa-unlock">
                                <option value="fa-solid fa-upload">
                                <option value="fa-solid fa-user">
                                <option value="fa-solid fa-user-group">
                                <option value="fa-solid fa-user-plus">
                                <option value="fa-solid fa-users">
                                <option value="fa-solid fa-video">
                                <option value="fa-solid fa-wand-magic-sparkles">
                                <option value="fa-solid fa-wrench">
                                <option value="fa-solid fa-xmark">
                            </datalist>
                            <datalist id="jem-icon-list-fa-regular">
                                <option value="fa-regular fa-address-book">
                                <option value="fa-regular fa-address-card">
                                <option value="fa-regular fa-bell">
                                <option value="fa-regular fa-bookmark">
                                <option value="fa-regular fa-calendar">
                                <option value="fa-regular fa-calendar-check">
                                <option value="fa-regular fa-calendar-days">
                                <option value="fa-regular fa-calendar-plus">
                                <option value="fa-regular fa-chart-bar">
                                <option value="fa-regular fa-circle-check">
                                <option value="fa-regular fa-circle-dot">
                                <option value="fa-regular fa-circle-question">
                                <option value="fa-regular fa-circle-xmark">
                                <option value="fa-regular fa-clock">
                                <option value="fa-regular fa-comment">
                                <option value="fa-regular fa-copy">
                                <option value="fa-regular fa-envelope">
                                <option value="fa-regular fa-eye">
                                <option value="fa-regular fa-eye-slash">
                                <option value="fa-regular fa-face-smile">
                                <option value="fa-regular fa-file">
                                <option value="fa-regular fa-file-lines">
                                <option value="fa-regular fa-flag">
                                <option value="fa-regular fa-floppy-disk">
                                <option value="fa-regular fa-folder">
                                <option value="fa-regular fa-folder-open">
                                <option value="fa-regular fa-heart">
                                <option value="fa-regular fa-image">
                                <option value="fa-regular fa-images">
                                <option value="fa-regular fa-lightbulb">
                                <option value="fa-regular fa-map">
                                <option value="fa-regular fa-pen-to-square">
                                <option value="fa-regular fa-square-check">
                                <option value="fa-regular fa-star">
                                <option value="fa-regular fa-thumbs-down">
                                <option value="fa-regular fa-thumbs-up">
                                <option value="fa-regular fa-trash-can">
                                <option value="fa-regular fa-user">
                            </datalist>
                            <datalist id="jem-icon-list-fa-brands">
                                <option value="fa-brands fa-facebook">
                                <option value="fa-brands fa-instagram">
                                <option value="fa-brands fa-linkedin">
                                <option value="fa-brands fa-tiktok">
                                <option value="fa-brands fa-twitter">
                                <option value="fa-brands fa-whatsapp">
                                <option value="fa-brands fa-x-twitter">
                                <option value="fa-brands fa-youtube">
                            </datalist>
                            <div id="jem-icon-preview" class="mt-2" style="min-height:2rem">
                                <?php if ($this->item->icon) : ?>
                                    <span id="jem-icon-glyph"
                                          class="<?php echo htmlspecialchars($this->item->icon, ENT_QUOTES, 'UTF-8'); ?>"
                                          style="font-size:1.5rem"></span>
                                    <small id="jem-icon-label" class="text-muted ms-2"><?php echo htmlspecialchars($this->item->icon, ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php else : ?>
                                    <span id="jem-icon-glyph" style="font-size:1.5rem"></span>
                                    <small id="jem-icon-label" class="text-muted ms-2"></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo $this->form->getLabel('color'); ?>
                            <?php echo $this->form->getInput('color'); ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('entity'); ?>
                        <?php echo $this->form->getInput('entity'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('published'); ?>
                        <?php echo $this->form->getInput('published'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('access'); ?>
                        <?php echo $this->form->getInput('access'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('language'); ?>
                        <?php echo $this->form->getInput('language'); ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php echo $this->form->getInput('id'); ?>
    <?php echo $this->form->getInput('ordering'); ?>
    <?php echo $this->form->getInput('attribs'); ?>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<script>
(function () {
    var input   = document.querySelector('input[name="jform[icon]"]');
    var select  = document.getElementById('jem-icon-library');
    var glyph   = document.getElementById('jem-icon-glyph');
    var label   = document.getElementById('jem-icon-label');

    if (!input || !select) { return; }

    function activeDatalist() {
        return 'jem-icon-list-' + select.value;
    }

    function detectLibrary(val) {
        if (!val) { return; }
        var prefix = val.split(' ')[0];
        if (['fa-solid','fa-regular','fa-brands'].indexOf(prefix) !== -1) {
            select.value = prefix;
        }
    }

    function updatePreview(val) {
        glyph.className = val ? val : '';
        label.textContent = val;
    }

    // Init: detect library from existing value and wire datalist
    detectLibrary(input.value.trim());
    input.setAttribute('list', activeDatalist());
    updatePreview(input.value.trim());

    select.addEventListener('change', function () {
        input.setAttribute('list', activeDatalist());
        input.focus();
    });

    input.addEventListener('input', function () {
        var val = this.value.trim();
        detectLibrary(val);
        updatePreview(val);
    });
})();
</script>
