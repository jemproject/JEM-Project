<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** Metadata belongs to an image slot, never to its filename or HTML alt attribute. */
final class JemImageMetadata
{
    // Leave room for HTML entity expansion in both slots of a TEXT JSON column.
    public const MAX_CAPTION_BYTES = 4096;

    public const CREDIT_LABELS = ['none', 'photo', 'image_credit', 'source', 'copyright', 'licence', 'custom'];

    public static function creditLabelMode($value): string
    {
        return in_array($value, self::CREDIT_LABELS, true) ? $value : 'image_credit';
    }

    public static function customCreditLabel($value): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/', '', self::alt($value))), 0, 80, 'UTF-8');
    }

    /** The same safe credit markup is used inline, in section footers and popups. */
    public static function creditHtml(string $credit, array $metadata = []): string
    {
        $credit = self::caption($credit);
        if ($credit === '') {
            return '';
        }
        $mode = self::creditLabelMode($metadata['credit_label'] ?? 'image_credit');
        $label = match ($mode) {
            'none' => '',
            'custom' => self::customCreditLabel($metadata['credit_label_custom'] ?? ''),
            'image_credit' => Text::_('COM_JEM_IMAGE_METADATA_FOOTER_LABEL'),
            default => Text::_('COM_JEM_IMAGE_METADATA_LABEL_' . strtoupper($mode)),
        };

        return ($label === '' ? '' : '<span class="jem-image-credit__label">'
            . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span> ') . $credit;
    }

    public static function slots(string $context): array
    {
        return match ($context) {
            'event' => ['datimage', 'fullimage'],
            'category' => ['image'],
            'venue' => ['locimage'],
            default => [],
        };
    }

    public static function decode($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    public static function alt($value): string
    {
        return mb_substr(trim(strip_tags(is_scalar($value) ? (string) $value : '')), 0, 255, 'UTF-8');
    }

    /** Accept only an opaque text colour, never arbitrary CSS declarations. */
    private static function textColour(string $style): string
    {
        if (!preg_match('/^\s*color\s*:\s*(#[a-f0-9]{6}|#[a-f0-9]{3}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))\s*;?\s*$/iD', $style, $match)) {
            return '';
        }
        $colour = strtolower($match[1]);
        if ($colour[0] === '#') {
            return strlen($colour) === 4
                ? '#' . $colour[1] . $colour[1] . $colour[2] . $colour[2] . $colour[3] . $colour[3]
                : $colour;
        }
        preg_match_all('/\d+/', $colour, $channels);
        $channels = array_map('intval', $channels[0]);

        return max($channels) <= 255 ? sprintf('#%02x%02x%02x', ...$channels) : '';
    }

    /** Build fresh markup with validated links and canonical text colours only. */
    public static function caption($value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return '';
        }
        $value = mb_strcut($value, 0, self::MAX_CAPTION_BYTES, 'UTF-8');
        // Four text values across an event's two images must also fit JSON/TEXT after escaping.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $value . '</body></html>', LIBXML_NONET);
            $body = $document->getElementsByTagName('body')->item(0);
            $result = $body ? self::children($body) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        // Bound the canonical HTML too, so repeated saves do not truncate escaped
        // text differently. Reparse the shorter input to keep tags well formed.
        if (strlen($result) > self::MAX_CAPTION_BYTES) {
            $length = max(0, (int) floor(strlen($value) * self::MAX_CAPTION_BYTES / strlen($result)) - 16);

            return self::caption(mb_strcut($value, 0, $length, 'UTF-8'));
        }

        return trim(html_entity_decode(strip_tags($result), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '' ? '' : $result;
    }

    private static function children(DOMNode $parent): string
    {
        $html = '';
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMText) {
                $html .= htmlspecialchars($node->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (!$node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'svg', 'math', 'iframe', 'object', 'embed', 'template'], true)) {
                continue;
            }
            $text = self::children($node);
            if ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                if (strlen($href) <= 2048 && !preg_match('/[\x00-\x20\x7f]/', $href)
                    && preg_match('~^https?://~i', $href) && filter_var($href, FILTER_VALIDATE_URL)) {
                    $html .= '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $text . '</a>';
                } else {
                    $html .= $text;
                }
            } elseif ($tag === 'span') {
                $colour = self::textColour($node->getAttribute('style'));
                $html .= $colour !== '' ? '<span style="color: ' . $colour . ';">' . $text . '</span>' : $text;
            } elseif (in_array($tag, ['strong', 'em'], true)) {
                $html .= '<' . $tag . '>' . $text . '</' . $tag . '>';
            } elseif ($tag === 'br') {
                $html .= '<br>';
            } else {
                $html .= $text;
            }
        }

        return $html;
    }

    public static function normalise($value, string $context): array
    {
        $input = self::decode($value);
        $result = [];
        foreach (self::slots($context) as $slot) {
            $entry = is_array($input[$slot] ?? null) ? $input[$slot] : [];
            $result[$slot] = [
                'alt' => self::alt($entry['alt'] ?? ''),
                'caption' => self::caption($entry['caption'] ?? ''),
                'image' => is_string($entry['image'] ?? null) ? $entry['image'] : '',
            ];
            $credit = self::caption($entry['credit'] ?? '');
            if ($credit !== '') {
                $result[$slot]['credit'] = $credit;
            }
            $label = self::creditLabelMode($entry['credit_label'] ?? 'image_credit');
            if ($label !== 'image_credit') {
                $result[$slot]['credit_label'] = $label;
            }
            if ($label === 'custom') {
                $result[$slot]['credit_label_custom'] = self::customCreditLabel($entry['credit_label_custom'] ?? '');
            }
            if (in_array($entry['align'] ?? '', ['center', 'right'], true)) {
                $result[$slot]['align'] = $entry['align'];
            }
            if (in_array($entry['footer'] ?? false, [true, 1, '1'], true)) {
                $result[$slot]['footer'] = true;
            }
        }

        return $result;
    }

    /** Merge omitted controls and enforce the editing profile on the server. */
    public static function merge($stored, $submitted, string $context, bool $advanced): array
    {
        $result = self::normalise($stored, $context);
        $input = self::decode($submitted);
        foreach (self::slots($context) as $slot) {
            if (!is_array($input[$slot] ?? null)) {
                continue;
            }
            if (array_key_exists('alt', $input[$slot])) {
                $result[$slot]['alt'] = self::alt($input[$slot]['alt']);
            }
            if ($advanced && array_key_exists('caption', $input[$slot])) {
                $result[$slot]['caption'] = self::caption($input[$slot]['caption']);
            }
            if ($advanced && array_key_exists('credit', $input[$slot])) {
                unset($result[$slot]['credit']);
                $credit = self::caption($input[$slot]['credit']);
                if ($credit !== '') {
                    $result[$slot]['credit'] = $credit;
                }
            }
            if ($advanced && array_key_exists('footer', $input[$slot])) {
                unset($result[$slot]['footer']);
                if (in_array($input[$slot]['footer'], [true, 1, '1'], true)) {
                    $result[$slot]['footer'] = true;
                }
            }
            if ($advanced && array_key_exists('align', $input[$slot])) {
                unset($result[$slot]['align']);
                if (in_array($input[$slot]['align'], ['center', 'right'], true)) {
                    $result[$slot]['align'] = $input[$slot]['align'];
                }
            }
            if ($advanced && array_key_exists('credit_label', $input[$slot])) {
                $result[$slot]['credit_label'] = self::creditLabelMode($input[$slot]['credit_label']);
            }
            if ($advanced && array_key_exists('credit_label_custom', $input[$slot])) {
                $result[$slot]['credit_label_custom'] = self::customCreditLabel($input[$slot]['credit_label_custom']);
            }
        }

        return self::normalise($result, $context);
    }

    public static function prepareSubmission(array &$data, string $context, int $copySourceId = 0): void
    {
        require_once __DIR__ . '/featurepolicy.class.php';
        $stored = self::stored($context, (int) ($data['id'] ?? 0) ?: $copySourceId);
        $metadata = self::decode($stored->image_metadata ?? '');
        if ($context === 'venue' && !isset($metadata['locimage']['alt'])) {
            $metadata['locimage']['alt'] = self::alt($stored->locimage_alt ?? '');
        }
        $data['image_metadata'] = json_encode(self::merge(
            $metadata, $data['image_metadata'] ?? [], $context,
            JemFeaturePolicy::current()->isAdvanced()
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function stored(string $context, int $id): ?object
    {
        if ($id < 1 || !self::slots($context)) {
            return null;
        }
        $table = ['event' => '#__jem_events', 'venue' => '#__jem_venues', 'category' => '#__jem_categories'][$context];
        $db = Factory::getContainer()->get('DatabaseDriver');
        $columns = array_merge(['image_metadata'], self::slots($context), $context === 'venue' ? ['locimage_alt'] : []);
        $query = $db->getQuery(true)->select($db->quoteName($columns))
            ->from($db->quoteName($table))->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /** Also used by direct table stores (copies, recurrence and imports). */
    public static function prepareStore(object $record, string $context): void
    {
        $stored = self::stored($context, (int) ($record->id ?? 0));
        $metadata = self::decode($record->image_metadata ?? ($stored->image_metadata ?? ''));
        if ($context === 'venue' && !isset($metadata['locimage']['alt'])) {
            $metadata['locimage']['alt'] = self::alt($record->locimage_alt ?? '');
        }
        $metadata = self::forImages($metadata, $record, $stored, $context);
        $record->image_metadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($context === 'venue') {
            $record->locimage_alt = $metadata['locimage']['alt'];
        }
    }

    public static function forImages($metadata, object $record, ?object $stored, string $context): array
    {
        $result = self::normalise($metadata, $context);
        $old = self::normalise($stored->image_metadata ?? '', $context);
        if ($context === 'venue' && $old['locimage']['alt'] === '') {
            $old['locimage']['alt'] = self::alt($stored->locimage_alt ?? '');
        }
        foreach (self::slots($context) as $slot) {
            $filename = (string) ($record->$slot ?? '');
            if ($filename === '') {
                $result[$slot] = ['alt' => '', 'caption' => '', 'image' => ''];
                continue;
            }
            if ($stored && (string) ($stored->$slot ?? '') !== $filename) {
                foreach (['alt', 'caption', 'credit'] as $key) {
                    if (($result[$slot][$key] ?? '') === ($old[$slot][$key] ?? '')) {
                        $result[$slot][$key] = '';
                    }
                }
                if ($result[$slot]['credit'] === '') {
                    unset($result[$slot]['credit']);
                }
            }
            $result[$slot]['image'] = $filename;
        }

        return $result;
    }

    public static function inheritCategory(array &$event, object $category): void
    {
        $metadata = self::normalise($event['image_metadata'] ?? '', 'event');
        $categoryMetadata = self::normalise($category->image_metadata ?? '', 'category');
        $metadata['datimage'] = $categoryMetadata['image'];
        $metadata['datimage']['image'] = (string) ($event['datimage'] ?? '');
        $event['image_metadata'] = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function resolve(object $record, string $context, string $slot = ''): array
    {
        if ($context === 'event') {
            $slot = $slot === 'fullimage' && !empty($record->fullimage) ? 'fullimage' : 'datimage';
        } else {
            $slot = self::slots($context)[0] ?? '';
        }
        // Event detail rows also contain joined venue data: never use event credits for it.
        $raw = $context === 'venue' && isset($record->locid)
            ? ($record->venue_image_metadata ?? '') : ($record->image_metadata ?? '');
        $metadata = self::normalise($raw, $context);
        $entry = $metadata[$slot] ?? ['alt' => '', 'caption' => '', 'image' => ''];
        if ($entry['image'] !== '' && $entry['image'] !== (string) ($record->$slot ?? '')) {
            $entry = ['alt' => '', 'caption' => '', 'image' => ''];
        }
        if ($entry['alt'] === '' && $context === 'venue') {
            $entry['alt'] = self::alt($record->locimage_alt ?? '');
        }

        return $entry;
    }

    /** Escape separately for lightbox HTML, then for its containing data attribute. */
    public static function lightboxTitle(string $title, string $caption, string $credit = '', array $metadata = []): string
    {
        $html = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $caption = self::caption($caption);
        if ($caption !== '') {
            $html .= '<span class="jem-lightbox-caption">' . $caption . '</span>';
        }
        $credit = self::creditHtml($credit, $metadata);
        if ($credit !== '') {
            $html .= '<span class="jem-lightbox-credit">' . $credit . '</span>';
        }

        return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function figure(string $imageHtml, string $caption, string $context, string $credit = '', string $alignment = 'left', array $metadata = []): string
    {
        $caption = self::caption($caption);
        $credit = self::creditHtml($credit, $metadata);
        if (($caption === '' && $credit === '') || $imageHtml === '') {
            return $imageHtml;
        }
        if ($caption !== '') {
            $alignmentClass = in_array($alignment, ['center', 'right'], true) ? ' jem-image-caption__text--' . $alignment : '';
            $caption = '<span class="jem-image-caption__text' . $alignmentClass . '">' . $caption . '</span>';
        }
        if ($credit !== '') {
            $caption .= '<span class="jem-image-credit">' . $credit . '</span>';
        }

        return '<figure class="jem-image-figure jem-image-figure--' . htmlspecialchars($context, ENT_QUOTES, 'UTF-8') . '">'
            . $imageHtml . '<figcaption class="jem-image-caption">' . $caption . '</figcaption></figure>';
    }

    /** Full-width credit belongs to the resource block, not the site's global footer. */
    public static function footer(object $record, $image, string $context, string $slot = ''): string
    {
        $metadata = self::resolve($record, $context, $slot);
        if (empty($image['original']) || empty($metadata['footer']) || empty($metadata['credit'])) {
            return '';
        }

        return '<div class="jem-image-credit jem-image-credit--footer" role="note">'
            . self::creditHtml($metadata['credit'], $metadata) . '</div>';
    }
}
