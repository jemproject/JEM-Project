<?php

declare(strict_types=1);

/**
 * Build JEM package ZIP files without requiring Ant.
 *
 * Usage:
 *   php scripts/build-packages.php
 *   php scripts/build-packages.php C:/GitHub/JEM-Project C:/GitHub/JEM-Project-4.5
 */

final class JemPackageBuilder
{
    private const MODULES = [
        'mod_jem',
        'mod_jem_banner',
        'mod_jem_cal',
        'mod_jem_jubilee',
        'mod_jem_teaser',
        'mod_jem_wide',
        'mod_jem_map',
        'mod_jem_types',
    ];

    private const PLUGINS = [
        'plugins/plg_content_jemembed'     => 'plg_content_jemembed.zip',
        'plugins/plg_content_jemlistevents' => 'plg_content_jemlistevents.zip',
        'plugins/plg_finder_jem'           => 'plg_finder_jem.zip',
        'plugins/plg_jem_comments'         => 'plg_jem_comments.zip',
        'plugins/plg_jem_mailer'           => 'plg_jem_mailer.zip',
        'plugins/plg_quickicon_jem'        => 'plg_quickicon_jem.zip',
        'plugins/plg_actionlog_jem'        => 'plg_actionlog_jem.zip',
    ];

    public function build(string $root): string
    {
        $root = $this->normalizeRoot($root);
        $version = $this->readPackageVersion($root);
        $buildDir = $this->newBuildDir($root);

        try {
            $componentZip = $buildDir . '/components/com_jem.zip';
            $this->ensureDir(dirname($componentZip));
            $this->zipDirectory($root, $componentZip, fn (string $relative): bool => $this->includeComponentEntry($relative));

            $this->ensureDir($buildDir . '/modules');
            foreach (self::MODULES as $module) {
                $source = $root . '/modules/' . $module;
                if (is_dir($source)) {
                    $this->zipDirectory($source, $buildDir . '/modules/' . $module . '.zip');
                }
            }

            $this->ensureDir($buildDir . '/plugins');
            foreach (self::PLUGINS as $sourceRelative => $zipName) {
                $source = $root . '/' . $sourceRelative;
                if (is_dir($source)) {
                    $this->zipDirectory($source, $buildDir . '/plugins/' . $zipName);
                }
            }

            $package = $buildDir . '/pkg_jem_v' . $version . '.zip';
            $this->zipPackage($root, $buildDir, $package);
            $this->validate($package);

            $target = $root . '/pkg_jem_v' . $version . '.zip';
            if (is_file($target)) {
                unlink($target);
            }
            if (!rename($package, $target)) {
                throw new RuntimeException('Could not move package to ' . $target);
            }

            return $target;
        } finally {
            $this->removeDir($buildDir);
        }
    }

    private function normalizeRoot(string $root): string
    {
        $real = realpath($root);
        if ($real === false || !is_dir($real)) {
            throw new RuntimeException('Invalid project root: ' . $root);
        }

        return rtrim(str_replace('\\', '/', $real), '/');
    }

    private function readPackageVersion(string $root): string
    {
        $manifest = $root . '/package/pkg_jem.xml';
        if (!is_file($manifest)) {
            throw new RuntimeException('Missing package manifest: ' . $manifest);
        }

        $xml = simplexml_load_file($manifest);
        if (!$xml || empty($xml->version)) {
            throw new RuntimeException('Could not read package version from ' . $manifest);
        }

        return trim((string) $xml->version);
    }

    private function newBuildDir(string $root): string
    {
        $base = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/jem-package-build-' . md5($root);
        $this->removeDir($base);
        $this->ensureDir($base);

        return $base;
    }

    private function zipPackage(string $root, string $buildDir, string $target): void
    {
        $zip = $this->openZip($target);

        foreach (glob($buildDir . '/components/*.zip') ?: [] as $file) {
            $zip->addFile($file, 'packages/' . basename($file));
        }
        foreach (glob($buildDir . '/modules/*.zip') ?: [] as $file) {
            $zip->addFile($file, 'packages/' . basename($file));
        }
        foreach (glob($buildDir . '/plugins/*.zip') ?: [] as $file) {
            $zip->addFile($file, 'packages/' . basename($file));
        }

        $this->addDirectoryToZip($zip, $root . '/package', '', fn (string $relative): bool => $this->includeCommonEntry($relative));
        $zip->close();
    }

    private function zipDirectory(string $source, string $target, ?callable $include = null): void
    {
        $zip = $this->openZip($target);
        $this->addDirectoryToZip($zip, $source, '', $include ?? fn (string $relative): bool => $this->includeCommonEntry($relative));
        $zip->close();
    }

