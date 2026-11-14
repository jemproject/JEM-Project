<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** Advance notice for large Events List PDF downloads; not a resource guarantee. */
final class JemPdfExportNotice
{
    public const DEFAULT_THRESHOLD = 100;
    private const BATCH_SIZE = 100;
    private const SAMPLE_SIZE = 25;

    /**
     * Count visible model results in bounded batches, keeping only a small layout sample.
     *
     * @param object $model Populated Events List model with current filters and ACL.
     * @param int $threshold Number of events at which confirmation is requested.
     * @return array|null Visible count and sample, or null for a small export.
     */
    public static function inspect($model, int $threshold): ?array
    {
        $threshold = max(1, $threshold);
        $total = (int) $model->getTotal();
        if ($total < $threshold) {
            return null;
        }

        $saved = [];
        foreach (['list.start', 'list.limit', 'list.skip_attendee_numbers'] as $key) {
            $saved[$key] = $model->getState($key);
        }
        $count = 0;
        $sample = [];
        $stride = max(1, (int) floor($total / self::SAMPLE_SIZE));
        $nextSample = 1;

        try {
            for ($start = 0; $start < $total; $start += self::BATCH_SIZE) {
                // A fresh clone keeps Joomla's per-page item cache bounded. Its state is
                // shared on clone, so restore the original model in finally below.
                $batch = clone $model;
                $batch->setState('list.start', $start);
                $batch->setState('list.limit', self::BATCH_SIZE);
                $batch->setState('list.skip_attendee_numbers', true);
                foreach ((array) $batch->getItems() as $row) {
                    if (isset($row->user_has_access_category) && !$row->user_has_access_category) {
                        continue;
                    }
                    ++$count;
                    if (count($sample) < self::SAMPLE_SIZE && $count >= $nextSample) {
                        $sample[] = $row;
                        $nextSample += $stride;
                    }
                }
                unset($batch);
            }
        } finally {
            foreach ($saved as $key => $value) {
                $model->setState($key, $value);
            }
        }

        return $count >= $threshold ? ['count' => $count, 'sample' => $sample] : null;
    }

    /**
     * Extrapolate sampled page usage; the result is explicitly an approximation.
     *
     * @param int $total Visible event count.
     * @param int $sampleCount Events rendered in the preview.
     * @param float $samplePages Fractional pages occupied by that preview.
     * @return int Estimated pages, never less than one.
     */
    public static function estimatePages(int $total, int $sampleCount, float $samplePages): int
    {
        return max(1, (int) ceil(max(0.0, $samplePages) * max(0, $total) / max(1, $sampleCount)));
    }

    /**
     * Accept an explicit same-session POST only; a URL flag is not confirmation.
     *
     * @return bool Whether the user submitted the confirmation form.
     */
    public static function isConfirmed(): bool
    {
        return Factory::getApplication()->input->post->getInt('jem_pdf_confirm', 0) === 1
            && Session::checkToken('post');
    }

    /**
     * Show an accessible confirmation page while retaining the current export filters.
     *
     * @param int $count Number of visible events.
     * @param int|null $pages Approximate pages, or null if preview is unavailable.
     * @return void Output HTML instead of generating the full PDF.
     */
    public static function display(int $count, ?int $pages): void
    {
        $app = Factory::getApplication();
        $base = JemOutput::prepareExportLink('index.php?option=com_jem&view=eventslist', true);
        $download = Route::_($base . '&format=raw&layout=pdf', false);
        $back = Route::_($base, false);
        $escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $heading = Text::_('COM_JEM_PDF_LARGE_TITLE');
        $app->setHeader('Content-Type', 'text/html; charset=utf-8', true);
        $app->setHeader('Cache-Control', 'private, no-store', true);
        $app->sendHeaders();
        echo '<!doctype html><html lang="' . $escape($app->getLanguage()->getTag()) . '" dir="'
            . ($app->getLanguage()->isRtl() ? 'rtl' : 'ltr') . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>' . $escape($heading) . '</title>'
            . '<style>body{font:1rem/1.6 system-ui,sans-serif;background:#f4f6f8;color:#172b4d;margin:0;padding:1rem}'
            . 'main{max-width:42rem;margin:4vh auto;background:white;border:1px solid #ccd4df;border-radius:.5rem;padding:2rem}'
            . 'h1{font-size:1.5rem}.actions{display:flex;flex-wrap:wrap;gap:1rem;align-items:center}'
            . 'button{font:inherit;background:#164b8b;color:white;border:0;border-radius:.25rem;padding:.65rem 1rem;cursor:pointer}'
            . 'a{color:#164b8b}button:focus-visible,a:focus-visible{outline:3px solid #b65c00;outline-offset:3px}</style></head><body><main>'
            . '<h1>' . $escape($heading) . '</h1><p><strong>'
            . $escape(Text::sprintf('COM_JEM_PDF_LARGE_COUNT', $count)) . '</strong></p><p>'
            . $escape($pages === null ? Text::_('COM_JEM_PDF_LARGE_PAGES_UNKNOWN')
                : Text::sprintf('COM_JEM_PDF_LARGE_PAGES', $pages)) . '</p><p>'
            . $escape(Text::_('COM_JEM_PDF_LARGE_EXPLANATION')) . '</p><p>'
            . $escape(Text::_('COM_JEM_PDF_LARGE_FILTERS')) . '</p><form method="post" action="'
            . $escape($download) . '"><input type="hidden" name="jem_pdf_confirm" value="1">'
            . '<input type="hidden" name="' . $escape(Session::getFormToken()) . '" value="1">'
            . '<div class="actions"><button type="submit">' . $escape(Text::_('COM_JEM_PDF_LARGE_GENERATE'))
            . '</button><a href="' . $escape($back) . '">' . $escape(Text::_('COM_JEM_PDF_LARGE_BACK'))
            . '</a></div></form></main></body></html>';
        $app->close();
    }
}
