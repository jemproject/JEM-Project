<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Shared resource budgets for external import parsers.
 */
class JemImportBudgetHelper
{
    public const MAX_FILE_BYTES = 10 * 1024 * 1024;
    public const MAX_RECORDS = 10000;
    public const MAX_FIELDS = 512;
    public const MAX_STRUCTURE_DEPTH = 32;
    public const MAX_STRUCTURE_NODES = 100000;
    public const MAX_FIELD_BYTES = 1024 * 1024;
    public const MAX_TOTAL_VALUE_BYTES = 20 * 1024 * 1024;
    public const MAX_LINE_BYTES = 1024 * 1024;
    public const MAX_ICS_LINES = 100000;

    /**
     * Validate and read one text import source within the common byte budget.
     *
     * @param   string  $path  Source file path.
     *
     * @return string
     *
     * @throws RuntimeException
     */
    public static function readTextFile($path)
    {
        self::assertFileSize($path);
        $content = file_get_contents($path);

        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        return $content;
    }

    /**
     * Validate an import source file size before parsing it.
     *
     * @param   string   $path      Source file path.
     * @param   integer  $maxBytes  Maximum accepted size.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    public static function assertFileSize($path, $maxBytes = self::MAX_FILE_BYTES)
    {
        $size = is_file($path) ? filesize($path) : false;

        if ($size === false) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_OPEN_FILE_ERROR'));
        }

        if ($size > (int) $maxBytes) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FILE_SIZE', self::formatMebibytes($maxBytes)));
        }
    }

    /**
     * Decode JSON after a bounded lexical preflight.
     *
     * @param   string  $content  Raw JSON document.
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public static function decodeJson($content)
    {
        self::assertTextSize($content);
        self::assertJsonStructure($content);

        try {
            $decoded = json_decode(
                $content,
                true,
                self::MAX_STRUCTURE_DEPTH + 1,
                JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
            );
        } catch (JsonException $e) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        if (!is_array($decoded)) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_PARSE_ERROR'));
        }

        self::assertStructure($decoded);

        return $decoded;
    }

    /**
     * Parse XML with network access disabled and DTD/entity declarations rejected.
     *
     * @param   string  $content  Raw XML document.
     *
     * @return SimpleXMLElement
     *
     * @throws RuntimeException
     */
    public static function loadXml($content)
    {
        self::assertTextSize($content);

        if (preg_match('/<\s*!(?:DOCTYPE|ENTITY)\b/i', $content)) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_XML_ENTITIES_NOT_ALLOWED'));
        }

