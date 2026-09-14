<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;

/**
 * Stores large import previews outside the Joomla session in bounded chunks.
 */
class JemImportPreviewHelper
{
    public const PAGE_SIZE = 100;
    public const MAX_USER_PREVIEWS = 3;
    public const PAYLOAD_TTL = 86400;
    public const MAX_PAYLOAD_BYTES = 64 * 1024 * 1024;
    private const PREFIX = "<?php die('Forbidden.'); ?>\n";
    private const PAYLOAD_KEYS = array('records', 'source_records', 'rows');

    /**
     * Store a large preview in private, user-bound chunks.
     *
     * @param   array    $preview  Complete preview payload.
     * @param   integer  $userId   Current user ID.
     * @param   string   $context  Preview context.
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public static function storePreview(array $preview, $userId, $context)
    {
        $context = self::normaliseContext($context);
        $total = count((array) ($preview['rows'] ?? array()));
        $preview['displayed_count'] = $total;
        $preview['total_count'] = $total;

        if (max(
            $total,
            count((array) ($preview['records'] ?? array())),
            count((array) ($preview['source_records'] ?? array()))
        ) <= self::PAGE_SIZE) {
            return $preview;
        }

        $directory = self::getDirectory();

        if (!is_dir($directory) && !self::createDirectory($directory)) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STORAGE_ERROR'));
        }

        self::cleanExpiredPreviews((int) $userId);
        self::enforceUserQuota((int) $userId);

        $token = $context . '-preview-' . (int) $userId . '-' . bin2hex(random_bytes(16));
        $manifest = array(
            'version' => 1,
            'context' => $context,
            'user_id' => (int) $userId,
            'created_at' => time(),
            'counts' => array(),
            'chunks' => array(),
            'bytes' => 0,
        );

        try {
            foreach (self::PAYLOAD_KEYS as $key) {
                $items = array_values((array) ($preview[$key] ?? array()));
                $manifest['counts'][$key] = count($items);
                $manifest['chunks'][$key] = 0;

                $itemCount = count($items);

                for ($offset = 0; $offset < $itemCount; $offset += self::PAGE_SIZE) {
                    $index = (int) ($offset / self::PAGE_SIZE);
                    $chunk = array_slice($items, $offset, self::PAGE_SIZE);
                    $encoded = self::encodePayload($chunk);
                    $manifest['bytes'] += strlen($encoded);

                    if ($manifest['bytes'] > self::MAX_PAYLOAD_BYTES) {
                        throw new RuntimeException(self::message(
                            'COM_JEM_IMPORT_LIMIT_PREVIEW_STORAGE',
                            self::formatMebibytes(self::MAX_PAYLOAD_BYTES)
                        ));
                    }

                    $path = self::getChunkPath($token, $key, $index + 1, (int) $userId);

                    if ($path === '' || !self::writeFile($path, self::PREFIX . $encoded)) {
                        throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STORAGE_ERROR'));
                    }

                    $manifest['chunks'][$key]++;
                }

                $preview[$key] = array();
                unset($items, $chunk);
            }

            $manifestPath = self::getManifestPath($token, (int) $userId);
            $encodedManifest = self::encodePayload($manifest);

            if ($manifestPath === '' || !self::writeFile($manifestPath, self::PREFIX . $encodedManifest)) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STORAGE_ERROR'));
            }
        } catch (Throwable $e) {
            self::deletePreview($token, (int) $userId);

            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STORAGE_ERROR'));
        }

        $preview['payload_token'] = $token;
        $preview['payload_counts'] = $manifest['counts'];
        $preview['total_count'] = $total;

        return self::loadPreviewPage($preview, (int) $userId, 1, self::PAGE_SIZE);
    }

    /**
     * Load one server-side preview page without reading the complete payload.
     *
     * @param   array    $preview   Stored preview metadata.
     * @param   integer  $userId    Current user ID.
     * @param   integer  $page      Requested page.
     * @param   integer  $pageSize  Rows per page.
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public static function loadPreviewPage(array $preview, $userId, $page, $pageSize = self::PAGE_SIZE)
    {
        $token = (string) ($preview['payload_token'] ?? '');
        $manifest = self::readManifest($token, (int) $userId);
        $pageSize = max(1, min(self::PAGE_SIZE, (int) $pageSize));
        $total = (int) ($manifest['counts']['rows'] ?? 0);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = max(1, min($pages, (int) $page));

        if ($pageSize !== self::PAGE_SIZE) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'));
        }

        $preview['records'] = array();
        $preview['rows'] = self::readChunk($token, 'rows', $page, (int) $userId);
        $preview['source_records'] = self::readChunk($token, 'source_records', $page, (int) $userId, true);
        $preview['displayed_count'] = count($preview['rows']);
        $preview['total_count'] = $total;
        $preview['preview_page'] = $page;
        $preview['preview_pages'] = $pages;
        $preview['preview_page_size'] = $pageSize;
        $preview['preview_offset'] = ($page - 1) * $pageSize;
        $preview['server_paginated'] = $pages > 1;
        $preview['preview_limited'] = true;

        return $preview;
    }

    /**
     * Iterate a stored payload one bounded chunk at a time.
     *
     * @param   array    $preview  Stored preview metadata.
     * @param   integer  $userId   Current user ID.
     * @param   string   $key      Payload collection.
     *
     * @return Generator
     *
     * @throws RuntimeException
     */
    public static function getPayloadBatches(array $preview, $userId, $key)
    {
        if (!in_array($key, self::PAYLOAD_KEYS, true)) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'));
        }

