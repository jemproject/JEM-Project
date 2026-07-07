<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$displayLegend = (int) ($this->calendarLegendDisplayLegend ?? 1);
$countcatevents = $this->calendarLegendCountCatEvents ?? array();
$countvenueevents = $this->calendarLegendCountVenueEvents ?? array();
$categoryColorMarker = (int) ($this->calendarLegendCategoryColorMarker ?? 0);
$eventUseCategoryBackground = !empty($this->calendarLegendEventUseCategoryBackground);
$showCategories = in_array($displayLegend, array(1, 2, 3, 4), true);
$showVenues = in_array($displayLegend, array(3, 4, 5, 6), true);
?>

<?php if ($showCategories) : ?>
    <div class="calendarLegends mt-4">
        <div class="legend-label"><?php echo Text::_('COM_JEM_CATEGORIES'); ?></div>
        <?php
        $counter = array();

        foreach ($this->rows as $row) {
            foreach ((array) $row->categories as $cat) {
                if (in_array($cat->id, $counter) || !array_key_exists($cat->id, $countcatevents)) {
                    continue;
                }

                    $counter[] = $cat->id;
                    $catColor = !empty($cat->color) ? $cat->color : '';
                    $useCategoryBackground = $eventUseCategoryBackground && $catColor !== '';
                    $categoryStyle = '';

                    if ($useCategoryBackground) {
                        $contrastColor = JemHelper::getContrastTextColor($catColor);
                        $categoryStyle = ' style="'
                            . '--bs-btn-color:' . $this->escape($contrastColor ?: '#000') . ';'
                            . '--bs-btn-bg:' . $this->escape($catColor) . ';'
                            . '--bs-btn-border-color:' . $this->escape($catColor) . ';'
                            . '--bs-btn-hover-color:' . $this->escape($contrastColor ?: '#000') . ';'
                            . '--bs-btn-hover-bg:' . $this->escape($catColor) . ';'
                            . '--bs-btn-hover-border-color:' . $this->escape($catColor) . ';'
                            . '--bs-btn-active-color:' . $this->escape($contrastColor ?: '#000') . ';'
                            . '--bs-btn-active-bg:' . $this->escape($catColor) . ';'
                            . '--bs-btn-active-border-color:' . $this->escape($catColor) . ';'
                            . 'background-color:' . $this->escape($catColor) . ';'
                            . 'border-color:' . $this->escape($catColor) . ';'
                            . 'color:' . $this->escape($contrastColor ?: '#000') . ';"';
                    }
                    ?>
                <div class="eventCat btn btn-outline-dark me-2<?php echo $useCategoryBackground ? ' jem-calendar-legend-full' : ''; ?>" id="cat<?php echo (int) $cat->id; ?>" data-filter-class="cat<?php echo (int) $cat->id; ?>"<?php echo $categoryStyle; ?>>
                    <?php
                    if (!$useCategoryBackground && $catColor !== '') {
                        $class = $categoryColorMarker ? 'colorpicbar' : 'colorpicblock ms-2';
                        echo '<span class="' . $class . '" style="background-color:' . $this->escape($catColor) . ';"></span>';
                    }

                    $textClass = $useCategoryBackground ? 'colorpicblocktext' : ($categoryColorMarker ? 'colorpicbartext' : 'colorpicblocktext pe-2');
                    if ($useCategoryBackground) {
                        echo '<span class="' . $textClass . '">';
                        echo '<span class="jem-calendar-legend-name">' . $this->escape($cat->catname) . '</span>';
                        echo '<span class="jem-calendar-legend-count">' . (int) $countcatevents[$cat->id] . '</span>';
                        echo '</span>';
                    } else {
                        $text = $cat->catname . ' (' . $countcatevents[$cat->id] . ')';
                        echo '<span class="' . $textClass . '">' . $this->escape($text) . '</span>';
                    }
                    ?>
                </div>
                <?php
            }
        }
        ?>
    </div>
<?php endif; ?>

<?php if ($showVenues) : ?>
    <div class="calendarLegends mt-4">
        <div class="legend-label"><?php echo Text::_('COM_JEM_VENUES'); ?></div>
        <?php
        $counter = array();

        foreach ($this->rows as $row) {
            $venueId = (int) $row->locid;

            if (!$venueId || in_array($venueId, $counter) || !array_key_exists($venueId, $countvenueevents)) {
                continue;
            }

            $counter[] = $venueId;
            $venueColor = !empty($row->l_color) ? $row->l_color : (!empty($row->venuecolor) ? $row->venuecolor : '');
            ?>
            <div class="eventVenues btn btn-outline-dark me-2" id="venue<?php echo $venueId; ?>" data-filter-class="venue<?php echo $venueId; ?>">
                <?php
                if ($venueColor) {
                    $class = $categoryColorMarker ? 'colorpicbarbottom-leyend' : 'colorpicblock ms-2';
                    echo '<span class="' . $class . '" style="background-color:' . $this->escape($venueColor) . ';"></span>';
                }

                $textClass = $categoryColorMarker ? 'colorpicbartext' : 'colorpicblocktext pe-2';
                echo '<span class="' . $textClass . '">';
                echo '<span class="jem-calendar-legend-name">' . $this->escape($row->venue) . '</span>';
                echo '<span class="jem-calendar-legend-count">' . (int) $countvenueevents[$venueId] . '</span>';
                echo '</span>';
                ?>
            </div>
            <?php
        }
        ?>
    </div>
<?php endif; ?>
