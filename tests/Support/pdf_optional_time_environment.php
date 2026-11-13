<?php

declare(strict_types=1);

/** Configuration boundary only; time validation and formatting are loaded from production. */
class PdfOptionalTimeSettings
{
    public static string $format = 'H:i';

    /** @return object Configured clock format with a suffix PDF callers must omit. */
    public static function config(): object
    {
        return (object) ['formattime' => self::$format, 'timename' => 'hours'];
    }
}

/**
 * Load a production method without the unrelated installed-component bootstrap.
 *
 * @param string $file Repository-relative PHP source path.
 * @param string $method Method name.
 * @return string Exact method declaration and body.
 */
function pdfOptionalTimeMethod(string $file, string $method): string
{
    $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
    $pattern = '/static public function ' . preg_quote($method, '/') . '\([^\n]*\)\s*\{.*?^    \}/ms';
    if (preg_match($pattern, $source, $match) !== 1) {
        throw new RuntimeException('Cannot load production time method: ' . $method);
    }

    return $match[0];
}

eval('class JemHelper extends PdfOptionalTimeSettings {'
    . pdfOptionalTimeMethod('site/helpers/helper.php', 'isValidTime') . '}');
eval('class JemOutput { public static function eventStatusLabel($event): string { return \'\'; }'
    . pdfOptionalTimeMethod('site/classes/output.class.php', 'formattime') . '}');