    private function addDirectoryToZip(ZipArchive $zip, string $source, string $prefix, callable $include): void
    {
        $source = rtrim(str_replace('\\', '/', realpath($source) ?: $source), '/');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $path = str_replace('\\', '/', $fileInfo->getPathname());
            $relative = ltrim(substr($path, strlen($source)), '/');
            if ($relative === '' || !$include($relative)) {
                continue;
            }

            $entry = ltrim($prefix . '/' . $relative, '/');
            if ($fileInfo->isDir()) {
                $zip->addEmptyDir($entry);
            } else {
                $zip->addFile($path, $entry);
            }
        }
    }

    private function includeCommonEntry(string $relative): bool
    {
        $relative = str_replace('\\', '/', $relative);
        $basename = basename($relative);

        if ($basename === '' || $basename === '.' || str_starts_with($basename, '.')) {
            return false;
        }

        return !preg_match('#(^|/)(\.DS_Store|Thumbs\.db|desktop\.ini|.*\.(bak|orig|log|tmp)|.*~)$#i', $relative);
    }

    private function includeComponentEntry(string $relative): bool
    {
        $relative = str_replace('\\', '/', $relative);
        $basename = basename($relative);

        if (!$this->includeCommonEntry($relative)) {
            return false;
        }

        if ($basename === 'CODEX_PENDING_DIARY.md' || $basename === 'import-catalog.xml' || $basename === 'import_catalog_jem.xml') {
            return false;
        }

        if (preg_match('#^(\.git|\.settings|\.tmp|\.phpunit\.cache|\.agents|\.claude|\.codex|\.cursor|\.github/copilot|3rd|build|docs|modules|package|plugins|scripts|tests|tools|vendor|_old[^/]*|old[^/]*)(/|$)#', $relative)) {
            return false;
        }

        return !preg_match('#(^|/)(pkg_jem_v.*\.zip.*|update_pkg_.*\.xml|composer\.(json|lock)|phpunit(\.progress)?\.xml(\.dist)?|build\..*|\.env(\..*)?|.*\.(pem|key|crt|pfx)|.*\.code-workspace)$#', $relative);
    }

    private function validate(string $package): void
    {
        $outer = new ZipArchive();
        if ($outer->open($package) !== true) {
            throw new RuntimeException('Could not validate package: ' . $package);
        }

        foreach (['pkg_jem.xml', 'pkg_install.php', 'packages/com_jem.zip', 'packages/mod_jem_types.zip', 'packages/plg_actionlog_jem.zip'] as $entry) {
            if ($outer->locateName($entry) === false) {
                throw new RuntimeException($package . ' is missing ' . $entry);
            }
        }

        $componentData = $outer->getFromName('packages/com_jem.zip');
        $outer->close();

        if ($componentData === false) {
            throw new RuntimeException($package . ' has no component archive');
        }

        $tmpComponent = tempnam(sys_get_temp_dir(), 'jem_component_');
        file_put_contents($tmpComponent, $componentData);

        $component = new ZipArchive();
        if ($component->open($tmpComponent) !== true) {
            @unlink($tmpComponent);
            throw new RuntimeException('Could not validate component archive in ' . $package);
        }

        foreach (['jem.xml', 'script.php', 'admin/jem.php', 'site/jem.php', 'site/classes/icalcreator/autoload.php', 'media/index.html', 'media/vendor/index.html', 'admin/assets/sampledata.zip'] as $entry) {
            if ($component->locateName($entry) === false) {
                $component->close();
                @unlink($tmpComponent);
                throw new RuntimeException($package . ':packages/com_jem.zip is missing ' . $entry);
            }
        }

        foreach (['import-catalog.xml', 'import_catalog_jem.xml', 'composer.json', 'composer.lock', 'vendor/autoload.php'] as $forbidden) {
            if ($component->locateName($forbidden) !== false) {
                $component->close();
                @unlink($tmpComponent);
                throw new RuntimeException($package . ':packages/com_jem.zip contains forbidden entry ' . $forbidden);
            }
        }

        for ($i = 0; $i < $component->numFiles; $i++) {
            $name = $component->getNameIndex($i);
            if (basename($name) !== '' && str_starts_with(basename($name), '.')) {
                $component->close();
                @unlink($tmpComponent);
                throw new RuntimeException($package . ':packages/com_jem.zip contains hidden/development entry ' . $name);
            }
        }

        $component->close();
        @unlink($tmpComponent);
    }

    private function openZip(string $target): ZipArchive
    {
        $this->ensureDir(dirname($target));
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create ZIP: ' . $target);
        }

        return $zip;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create directory: ' . $dir);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($dir);
    }
}

$roots = array_slice($argv, 1);
if (!$roots) {
    $roots = [
        dirname(__DIR__),
        dirname(__DIR__) . '/../JEM-Project-4.5',
    ];
}

$builder = new JemPackageBuilder();
foreach ($roots as $root) {
    echo $builder->build($root) . PHP_EOL;
}
