<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$requirements = (array) ($this->item->pricing_requirements ?? array());
$venueExists = !empty($requirements['venue_exists']);
$countryCode = strtoupper((string) ($requirements['country_code'] ?? ''));
$capacityReady = !empty($requirements['capacity_ready']);
$pricedReady = $venueExists && $countryCode !== '' && $capacityReady;
$isSaved = !empty($this->item->id);
$effectiveDate = trim((string) ($this->item->dates ?? ''));
$parsedEffectiveDate = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDate, new DateTimeZone('UTC'));
if (!$parsedEffectiveDate || $parsedEffectiveDate->format('Y-m-d') !== $effectiveDate) {
    $effectiveDate = gmdate('Y-m-d');
}
$poolCandidates = array_values((array) ($requirements['pool_candidates'] ?? array()));
?>

<div class="jem-event-pricing"
     data-country-code="<?php echo htmlspecialchars($countryCode, ENT_QUOTES, 'UTF-8'); ?>"
     data-effective-date="<?php echo htmlspecialchars($effectiveDate, ENT_QUOTES, 'UTF-8'); ?>">
    <fieldset class="adminform">
        <legend><?php echo Text::_('COM_JEM_EVENT_PRICING_CAPACITY_TAB'); ?></legend>
        <p class="text-muted"><?php echo Text::_('COM_JEM_EVENT_PRICING_INTRO'); ?></p>
        <?php echo $this->form->renderField('pricing_mode'); ?>
    </fieldset>

    <div class="jem-event-pricing-details">
        <div class="jem-event-pricing-readiness" aria-label="<?php echo htmlspecialchars(Text::_('COM_JEM_EVENT_PRICING_REQUIREMENTS'), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="jem-event-pricing-check <?php echo $venueExists ? 'is-ready' : 'is-missing'; ?>">
                <strong><?php echo Text::_('COM_JEM_EVENT_PRICING_REQUIREMENT_VENUE'); ?></strong>
                <span><?php echo $venueExists ? Text::_('JYES') : Text::_('JNO'); ?></span>
            </div>
            <div class="jem-event-pricing-check <?php echo $countryCode !== '' ? 'is-ready' : 'is-missing'; ?>">
                <strong><?php echo Text::_('COM_JEM_EVENT_PRICING_REQUIREMENT_COUNTRY'); ?></strong>
                <span><?php echo $countryCode !== '' ? htmlspecialchars($countryCode, ENT_QUOTES, 'UTF-8') : Text::_('JNO'); ?></span>
            </div>
            <div class="jem-event-pricing-check <?php echo $capacityReady ? 'is-ready' : 'is-missing'; ?>">
                <strong><?php echo Text::_('COM_JEM_EVENT_PRICING_REQUIREMENT_CONFIGURATION'); ?></strong>
                <span><?php echo $capacityReady
                    ? Text::sprintf(
                        'COM_JEM_EVENT_PRICING_CONFIGURATION_SUMMARY',
                        (int) ($requirements['space_count'] ?? 0),
                        (int) ($requirements['configured_capacity'] ?? 0),
                        (int) ($requirements['profile_revision'] ?? 0)
                    )
                    : Text::_('JNO'); ?></span>
            </div>
        </div>

        <?php if (!$pricedReady) : ?>
            <div class="alert alert-warning">
                <?php echo Text::_('COM_JEM_EVENT_PRICING_REQUIREMENTS_MISSING'); ?>
            </div>
        <?php elseif (!$isSaved) : ?>
            <div class="alert alert-info">
                <?php echo Text::_('COM_JEM_EVENT_PRICING_FIRST_SAVE_NOTICE'); ?>
            </div>
        <?php else : ?>
            <div class="alert alert-info">
                <?php echo Text::_('COM_JEM_EVENT_PRICING_SNAPSHOT_NOTICE'); ?>
            </div>
        <?php endif; ?>

        <?php echo $this->form->getInput('management_fee_mode'); ?>
        <?php echo $this->form->getInput('management_fee_basis'); ?>
        <?php echo $this->form->getInput('venue_profile_id'); ?>
        <?php echo $this->form->getInput('venue_profile_revision'); ?>
        <?php echo $this->form->getInput('venue_snapshot'); ?>

        <fieldset class="adminform jem-event-pricing-policy">
            <legend><?php echo Text::_('COM_JEM_EVENT_PRICING_POLICY'); ?></legend>
            <div class="jem-event-pricing-grid">
                <?php echo $this->form->renderField('currency'); ?>
                <?php echo $this->form->renderField('prices_include_tax'); ?>
                <?php echo $this->form->renderField('default_tax_rate_id'); ?>
                <?php echo $this->form->renderField('management_fee_value'); ?>
                <?php echo $this->form->renderField('management_fee_tax_rate_id'); ?>
                <?php echo $this->form->renderField('management_fee_refundable'); ?>
                <?php if ($isSaved) : ?>
                    <?php echo $this->form->renderField('reload_venue_capacity'); ?>
                <?php endif; ?>
            </div>
        </fieldset>

        <?php if ($isSaved) : ?>
            <fieldset class="adminform jem-event-capacity-pools">
                <legend><?php echo Text::_('COM_JEM_EVENT_CAPACITY_AREAS'); ?></legend>
                <p class="text-muted"><?php echo Text::_('COM_JEM_EVENT_CAPACITY_AREAS_DESC'); ?></p>
                <?php echo $this->form->getInput('capacity_pools'); ?>
            </fieldset>
        <?php endif; ?>

        <fieldset class="adminform jem-event-price-options">
            <legend><?php echo Text::_('COM_JEM_EVENT_PRICE_OPTIONS'); ?></legend>
            <p class="text-muted"><?php echo Text::_('COM_JEM_EVENT_PRICE_OPTIONS_DESC'); ?></p>
            <div class="jem-event-price-toolbar">
                <button type="button" class="btn btn-outline-secondary btn-sm jem-price-advanced-toggle" aria-expanded="false">
                    <?php echo Text::_('COM_JEM_EVENT_PRICE_SHOW_ADVANCED'); ?>
                </button>
            </div>
            <?php echo $this->form->getInput('event_prices'); ?>
        </fieldset>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.jem-event-pricing');
    const mode = document.getElementById('jform_pricing_mode');
    if (!root || !mode) {
        return;
    }

    const details = root.querySelector('.jem-event-pricing-details');
    const countryCode = (root.dataset.countryCode || '').toUpperCase();
    const effectiveDate = root.dataset.effectiveDate || '';
    const isSaved = <?php echo $isSaved ? 'true' : 'false'; ?>;
    const poolCandidates = <?php echo json_encode(
        $poolCandidates,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ); ?>;
    const advancedToggle = root.querySelector('.jem-price-advanced-toggle');
    const copyText = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_COPY')); ?>;
    const copySuffix = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_COPY_SUFFIX')); ?>;
    const previewNet = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_PREVIEW_NET')); ?>;
    const previewTax = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_PREVIEW_TAX')); ?>;
    const previewGross = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_PREVIEW_GROSS')); ?>;
    const previewIncomplete = <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_PREVIEW_INCOMPLETE')); ?>;

    const priceSubform = function () {
        return Array.from(root.querySelectorAll('joomla-field-subform')).find(function (field) {
            return (field.getAttribute('name') || '').indexOf('[event_prices]') !== -1;
        }) || null;
    };

    const priceRows = function () {
        const subform = priceSubform();
        return subform && typeof subform.getRows === 'function'
            ? subform.getRows()
            : Array.from(root.querySelectorAll('.jem-event-price-options .subform-repeatable-group'));
    };

    const updateMode = function () {
        details.hidden = mode.value === 'classic';
    };

    const filterTaxRates = function () {
        root.querySelectorAll('select[id$="tax_rate_id"]').forEach(function (select) {
            Array.from(select.options).forEach(function (option) {
                const optionCountry = (option.dataset.countryCode || '').toUpperCase();
                const validFrom = option.dataset.validFrom || '';
                const validUntil = option.dataset.validUntil || '';
                const countryApplies = !option.value || !optionCountry || !countryCode || optionCountry === countryCode;
                const dateApplies = !option.value || !effectiveDate
                    || ((!validFrom || validFrom === '0000-00-00' || validFrom <= effectiveDate)
                        && (!validUntil || validUntil === '0000-00-00' || validUntil >= effectiveDate));
                const applicable = countryApplies && dateApplies;
                option.hidden = !applicable;
                option.disabled = !applicable && !option.selected;
            });
        });
    };

    const populateCapacityOptions = function () {
        if (isSaved || !poolCandidates.length) {
            return;
        }
        priceRows().forEach(function (row) {
            const select = row.querySelector('[name$="[capacity_pool_id]"]');
            if (!select) {
                return;
            }
            poolCandidates.forEach(function (pool) {
                const value = 'source:' + pool.code;
                if (Array.from(select.options).some(function (option) { return option.value === value; })) {
                    return;
                }
                const option = document.createElement('option');
                option.value = value;
                option.textContent = pool.name + ' [' + pool.code + '] - ' + pool.capacity;
                select.appendChild(option);
            });
            if (poolCandidates.length === 1 && !select.value) {
                select.value = 'source:' + poolCandidates[0].code;
            }
        });
    };

    const fieldKey = function (control) {
        const match = (control.name || '').match(/\[([^\]]+)\](?:\[\])?$/);
        return match ? match[1] : '';
    };

    const rowControls = function (row) {
        return Array.from(row.querySelectorAll('input[name], select[name], textarea[name]')).filter(function (control) {
            return control.closest('.subform-repeatable-group') === row;
        });
    };

    const decorateRows = function () {
        const advancedKeys = [
            'code', 'description', 'quota', 'min_quantity', 'max_quantity',
            'available_from', 'available_until', 'min_age', 'max_age',
            'access_level_id', 'user_group_id', 'verification_mode'
        ];
        priceRows().forEach(function (row) {
            const rowId = row.querySelector('[name$="[id]"]');
            const rowCode = row.querySelector('[name$="[code]"]');
            if (rowCode) {
                const stored = rowId && Number.parseInt(rowId.value || '0', 10) > 0;
                rowCode.readOnly = stored;
                rowCode.classList.toggle('readonly', stored);
            }
            rowControls(row).forEach(function (control) {
                if (advancedKeys.indexOf(fieldKey(control)) !== -1) {
                    const group = control.closest('.control-group');
                    if (group) {
                        group.classList.add('jem-price-advanced-field');
                    }
                }
            });

            const buttonGroup = row.querySelector(':scope > .btn-toolbar .btn-group');
            if (buttonGroup && !buttonGroup.querySelector('.jem-price-copy')) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-outline-secondary jem-price-copy';
                button.textContent = copyText;
                button.setAttribute('aria-label', copyText);
                buttonGroup.prepend(button);
            }

            if (!row.querySelector(':scope > .jem-price-preview')) {
                const preview = document.createElement('div');
                preview.className = 'jem-price-preview alert alert-light';
                preview.setAttribute('aria-live', 'polite');
                row.appendChild(preview);
            }
        });
    };

    const decimalMinorUnits = function (value) {
        const match = String(value || '').trim().match(/^(\d+)(?:\.(\d{1,2}))?$/);
        if (!match) {
            return null;
        }
        return (BigInt(match[1]) * 100n) + BigInt((match[2] || '').padEnd(2, '0'));
    };

    const rateBasisPoints = function (value) {
        const match = String(value || '').trim().match(/^(\d{1,3})(?:\.(\d{1,2}))?$/);
        return match ? (BigInt(match[1]) * 100n) + BigInt((match[2] || '').padEnd(2, '0')) : 0n;
    };

    const roundedRatio = function (value, multiplier, divisor) {
        return ((value * multiplier) + (divisor / 2n)) / divisor;
    };

    const formatMoney = function (minor, currency) {
        const whole = minor / 100n;
        const decimals = String(minor % 100n).padStart(2, '0');
        return String(whole) + '.' + decimals + (currency ? ' ' + currency : '');
    };

    const updatePreviews = function () {
        const includesTax = String((root.querySelector('[name="jform[prices_include_tax]"]:checked') || {}).value || '1') === '1';
        const currency = (root.querySelector('#jform_currency') || {}).value || '';
        priceRows().forEach(function (row) {
            const amount = row.querySelector('[name$="[amount]"]');
            const tax = row.querySelector('[name$="[tax_rate_id]"]');
            const preview = row.querySelector(':scope > .jem-price-preview');
            const entered = amount ? decimalMinorUnits(amount.value) : null;
            const option = tax && tax.selectedIndex >= 0 ? tax.options[tax.selectedIndex] : null;
            if (!preview || entered === null || !option || !option.value) {
                if (preview) {
                    preview.textContent = previewIncomplete;
                }
                return;
            }

            const rate = rateBasisPoints(option.dataset.taxRate || '0');
            let net = entered;
            let taxAmount = 0n;
            let gross = entered;
            if (rate > 0n && includesTax) {
                net = roundedRatio(entered, 10000n, 10000n + rate);
                taxAmount = entered - net;
            } else if (rate > 0n) {
                taxAmount = roundedRatio(entered, rate, 10000n);
                gross = entered + taxAmount;
            }
            preview.textContent = previewNet + ': ' + formatMoney(net, currency)
                + ' · ' + previewTax + ': ' + formatMoney(taxAmount, currency)
                + ' · ' + previewGross + ': ' + formatMoney(gross, currency);
        });
    };

    const copyRow = function (source) {
        const subform = priceSubform();
        if (!subform || typeof subform.addRow !== 'function') {
            return;
        }
        const target = subform.addRow(source);
        if (!target) {
            return;
        }
        const sourceByKey = {};
        rowControls(source).forEach(function (control) {
            const key = fieldKey(control);
            if (!sourceByKey[key]) {
                sourceByKey[key] = [];
            }
            sourceByKey[key].push(control);
        });
        rowControls(target).forEach(function (control, index) {
            const key = fieldKey(control);
            const candidates = sourceByKey[key] || [];
            const sourceControl = candidates.find(function (candidate) {
                return candidate.type !== 'radio' || candidate.value === control.value;
            }) || candidates[index] || candidates[0];
            if (!sourceControl) {
                return;
            }
            if (control.type === 'checkbox' || control.type === 'radio') {
                control.checked = sourceControl.checked;
            } else {
                control.value = sourceControl.value;
                if (sourceControl.dataset.altValue) {
                    control.dataset.altValue = sourceControl.dataset.altValue;
                }
            }
            if (key === 'id') {
                control.value = '0';
            } else if (key === 'code') {
                const baseCode = (sourceControl.value || 'admission').replace(/(?:-copy(?:-\d+)?)$/, '');
                const usedCodes = priceRows().map(function (row) {
                    const code = row.querySelector('[name$="[code]"]');
                    return code ? code.value.toLowerCase() : '';
                });
                let candidate = baseCode + '-copy';
                let suffix = 2;
                while (usedCodes.indexOf(candidate.toLowerCase()) !== -1) {
                    candidate = baseCode + '-copy-' + suffix;
                    suffix += 1;
                }
                control.value = candidate;
            } else if (key === 'name') {
                control.value = (sourceControl.value || '') + copySuffix;
            }
        });
        mode.value = 'multiple';
        mode.dispatchEvent(new Event('change', {bubbles: true}));
        decorateRows();
        filterTaxRates();
        populateCapacityOptions();
        updatePreviews();
    };

    root.addEventListener('click', function (event) {
        const copy = event.target.closest('.jem-price-copy');
        if (copy) {
            copyRow(copy.closest('.subform-repeatable-group'));
            return;
        }
        if (event.target.closest('.jem-price-advanced-toggle')) {
            const show = !root.classList.contains('show-price-advanced');
            root.classList.toggle('show-price-advanced', show);
            advancedToggle.setAttribute('aria-expanded', show ? 'true' : 'false');
            advancedToggle.textContent = show
                ? <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_HIDE_ADVANCED')); ?>
                : <?php echo json_encode(Text::_('COM_JEM_EVENT_PRICE_SHOW_ADVANCED')); ?>;
        }
    });

    root.addEventListener('subform-row-add', function (event) {
        if (!event.detail || !event.detail.row || !event.detail.row.closest('.jem-event-price-options')) {
            return;
        }
        const defaultTax = document.getElementById('jform_default_tax_rate_id');
        const rowTax = event.detail.row.querySelector('[name$="[tax_rate_id]"]');
        if (defaultTax && rowTax && defaultTax.value) {
            rowTax.value = defaultTax.value;
        }
        if (priceRows().length > 1) {
            mode.value = 'multiple';
            mode.dispatchEvent(new Event('change', {bubbles: true}));
        }
        decorateRows();
        filterTaxRates();
        populateCapacityOptions();
        updatePreviews();
    });

    root.addEventListener('subform-row-remove', function () {
        window.setTimeout(function () {
            if (priceRows().length === 1 && mode.value === 'multiple') {
                mode.value = 'single';
                mode.dispatchEvent(new Event('change', {bubbles: true}));
            }
            updatePreviews();
        }, 0);
    });

    root.addEventListener('input', updatePreviews);
    root.addEventListener('change', updatePreviews);

    const defaultTax = document.getElementById('jform_default_tax_rate_id');
    if (defaultTax) {
        defaultTax.addEventListener('change', function () {
            priceRows().forEach(function (row) {
                const rowTax = row.querySelector('[name$="[tax_rate_id]"]');
                if (rowTax && !rowTax.value && defaultTax.value) {
                    rowTax.value = defaultTax.value;
                    rowTax.dispatchEvent(new Event('change', {bubbles: true}));
                }
            });
        });
    }

    mode.addEventListener('change', updateMode);
    new MutationObserver(function () {
        decorateRows();
        filterTaxRates();
        populateCapacityOptions();
    }).observe(root, {childList: true, subtree: true});
    updateMode();
    decorateRows();
    filterTaxRates();
    populateCapacityOptions();
    updatePreviews();
});
</script>
