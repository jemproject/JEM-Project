<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

/** Compensating filesystem journal: never overwrite or remove pre-existing files. */
final class JemSampleDataFiles
{
    private array $files = array();
    private array $directories = array();
    private array $roots;

    public function __construct(array $roots)
    {
        $this->roots = array_map(static function ($root) {
            return rtrim(str_replace('\\', '/', $root), '/');
        }, $roots);
    }

    private function checkedPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (in_array('..', explode('/', $path), true) || in_array('.', explode('/', $path), true)) {
            throw new RuntimeException('Invalid sample media path.');
        }
        foreach ($this->roots as $root) {
            if ($path !== $root && strpos($path, $root . '/') !== 0) {
                continue;
            }
            for ($parent = $path; $parent !== dirname($parent); $parent = dirname($parent)) {
                if (is_link($parent)) {
                    throw new RuntimeException('Sample media paths cannot use symbolic links.');
                }
            }
            return $path;
        }
        throw new RuntimeException('Sample media path is outside the configured folders.');
    }

    private function ensureDirectory(string $path): void
    {
        $path = $this->checkedPath($path);
        if (is_dir($path)) {
            return;
        }
        if (file_exists($path)) {
            throw new RuntimeException('A file blocks the sample media folder: ' . $path);
        }
        if (!is_dir(dirname($path))) {
            $this->ensureDirectory(dirname($path));
        }
        if (!@mkdir($path, 0777)) {
            throw new RuntimeException('Could not create sample media folder: ' . $path);
        }
        $this->directories[] = $path;
    }

    public function copy(string $source, string $target): void
    {
        $target = $this->checkedPath($target);
        if (!is_file($source) || !is_readable($source)) {
            throw new RuntimeException('Could not read sample media: ' . $source);
        }
        if (file_exists($target)) {
            $sourceHash = hash_file('sha256', $source);
            if (is_file($target) && $sourceHash !== false && $sourceHash === hash_file('sha256', $target)) {
                return;
            }
            throw new RuntimeException('An existing file conflicts with sample media: ' . $target);
        }
        $this->ensureDirectory(dirname($target));
        $input = @fopen($source, 'rb');
        if ($input === false) {
            throw new RuntimeException('Could not open sample media: ' . $source);
        }
        try {
            $output = @fopen($target, 'xb');
            if ($output === false) {
                throw new RuntimeException('Could not create sample media: ' . $target);
            }
            $this->files[] = $target;
            try {
                $copied = stream_copy_to_stream($input, $output);
                if ($copied === false || $copied !== filesize($source) || !fflush($output)) {
                    throw new RuntimeException('Could not finish copying sample media: ' . $target);
                }
            } finally {
                fclose($output);
            }
        } finally {
            fclose($input);
        }
    }

    public function generate(string $target, callable $generator): void
    {
        $target = $this->checkedPath($target);
        if (is_file($target)) {
            return;
        }
        $this->ensureDirectory(dirname($target));
        // The image library chooses its output format from the extension.
        $temporary = dirname($target) . '/jem_sample_' . bin2hex(random_bytes(16))
            . '.' . pathinfo($target, PATHINFO_EXTENSION);
        $reservation = @fopen($temporary, 'xb');
        if ($reservation === false) {
            throw new RuntimeException('Could not reserve a sample thumbnail.');
        }
        fclose($reservation);
        $this->files[] = $temporary;
        $generated = $generator($temporary);
        clearstatcache(true, $temporary);
        if ($generated === false || !is_file($temporary) || filesize($temporary) === 0) {
            throw new RuntimeException('Could not generate sample thumbnail: ' . $target);
        }
        $this->copy($temporary, $target);
        if (!@unlink($temporary)) {
            throw new RuntimeException('Could not remove staged sample thumbnail.');
        }
    }

    /** Returns false if any cleanup failed; never hides an incomplete recovery. */
    public function rollback(): bool
    {
        $complete = true;
        foreach (array_reverse($this->files) as $file) {
            try {
                $this->checkedPath($file);
                if (file_exists($file) && !@unlink($file)) {
                    $complete = false;
                }
            } catch (Throwable $error) {
                $complete = false;
            }
        }
        foreach (array_reverse($this->directories) as $directory) {
            try {
                $this->checkedPath($directory);
                if (is_dir($directory) && !@rmdir($directory)) {
                    $complete = false;
                }
            } catch (Throwable $error) {
                $complete = false;
            }
        }
        return $complete;
    }
}
