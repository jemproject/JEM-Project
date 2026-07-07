<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DirectSuperglobalsTest extends TestCase
{
    /**
     * Existing reviewed uses. Prefer Joomla input APIs for new request data access.
     */
    private const ALLOWED_USES = array(
        'modules/mod_jem_wide/tmpl/default_jem_eventslist.php:$_SERVER',
        'modules/mod_jem_wide/tmpl/default_jem_eventslist_small.php:$_SERVER',
        'plugins/plg_content_jemembed/jemembed.php:$_SERVER',
        'site/classes/icalcreator/Traits/PRODIDtrait.php:$_SERVER',
        'site/classes/iCalcreator.class.php:$_SERVER',
        'site/common/views/tmpl/responsive/default_jem_eventslist.php:$_SERVER',
        'site/common/views/tmpl/responsive/default_jem_eventslist_small.php:$_SERVER',
        'site/controller.php:$_SERVER',
        'site/helpers/helper.php:$_SERVER',
        'site/views/categories/tmpl/responsive/default_jem_eventslist.php:$_SERVER',
        'site/views/categories/tmpl/responsive/default_jem_eventslist_small.php:$_SERVER',
        'site/views/editevent/view.html.php:$_GET',
        'site/views/search/tmpl/responsive/default_jem_eventslist.php:$_SERVER',
        'site/views/search/tmpl/responsive/default_jem_eventslist_small.php:$_SERVER',
        'site/views/venueslist/tmpl/responsive/default_venues.php:$_SERVER',
    );

    public function testNoNewDirectSuperglobalAccessIsIntroduced(): void
    {
        $allowed = array_flip(self::ALLOWED_USES);
        $findings = array();

        foreach ($this->phpFiles() as $path) {
            $relative = $this->relativePath($path);

            if ($this->isReviewedThirdPartyPath($relative)) {
                continue;
            }

            $contents = $this->stripComments((string) file_get_contents($path));

            preg_match_all('/\$_(GET|POST|REQUEST|COOKIE|FILES|SERVER)\b/', $contents, $matches);

            foreach ($matches[0] as $superglobal) {
                $key = $relative . ':' . $superglobal;

                if (!isset($allowed[$key])) {
                    $findings[] = $key;
                }
            }
        }

        $findings = array_values(array_unique($findings));
        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "New direct superglobal access needs review:\n" . implode("\n", $findings)
        );
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(): iterable
    {
        foreach (array('admin', 'site', 'modules', 'plugins') as $root) {
            $directory = JEM_TEST_ROOT . '/' . $root;

            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function stripComments(string $contents): string
    {
        $tokens = token_get_all($contents);
        $clean = '';

        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }

            $clean .= is_array($token) ? $token[1] : $token;
        }

        return $clean;
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }

    private function isReviewedThirdPartyPath(string $relative): bool
    {
        return str_starts_with($relative, 'site/classes/tcpdf/');
    }
}
