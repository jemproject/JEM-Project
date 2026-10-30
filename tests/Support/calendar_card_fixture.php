<?php
declare(strict_types=1);

/** Render actual production card assembly and tooltip with synthetic inputs, not a full Joomla view. */
$root = dirname(__DIR__, 2);
$view = $argv[1] ?? 'calendar';
$layout = $argv[2] ?? 'standard';
if (!in_array($view, ['calendar', 'weekcal'], true) || !in_array($layout, ['standard', 'responsive'], true)) {
    throw new RuntimeException('Unexpected calendar fixture');
}
final class CalendarCardHtml
{
    /** @return string Ignore tooltip asset registration. */
    public static function _(string $key): string
    {
        return '';
    }
    /** @return string Attribute-safe synthetic tooltip. */
    public static function tooltipText(...$values): string
    {
        return 'Calendar fixture';
    }
}
final class CalendarCardRoute
{
    /** @return string Preserve the synthetic destination. */
    public static function _(string $url): string
    {
        return $url;
    }
}
$helper = file_get_contents($root . '/site/helpers/helper.php');
$start = strpos($helper, '    static public function caltooltip(');
$end = strpos($helper, "\n    /**", $start);
eval('use CalendarCardHtml as HTMLHelper; use CalendarCardRoute as Route; class JemHelper {'
    . substr($helper, $start, $end - $start) . '}');
$template = file_get_contents($root . '/site/views/' . $view . '/tmpl/'
    . ($layout === 'responsive' ? 'responsive/' : '') . 'default.php');
$start = strpos($template, '        //generate the output');
$end = strpos($template, '        $this->cal->setEventContent(', $start);
$assembly = substr($template, $start, $end - $start);

final class CalendarCardFixture
{
    /** @return string Escape fixture attributes as the real view does. */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Execute the actual card assembly for a synthetic presentation case.
     *
     * @param string $assembly Production template fragment.
     * @param string $view Month or week view.
     * @param int $id Unique case ID.
     * @param string $title One-line or wrapping title.
     * @param bool $time Include the optional time label.
     * @param bool $marker Select category-marker presentation.
     * @param bool $bar Render a visible category bar.
     * @return string Card markup with its filter wrapper.
     */
    public function render(string $assembly, string $view, int $id, string $title, bool $time, bool $marker, bool $bar): string
    {
        $eventid = $id;
        $category = (object) ['id' => 1, 'color' => '#a3b38d'];
        $row = (object) ['title' => $title];
        $categoryColorMarker = $marker;
        $featuredclass = $id % 2 ? 'borderfeatured' : '';
        $featuredstyle = 'border-color:#777;background:#a3b38d';
        $evbg_usecatcolor = 0;
        $catcolor = [];
        $eventbackgroundcolor = '';
        $venueId = 1;
        $eventFilterClasses = ['cat1', 'venue1'];
        $venueColorBar = $view === 'calendar' && $bar
            ? '<div class="eventcontentbottom"><div class="colorpicbarbottom" style="background:#855"></div></div>' : '';
        $color = $bar ? '<div class="eventcontenttop pt-0"><div class="colorpicbar" style="background:#588"></div></div>' : '';
        $editicon = '';
        $catname = $eventname = $timehtml = $venue = $contact = $eventstate = $eventdate = $statusicon = $eventaccess = '';
        $detaillink = '#event-' . $id;
        $timetp = $time ? '10:00 - 11:00<br>' : '';
        $content = $view === 'weekcal' ? '<div class="event-filter">' : '';
        $contentend = '</div>';
        eval($assembly);
        return $content;
    }
}
$fixture = new CalendarCardFixture();
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<link rel="stylesheet" href="/calendar.css"><style>'
    . '*{box-sizing:border-box}body{margin:8px;font:14px/1.2 Georgia,serif}'
    . '.pt-0{padding-top:0!important}.pb-2{padding-bottom:.5rem!important}'
    . '.ps-0{padding-left:0!important}.pe-0{padding-right:0!important}'
    . '#jem table{table-layout:fixed}#jem td{width:25%;vertical-align:top}'
    . '#jem .eventcontentinner{border:1px solid #777;text-align:center}'
    . 'body.tall #jem .eventcontentinner{min-height:100px}'
    . '</style></head><body><div id="jem" class="jem_calendar"><table class="'
    . ($view === 'calendar' ? 'month' : 'week') . '"><tbody><tr>';
$id = 0;
foreach ([false, true] as $time) {
    foreach ([false, true] as $marker) {
        echo '<td>';
        foreach (['Workshop', 'MASTER CLASS PASTA BAMBINI - Halloween edition for families'] as $title) {
            foreach ([false, true] as $bar) {
                echo '<div class="fixture-case" data-bar="' . (int) $bar . '">'
                    . $fixture->render($assembly, $view, ++$id, $title, $time, $marker, $bar) . '</div>';
            }
        }
        echo '</td>';
    }
}
echo '</tr></tbody></table></div></body></html>';