        if (substr_count($content, '<') > (self::MAX_STRUCTURE_NODES * 2) + 10) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_STRUCTURE_NODES', self::MAX_STRUCTURE_NODES));
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $content,
                'SimpleXMLElement',
                LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT
            );

            if ($xml === false) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_PARSE_ERROR'));
            }

            self::assertXmlStructure($xml);

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Validate a parsed record collection against common row and value budgets.
     *
     * @param   array  $records  Parsed source records.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    public static function assertRecordList(array $records)
    {
        self::assertRecordCount(count($records));
        $totalBytes = 0;

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_PARSE_ERROR'));
            }

            self::assertTabularRow($record, $totalBytes);
        }
    }

    /**
     * Validate one CSV/tabular row and its cumulative scalar size.
     *
     * @param   array    $row         Parsed row.
     * @param   integer  $totalBytes  Cumulative scalar bytes.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    public static function assertTabularRow(array $row, &$totalBytes)
    {
        if (count($row) > self::MAX_FIELDS) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FIELDS', self::MAX_FIELDS));
        }

        foreach ($row as $value) {
            $bytes = strlen((string) $value);

            if ($bytes > self::MAX_FIELD_BYTES) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FIELD_SIZE', self::formatMebibytes(self::MAX_FIELD_BYTES)));
            }

            $totalBytes += $bytes;

            if ($totalBytes > self::MAX_TOTAL_VALUE_BYTES) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_VALUE_SIZE', self::formatMebibytes(self::MAX_TOTAL_VALUE_BYTES)));
            }
        }
    }

    /**
     * Validate an accumulated record count.
     *
     * @param   integer  $count  Number of parsed records.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    public static function assertRecordCount($count)
    {
        if ((int) $count > self::MAX_RECORDS) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_RECORDS', self::MAX_RECORDS));
        }
    }

    /**
     * Validate an ICS document before it is split into lines and events.
     *
     * @param   string  $content  Raw ICS document.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    public static function assertIcs($content)
    {
        self::assertTextSize($content);
        $content = str_replace("\r\n", "\n", (string) $content);
        $lineCount = substr_count($content, "\n") + 1;

        if ($lineCount > self::MAX_ICS_LINES) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_LINES', self::MAX_ICS_LINES));
        }

        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $lineEnd = strpos($content, "\n", $offset);
            $lineEnd = $lineEnd === false ? $length : $lineEnd;

            if ($lineEnd - $offset > self::MAX_LINE_BYTES) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FIELD_SIZE', self::formatMebibytes(self::MAX_FIELD_BYTES)));
            }

            $offset = $lineEnd + 1;
        }
    }

    private static function assertTextSize($content)
    {
        if (strlen((string) $content) > self::MAX_FILE_BYTES) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FILE_SIZE', self::formatMebibytes(self::MAX_FILE_BYTES)));
        }
    }

    private static function assertJsonStructure($content)
    {
        $depth = 0;
        $nodes = 0;
        $inString = false;
        $escaped = false;
        $stringBytes = 0;
        $length = strlen($content);

        for ($index = 0; $index < $length; $index++) {
            $character = $content[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;

                    if ($stringBytes > self::MAX_FIELD_BYTES) {
                        throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FIELD_SIZE', self::formatMebibytes(self::MAX_FIELD_BYTES)));
                    }
                } else {
                    $stringBytes++;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;
                $stringBytes = 0;
                $nodes++;
            } elseif ($character === '{' || $character === '[') {
                $depth++;
                $nodes++;

                if ($depth > self::MAX_STRUCTURE_DEPTH) {
                    throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_STRUCTURE_DEPTH', self::MAX_STRUCTURE_DEPTH));
                }
            } elseif ($character === '}' || $character === ']') {
                $depth--;
            } elseif ($character === ',') {
                $nodes++;
            }

            if ($nodes > self::MAX_STRUCTURE_NODES) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_STRUCTURE_NODES', self::MAX_STRUCTURE_NODES));
            }
        }
    }

    private static function assertStructure(array $value)
    {
        $stack = array(array($value, 1));
        $nodes = 0;
        $totalBytes = 0;

        while ($stack) {
            list($current, $depth) = array_pop($stack);

            if ($depth > self::MAX_STRUCTURE_DEPTH) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_STRUCTURE_DEPTH', self::MAX_STRUCTURE_DEPTH));
            }

            if (count($current) > self::MAX_FIELDS && array_keys($current) !== range(0, count($current) - 1)) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FIELDS', self::MAX_FIELDS));
            }

            foreach ($current as $key => $item) {
                $nodes++;
                $keyBytes = strlen((string) $key);

                if ($keyBytes > self::MAX_FIELD_BYTES) {
                    throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FIELD_SIZE', self::formatMebibytes(self::MAX_FIELD_BYTES)));
                }

                $totalBytes += $keyBytes;

                if ($nodes > self::MAX_STRUCTURE_NODES) {
                    throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_STRUCTURE_NODES', self::MAX_STRUCTURE_NODES));
                }

                if (is_array($item)) {
                    $stack[] = array($item, $depth + 1);
                    continue;
                }

                $bytes = strlen((string) $item);

                if ($bytes > self::MAX_FIELD_BYTES) {
                    throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FIELD_SIZE', self::formatMebibytes(self::MAX_FIELD_BYTES)));
                }

                $totalBytes += $bytes;

                if ($totalBytes > self::MAX_TOTAL_VALUE_BYTES) {
                    throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_VALUE_SIZE', self::formatMebibytes(self::MAX_TOTAL_VALUE_BYTES)));
                }
            }
        }
    }

    private static function assertXmlStructure(SimpleXMLElement $xml)
    {
        $stack = array(array($xml, 1));
        $nodes = 0;
        $totalBytes = 0;

        while ($stack) {
            list($node, $depth) = array_pop($stack);
            $nodes++;

            if ($nodes > self::MAX_STRUCTURE_NODES) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_STRUCTURE_NODES', self::MAX_STRUCTURE_NODES));
            }

            if ($depth > self::MAX_STRUCTURE_DEPTH) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_STRUCTURE_DEPTH', self::MAX_STRUCTURE_DEPTH));
            }

            $valueBytes = strlen((string) $node);

            if ($valueBytes > self::MAX_FIELD_BYTES) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_FIELD_SIZE', self::formatMebibytes(self::MAX_FIELD_BYTES)));
            }

            $totalBytes += $valueBytes;

            if ($totalBytes > self::MAX_TOTAL_VALUE_BYTES) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_LIMIT_VALUE_SIZE', self::formatMebibytes(self::MAX_TOTAL_VALUE_BYTES)));
            }

            foreach ($node->children() as $child) {
                $stack[] = array($child, $depth + 1);
            }
        }
    }

    private static function formatMebibytes($bytes)
    {
        return rtrim(rtrim(number_format(((int) $bytes) / 1048576, 2, '.', ''), '0'), '.');
    }

    private static function message($key, ...$arguments)
    {
        if (class_exists(Text::class)) {
            return $arguments ? Text::sprintf($key, ...$arguments) : Text::_($key);
        }

        return $arguments ? $key . ': ' . implode(', ', $arguments) : $key;
    }
}
