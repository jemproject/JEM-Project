<?php

declare(strict_types=1);

/**
 * Inspect, validate or remove only JEM database tables in a local Joomla site.
 * Database credentials are read from configuration.php and are never printed.
 *
 * Usage:
 * php tools/qa/jem-database-audit.php snapshot --site=C:/xampp/htdocs/jl546 --output=C:/temp/before.json
 * php tools/qa/jem-database-audit.php validate --site=C:/xampp/htdocs/jl546 --source-root=C:/GitHub/JEM-Project-5.0.1 --before=C:/temp/before.json --output=C:/temp/after.json
 * php tools/qa/jem-database-audit.php cleanup --site=C:/xampp/htdocs/jl546 --confirm=DROP_JEM_ONLY
 */

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

/** @return array<string, string> */
function parseOptions(array $arguments): array
{
    $options = [];

    foreach (array_slice($arguments, 2) as $argument) {
        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            fail('Invalid argument: ' . $argument, 64);
        }

        [$name, $value] = explode('=', substr($argument, 2), 2);
        $options[$name] = $value;
    }

    return $options;
}

function requiredOption(array $options, string $name): string
{
    $value = trim($options[$name] ?? '');

    if ($value === '') {
        fail('Missing required option --' . $name, 64);
    }

    return $value;
}

/** @return array{database: mysqli, prefix: string} */
function connectToJoomla(string $siteRoot): array
{
    $configuration = rtrim(str_replace('\\', '/', $siteRoot), '/') . '/configuration.php';

    if (!is_file($configuration)) {
        fail('Joomla configuration not found: ' . $configuration);
    }

    require_once $configuration;

    if (!class_exists('JConfig')) {
        fail('JConfig was not defined by ' . $configuration);
    }

    $config = new JConfig();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $database = new mysqli(
        (string) $config->host,
        (string) $config->user,
        (string) $config->password,
        (string) $config->db
    );
    $database->set_charset('utf8mb4');

    return ['database' => $database, 'prefix' => (string) $config->dbprefix];
}

function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

/** @return list<string> */
function jemTables(mysqli $database, string $prefix): array
{
    $tables = [];
    $result = $database->query('SHOW TABLES');

    while ($row = $result->fetch_row()) {
        $table = (string) $row[0];

        if (str_starts_with($table, $prefix . 'jem_')) {
            $tables[] = $table;
        }
    }

    sort($tables, SORT_STRING);

    return $tables;
}

