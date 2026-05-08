<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

$wa->addInlineStyle('
/* JEM event links admin layout */

.jem-links-tab {
    max-width: 100%;
}

.jem-links-tab .adminform-subform {
    max-width: 100%;
    overflow-x: visible;
}

/* Repeatable wrapper */
.jem-links-tab .subform-repeatable-wrapper,
.jem-links-tab .subform-repeatable {
    width: 100%;
    max-width: 100%;
    overflow: visible;
}

/* Global add button */
.jem-links-tab .subform-repeatable-wrapper > .btn-toolbar,
.jem-links-tab .subform-repeatable > .btn-toolbar {
    margin: 0 0 1rem 0 !important;
    padding: 0 !important;
}

/* Hide the inner add button inside each repeatable item */
.jem-links-tab .subform-repeatable-group > .btn-toolbar .group-add,
.jem-links-tab .subform-repeatable-group > .btn-toolbar .btn-success,
.jem-links-tab .subform-repeatable-group > .btn-toolbar .btn:first-child {
    display: none !important;
}

/* Inline field layout */
.jem-links-tab .subform-repeatable-group .control-group {
    display: grid !important;
    grid-template-columns: 135px minmax(0, 1fr);
    column-gap: .75rem;

    width: 100%;
    max-width: 100%;
    min-width: 0;

    margin: 0;
    padding: 0;
}

/* Field labels aligned with the input vertical center */
.jem-links-tab .subform-repeatable-group .control-label {
    display: flex !important;
    align-items: center !important;

    float: none !important;

    width: auto !important;
    max-width: 135px;
    min-height: 44px;

    margin: 0 !important;
    padding: 0 !important;

    font-weight: 600;
    color: #1d2733;
    line-height: 1.2;
}

/* Field controls */
.jem-links-tab .subform-repeatable-group .controls {
    display: block !important;

    width: 100% !important;
    max-width: 100%;
    min-width: 0;

    margin: 0 !important;
    padding: 0 !important;
}

/* Main repeatable item card */
.jem-links-tab .subform-repeatable-group {
    position: relative;

    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: .85rem 1.75rem;

    width: 100%;
    max-width: 100%;
    box-sizing: border-box;

    margin: 0 0 1rem 0;
    padding: 1rem;
    padding-top: 3.25rem;

    border: 1px solid #d7dce1;
    border-radius: .5rem;
    background: #fff;

    overflow: visible !important;
}

/* Inner action toolbar */
.jem-links-tab .subform-repeatable-group > .btn-toolbar {
    position: absolute;
    top: .85rem;
    right: 1rem;
    z-index: 20;

    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-end !important;

    gap: 0 !important;
    margin: 0 !important;
    padding: 0 !important;

    width: auto !important;
    max-width: none !important;
    height: 2.25rem;

    overflow: visible !important;
}

/* Inner action button groups */
.jem-links-tab .subform-repeatable-group > .btn-toolbar > *,
.jem-links-tab .subform-repeatable-group > .btn-toolbar .btn-group {
    position: static !important;

    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;

    gap: 0 !important;
    margin: 0 !important;
    padding: 0 !important;

    width: auto !important;
    max-width: none !important;
    height: 2.25rem;

    overflow: visible !important;
}

/* Hide the inner add button inside each repeatable item */
.jem-links-tab .subform-repeatable-group .group-add {
    display: none !important;
}

/* Order the inner buttons: move first, remove second */
.jem-links-tab .subform-repeatable-group .group-move,
.jem-links-tab .subform-repeatable-group .sortable-handler,
.jem-links-tab .subform-repeatable-group .handle {
    order: 1 !important;
}

.jem-links-tab .subform-repeatable-group .group-remove {
    order: 2 !important;
}

/* Normalize inner action buttons */
.jem-links-tab .subform-repeatable-group > .btn-toolbar .btn,
.jem-links-tab .subform-repeatable-group .group-remove,
.jem-links-tab .subform-repeatable-group .group-move,
.jem-links-tab .subform-repeatable-group .sortable-handler,
.jem-links-tab .subform-repeatable-group .handle {
    position: static !important;
    top: auto !important;
    right: auto !important;
    bottom: auto !important;
    left: auto !important;

    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    width: 2.25rem !important;
    min-width: 2.25rem !important;
    max-width: 2.25rem !important;

    height: 2.25rem !important;
    min-height: 2.25rem !important;
    max-height: 2.25rem !important;

    margin: 0 !important;
    padding: 0 !important;

    line-height: 1 !important;
    flex: 0 0 2.25rem !important;
    transform: none !important;

    overflow: visible !important;
}

/* Normalize action button icons */
.jem-links-tab .subform-repeatable-group > .btn-toolbar .btn span,
.jem-links-tab .subform-repeatable-group > .btn-toolbar .btn i,
.jem-links-tab .subform-repeatable-group > .btn-toolbar .btn svg {
    margin: 0 !important;
    line-height: 1 !important;
    transform: none !important;
}

/* Inline field layout */
.jem-links-tab .subform-repeatable-group .control-group {
    display: grid !important;
    grid-template-columns: 125px minmax(0, 1fr);
    align-items: center;
    column-gap: .75rem;

    width: 100%;
    max-width: 100%;
    min-width: 0;

    margin: 0;
    padding: 0;
}

/* Field labels */
.jem-links-tab .subform-repeatable-group .control-label {
    display: block !important;
    float: none !important;

    width: auto !important;
    max-width: 125px;

    margin: 0 !important;
    padding: 0 !important;

    font-weight: 600;
    color: #1d2733;
    line-height: 1.2;
}

/* Field controls */
.jem-links-tab .subform-repeatable-group .controls {
    display: block !important;

    width: 100% !important;
    max-width: 100%;
    min-width: 0;

    margin: 0 !important;
    padding: 0 !important;
}

/* Inputs and selects */
.jem-links-tab .subform-repeatable-group input,
.jem-links-tab .subform-repeatable-group select,
.jem-links-tab .subform-repeatable-group textarea,
.jem-links-tab .subform-repeatable-group .form-control,
.jem-links-tab .subform-repeatable-group .form-select,
.jem-links-tab .subform-repeatable-group .custom-select {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0;

    box-sizing: border-box;
}

/* Help text */
.jem-links-tab .form-text,
.jem-links-tab .small,
.jem-links-tab .text-muted {
    display: block;

    margin-top: .15rem;

    font-size: .8rem;
    line-height: 1.25;
}

/* Media field alignment */
.jem-links-tab .subform-repeatable-group .control-group:has(.field-media-wrapper),
.jem-links-tab .subform-repeatable-group .control-group:has(.field-media-preview),
.jem-links-tab .subform-repeatable-group .control-group:has(.field-media-input) {
    align-items: start;
}

/* Compact media wrapper */
.jem-links-tab .field-media-wrapper {
    width: 100%;
    max-width: 100%;
}

/* Compact media preview */
.jem-links-tab .field-media-preview {
    width: 100%;
    max-width: 100%;

    min-height: 70px;
    max-height: 90px;

    overflow: hidden;
    box-sizing: border-box;
}

/* Media image */
.jem-links-tab .field-media-preview img {
    max-width: 100%;
    max-height: 70px;

    width: auto;
    height: auto;

    object-fit: contain;
}

/* Media input row */
.jem-links-tab .field-media-wrapper .input-group {
    width: 100%;
    max-width: 100%;

    display: flex;
    flex-wrap: nowrap;
}

/* Media input */
.jem-links-tab .field-media-wrapper .input-group input {
    min-width: 0;
}

/* Media buttons */
.jem-links-tab .field-media-wrapper .input-group .btn {
    flex: 0 0 auto;
}

/* Prevent long content from forcing horizontal scrolling */
.jem-links-tab .subform-repeatable-group * {
    min-width: 0;
}

/* Responsive layout for tablets */
@media (max-width: 991.98px) {
    .jem-links-tab .subform-repeatable-group {
        grid-template-columns: 1fr;
        gap: .75rem;
    }
}

/* Responsive layout for mobile */
@media (max-width: 575.98px) {
    .jem-links-tab .subform-repeatable-group {
        padding: .85rem;
        padding-top: 3.25rem;
    }

    .jem-links-tab .subform-repeatable-group .control-group {
        grid-template-columns: 1fr;
        row-gap: .25rem;
    }

    .jem-links-tab .subform-repeatable-group .control-label {
        max-width: 100%;
    }

    .jem-links-tab .subform-repeatable-group > .btn-toolbar {
        top: .5rem;
        right: .5rem;
    }

    .jem-links-tab .field-media-wrapper .input-group {
        flex-wrap: wrap;
    }

    .jem-links-tab .field-media-wrapper .input-group input {
        flex-basis: 100%;
    }

    .jem-links-tab .field-media-wrapper .input-group .btn {
        flex: 1 1 auto;
    }
}
');
?>

<div class="row jem-links-tab">
    <div class="col-12">
        <div class="adminform-subform">
            <?php echo $this->form->renderField('event_links'); ?>
        </div>
    </div>
</div>