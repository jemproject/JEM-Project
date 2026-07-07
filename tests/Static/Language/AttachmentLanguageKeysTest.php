<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AttachmentLanguageKeysTest extends TestCase
{
    private const REQUIRED_ADMIN_KEYS = array(
        'COM_JEM_EVENT_ATTACHMENTS_LAYOUT' => 'Display attachments as',
        'COM_JEM_EVENT_ATTACHMENTS_LAYOUT_DESC' => 'Override the global attachment layout for this event.',
        'COM_JEM_EVENT_ATTACHMENTS_ICON_SIZE_DESC' => 'Override the global attachment icon setting for this event.',
        'COM_JEM_EVENT_ATTACHMENTS_FRAME' => 'Show as button',
        'COM_JEM_EVENT_ATTACHMENTS_FRAME_DESC' => 'Display attachments with a button-style frame.',
        'COM_JEM_SETTINGS_ATTACHEMENT_LAYOUT' => 'Attachment layout',
        'COM_JEM_SETTINGS_ATTACHEMENT_ICON_SIZE' => 'Show file icon',
        'COM_JEM_SETTINGS_ATTACHEMENT_ICON_SIZE_NORMAL' => 'Yes, normal icons',
        'COM_JEM_SETTINGS_ATTACHEMENT_ICON_SIZE_MEDIUM' => 'Yes, medium icons',
        'COM_JEM_SETTINGS_ATTACHEMENT_ICON_SIZE_LARGE' => 'Yes, large icons',
    );

    private const REQUIRED_SITE_KEYS = array(
        'COM_JEM_EVENT_ATTACHMENTS_LAYOUT' => 'Display attachments as',
        'COM_JEM_EVENT_ATTACHMENTS_LAYOUT_DESC' => 'Override the global attachment layout for this event.',
        'COM_JEM_EVENT_ATTACHMENTS_ICON_SIZE_DESC' => 'Override the global attachment icon setting for this event.',
        'COM_JEM_EVENT_ATTACHMENTS_FRAME' => 'Show as button',
        'COM_JEM_EVENT_ATTACHMENTS_FRAME_DESC' => 'Display attachments with a button-style frame.',
        'COM_JEM_SETTINGS_ATTACHEMENT_ICON_SIZE' => 'Show file icon',
        'COM_JEM_SETTINGS_ATTACHEMENT_ICON_SIZE_NORMAL' => 'Yes, normal icons',
        'COM_JEM_SETTINGS_ATTACHEMENT_ICON_SIZE_MEDIUM' => 'Yes, medium icons',
        'COM_JEM_SETTINGS_ATTACHEMENT_ICON_SIZE_LARGE' => 'Yes, large icons',
    );

    public function testAdminAttachmentLanguageKeysKeepExpectedText(): void
    {
        $this->assertLanguageValues(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', self::REQUIRED_ADMIN_KEYS);
    }

    public function testSiteAttachmentLanguageKeysKeepExpectedText(): void
    {
        $this->assertLanguageValues(JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini', self::REQUIRED_SITE_KEYS);
    }

    /**
     * @param array<string, string> $expected
     */
    private function assertLanguageValues(string $path, array $expected): void
    {
        $values = $this->readLanguageValues($path);

        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $values, $key . ' is missing from ' . $this->relativePath($path));
            self::assertSame($value, $values[$key], $key . ' changed unexpectedly in ' . $this->relativePath($path));
        }
    }

    /**
     * @return array<string, string>
     */
    private function readLanguageValues(string $path): array
    {
        self::assertFileExists($path);

        $values = array();
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: array();

        foreach ($lines as $line) {
            if (!preg_match('/^([A-Z0-9_]+)="(.*)"$/', $line, $match)) {
                continue;
            }

            $values[$match[1]] = str_replace('""', '"', $match[2]);
        }

        return $values;
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