/** @return list<array<string, mixed>> */
function queryRows(mysqli $database, string $sql): array
{
    $rows = [];
    $result = $database->query($sql);

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

/** @return array<string, mixed> */
function tableSnapshot(mysqli $database, string $table, string $prefix): array
{
    $columns = queryRows($database, 'SHOW FULL COLUMNS FROM ' . quoteIdentifier($table));
    $indexes = queryRows($database, 'SHOW INDEX FROM ' . quoteIdentifier($table));
    $primary = [];

    foreach ($indexes as $index) {
        if (($index['Key_name'] ?? '') === 'PRIMARY') {
            $primary[(int) $index['Seq_in_index']] = (string) $index['Column_name'];
        }
    }

    ksort($primary);
    $primary = array_values($primary);
    $order = $primary !== []
        ? ' ORDER BY ' . implode(', ', array_map('quoteIdentifier', $primary))
        : '';
    $rows = queryRows($database, 'SELECT * FROM ' . quoteIdentifier($table) . $order);

    return [
        'name' => '#__' . substr($table, strlen($prefix)),
        'actual_name' => $table,
        'columns' => $columns,
        'indexes' => $indexes,
        'primary_key' => $primary,
        'row_count' => count($rows),
        'row_hash' => hash('sha256', json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        'rows' => $rows,
    ];
}

function isJemExtension(array $extension): bool
{
    $type = strtolower((string) ($extension['type'] ?? ''));
    $element = strtolower((string) ($extension['element'] ?? ''));
    $folder = strtolower((string) ($extension['folder'] ?? ''));
    $name = strtolower((string) ($extension['name'] ?? ''));

    return ($type === 'component' && $element === 'com_jem')
        || ($type === 'package' && $element === 'pkg_jem')
        || ($type === 'module' && str_starts_with($element, 'mod_jem'))
        || ($type === 'plugin' && ($folder === 'jem' || str_contains($name, 'jem') || str_contains($element, 'jem')));
}

/** @return array<string, mixed> */
function extensionSnapshot(mysqli $database, string $prefix): array
{
    $extensions = queryRows(
        $database,
        'SELECT extension_id, name, type, element, folder, client_id, enabled, manifest_cache FROM '
        . quoteIdentifier($prefix . 'extensions') . ' ORDER BY extension_id'
    );
    $jem = [];
    $nonJem = [];

    foreach ($extensions as $extension) {
        $manifest = json_decode((string) ($extension['manifest_cache'] ?? ''), true);
        unset($extension['manifest_cache']);
        $extension['version'] = is_array($manifest) ? (string) ($manifest['version'] ?? '') : '';

        if (isJemExtension($extension)) {
            $jem[] = $extension;
        } else {
            $nonJem[] = $extension;
        }
    }

    return [
        'jem' => $jem,
        'non_jem_count' => count($nonJem),
        'non_jem_hash' => hash('sha256', json_encode($nonJem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
    ];
}

/** @return array<string, mixed> */
function snapshot(mysqli $database, string $prefix): array
{
    $tables = [];

    foreach (jemTables($database, $prefix) as $table) {
        $tableData = tableSnapshot($database, $table, $prefix);
        $tables[$tableData['name']] = $tableData;
    }

    $ids = [];
    foreach ([
        'events' => '#__jem_events',
        'venues' => '#__jem_venues',
        'categories' => '#__jem_categories',
        'groups' => '#__jem_groups',
        'types' => '#__jem_types',
        'specialdays' => '#__jem_specialdays',
        'registrations' => '#__jem_register',
        'attachments' => '#__jem_attachments',
    ] as $key => $logicalTable) {
        $rows = $tables[$logicalTable]['rows'] ?? [];
        $ids[$key] = isset($rows[0]['id']) ? (int) $rows[0]['id'] : null;
    }

    return [
        'generated_at' => gmdate('c'),
        'extensions' => extensionSnapshot($database, $prefix),
        'tables' => $tables,
        'ids' => $ids,
    ];
}

/** @return array<string, list<string>> */
function expectedSchema(string $sourceRoot): array
{
    $sqlFile = rtrim(str_replace('\\', '/', $sourceRoot), '/') . '/admin/sql/install.mysql.utf8.sql';
    $sql = is_file($sqlFile) ? file_get_contents($sqlFile) : false;

    if ($sql === false) {
        fail('Current install SQL not found: ' . $sqlFile);
    }

    preg_match_all(
        '/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?(#__jem_[a-z0-9_]+)`?\s*\((.*?)\)\s*(?:ENGINE|TYPE)\s*=/is',
        $sql,
        $matches,
        PREG_SET_ORDER
    );

    $expected = [];
    foreach ($matches as $match) {
        preg_match_all('/^\s*`([^`]+)`\s+/m', $match[2], $columnMatches);
        $expected[$match[1]] = array_values(array_unique($columnMatches[1]));
    }

    ksort($expected, SORT_STRING);

    return $expected;
}

/** @return array{failures: list<string>, warnings: list<string>} */
function validateSchema(array $current, array $expected): array
{
    $failures = [];
    $warnings = [];

    foreach ($expected as $table => $expectedColumns) {
        if (!isset($current['tables'][$table])) {
            $failures[] = 'Missing table ' . $table;
            continue;
        }

        $actualColumns = array_map(
            static fn (array $column): string => (string) $column['Field'],
            $current['tables'][$table]['columns']
        );

        foreach (array_diff($expectedColumns, $actualColumns) as $column) {
            $failures[] = 'Missing column ' . $table . '.' . $column;
        }

        foreach (array_diff($actualColumns, $expectedColumns) as $column) {
            $warnings[] = 'Legacy or extra column ' . $table . '.' . $column;
        }
    }

    foreach (array_diff(array_keys($current['tables']), array_keys($expected)) as $table) {
        $warnings[] = 'Legacy or extra table ' . $table;
    }

    return ['failures' => $failures, 'warnings' => $warnings];
}

function primarySignature(array $row, array $primary): string
{
    $values = [];

    foreach ($primary as $column) {
        $values[$column] = $row[$column] ?? null;
    }

    return json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** @return array{failures: list<string>, warnings: list<string>} */
function validateData(array $before, array $after): array
{
    $failures = [];
    $warnings = [];
    $ignoredChanges = ['checked_out', 'checked_out_time', 'modified', 'modified_by', 'version'];

    foreach ($before['tables'] ?? [] as $table => $beforeTable) {
        if (!isset($after['tables'][$table])) {
            $failures[] = 'Upgrade removed table containing baseline data: ' . $table;
            continue;
        }

        $afterTable = $after['tables'][$table];
        if ((int) $afterTable['row_count'] < (int) $beforeTable['row_count']) {
            $failures[] = 'Row count decreased in ' . $table . ': ' . $beforeTable['row_count'] . ' -> ' . $afterTable['row_count'];
        }

        $primary = $beforeTable['primary_key'] ?? [];
        if ($primary === []) {
            continue;
        }

        $afterRows = [];
        foreach ($afterTable['rows'] as $row) {
            $afterRows[primarySignature($row, $primary)] = $row;
        }

        foreach ($beforeTable['rows'] as $beforeRow) {
            $signature = primarySignature($beforeRow, $primary);
            if (!isset($afterRows[$signature])) {
                $failures[] = 'Baseline row missing after upgrade in ' . $table . ': ' . $signature;
                continue;
            }

            foreach ($beforeRow as $column => $value) {
                if (in_array($column, $ignoredChanges, true) || !array_key_exists($column, $afterRows[$signature])) {
                    continue;
                }

                if ((string) $afterRows[$signature][$column] !== (string) $value) {
                    $warnings[] = 'Value changed during upgrade: ' . $table . ' ' . $signature . ' column ' . $column;
                }
            }
        }
    }

    return ['failures' => $failures, 'warnings' => $warnings];
}

function writeReport(array $report, ?string $output): void
{
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        fail('Could not encode the database report.');
    }

    if ($output !== null && $output !== '') {
        $directory = dirname($output);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            fail('Could not create report directory: ' . $directory);
        }

        if (file_put_contents($output, $json . PHP_EOL) === false) {
            fail('Could not write report: ' . $output);
        }

        echo 'Report written: ' . $output . PHP_EOL;
        return;
    }

    echo $json . PHP_EOL;
}

$command = $argv[1] ?? '';
$options = parseOptions($argv);
$siteRoot = requiredOption($options, 'site');
$connection = connectToJoomla($siteRoot);
$database = $connection['database'];
$prefix = $connection['prefix'];

if ($command === 'cleanup') {
    if (($options['confirm'] ?? '') !== 'DROP_JEM_ONLY') {
        fail('Cleanup requires --confirm=DROP_JEM_ONLY.', 64);
    }

    $tables = jemTables($database, $prefix);
    foreach ($tables as $table) {
        $database->query('DROP TABLE ' . quoteIdentifier($table));
    }

    echo 'Dropped ' . count($tables) . ' JEM tables from the selected Joomla database.' . PHP_EOL;
    exit(0);
}

$current = snapshot($database, $prefix);

if ($command === 'snapshot') {
    writeReport($current, $options['output'] ?? null);
    exit(0);
}

if ($command === 'validate') {
    $schema = validateSchema($current, expectedSchema(requiredOption($options, 'source-root')));
    $data = ['failures' => [], 'warnings' => []];

    if (isset($options['before']) && $options['before'] !== '') {
        $beforeJson = file_get_contents($options['before']);
        $before = $beforeJson === false ? null : json_decode($beforeJson, true);

        if (!is_array($before)) {
            fail('Could not read baseline snapshot: ' . $options['before']);
        }

        $data = validateData($before, $current);
    }

    $report = [
        'result' => ($schema['failures'] === [] && $data['failures'] === []) ? 'PASS' : 'FAIL',
        'schema' => $schema,
        'data' => $data,
        'snapshot' => $current,
    ];
    writeReport($report, $options['output'] ?? null);
    exit($report['result'] === 'PASS' ? 0 : 1);
}

fail('Unknown command. Use snapshot, validate or cleanup.', 64);
