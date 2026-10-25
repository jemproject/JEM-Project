<?php

/**
 * Add the legacy migration chain and installer fixes to the published JEM 4.5.0 ZIP.
 *
 * Usage: php tools/rebuild-4.5-direct-migration.php
 * The immutable v4.5.0.-stable tag is the input. The candidate is written under build/.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$build = $root . '/build';
$source = $build . '/pkg_jem_v4.5.0-original.zip';
$candidate = $build . '/pkg_jem_v4.5.0-direct-migration.zip';
$packageTime = strtotime('2026-10-25 00:00:00 UTC');

if (!is_dir($build) && !mkdir($build, 0775, true)) {
    throw new RuntimeException('Cannot create the build directory.');
}

$process = proc_open(
    array('git', 'show', 'v4.5.0.-stable:pkg_jem_v4.5.0.zip'),
    array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
    $pipes,
    $root
);

if (!is_resource($process)) {
    throw new RuntimeException('Cannot read the original package from the stable tag.');
}

fclose($pipes[0]);
$originalBytes = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$gitError = stream_get_contents($pipes[2]);
fclose($pipes[2]);

if (proc_close($process) !== 0 || $originalBytes === false) {
    throw new RuntimeException('Cannot read the stable package: ' . $gitError);
}

if (file_put_contents($source, $originalBytes) === false) {
    throw new RuntimeException('Cannot stage the original package.');
}

if (!copy($source, $candidate)) {
    throw new RuntimeException('Cannot copy the published package.');
}

$package = new ZipArchive();
if ($package->open($candidate) !== true) {
    throw new RuntimeException('Cannot open the package candidate.');
}

$componentPath = 'pkg_jem/packages/com_jem.zip';
$componentBytes = $package->getFromName($componentPath);
if ($componentBytes === false) {
    throw new RuntimeException('Component ZIP is missing from the package.');
}

$componentFile = tempnam(sys_get_temp_dir(), 'jem45_');
if ($componentFile === false || file_put_contents($componentFile, $componentBytes) === false) {
    throw new RuntimeException('Cannot stage the component ZIP.');
}

try {
    $component = new ZipArchive();
    if ($component->open($componentFile) !== true) {
        throw new RuntimeException('Cannot open the component ZIP.');
    }

    $componentFiles = array(
        'com_jem/script.php' => $root . '/script.php',
        'com_jem/jem.xml' => $root . '/jem.xml',
        'com_jem/admin/language/en-GB/com_jem.sys.ini' => $root . '/admin/language/en-GB/com_jem.sys.ini',
    );

    $addedComponentEntries = array();
    foreach (array('2.3.13', '2.3.14', '2.3.15', '2.3.16', '2.3.17', '4.0b2', '4.0b4', '4.4.3') as $version) {
        $entry = 'com_jem/admin/sql/updates/mysql/' . $version . '.sql';
        $componentFiles[$entry] = $root . '/admin/sql/updates/mysql/' . $version . '.sql';
        $addedComponentEntries[] = $entry;
    }

    foreach ($componentFiles as $entry => $file) {
        $exists = $component->locateName($entry) !== false;
        if ($exists === in_array($entry, $addedComponentEntries, true)
            || !$component->addFile($file, $entry)
            || !$component->setMtimeName($entry, $packageTime)) {
            throw new RuntimeException('Cannot replace component entry: ' . $entry);
        }
    }

    if (!$component->close()) {
        throw new RuntimeException('Cannot finalize the component ZIP.');
    }

    if (!$package->addFile($componentFile, $componentPath)
        || !$package->addFile($root . '/package/pkg_install.php', 'pkg_jem/pkg_install.php')
        || !$package->addFile($root . '/package/pkg_jem.xml', 'pkg_jem/pkg_jem.xml')
        || !$package->setMtimeName($componentPath, $packageTime)
        || !$package->setMtimeName('pkg_jem/pkg_install.php', $packageTime)
        || !$package->setMtimeName('pkg_jem/pkg_jem.xml', $packageTime)) {
        throw new RuntimeException('Cannot replace the package installer files.');
    }

    if (!$package->close()) {
        throw new RuntimeException('Cannot finalize the package ZIP.');
    }
} finally {
    if (is_file($componentFile)) {
        unlink($componentFile);
    }
}

/** @return array<string, string> */
function entryHashes(ZipArchive $archive): array
{
    $hashes = array();

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $name = $archive->getNameIndex($index);
        $contents = $archive->getFromIndex($index);

        if ($name === false || $contents === false) {
            throw new RuntimeException('Cannot read a ZIP entry.');
        }

        $hashes[$name] = hash('sha256', $contents);
    }

    ksort($hashes);
    return $hashes;
}

function verifyChanges(array $before, array $after, array $allowed, array $added = array()): void
{
    $expected = array_merge(array_keys($before), $added);
    sort($expected);
    if ($expected !== array_keys($after)) {
        throw new RuntimeException('The package entry list changed unexpectedly.');
    }

    foreach ($before as $name => $hash) {
        if (!in_array($name, $allowed, true) && $hash !== $after[$name]) {
            throw new RuntimeException('An unrelated package entry changed: ' . $name);
        }
    }
}

$original = new ZipArchive();
$updated = new ZipArchive();
if ($original->open($source) !== true || $updated->open($candidate) !== true) {
    throw new RuntimeException('Cannot verify the package ZIPs.');
}

verifyChanges(
    entryHashes($original),
    entryHashes($updated),
    array('pkg_jem/pkg_install.php', 'pkg_jem/pkg_jem.xml', $componentPath)
);

if ($updated->getFromName('pkg_jem/pkg_install.php') !== file_get_contents($root . '/package/pkg_install.php')) {
    throw new RuntimeException('Package preflight does not match the source.');
}

if ($updated->getFromName('pkg_jem/pkg_jem.xml') !== file_get_contents($root . '/package/pkg_jem.xml')) {
    throw new RuntimeException('Package manifest does not match the source.');
}

$originalComponent = $original->getFromName($componentPath);
$updatedComponent = $updated->getFromName($componentPath);
$original->close();
$updated->close();

if ($originalComponent === false || $updatedComponent === false) {
    throw new RuntimeException('Cannot verify the nested component ZIP.');
}

$originalComponentFile = tempnam(sys_get_temp_dir(), 'jem45_old_');
$updatedComponentFile = tempnam(sys_get_temp_dir(), 'jem45_new_');
if ($originalComponentFile === false || $updatedComponentFile === false) {
    throw new RuntimeException('Cannot stage the component ZIPs for verification.');
}

try {
    file_put_contents($originalComponentFile, $originalComponent);
    file_put_contents($updatedComponentFile, $updatedComponent);
    $oldComponentZip = new ZipArchive();
    $newComponentZip = new ZipArchive();

    if ($oldComponentZip->open($originalComponentFile) !== true
        || $newComponentZip->open($updatedComponentFile) !== true) {
        throw new RuntimeException('Cannot verify the nested component ZIPs.');
    }

    verifyChanges(
        entryHashes($oldComponentZip),
        entryHashes($newComponentZip),
        array_keys($componentFiles),
        $addedComponentEntries
    );

    foreach ($componentFiles as $entry => $file) {
        if ($newComponentZip->getFromName($entry) !== file_get_contents($file)) {
            throw new RuntimeException('Component entry does not match the source: ' . $entry);
        }
    }

    $oldComponentZip->close();
    $newComponentZip->close();
} finally {
    unlink($originalComponentFile);
    unlink($updatedComponentFile);
}

echo $candidate . PHP_EOL;
