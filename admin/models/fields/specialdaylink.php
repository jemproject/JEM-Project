<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

class JFormFieldSpecialdaylink extends FormField
{
    protected $type = 'Specialdaylink';

    protected function getInput()
    {
        $app = Factory::getApplication();
        $wa = $app->getDocument()->getWebAssetManager();
        $id = preg_replace('/[^A-Za-z0-9_\-]/', '_', $this->id);
        $articleModalId = $id . '_article_modal';
        $menuModalId = $id . '_menu_modal';
        $articleCallback = 'jSelectSpecialDayArticleLink_' . $id;
        $menuCallback = 'jSelectSpecialDayMenuLink_' . $id;
        $value = htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8');
        $status = $this->getCurrentLinkStatus((string) $this->value);
        $statusType = htmlspecialchars($status['type'], ENT_QUOTES, 'UTF-8');
        $statusText = htmlspecialchars($status['text'], ENT_QUOTES, 'UTF-8');
        $articleLink = Uri::root(true) . '/administrator/index.php?option=com_content&view=articles&layout=modal&tmpl=component&function=' . rawurlencode($articleCallback) . '&' . Session::getFormToken() . '=1';
        $menuLink = Uri::root(true) . '/administrator/index.php?option=com_menus&view=items&layout=modal&tmpl=component&function=' . rawurlencode($menuCallback) . '&client_id=0&' . Session::getFormToken() . '=1';

        $script = array();
        $script[] = '(function() {';
        $script[] = '    function setSpecialDayLink(source, title, link, fallbackId) {';
        $script[] = '        var input = document.getElementById("' . $id . '");';
        $script[] = '        var field = input ? input.closest(".jem-specialday-link-field") : null;';
        $script[] = '        var status = field ? field.parentNode.querySelector(".jem-specialday-link-status") : null;';
        $script[] = '        var selectedLink = link || "";';
        $script[] = '        if (!selectedLink && source === "article" && fallbackId) {';
        $script[] = '            selectedLink = "index.php?option=com_content&view=article&id=" + fallbackId;';
        $script[] = '        }';
        $script[] = '        if (!selectedLink && source === "menu" && fallbackId) {';
        $script[] = '            selectedLink = "index.php?Itemid=" + fallbackId;';
        $script[] = '        }';
        $script[] = '        if (!selectedLink) {';
        $script[] = '            return;';
        $script[] = '        }';
        $script[] = '        if (input) {';
        $script[] = '            input.value = selectedLink;';
        $script[] = '            input.dispatchEvent(new Event("change", { bubbles: true }));';
        $script[] = '        }';
        $script[] = '        if (field) {';
        $script[] = '            field.setAttribute("data-link-type", source);';
        $script[] = '            field.querySelectorAll("[data-specialday-link-source]").forEach(function(button) {';
        $script[] = '                button.classList.toggle("active", button.getAttribute("data-specialday-link-source") === source);';
        $script[] = '                button.classList.toggle("btn-primary", button.getAttribute("data-specialday-link-source") === source);';
        $script[] = '                button.classList.toggle("btn-secondary", button.getAttribute("data-specialday-link-source") !== source);';
        $script[] = '            });';
        $script[] = '        }';
        $script[] = '        if (status) {';
        $script[] = '            status.textContent = (source === "menu" ? "' . addslashes(Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_MENU')) . '" : "' . addslashes(Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_ARTICLE')) . '") + ": " + (title || selectedLink);';
        $script[] = '            status.hidden = false;';
        $script[] = '        }';
        $script[] = '        ["' . $articleModalId . '", "' . $menuModalId . '"].forEach(function(modalId) {';
        $script[] = '            var modal = document.getElementById(modalId);';
        $script[] = '            if (modal && window.bootstrap) {';
        $script[] = '                var instance = bootstrap.Modal.getInstance(modal) || bootstrap.Modal.getOrCreateInstance(modal);';
        $script[] = '                instance.hide();';
        $script[] = '            }';
        $script[] = '        });';
        $script[] = '    }';
        $script[] = '    function getSelectedLinkData(selected, source) {';
        $script[] = '        var onclick = selected.getAttribute("onclick") || "";';
        $script[] = '        var link = selected.getAttribute("data-uri") || selected.getAttribute("data-link") || selected.getAttribute("href") || "";';
        $script[] = '        var id = selected.getAttribute("data-id") || selected.getAttribute("data-item-id") || "";';
        $script[] = '        var title = selected.getAttribute("data-title") || selected.getAttribute("title") || selected.textContent || "";';
        $script[] = '        var match;';
        $script[] = '        if (!id && (match = onclick.match(/(?:jSelectSpecialDayArticleLink_|jSelectSpecialDayMenuLink_)[^(]*\\(([^)]*)\\)/))) {';
        $script[] = '            var args = match[1].split(",").map(function(value) { return value.trim().replace(/^["\\\']|["\\\']$/g, ""); });';
        $script[] = '            id = args[0] || id;';
        $script[] = '            title = args[1] || title;';
        $script[] = '            link = args[4] || args[2] || link;';
        $script[] = '        }';
        $script[] = '        if (!link && source === "article" && id) {';
        $script[] = '            link = "index.php?option=com_content&view=article&id=" + id;';
        $script[] = '        }';
        $script[] = '        if (!link && source === "menu" && id) {';
        $script[] = '            link = "index.php?Itemid=" + id;';
        $script[] = '        }';
        $script[] = '        return { title: title, link: link, id: id };';
        $script[] = '    }';
        $script[] = '    function bindSpecialDayLinkFrame(modalId, source) {';
        $script[] = '        var modal = document.getElementById(modalId);';
        $script[] = '        var iframe = modal ? modal.querySelector("iframe") : null;';
        $script[] = '        if (!iframe) { return; }';
        $script[] = '        var bind = function() {';
        $script[] = '            var doc = iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null);';
        $script[] = '            if (!doc || doc.__jemSpecialDayLinkBound) { return; }';
        $script[] = '            doc.__jemSpecialDayLinkBound = true;';
        $script[] = '            doc.addEventListener("click", function(event) {';
        $script[] = '                var selected = event.target && event.target.closest ? event.target.closest("[data-content-select], .select-link, a[onclick*=jSelectSpecialDay], button[onclick*=jSelectSpecialDay], tr[data-id], tr[data-item-id]") : null;';
        $script[] = '                if (!selected) { return; }';
        $script[] = '                event.preventDefault();';
        $script[] = '                var selectedData = getSelectedLinkData(selected, source);';
        $script[] = '                setSpecialDayLink(source, selectedData.title, selectedData.link, selectedData.id);';
        $script[] = '            }, true);';
        $script[] = '        };';
        $script[] = '        iframe.addEventListener("load", bind);';
        $script[] = '        bind();';
        $script[] = '    }';
        $script[] = '    function retryBindSpecialDayLinkFrame(modalId, source) {';
        $script[] = '        var attempts = 0;';
        $script[] = '        var timer = window.setInterval(function() {';
        $script[] = '            attempts += 1;';
        $script[] = '            bindSpecialDayLinkFrame(modalId, source);';
        $script[] = '            var modal = document.getElementById(modalId);';
        $script[] = '            var iframe = modal ? modal.querySelector("iframe") : null;';
        $script[] = '            var doc = iframe ? (iframe.contentDocument || (iframe.contentWindow ? iframe.contentWindow.document : null)) : null;';
        $script[] = '            if ((doc && doc.__jemSpecialDayLinkBound) || attempts >= 20) {';
        $script[] = '                window.clearInterval(timer);';
        $script[] = '            }';
        $script[] = '        }, 250);';
        $script[] = '    }';
        $script[] = '    function isSpecialDayLinkModalOpen() {';
        $script[] = '        return ["' . $articleModalId . '", "' . $menuModalId . '"].some(function(modalId) {';
        $script[] = '            var modal = document.getElementById(modalId);';
        $script[] = '            return modal && (modal.classList.contains("show") || modal.open || modal.getAttribute("open") !== null || modal.offsetParent !== null);';
        $script[] = '        });';
        $script[] = '    }';
        $script[] = '    window.' . $articleCallback . ' = function(id, title, catid, object, link) {';
        $script[] = '        setSpecialDayLink("article", title, link, id);';
        $script[] = '    };';
        $script[] = '    window.' . $menuCallback . ' = function(id, title, arg3, object, link) {';
        $script[] = '        var selectedLink = link || (/^(index\\.php|https?:\\/\\/|\\/)/.test(arg3 || "") ? arg3 : "");';
        $script[] = '        setSpecialDayLink("menu", title, selectedLink, id);';
        $script[] = '    };';
        $script[] = '    window.addEventListener("message", function(event) {';
        $script[] = '        var data = event.data || {};';
        $script[] = '        if (!data || data.messageType !== "joomla:content-select") { return; }';
        $script[] = '        if (data.function !== "' . $articleCallback . '" && data.function !== "' . $menuCallback . '" && !isSpecialDayLinkModalOpen()) { return; }';
        $script[] = '        if (data.contentType && data.contentType !== "com_content.article" && data.contentType !== "com_menus.item") { return; }';
        $script[] = '        var source = data.function === "' . $menuCallback . '" || data.contentType === "com_menus.item" ? "menu" : "article";';
        $script[] = '        setSpecialDayLink(source, data.title || data.text || "", data.uri || data.url || data.link || "", data.id || data.itemId || "");';
        $script[] = '    });';
        $script[] = '    function initSpecialDayLinkField() {';
        $script[] = '        var input = document.getElementById("' . $id . '");';
        $script[] = '        if (!input) { return; }';
        $script[] = '        if (input.__jemSpecialDayLinkInitialised) { return; }';
        $script[] = '        input.__jemSpecialDayLinkInitialised = true;';
        $script[] = '        input.addEventListener("input", function() {';
        $script[] = '            var field = input.closest(".jem-specialday-link-field");';
        $script[] = '            var status = field ? field.parentNode.querySelector(".jem-specialday-link-status") : null;';
        $script[] = '            if (field) {';
        $script[] = '                field.setAttribute("data-link-type", input.value ? "url" : "");';
        $script[] = '                field.querySelectorAll("[data-specialday-link-source]").forEach(function(button) {';
        $script[] = '                    button.classList.remove("active", "btn-primary");';
        $script[] = '                    button.classList.add("btn-secondary");';
        $script[] = '                });';
        $script[] = '            }';
        $script[] = '            if (status) {';
        $script[] = '                status.textContent = input.value ? "' . addslashes(Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_URL')) . ': " + input.value : "";';
        $script[] = '                status.hidden = !input.value;';
        $script[] = '            }';
        $script[] = '        });';
        $script[] = '        document.querySelectorAll("[data-specialday-link-source]").forEach(function(button) {';
        $script[] = '            button.addEventListener("click", function() {';
        $script[] = '                var source = button.getAttribute("data-specialday-link-source") || "article";';
        $script[] = '                retryBindSpecialDayLinkFrame(source === "menu" ? "' . $menuModalId . '" : "' . $articleModalId . '", source);';
        $script[] = '            });';
        $script[] = '        });';
        $script[] = '    }';
        $script[] = '    if (document.readyState === "loading") {';
        $script[] = '        document.addEventListener("DOMContentLoaded", initSpecialDayLinkField);';
        $script[] = '    } else {';
        $script[] = '        initSpecialDayLinkField();';
        $script[] = '    }';
        $script[] = 'document.addEventListener("shown.bs.modal", function(event) {';
        $script[] = '    if (!event.target || (event.target.id !== "' . $articleModalId . '" && event.target.id !== "' . $menuModalId . '")) {';
        $script[] = '        return;';
        $script[] = '    }';
        $script[] = '    var iframe = event.target.querySelector("iframe");';
        $script[] = '    if (iframe) {';
        $script[] = '        iframe.style.height = "90vh";';
        $script[] = '        iframe.style.minHeight = "34rem";';
        $script[] = '        iframe.style.width = "100%";';
        $script[] = '    }';
        $script[] = '    bindSpecialDayLinkFrame(event.target.id, event.target.id === "' . $menuModalId . '" ? "menu" : "article");';
        $script[] = '});';
        $script[] = '}());';
        $wa->addInlineScript(implode("\n", $script));
        $wa->addInlineStyle(
            '.jem-specialday-link-modal-dialog{width:90vw;max-width:90vw;margin:5vh auto;}'
            . '.jem-specialday-link-modal-dialog .modal-body{padding:0;overflow:hidden;}'
            . '.jem-specialday-link-modal-dialog iframe{display:block;width:100%;height:90vh;min-height:34rem;border:0;}'
            . '.jem-specialday-link-field .btn.active{box-shadow:inset 0 0 0 2px rgba(255,255,255,.45);}'
            . '.jem-specialday-link-status{display:block;margin-top:.35rem;color:var(--text-muted,#6c757d);font-size:.9rem;}'
        );

        $html = array();
        $html[] = '<div class="input-group jem-specialday-link-field" data-link-type="' . $statusType . '" style="max-width: 54rem;">';
        $html[] = '  <input type="text" id="' . $id . '" name="' . $this->name . '" value="' . $value . '" class="form-control" maxlength="2048" placeholder="' . htmlspecialchars(Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_HINT'), ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '  <button type="button" class="btn ' . ($status['type'] === 'article' ? 'btn-primary active' : 'btn-secondary') . '" data-specialday-link-source="article" data-bs-toggle="modal" data-bs-target="#' . $articleModalId . '" title="' . htmlspecialchars(Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_SELECT_ARTICLE'), ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars(Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_SELECT_ARTICLE'), ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '      <span class="icon-link" aria-hidden="true"></span>';
        $html[] = '  </button>';
        $html[] = '  <button type="button" class="btn ' . ($status['type'] === 'menu' ? 'btn-primary active' : 'btn-secondary') . '" data-specialday-link-source="menu" data-bs-toggle="modal" data-bs-target="#' . $menuModalId . '" title="' . htmlspecialchars(Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_SELECT_MENU'), ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars(Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_SELECT_MENU'), ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '      <span class="icon-menu" aria-hidden="true"></span>';
        $html[] = '  </button>';
        $html[] = '</div>';
        $html[] = '<span class="jem-specialday-link-status"' . ($statusText === '' ? ' hidden' : '') . '>' . $statusText . '</span>';
        $html[] = HTMLHelper::_(
            'bootstrap.renderModal',
            $articleModalId,
            array(
                'url' => $articleLink,
                'title' => Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_SELECT_ARTICLE'),
                'modalWidth' => 90,
                'bodyHeight' => 80,
                'height' => '90vh',
                'width' => '100%',
                'modalCss' => 'modal-xl jem-specialday-link-modal-dialog',
                'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('JCANCEL') . '</button>',
            )
        );
        $html[] = HTMLHelper::_(
            'bootstrap.renderModal',
            $menuModalId,
            array(
                'url' => $menuLink,
                'title' => Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_SELECT_MENU'),
                'modalWidth' => 90,
                'bodyHeight' => 80,
                'height' => '90vh',
                'width' => '100%',
                'modalCss' => 'modal-xl jem-specialday-link-modal-dialog',
                'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('JCANCEL') . '</button>',
            )
        );

        return implode("\n", $html);
    }

    private function getCurrentLinkStatus(string $link): array
    {
        $link = trim($link);

        if ($link === '') {
            return array('type' => '', 'text' => '');
        }

        if (strpos($link, 'option=com_content') !== false && preg_match('/(?:[?&]id=|\/article\/)(\d+)/', $link, $match)) {
            $title = $this->getArticleTitle((int) $match[1]);

            return array(
                'type' => 'article',
                'text' => Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_ARTICLE') . ': ' . ($title ?: $link),
            );
        }

        if (preg_match('/(?:[?&]Itemid=|[?&]itemid=)(\d+)/', $link, $match)) {
            $title = $this->getMenuTitle((int) $match[1]);

            return array(
                'type' => 'menu',
                'text' => Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_MENU') . ': ' . ($title ?: $link),
            );
        }

        return array(
            'type' => 'url',
            'text' => Text::_('COM_JEM_SPECIAL_DAY_FIELD_LINK_URL') . ': ' . $link,
        );
    }

    private function getArticleTitle(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('title'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);
        $db->setQuery($query);

        return (string) $db->loadResult();
    }

    private function getMenuTitle(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('title'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);
        $db->setQuery($query);

        return (string) $db->loadResult();
    }
}
