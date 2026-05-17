<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

$wa->registerAndUseStyle('com_jem.jem-links', 'media/com_jem/css/jem-links.css');
?>

    <div class="row jem-links-tab">
        <div class="col-12">
            <div class="adminform-subform">

                <div class="jem-links-global-options">
                    <?php echo $this->form->renderField('links_layout', 'attribs'); ?>
                    <?php echo $this->form->renderField('links_order', 'attribs'); ?>
                </div>

                <?php echo $this->form->renderField('event_links'); ?>

            </div>
        </div>
    </div>

<?php
$this->document->getWebAssetManager()->addInlineScript('
    document.addEventListener("DOMContentLoaded", function () {
        const getInlineHelpState = function () {
            const reference = document.querySelector("#jform_attribs_links_layout-desc, #jform_attribs_links_order-desc");

            if (!reference) {
                return false;
            }

            return !reference.classList.contains("d-none")
                && window.getComputedStyle(reference).display !== "none";
        };

        const normalizeJemLinksInlineHelp = function () {
            const showHelp = getInlineHelpState();

            const descriptions = document.querySelectorAll(
                ".jem-links-tab .subform-repeatable-group [id$=\"-desc\"]"
            );

            descriptions.forEach(function (description) {
                description.classList.add("hide-aware-inline-help");

                if (showHelp) {
                    description.classList.remove("d-none");
                } else {
                    description.classList.add("d-none");
                }

                const text = description.querySelector(".form-text");

                if (text) {
                    text.classList.remove("hide-aware-inline-help", "d-none");
                }
            });
        };

        normalizeJemLinksInlineHelp();

        document.addEventListener("click", function (event) {
            const inlineHelpButton = event.target.closest(
                ".button-inlinehelp, " +
                ".toolbar-inlinehelp, " +
                "[data-task=\"inlinehelp\"], " +
                "[onclick*=\"inlinehelp\"]"
            );

            if (!inlineHelpButton) {
                return;
            }

            setTimeout(normalizeJemLinksInlineHelp, 150);
        });

        document.addEventListener("subform-row-add", function () {
            setTimeout(normalizeJemLinksInlineHelp, 150);
        });

        document.addEventListener("joomla:updated", function () {
            setTimeout(normalizeJemLinksInlineHelp, 150);
        });
    });
');
?>
