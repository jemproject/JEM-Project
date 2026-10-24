<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Document\Feed\FeedEnclosure;
use Joomla\CMS\Document\Feed\FeedItem;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

require_once __DIR__ . '/imageresourcepolicy.class.php';

/**
 * Builds the four public event feeds from their already filtered list rows.
 */
final class JemFeed
{
    private const STATUS_KEYS = array('cancelled', 'postponed', 'rescheduled', 'moved_online', 'new');

    /**
     * Initialise the model before setting the feed limit so populateState()
     * cannot replace it with the HTML page size or a remembered user choice.
     */
    public static function limitModel($model, $app): void
    {
        $model->getState();
        $model->setState('list.start', 0);
        $model->setState('list.limit', max(1, (int) $app->get('feed_limit', 10)));
    }

    /**
     * The list model remains responsible for event, category and venue ACL.
     */
    public static function addEvents($document, $rows): void
    {
        foreach ((array) $rows as $row) {
            $categories = array();

            foreach ((array) ($row->categories ?? array()) as $category) {
                $name = trim((string) ($category->catname ?? ''));

                if ($name !== '') {
                    $categories[] = $name;
                }
            }

            $status = strtolower(trim((string) ($row->event_status ?? 'scheduled')));
            $statusText = in_array($status, self::STATUS_KEYS, true)
                ? Text::_('COM_JEM_EVENT_STATUS_' . strtoupper($status))
                : '';

            $title = html_entity_decode(strip_tags((string) ($row->title ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $item = new FeedItem();
            $item->title = ($statusText !== '' ? '[' . $statusText . '] ' : '') . $title;
            $item->link = Route::_(JemHelperRoute::getEventRoute($row->slug));
            $item->category = $categories;
            $item->date = !empty($row->created) ? date('r', strtotime((string) $row->created)) : '';

            $parts = array();

            if ($statusText !== '') {
                $parts[] = '<p><strong>' . self::escape($statusText) . '</strong></p>';
            }

            $image = self::image($row);

            if ($image !== null) {
                $parts[] = '<p><img src="' . self::escape($image['url']) . '" alt="' . self::escape($title) . '"></p>';
                $enclosure = new FeedEnclosure();
                $enclosure->url = $image['url'];
                $enclosure->length = $image['length'];
                $enclosure->type = $image['type'];
                $item->setEnclosure($enclosure);
            }

            $schedule = self::schedule($row);
            $details = array(self::escape(Text::_('COM_JEM_DATE')) . ': ' . self::escape($schedule));

            if (self::enabled($row, 'event_show_venue') && !empty($row->venue)) {
                $venue = (string) $row->venue . (!empty($row->city) ? ' / ' . (string) $row->city : '');
                $details[] = self::escape(Text::_('COM_JEM_VENUE')) . ': ' . self::escape($venue);
            }

            if (self::enabled($row, 'event_show_online_meeting')) {
                $onlineUrl = JemHelper::getOnlineMeetingUrl($row);

                if ($onlineUrl !== '') {
                    $onlineLabel = JemHelper::getOnlineMeetingLabel($row);
                    $details[] = self::escape(Text::_('COM_JEM_ONLINE_MEETING')) . ': <a href="'
                        . self::escape($onlineUrl) . '">' . self::escape($onlineLabel) . '</a>';
                }
            }

            if ($categories) {
                $details[] = self::escape(Text::_('COM_JEM_CATEGORY')) . ': '
                    . self::escape(implode(', ', $categories));
            }

            $parts[] = '<p>' . implode('<br>', $details) . '</p>';
            $body = self::body($row);

            if ($body !== '') {
                $parts[] = $body;
            }

            // Joomla's RSS renderer wraps descriptions in CDATA.
            $item->description = str_replace(']]>', ']]&gt;', implode('', $parts));
            $document->addItem($item);
        }
    }

    private static function schedule($row): string
    {
        $formatted = method_exists('JemOutput', 'formatEventSchedule')
            ? JemOutput::formatEventSchedule($row)
            : JemOutput::formatLongDateTime(
                $row->dates,
                $row->times,
                $row->enddates,
                $row->endtimes,
                JemHelper::config()->showtime
            );

        $formatted = preg_replace('/<br\s*\/?\s*>/i', '; ', (string) $formatted);
        $schedule = trim(html_entity_decode(strip_tags($formatted), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $schedule = preg_replace('/\s+/u', ' ', $schedule) ?? $schedule;

        $hasTimes = !empty($row->times);

        if (class_exists('JemEventSchedule', false) && method_exists('JemEventSchedule', 'hasTimes')) {
            $hasTimes = JemEventSchedule::hasTimes($row);
        }

        if ($hasTimes && self::enabled($row, 'event_show_timezone')) {
            $schedule .= ' (' . JemHelper::getEventTimeZoneName($row) . ')';
        }

        return $schedule;
    }

    /**
     * Feed HTML is more constrained than editor HTML because feed readers
     * render it outside the site's template and content-plugin context.
     */
    private static function body($row): string
    {
        if (!self::enabled($row, 'event_show_description')) {
            return '';
        }

        $intro = trim((string) ($row->introtext ?? ''));
        $full = trim((string) ($row->fulltext ?? ''));

        if ($full === '' || $full === '<br>') {
            $html = $intro;
        } else {
            $html = self::enabled($row, 'event_show_intro') ? $intro . ' ' . $full : $full;
        }

        if ($html === '') {
            return '';
        }

        $filter = InputFilter::getInstance(
            array('p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li', 'blockquote', 'img'),
            array('href', 'src', 'alt', 'title', 'width', 'height'),
            InputFilter::ONLY_ALLOW_DEFINED_TAGS,
            InputFilter::ONLY_ALLOW_DEFINED_ATTRIBUTES
        );

        return trim((string) $filter->clean($html, 'HTML'));
    }

    private static function enabled($row, string $name): bool
    {
        $value = isset($row->params) && method_exists($row->params, 'get')
            ? $row->params->get($name, null)
            : null;

        if ($value === null || $value === '') {
            $value = JemHelper::globalattribs()->get($name, 1);
        }

        return (int) $value === 1;
    }

    /**
     * Resolve only readable public raster files. Never create thumbnails in
     * a feed request or pass unescaped filenames to Joomla's XML enclosure.
     */
    private static function image($row): ?array
    {
        $siteRoot = realpath(JPATH_SITE);

        if ($siteRoot === false) {
            return null;
        }

        foreach (array('datimage', 'fullimage') as $field) {
            if ($field === 'fullimage') {
                $layout = (string) ($row->fullimage_layout ?? 'global');

                if ($layout === '' || $layout === 'global') {
                    $layout = (string) JemHelper::globalattribs()->get('event_detail_image_layout', 'right');
                }

                if ($layout === 'hidden') {
                    continue;
                }
            }

            $name = trim(str_replace('\\', '/', (string) ($row->$field ?? '')));

            if ($name === '' || str_contains($name, "\0")) {
                continue;
            }

            if (str_contains($name, '/')) {
                $relative = ltrim($name, '/');

                if (!str_starts_with($relative, 'images/') && !str_starts_with($relative, 'media/')) {
                    continue;
                }
            } else {
                $folder = trim(str_replace('\\', '/', (string) ($row->image_path ?? '')), '/');
                $relative = 'images/jem/events/' . ($folder !== '' ? $folder . '/' : '') . $name;
            }

            $absolute = realpath($siteRoot . '/' . $relative);
            $imagesRoot = realpath($siteRoot . '/images');
            $mediaRoot = realpath($siteRoot . '/media');

            if ($absolute === false || !self::inside($absolute, $siteRoot)
                || (!self::inside($absolute, $imagesRoot) && !self::inside($absolute, $mediaRoot))) {
                continue;
            }

            $size = @filesize($absolute);

            if ($size === false || $size < 1 || $size > 20971520) {
                continue;
            }

            $resource = JemImageResourcePolicy::inspect($absolute);

            if (empty($resource['accepted'])) {
                continue;
            }

            $path = str_replace('\\', '/', substr($absolute, strlen($siteRoot) + 1));
            $url = Uri::root() . implode('/', array_map('rawurlencode', explode('/', $path)));

            // Joomla's RSS/Atom renderer writes enclosure attributes verbatim.
            if (!filter_var($url, FILTER_VALIDATE_URL) || preg_match('/["&<>\x00-\x1F]/', $url)) {
                continue;
            }

            return array(
                'url' => $url,
                'length' => (int) $size,
                'type' => image_type_to_mime_type((int) $resource['type']),
            );
        }

        return null;
    }

    private static function inside(string $path, $root): bool
    {
        if (!is_string($root)) {
            return false;
        }

        $prefix = rtrim($root, '\\/') . DIRECTORY_SEPARATOR;

        return (PHP_OS_FAMILY === 'Windows' ? strncasecmp($path, $prefix, strlen($prefix))
            : strncmp($path, $prefix, strlen($prefix))) === 0;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
