<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Minimal PDF integration wrapper for JEM.
 */
class JemPdf
{
    /**
     * Checks whether TCPDF is available, loading JEM's bundled copy only when
     * no TCPDF classes have already been loaded by Joomla or another extension.
     */
    public static function isAvailable(): bool
    {
        if (class_exists('TCPDF', false)) {
            return true;
        }

        if (self::hasPartialTcpdfLoad()) {
            self::log('TCPDF cannot be loaded because another extension has already loaded partial TCPDF classes.');

            return false;
        }

        $path = JPATH_COMPONENT_SITE . '/classes/tcpdf/tcpdf.php';

        if (!is_file($path)) {
            self::log('TCPDF library file not found: ' . $path);

            return false;
        }

        require_once $path;

        return class_exists('TCPDF', false);
    }

    /**
     * Creates a basic TCPDF document instance.
     *
     * @return TCPDF|null
     */
    public static function createDocument(
        string $title = '',
        string $orientation = 'L',
        string $format = 'A4',
        string $encoding = 'UTF-8',
        string $fontFamily = 'dejavusans'
    ) {
        if (!self::isAvailable()) {
            return null;
        }

        $pdf = new TCPDF($orientation, 'mm', $format, true, $encoding, false);
        $pdf->SetCreator('JEM');
        $pdf->SetFont(self::resolveFontFamily($fontFamily), '', 10);

        if ($title !== '') {
            $pdf->SetTitle($title);
        }

        return $pdf;
    }

    /**
     * Returns true when a PDF format should be treated as a poster/layout sheet
     * where JEM should try to keep the view on one page.
     */
    public static function prefersSinglePage(string $format): bool
    {
        return in_array(strtoupper(trim($format)), array('A3', 'A2', 'A1'), true);
    }

    /**
     * Reduces configured margins for poster formats so the rendered view can
     * use as much of the paper as possible.
     */
    public static function fitSinglePageMargins(array $margins, string $format): array
    {
        if (!self::prefersSinglePage($format)) {
            return $margins;
        }

        $limit = strtoupper(trim($format)) === 'A3' ? 8 : 10;

        return array(
            'top'    => min((int) ($margins['top'] ?? $limit), $limit),
            'right'  => min((int) ($margins['right'] ?? $limit), $limit),
            'bottom' => min((int) ($margins['bottom'] ?? $limit), $limit),
            'left'   => min((int) ($margins['left'] ?? $limit), $limit),
        );
    }

    /**
     * Returns a modest scale factor for poster PDF layouts.
     */
    public static function getPosterScale(string $format): float
    {
        switch (strtoupper(trim($format))) {
            case 'A1':
                return 1.65;

            case 'A2':
                return 1.35;

            case 'A3':
                return 1.15;
        }

        return 1.0;
    }

    /**
     * Returns the bundled PDF font families kept in the small JEM package.
     */
    public static function getBundledFonts(): array
    {
        return array(
            'helvetica' => array(
                'label'   => 'Sans-serif (Helvetica)',
                'family'  => 'helvetica',
                'aliases' => array('sans', 'sans-serif', 'arial', 'verdana', 'calibri', 'open sans', 'roboto'),
            ),
            'dejavusans' => array(
                'label'   => 'Unicode Sans-serif (DejaVu Sans)',
                'family'  => 'dejavusans',
                'aliases' => array('unicode', 'dejavu', 'dejavu sans'),
            ),
            'times' => array(
                'label'   => 'Serif (Times)',
                'family'  => 'times',
                'aliases' => array('serif', 'times new roman', 'georgia', 'palatino', 'palatino linotype'),
            ),
            'courier' => array(
                'label'   => 'Monospace (Courier)',
                'family'  => 'courier',
                'aliases' => array('mono', 'monospace', 'courier new', 'consolas'),
            ),
        );
    }

    /**
     * Resolves configured or requested font names to a bundled TCPDF family.
     */
    public static function resolveFontFamily(string $fontFamily = ''): string
    {
        $fontFamily = strtolower(trim($fontFamily));

        if ($fontFamily === '') {
            return 'dejavusans';
        }

        foreach (self::getBundledFonts() as $font) {
            $family = (string) $font['family'];

            if ($fontFamily === $family || in_array($fontFamily, $font['aliases'], true)) {
                return $family;
            }
        }

        return 'dejavusans';
    }

    /**
     * Reserved location for future custom TCPDF fonts installed by JEM.
     */
    public static function getCustomFontsPath(): string
    {
        return defined('JPATH_SITE') ? JPATH_SITE . '/media/com_jem/pdf-fonts' : '';
    }

    private static function hasPartialTcpdfLoad(): bool
    {
        foreach (array('TCPDF_FONTS', 'TCPDF_STATIC', 'TCPDF_COLORS', 'TCPDF_IMAGES', 'TCPDF_FONT_DATA') as $class) {
            if (class_exists($class, false)) {
                return true;
            }
        }

        return false;
    }

    private static function log(string $message): void
    {
        if (class_exists('JemHelper', false) && method_exists('JemHelper', 'addLogEntry')) {
            JemHelper::addLogEntry($message, __METHOD__);
        }
    }
}
