<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/imagemetadata.class.php';

final class ImageCreditLabelTextStub
{
    public static function _(string $key): string
    {
        $strings = parse_ini_file(JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);

        return $strings[$key] ?? $key;
    }
}

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ImageCreditLabelTest extends TestCase
{
    protected function setUp(): void
    {
        class_alias(ImageCreditLabelTextStub::class, 'Joomla\CMS\Language\Text');
    }

    public function testAllLabelsRenderConsistentlyInBothPositionsAndLightbox(): void
    {
        foreach ([
            'none' => '', 'photo' => 'Photo:', 'image_credit' => 'Image credit:',
            'source' => 'Source:', 'copyright' => 'Copyright:', 'licence' => 'Licence:',
            'custom' => 'Courtesy of:',
        ] as $mode => $label) {
            $metadata = ['credit_label' => $mode, 'credit_label_custom' => 'Courtesy of:',
                'credit' => '<a href="https://example.org">Author</a>', 'footer' => true, 'image' => 'photo.jpg'];
            $credit = JemImageMetadata::creditHtml($metadata['credit'], $metadata);
            self::assertSame($label !== '', str_contains($credit, 'jem-image-credit__label'));
            if ($label !== '') {
                self::assertStringContainsString('>' . $label . '</span>', $credit);
            }
            self::assertStringContainsString('href="https://example.org"', $credit);
            self::assertStringContainsString($credit, JemImageMetadata::figure('<img>', '', 'event', $metadata['credit'], 'left', $metadata));
            self::assertStringContainsString($credit, html_entity_decode(JemImageMetadata::lightboxTitle('Title', '', $metadata['credit'], $metadata), ENT_QUOTES));
            foreach (['event', 'category', 'venue'] as $context) {
                $slot = JemImageMetadata::slots($context)[0];
                $record = (object) [$slot => 'photo.jpg', 'image_metadata' => [$slot => $metadata]];
                self::assertStringContainsString($credit, JemImageMetadata::footer($record, ['original' => 'photo.jpg'], $context));
            }
        }
    }

    public function testDefaultsApplyToExistingBetaMetadataAndInvalidModes(): void
    {
        foreach ([null, '', [], '<script>', 'UNKNOWN', 12] as $invalid) {
            self::assertSame('image_credit', JemImageMetadata::creditLabelMode($invalid));
            self::assertStringContainsString('Image credit:', JemImageMetadata::creditHtml('Author', ['credit_label' => $invalid]));
        }
        self::assertStringContainsString('Image credit:', JemImageMetadata::creditHtml('Already saved author'));
        self::assertSame('', JemImageMetadata::creditHtml('', ['credit_label' => 'photo']));
        self::assertSame('', JemImageMetadata::creditHtml('<br>', ['credit_label' => 'photo']));
        self::assertSame('Author', JemImageMetadata::creditHtml('Author', ['credit_label' => 'custom']));
    }

    public function testCustomLabelIsPlainBoundedEscapedText(): void
    {
        $label = JemImageMetadata::customCreditLabel('<img src=x onerror=alert(1)>Photo & source:' . "\n");
        self::assertSame('Photo & source:', $label);
        self::assertSame(80, mb_strlen(JemImageMetadata::customCreditLabel(str_repeat('é', 100))));
        self::assertSame('', JemImageMetadata::customCreditLabel(['unsafe']));
        $html = JemImageMetadata::creditHtml('<a href="javascript:alert(1)">Author</a>', [
            'credit_label' => 'custom', 'credit_label_custom' => $label,
        ]);
        self::assertStringContainsString('Photo &amp; source:', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testEditingPolicyRoundTripAndClearForAllSlots(): void
    {
        foreach (['event', 'category', 'venue'] as $context) {
            foreach (JemImageMetadata::slots($context) as $slot) {
                $stored = [$slot => ['image' => 'photo.jpg', 'credit' => 'Author', 'credit_label' => 'photo']];
                $input = [$slot => ['credit_label' => 'custom', 'credit_label_custom' => '<b>Courtesy:</b>']];
                self::assertSame('photo', JemImageMetadata::merge($stored, $input, $context, false)[$slot]['credit_label']);
                $updated = JemImageMetadata::merge($stored, $input, $context, true);
                self::assertSame('custom', $updated[$slot]['credit_label']);
                self::assertSame('Courtesy:', $updated[$slot]['credit_label_custom']);
                self::assertSame($updated, JemImageMetadata::normalise(json_encode($updated), $context));
                $reset = JemImageMetadata::merge($updated, [$slot => ['credit_label' => 'image_credit']], $context, true);
                self::assertArrayNotHasKey('credit_label_custom', $reset[$slot]);
                self::assertArrayNotHasKey('credit_label', $reset[$slot]);
                $cleared = JemImageMetadata::forImages($updated, (object) [$slot => ''], null, $context);
                self::assertSame(['alt' => '', 'caption' => '', 'image' => ''], $cleared[$slot]);
            }
        }
    }
}
