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
 * Stores large import previews outside the Joomla session.
 */
class JemImportPreviewHelper
{
    public const PAGE_SIZE = 100;
    private const PREFIX = "<?php die('Forbidden.'); ?>\n";

    public static function storeVenuePreview(array $preview, $userId)
    {
        $sourceRecords = (array) ($preview['source_records'] ?? array());

        if (count($sourceRecords) <= self::PAGE_SIZE) {
            $preview['displayed_count'] = count((array) ($preview['rows'] ?? array()));
            $preview['total_count'] = count((array) ($preview['rows'] ?? array()));
            return $preview;
        }

        $directory = self::getDirectory();

        if (!is_dir($directory) && !Folder::create($directory)) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STORAGE_ERROR'));
        }

        $token = 'venue-preview-' . (int) $userId . '-' . bin2hex(random_bytes(16)) . '.php';
        $payload = array(
            'records' => (array) ($preview['records'] ?? array()),
            'source_records' => $sourceRecords,
            'rows' => (array) ($preview['rows'] ?? array()),
        );
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false || !File::write($directory . '/' . $token, self::PREFIX . $encoded)) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STORAGE_ERROR'));
        }

        $preview['payload_token'] = $token;
        $preview['total_count'] = count($payload['rows']);

        return self::applyPage($preview, $payload, 1, self::PAGE_SIZE);
    }

    public static function loadVenuePreview(array $preview, $userId)
    {
        $payload = self::readPayload((string) ($preview['payload_token'] ?? ''), $userId);

        if ($payload === null) {
            return $preview;
        }

        $preview['records'] = $payload['records'];
        $preview['source_records'] = $payload['source_records'];
        $preview['rows'] = $payload['rows'];
        $preview['total_count'] = count($payload['rows']);

        return $preview;
    }

    public static function loadVenuePreviewPage(array $preview, $userId, $page, $pageSize = self::PAGE_SIZE)
    {
        $payload = self::readPayload((string) ($preview['payload_token'] ?? ''), $userId);

        if ($payload === null) {
            return $preview;
        }

        return self::applyPage($preview, $payload, $page, $pageSize);
    }

    public static function deleteVenuePreview($token, $userId)
    {
        $path = self::getPayloadPath($token, $userId);

        if ($path !== '' && is_file($path)) {
            File::delete($path);
        }
    }

    private static function applyPage(array $preview, array $payload, $page, $pageSize)
    {
        $pageSize = max(1, min(500, (int) $pageSize));
        $total = count($payload['rows']);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = max(1, min($pages, (int) $page));
        $offset = ($page - 1) * $pageSize;

        $preview['records'] = array();
        $preview['rows'] = array_slice($payload['rows'], $offset, $pageSize);
        $preview['source_records'] = array_slice($payload['source_records'], $offset, $pageSize);
        $preview['displayed_count'] = count($preview['rows']);
        $preview['total_count'] = $total;
        $preview['preview_page'] = $page;
        $preview['preview_pages'] = $pages;
        $preview['preview_page_size'] = $pageSize;
        $preview['preview_offset'] = $offset;
        $preview['server_paginated'] = $pages > 1;

        return $preview;
    }

    private static function readPayload($token, $userId)
    {
        if (trim((string) $token) === '') {
            return null;
        }

        $path = self::getPayloadPath($token, $userId);
        $content = $path !== '' && is_file($path) ? file_get_contents($path) : false;

        if (!is_string($content) || !str_starts_with($content, self::PREFIX)) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'));
        }

        $payload = json_decode(substr($content, strlen(self::PREFIX)), true);

        if (!is_array($payload) || !isset($payload['records'], $payload['source_records'], $payload['rows'])) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAYLOAD_MISSING'));
        }

        $payload['records'] = (array) $payload['records'];
        $payload['source_records'] = (array) $payload['source_records'];
        $payload['rows'] = (array) $payload['rows'];

        return $payload;
    }

    private static function getPayloadPath($token, $userId)
    {
        $token = trim((string) $token);

        if (!preg_match('/^venue-preview-' . (int) $userId . '-[a-f0-9]{32}\.php$/', $token)) {
            return '';
        }

        return self::getDirectory() . '/' . $token;
    }

    private static function getDirectory()
    {
        return JPATH_CACHE . '/com_jem';
    }
}
