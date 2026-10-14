<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

/** Policies for the bundled, trusted sample SQL (not a general SQL parser). */
final class JemSampleDataImport
{
    public static function hasFieldConfiguration($value): bool
    {
        return !in_array(trim((string) $value), array('', '{}', '[]'), true);
    }

    /** Keep the SQL source and its bundled archive unchanged; protect imports at execution time. */
    public static function queries(string $query, bool $keepFields): array
    {
        $statement = trim(preg_replace('/\A(?:\s*--[^\r\n]*(?:\r?\n|$))*/', '', $query));
        if (
            $keepFields && preg_match('/^INSERT\s+INTO\s+`#__jem_config`/i', $statement)
            && strpos($statement, "'custom_fields_config'") !== false
        ) {
            return array();
        }

        if (!preg_match('/^INSERT\s+INTO\s+`#__jem_types`\s*(\([^)]*\))\s+VALUES\s+(.+)$/is', $statement, $match)) {
            return array($query);
        }

        $columns = array_map(static function ($column) {
            return trim($column, " \t\r\n`");
        }, explode(',', trim($match[1], '()')));
        $aliasIndex = array_search('alias', $columns, true);
        $entityIndex = array_search('entity', $columns, true);
        if ($aliasIndex === false || $entityIndex === false) {
            throw new RuntimeException('Sample types require alias and entity columns.');
        }

        $queries = array();
        foreach (self::splitValues(rtrim($match[2], "; \t\r\n")) as $row) {
            if ($row[0] !== '(' || substr($row, -1) !== ')') {
                throw new RuntimeException('Invalid sample type row.');
            }
            $values = self::splitValues(substr($row, 1, -1));
            if (count($values) !== count($columns)) {
                throw new RuntimeException('Invalid sample type columns.');
            }
            $queries[] = 'INSERT INTO `#__jem_types` ' . $match[1]
                . ' SELECT ' . implode(', ', $values) . ' FROM DUAL WHERE NOT EXISTS ('
                . 'SELECT 1 FROM `#__jem_types` WHERE `alias` = ' . $values[$aliasIndex]
                . ' AND `entity` = ' . $values[$entityIndex] . ')';
        }
        return $queries;
    }

    private static function splitValues(string $text): array
    {
        $values = array();
        $depth = 0;
        $quote = null;
        $start = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    if ($i + 1 < $length && $text[$i + 1] === $quote) {
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
            } elseif ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $values[] = trim(substr($text, $start, $i - $start));
                $start = $i + 1;
            }
            if ($depth < 0) {
                throw new RuntimeException('Unbalanced sample type values.');
            }
        }
        if ($quote !== null || $depth !== 0) {
            throw new RuntimeException('Unbalanced sample type values.');
        }
        $values[] = trim(substr($text, $start));
        return $values;
    }
}
