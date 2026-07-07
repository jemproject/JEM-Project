<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jem/helpers/countries.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$function = Factory::getApplication()->input->getCmd('function', 'jSelectVenue');
Factory::getDocument()->setTitle(Text::_('COM_JEM_SELECT_VENUE'));

if (!function_exists('jem_choosevenue_country')) {
    function jem_choosevenue_country($country)
    {
        $country = trim((string) $country);

        if ($country === '') {
            return '';
        }

        $countryName = JemHelperCountries::getCountryName($country) ?: $country;
        $flagSrc = JemHelperCountries::getIsoFlag($country);
        $html = '';

        if ($flagSrc) {
            $html .= '<img src="' . htmlspecialchars($flagSrc, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') . '" class="venue_country_flag jem-choosevenue-country-flag" />';
        }

        return $html . '<span>' . htmlspecialchars($country, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
?>

<script>
    if (window.parent && window.parent.document) {
        var modalTitle = window.parent.document.querySelector('.modal.show .modal-title, .joomla-modal.show .modal-title');
        if (modalTitle) {
            modalTitle.textContent = "<?php echo $this->escape(Text::_('COM_JEM_SELECT_VENUE')); ?>";
        }
    }

    function tableOrdering( order, dir, view )
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value     = order;
        form.filter_order_Dir.value    = dir;
        form.submit( view );
    }
</script>

<style>
    #jem.jem_select_venue {
        padding: 1rem;
    }

    #jem.jem_select_venue #jem_filter {
        display: grid !important;
        grid-template-columns: auto auto minmax(7rem, 1fr) auto auto auto auto auto;
        align-items: center;
        gap: .5rem;
        margin: 0;
        padding: .75rem;
        border: 1px solid var(--border-color, #dfe3e7);
        border-radius: .25rem;
        width: 100%;
        box-sizing: border-box;
    }

    #jem.jem_select_venue #jem_filter > div {
        display: flex !important;
        flex-flow: row nowrap !important;
        align-items: center;
        gap: .5rem;
        width: auto !important;
        margin: 0 !important;
        min-width: 0;
    }

    #jem.jem_select_venue #jem_filter .jem-row {
        gap: .5rem;
    }

    #jem.jem_select_venue #filter_search {
        width: 100%;
        min-width: 7rem;
        max-width: none;
    }

    #jem.jem_select_venue #jem_filter select {
        width: auto;
        min-width: 5.5rem;
        max-width: 9rem;
        padding-right: 2rem !important;
        background-position: right .5rem center !important;
        background-size: 1rem auto !important;
    }

    #jem.jem_select_venue #jem_filter select#limit {
        min-width: 5rem;
        max-width: 5.5rem;
    }

    #jem.jem_select_venue #jem_filter .btn,
    #jem.jem_select_venue #jem_filter button {
        width: auto !important;
        white-space: nowrap;
    }

    #jem.jem_select_venue .jem-choosevenue-search {
        display: contents !important;
    }

    #jem.jem_select_venue .jem-choosevenue-actions,
    #jem.jem_select_venue .jem-choosevenue-limit {
        display: contents !important;
    }

    #jem.jem_select_venue .jem-choosevenue-limit label {
        margin: 0;
        white-space: nowrap;
    }

    @media (max-width: 760px) {
        #jem.jem_select_venue #jem_filter {
            grid-template-columns: 1fr;
        }

        #jem.jem_select_venue #jem_filter > div {
            display: flex !important;
            flex-wrap: wrap !important;
        }

        #jem.jem_select_venue #filter_search {
            flex: 1 1 100%;
        }
    }

    #jem.jem_select_venue .jem-small-list {
        align-items: center;
    }

    #jem.jem_select_venue .jem-venue-number {
        flex: 0 0 3rem;
        max-width: 3rem;
        text-align: center;
        white-space: nowrap;
    }

    #jem.jem_select_venue .jem-venue-country {
        text-align: right;
        white-space: nowrap;
    }

    #jem.jem_select_venue .jem-choosevenue-country {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: .35rem;
    }

    #jem.jem_select_venue .jem-choosevenue-country-flag {
        width: 20px;
        height: auto;
        vertical-align: middle;
    }
