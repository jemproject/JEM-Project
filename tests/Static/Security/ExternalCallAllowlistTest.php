<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ExternalCallAllowlistTest extends TestCase
{
    /**
     * Documented external domains already used by JEM, licenses, schemas, maps, or vendored libraries.
     * New domains can mean a new API dependency or data-leak path and must be reviewed here.
     */
    private const ALLOWED_DOMAINS = array(
        'ajax.googleapis.com',
        'chart.apis.google.com',
        'code.google.com',
        'developers.google.com',
        'dev.mysql.com',
        'diveintomark.org',
        'docs.joomla.org',
        'docs-next.joomla.org',
        'en.wikipedia.org',
        'api.worldbank.org',
        'bugs.php.net',
        'erikastokes.com',
        'github.com',
        'gist.github.com',
        'google-maps-utility-library-v3.googlecode.com',
        'johannburkard.de',
        'joomlaeventmanager.net',
        'keithdevens.com',
        'keyj.emphy.de',
        'kigkonsult.se',
        'leafletjs.com',
        'localhost',
        'lokeshdhakar.com',
        'maps.google',
        'maps.google.com',
        'maps.googleapis.com',
        'nifox.com',
        'nominatim.openstreetmap.org',
        'openstreetmap.org',
        'php.net',
        'opentopomap.org',
        'php.watch',
        'routing.openstreetmap.de',
        'schema.org',
        'staticmap.openstreetmap.de',
        'stackoverflow.com',
        'tile.openstreetmap.org',
        'tile.opentopomap.org',
        'ubilabs.net',
        'www.apache.org',
        'www.cl.cam.ac.uk',
        'www.corissia.com',
        'www.findlatitudeandlongitude.com',
        'www.gchats.com',
        'www.gnu.org',
        'www.google',
        'www.google.com',
        'www.ietf.org',
        'www.joomla.org',
        'www.joomlaeventmanager.net',
        'www.micronetwork.de',
        'www.nbdtech.com',
        'www.openstreetmap.org',
        'www.opensource.org',
        'www.php.net',
        'www.quirksmode.org',
        'www.w3.org',
    );

    public function testExternalUrlsUseReviewedDomains(): void
    {
        $allowed = array_flip(self::ALLOWED_DOMAINS);
        $findings = array();

        foreach ($this->sourceFiles() as $path) {
            $relative = $this->relativePath($path);

            if ($this->isReviewedThirdPartyPath($relative)) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            preg_match_all('~https?://([^/\'"\s<>)]+)~i', $contents, $matches);

            foreach ($matches[1] as $host) {
                $host = $this->normaliseHost($host);

                if ($host !== '' && !isset($allowed[$host])) {
                    $findings[] = $relative . ':' . $host;
                }
            }
        }

        $findings = array_values(array_unique($findings));
        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "New external domains need review/documentation:\n" . implode("\n", $findings)
        );
    }

    /**
     * @return iterable<string>
     */
    private function sourceFiles(): iterable
    {
        foreach (array('admin', 'site', 'modules', 'plugins', 'media') as $root) {
            $directory = JEM_TEST_ROOT . '/' . $root;

            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), array('php', 'js', 'xml'), true)) {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }

    private function normaliseHost(string $host): string
    {
        $host = strtolower(trim($host, ".,;:)]}'\""));
        $host = (string) preg_replace('/:\d+$/', '', $host);
        $host = (string) preg_replace('/^\{[a-z]\}\./', '', $host);
        $host = rtrim($host, '.');

        return $host;
    }

    private function isReviewedThirdPartyPath(string $relative): bool
    {
        return str_starts_with($relative, 'site/classes/tcpdf/');
    }
}
