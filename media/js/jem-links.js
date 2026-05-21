document.addEventListener("change", function(event) {
    if (!event.target.classList.contains("jem-link-type-select")) {
        return;
    }

    const select = event.target;
    const wrapper = select.closest(".jem-link-type-field");
    const group = select.closest(".subform-repeatable-group");
    const preview = wrapper ? wrapper.querySelector(".jem-link-type-icon-preview") : null;
    let icons = {};

    try {
        icons = JSON.parse(select.getAttribute("data-icons") || "{}");
    } catch (error) {
        icons = {};
    }

    const iconClass = icons[select.value] || "";

    if (preview) {
        preview.innerHTML = "";
        if (iconClass) {
            const icon = document.createElement("span");
            icon.className = iconClass;
            preview.appendChild(icon);
        }
    }

    if (group) {
        const hiddenIcon = group.querySelector("input[type=\"hidden\"][name$=\"[icon]\"]");
        if (hiddenIcon) {
            hiddenIcon.value = iconClass;
        }
    }
});

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