        $token = (string) ($preview['payload_token'] ?? '');

        if ($token === '') {
            $items = array_values((array) ($preview[$key] ?? array()));

            foreach (array_chunk($items, self::PAGE_SIZE) as $chunk) {
                yield $chunk;
            }

            return;
        }

        $manifest = self::readManifest($token, (int) $userId);
        $chunks = (int) ($manifest['chunks'][$key] ?? 0);

        for ($number = 1; $number <= $chunks; $number++) {
            yield self::readChunk($token, $key, $number, (int) $userId);
        }
    }

    /**
     * Return the number of items available in one payload collection.
     *
     * @param   array    $preview  Stored preview metadata.
     * @param   integer  $userId   Current user ID.
     * @param   string   $key      Payload collection.
     *
     * @return integer
     */
    public static function getPayloadCount(array $preview, $userId, $key)
    {
        if (empty($preview['payload_token'])) {
            return count((array) ($preview[$key] ?? array()));
        }

        $manifest = self::readManifest((string) $preview['payload_token'], (int) $userId);

        return (int) ($manifest['counts'][$key] ?? 0);
    }

    /**
     * Delete all files belonging to one user-bound preview token.
     *
     * @param   string   $token   Preview token.
     * @param   integer  $userId  Current user ID.
     *
     * @return void
     */
    public static function deletePreview($token, $userId)
    {
        if (!self::isValidToken($token, (int) $userId)) {
            return;
        }

        $directory = self::getDirectory();
        $files = glob($directory . '/' . $token . '-*.php') ?: array();

        foreach ($files as $path) {
            if (is_file($path)) {
                self::deleteFile($path);
            }
        }
    }

    public static function storeVenuePreview(array $preview, $userId)
    {
        return self::storePreview($preview, $userId, 'venues');
    }

    public static function loadPreview(array $preview, $userId)
    {
        foreach (self::PAYLOAD_KEYS as $key) {
            $preview[$key] = array();

            foreach (self::getPayloadBatches($preview, $userId, $key) as $batch) {
                $preview[$key] = array_merge($preview[$key], $batch);
            }
        }

        return $preview;
    }

    public static function loadVenuePreview(array $preview, $userId)
    {
        return self::loadPreview($preview, $userId);
    }

    public static function loadVenuePreviewPage(array $preview, $userId, $page, $pageSize = self::PAGE_SIZE)
    {
        return self::loadPreviewPage($preview, $userId, $page, $pageSize);
    }

    public static function deleteVenuePreview($token, $userId)
    {
        self::deletePreview($token, $userId);
    }

    private static function readManifest($token, $userId)
    {
        $path = self::getManifestPath($token, $userId);
        $manifest = self::readEncodedFile($path);

        if (!is_array($manifest)
            || (int) ($manifest['version'] ?? 0) !== 1
            || (int) ($manifest['user_id'] ?? 0) !== (int) $userId
            || (int) ($manifest['created_at'] ?? 0) < time() - self::PAYLOAD_TTL
            || !isset($manifest['counts'], $manifest['chunks'])) {
            self::deletePreview($token, $userId);
            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'));
        }

        return $manifest;
    }

    private static function readChunk($token, $key, $number, $userId, $optional = false)
    {
        $path = self::getChunkPath($token, $key, $number, $userId);

        if ($optional && ($path === '' || !is_file($path))) {
            return array();
        }

        $chunk = self::readEncodedFile($path);

        if (!is_array($chunk) || count($chunk) > self::PAGE_SIZE) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'));
        }

        return array_values($chunk);
    }

    private static function readEncodedFile($path)
    {
        $content = $path !== '' && is_file($path) ? file_get_contents($path) : false;

        if (!is_string($content)
            || strlen($content) > self::MAX_PAYLOAD_BYTES + strlen(self::PREFIX)
            || !str_starts_with($content, self::PREFIX)) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'));
        }

        try {
            $encoded = substr($content, strlen(self::PREFIX));

            if (strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
                throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'));
            }

            $decoded = json_decode($encoded, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'));
        }

        return $decoded;
    }

    private static function encodePayload(array $payload)
    {
        try {
            return json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STORAGE_ERROR'));
        }
    }

    private static function getManifestPath($token, $userId)
    {
        return self::isValidToken($token, $userId) ? self::getDirectory() . '/' . $token . '-manifest.php' : '';
    }

    private static function getChunkPath($token, $key, $number, $userId)
    {
        if (!self::isValidToken($token, $userId)
            || !in_array($key, self::PAYLOAD_KEYS, true)
            || (int) $number < 1
            || (int) $number > 100000) {
            return '';
        }

        return self::getDirectory() . '/' . $token . '-' . $key . '-' . sprintf('%05d', (int) $number) . '.php';
    }

    private static function isValidToken($token, $userId)
    {
        return preg_match(
            '/^(?:events|venues|specialdays)-preview-' . (int) $userId . '-[a-f0-9]{32}$/',
            trim((string) $token)
        ) === 1;
    }

    private static function normaliseContext($context)
    {
        $context = strtolower(trim((string) $context));

        if (!in_array($context, array('events', 'venues', 'specialdays'), true)) {
            throw new RuntimeException(self::message('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STORAGE_ERROR'));
        }

        return $context;
    }

    private static function cleanExpiredPreviews($userId)
    {
        $directory = self::getDirectory();
        $pattern = $directory . '/*-preview-' . (int) $userId . '-*-manifest.php';

        foreach (glob($pattern) ?: array() as $path) {
            if (!is_file($path) || filemtime($path) >= time() - self::PAYLOAD_TTL) {
                continue;
            }

            $token = substr(basename($path), 0, -strlen('-manifest.php'));
            self::deletePreview($token, $userId);
        }
    }

    private static function enforceUserQuota($userId)
    {
        $directory = self::getDirectory();
        $files = glob($directory . '/*-preview-' . (int) $userId . '-*-manifest.php') ?: array();

        usort($files, static function ($left, $right) {
            return (int) filemtime($left) <=> (int) filemtime($right);
        });

        while (count($files) >= self::MAX_USER_PREVIEWS) {
            $path = array_shift($files);
            $token = substr(basename($path), 0, -strlen('-manifest.php'));
            self::deletePreview($token, $userId);
        }
    }

    private static function getDirectory()
    {
        return JPATH_CACHE . '/com_jem';
    }

    private static function formatMebibytes($bytes)
    {
        return rtrim(rtrim(number_format(((int) $bytes) / 1048576, 2, '.', ''), '0'), '.');
    }

    private static function createDirectory($directory)
    {
        return class_exists(Folder::class) ? Folder::create($directory) : mkdir($directory, 0755, true);
    }

    private static function writeFile($path, $content)
    {
        return class_exists(File::class) ? File::write($path, $content) : file_put_contents($path, $content) !== false;
    }

    private static function deleteFile($path)
    {
        return class_exists(File::class) ? File::delete($path) : unlink($path);
    }

    private static function message($key, ...$arguments)
    {
        if (class_exists(Text::class)) {
            return $arguments ? Text::sprintf($key, ...$arguments) : Text::_($key);
        }

        return $arguments ? $key . ': ' . implode(', ', $arguments) : $key;
    }
}
