<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LanguageKeysTest extends TestCase
{
    /**
     * These keys were already missing when the test suite was introduced.
     * Keep the list explicit so new missing keys still fail the build.
     */
    private const KNOWN_MISSING = array(
        'admin' => array(
            'COM_JEM_EDITEVENT_FIELD_INVITED_USERS',
            'COM_JEM_EDITEVENT_FIELD_INVITED_USERS_DESC',
            'COM_JEM_EVENT_ATTRIBS_FIELDSET_LABEL',
            'COM_JEM_EVENT_FIELD_SHOW_CATEGORY_DESC',
            'COM_JEM_EVENT_FIELD_SHOW_VENUE_NAME_DESC',
            'COM_JEM_FIELD_CREATED_BY_ALIAS_DESC',
            'COM_JEM_FIELD_CREATED_BY_ALIAS_LABEL',
            'COM_JEM_GD_VERSION_DESC',
            'COM_JEM_GLOBAL_PARAMETERS_RECURRENCE',
            'COM_JEM_IMAGESELECT',
        ),
        'site' => array(
            'COM_JEM_EDITEVENT_FIELD_EDITED_AT',
            'COM_JEM_EDITEVENT_FIELD_IMAGESELECT',
            'COM_JEM_EDITEVENT_FIELD_IMAGESELECT_DESC',
            'COM_JEM_EDITEVENT_FIELD_RECURRING_EVENTS',
            'COM_JEM_EDITEVENT_FIELD_REVISED',
            'COM_JEM_EDITVENUE_FIELD_IMAGESELECT',
            'COM_JEM_EDITVENUE_FIELD_IMAGESELECT_DESC',
            'COM_JEM_FIELD_CREATED_BY_DESC',
            'COM_JEM_FIELD_CREATED_BY_LABEL',
            'COM_JEM_FIELD_MODIFIED_DESC',
            'COM_JEM_FIELD_VERSION_DESC',
            'COM_JEM_FIELD_VERSION_LABEL',
            'COM_JEM_FIELDSET_PUBLISHING',
            'COM_JEM_LONGITUDE_DESC',
            'COM_JEM_PUBLISHED',
            'COM_JEM_RESERVED_PLACES_DESC',
        ),
    );

    public function testAdminFormLanguageKeysExistOrAreInBaseline(): void
    {
        $this->assertFormLanguageKeysAreCovered(
            'admin',
            JEM_TEST_ROOT . '/admin/models/forms',
            JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini'
        );
    }

    public function testSiteFormLanguageKeysExistOrAreInBaseline(): void
    {
        $this->assertFormLanguageKeysAreCovered(
            'site',
            JEM_TEST_ROOT . '/site/models/forms',
            JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini'
        );
    }

    private function assertFormLanguageKeysAreCovered(string $scope, string $formsPath, string $languageFile): void
    {
        $defined = $this->readLanguageKeys($languageFile);
        $used = $this->readFormLanguageKeys($formsPath);
        $knownMissing = array_flip(self::KNOWN_MISSING[$scope]);

        $missing = array_values(array_filter(
            $used,
            static fn (string $key): bool => !isset($defined[$key]) && !isset($knownMissing[$key])
        ));

        sort($missing);

        self::assertSame(
            array(),
            $missing,
            'New missing ' . $scope . " language keys:\n" . implode("\n", $missing)
        );
    }

    /**
     * @return array<string, true>
     */
    private function readLanguageKeys(string $path): array
    {
        self::assertFileExists($path);

        preg_match_all('/^([A-Z0-9_]+)=/m', (string) file_get_contents($path), $matches);

        return array_fill_keys($matches[1], true);
    }

    /**
     * @return list<string>
     */
    private function readFormLanguageKeys(string $formsPath): array
    {
        $keys = array();
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($formsPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'xml') {
                continue;
            }

            $xml = new DOMDocument();
            $xml->load($file->getPathname());
            $xpath = new DOMXPath($xml);

            foreach ($xpath->query('//*[@label or @description or @hint or @message]') ?: array() as $node) {
                foreach (array('label', 'description', 'hint', 'message') as $attribute) {
                    $value = $node instanceof DOMElement ? $node->getAttribute($attribute) : '';

                    if (str_starts_with($value, 'COM_JEM_')) {
                        $keys[$value] = true;
                    }
                }
            }

            foreach ($xpath->query('//option') ?: array() as $option) {
                $value = trim($option->textContent);

                if (str_starts_with($value, 'COM_JEM_')) {
                    $keys[$value] = true;
                }
            }
        }

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }
}