</style>

<div id="jem" class="jem_select_venue">
    <div class="clr"></div>

    <form action="<?php echo Route::_('index.php?option=com_jem&view=editevent&layout=choosevenue&tmpl=component&function='.$this->escape($function).'&'.Session::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">
        <div class="jem-row valign-baseline">
            <div id="jem_filter" class="jem-form jem-row jem-justify-start">
                <div class="jem-choosevenue-label">
                    <?php
                    echo '<label for="filter_type">'.Text::_('COM_JEM_FILTER').'</label>';
                    ?>
                </div>
                <div class="jem-row jem-justify-start jem-nowrap jem-choosevenue-search">
                    <?php echo $this->searchfilter; ?>
                    <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->filter, ENT_QUOTES, 'UTF-8');?>" class="inputbox" onchange="document.adminForm.submit();" />
                </div>
                <button type="submit" class="pointer btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                <button type="button" class="pointer btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                <button type="button" class="pointer btn btn-primary" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo Text::_('COM_JEM_SELECT_VENUE') ?>');"><?php echo Text::_('COM_JEM_NOVENUE')?></button>
                <div class="jem-row jem-justify-start jem-nowrap jem-choosevenue-limit">
                    <?php echo '<label for="limit">'.Text::_('COM_JEM_DISPLAY_NUM').'</label>'; ?>
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
            </div>
        </div>

        <hr class="jem-hr"/>

        <div class="jem-sort jem-sort-small">
            <div class="jem-list-row jem-small-list">
                <div class="sectiontableheader jem-venue-number"><?php echo Text::_('COM_JEM_NUM'); ?></div>
                <div class="sectiontableheader jem-venue-name"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_VENUE', 'l.venue', $this->lists['order_Dir'], $this->lists['order'], 'selectvenue' ); ?></div>
                <div class="sectiontableheader jem-venue-city"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order'], 'selectvenue' ); ?></div>
                <div class="sectiontableheader jem-venue-state"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></div>
                <div class="sectiontableheader jem-venue-country"><?php echo Text::_('COM_JEM_COUNTRY'); ?></div>
            </div>
        </div>

        <ul class="eventlist eventtable">
            <?php if (empty($this->rows)) : ?>
                <li class="jem-event jem-list-row jem-small-list"><?php echo Text::_('COM_JEM_NOVENUES'); ?></li>
            <?php else :?>
                <?php foreach ($this->rows as $i => $row) : ?>
                    <li class="jem-event jem-list-row jem-small-list row<?php echo $i % 2; ?>">
                        <div class="jem-event-info-small jem-venue-number">
                            <?php echo $this->pagination->getRowOffset( $i ); ?>
                        </div>

                        <div class="jem-event-info-small jem-venue-name">
              <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_SELECT'), $row->venue, 'editlinktip selectvenue'); ?>>
                                <a class="pointer" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->venue)); ?>');"><?php echo $this->escape($row->venue); ?></a>
                            </span>
                        </div>

                        <div class="jem-event-info-small jem-venue-city">
                            <?php echo $this->escape($row->city); ?>
                        </div>

                        <div class="jem-event-info-small jem-venue-state">
                            <?php echo $this->escape($row->state); ?>
                        </div>

                        <div class="jem-event-info-small jem-venue-country">
                            <span class="jem-choosevenue-country"><?php echo !empty($row->country) ? jem_choosevenue_country($row->country) : '-'; ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <input type="hidden" name="task" value="selectvenue" />
        <input type="hidden" name="option" value="com_jem" />
        <input type="hidden" name="tmpl" value="component" />
        <input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
        <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
    </form>

    <div class="pagination">
        <?php echo $this->pagination->getPagesLinks(); ?>
    </div>
</div>
