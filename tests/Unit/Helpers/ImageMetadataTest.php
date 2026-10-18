<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/imagemetadata.class.php';

final class ImageMetadataFooterTextStub
{
    public static function _(string $key): string
    {
        return '<Image credit>';
    }
}

final class ImageMetadataTest extends TestCase
{
    public function testRemovingOneImageClearsItsMetadataWithoutTouchingOtherSlots(): void
    {
        foreach (['event', 'venue', 'category'] as $context) {
            $metadata = [];
            $record = new stdClass();
            foreach (JemImageMetadata::slots($context) as $slot) {
                $record->$slot = $slot . '.jpg';
                $metadata[$slot] = ['image' => $slot . '.jpg', 'alt' => 'Alt', 'caption' => 'Caption',
                    'credit' => 'Credit', 'align' => 'right', 'footer' => true];
            }
            $stored = clone $record;
            $stored->image_metadata = $metadata;
            $removed = JemImageMetadata::slots($context)[0];
            $record->$removed = '';
            $result = JemImageMetadata::forImages($metadata, $record, $stored, $context);
            self::assertSame(['alt' => '', 'caption' => '', 'image' => ''], $result[$removed]);
            foreach (array_diff(JemImageMetadata::slots($context), [$removed]) as $other) {
                self::assertEquals($metadata[$other], $result[$other]);
            }
        }
    }

    public function testCaptionKeepsLimitedFormattingAndUnicodeCredits(): void
    {
        $html = 'Foto: <strong>María</strong><br><a href="https://example.org/photo?x=1&amp;y=2" target="_blank" onclick="bad()">Fuente</a>';
        self::assertSame('Foto: <strong>María</strong><br><a href="https://example.org/photo?x=1&amp;y=2">Fuente</a>', JemImageMetadata::caption($html));
        self::assertSame(JemImageMetadata::caption($html), JemImageMetadata::caption(JemImageMetadata::caption($html)));
    }

    public function testCaptionRejectsActiveContentAndUnsafeLinks(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,hello', '//example.org', 'file:///secret', "https://example.org/\nunsafe"] as $url) {
            self::assertSame('Credit', JemImageMetadata::caption('<a href="' . $url . '">Credit</a>'));
        }
        self::assertSame('Photo', JemImageMetadata::caption('<script>alert(1)</script><svg onload="bad()">bad</svg><img src=x onerror=bad()>Photo'));
        self::assertSame('', JemImageMetadata::caption('<br><strong> </strong>'));
        self::assertSame('', JemImageMetadata::caption(['unexpected']));
        self::assertSame('Name', JemImageMetadata::alt('<b>Name</b>'));
        self::assertSame(255, mb_strlen(JemImageMetadata::alt(str_repeat('é', 300))));
    }

    public function testEssentialPreservesCreditsAndRejectsForgedCaptionChanges(): void
    {
        $stored = ['datimage' => ['alt' => 'Old alt', 'caption' => 'Original credit', 'image' => 'photo.jpg']];
        $input = ['datimage' => ['alt' => 'New alt', 'caption' => 'Forged', 'image' => 'forged.jpg']];
        $result = JemImageMetadata::merge($stored, $input, 'event', false);
        self::assertSame('Original credit', $result['datimage']['caption']);
        self::assertSame('New alt', $result['datimage']['alt']);
        self::assertSame('photo.jpg', $result['datimage']['image']);
        self::assertSame('Original credit', JemImageMetadata::merge($stored, [], 'event', true)['datimage']['caption']);
    }

    public function testAdvancedCanEditOrExplicitlyClearEachSlotIndependently(): void
    {
        $stored = ['datimage' => ['caption' => 'Intro'], 'fullimage' => ['caption' => 'Full']];
        $result = JemImageMetadata::merge($stored, ['datimage' => ['caption' => '']], 'event', true);
        self::assertSame('', $result['datimage']['caption']);
        self::assertSame('Full', $result['fullimage']['caption']);
    }

    public function testReplacementClearsUneditedCreditsAndRemovalClearsEverything(): void
    {
        $metadata = ['datimage' => ['alt' => 'Old alt', 'caption' => 'Old credit', 'image' => 'old.jpg']];
        $stored = (object) ['datimage' => 'old.jpg', 'image_metadata' => $metadata];
        $result = JemImageMetadata::forImages($metadata, (object) ['datimage' => 'new.jpg'], $stored, 'event');
        self::assertSame(['alt' => '', 'caption' => '', 'image' => 'new.jpg'], $result['datimage']);
        $metadata['datimage']['caption'] = 'New credit';
        self::assertSame('New credit', JemImageMetadata::forImages($metadata, (object) ['datimage' => 'new.jpg'], $stored, 'event')['datimage']['caption']);
        self::assertSame(['alt' => '', 'caption' => '', 'image' => ''], JemImageMetadata::forImages($metadata, (object) ['datimage' => ''], $stored, 'event')['datimage']);
    }

    public function testCopiesAndFolderMovesKeepMetadata(): void
    {
        $metadata = ['datimage' => ['alt' => 'Alt', 'caption' => 'Credit', 'image' => 'a.jpg']];
        $record = (object) ['datimage' => 'a.jpg', 'image_path' => 'new-folder'];
        $stored = (object) ['datimage' => 'a.jpg', 'image_metadata' => $metadata, 'image_path' => 'old-folder'];
        self::assertSame('Credit', JemImageMetadata::forImages($metadata, $record, $stored, 'event')['datimage']['caption']);
        self::assertSame('Credit', JemImageMetadata::forImages($metadata, $record, null, 'event')['datimage']['caption']);
    }

    public function testFullImageFallbackAndJoinedVenueUseTheCorrectMetadata(): void
    {
        $record = (object) [
            'datimage' => 'intro.jpg', 'fullimage' => '', 'locid' => 9, 'locimage' => 'venue.jpg',
            'image_metadata' => ['datimage' => ['caption' => 'Intro'], 'fullimage' => ['caption' => 'Full']],
            'venue_image_metadata' => ['locimage' => ['caption' => 'Venue']],
        ];
        self::assertSame('Intro', JemImageMetadata::resolve($record, 'event', 'fullimage')['caption']);
        $record->fullimage = 'full.jpg';
        self::assertSame('Full', JemImageMetadata::resolve($record, 'event', 'fullimage')['caption']);
        self::assertSame('Venue', JemImageMetadata::resolve($record, 'venue')['caption']);
        self::assertSame('Legacy alt', JemImageMetadata::resolve((object) ['locimage_alt' => 'Legacy alt'], 'venue')['alt']);
    }

    public function testMismatchedFilenameNeverUsesStaleMetadata(): void
    {
        $record = (object) ['datimage' => 'new.jpg', 'image_metadata' => ['datimage' => ['image' => 'old.jpg', 'caption' => 'Wrong credit']]];
        self::assertSame('', JemImageMetadata::resolve($record, 'event')['caption']);
    }

    public function testCategoryDefaultCopiesMetadataAsSnapshot(): void
    {
        $event = ['datimage' => 'category_original.jpg'];
        $category = (object) ['image_metadata' => ['image' => ['alt' => 'Alt', 'caption' => 'Category credit', 'image' => 'original.jpg']]];
        JemImageMetadata::inheritCategory($event, $category);
        $metadata = json_decode($event['image_metadata'], true);
        self::assertSame('Category credit', $metadata['datimage']['caption']);
        self::assertSame('category_original.jpg', $metadata['datimage']['image']);
        self::assertSame('', $metadata['fullimage']['caption']);
    }

    public function testEmptyCaptionDoesNotChangeMarkupAndCreditsUseSemanticWrapper(): void
    {
        self::assertSame('<img>', JemImageMetadata::figure('<img>', '', 'event'));
        self::assertSame('', JemImageMetadata::figure('', 'Credit', 'event'));
        self::assertStringContainsString('<span class="jem-image-caption__text">Credit</span>', JemImageMetadata::figure('<img>', 'Credit', 'venue'));
    }

    public function testEscapedCaptionsFitTheDatabaseTextColumn(): void
    {
        $entry = ['caption' => str_repeat('"', 9000), 'alt' => str_repeat('"', 255)];
        $metadata = JemImageMetadata::normalise(['datimage' => $entry, 'fullimage' => $entry], 'event');
        self::assertLessThan(65535, strlen(json_encode($metadata)));
        self::assertStringNotContainsString('"', $metadata['datimage']['caption']);
        self::assertSame($metadata, JemImageMetadata::normalise($metadata, 'event'));
    }

    public function testLightboxTitleEscapesBothHtmlParsingContexts(): void
    {
        $title = 'Event: <img src=x onerror=alert(1)> " &lt;literal&gt;';
        $caption = 'Photo: <a href="https://example.org/?x=1&amp;y=2" onclick="bad()">Author</a><script>bad()</script>';
        $attribute = JemImageMetadata::lightboxTitle($title, $caption);
        // Browser first parses the data-title attribute, then lightbox inserts HTML.
        $outer = new DOMDocument();
        $outer->loadHTML('<a data-title="' . $attribute . '">Open</a>');
        $html = $outer->getElementsByTagName('a')->item(0)->getAttribute('data-title');
        $inner = new DOMDocument();
        $inner->loadHTML('<div>' . $html . '</div>');
        self::assertSame(0, $inner->getElementsByTagName('img')->length);
        self::assertSame(0, $inner->getElementsByTagName('script')->length);
        self::assertStringContainsString($title, $inner->textContent);
        self::assertSame('jem-lightbox-caption', $inner->getElementsByTagName('span')->item(0)->getAttribute('class'));
        self::assertSame('https://example.org/?x=1&y=2', $inner->getElementsByTagName('a')->item(0)->getAttribute('href'));
        self::assertFalse($inner->getElementsByTagName('a')->item(0)->hasAttribute('onclick'));
    }

    public function testEmptyLightboxCaptionDoesNotAddASecondLine(): void
    {
        self::assertSame('Event: Plain title', JemImageMetadata::lightboxTitle('Event: Plain title', ''));
        self::assertStringNotContainsString('jem-lightbox-caption', JemImageMetadata::lightboxTitle('Title', '<br>'));
    }

    public function testFooterPreferenceIsStrictAndPreservedInEssential(): void
    {
        $stored = ['datimage' => ['caption' => 'Description', 'credit' => 'Photo credit', 'footer' => true]];
        $forged = ['datimage' => ['caption' => 'Changed', 'credit' => 'Forged credit', 'footer' => '0']];
        $essential = JemImageMetadata::merge($stored, $forged, 'event', false);
        self::assertTrue($essential['datimage']['footer']);
        self::assertSame('Description', $essential['datimage']['caption']);
        self::assertSame('Photo credit', $essential['datimage']['credit']);
        self::assertArrayNotHasKey('footer', JemImageMetadata::merge($stored, $forged, 'event', true)['datimage']);
        foreach (['yes', 'true', [], 2] as $invalid) {
            self::assertArrayNotHasKey('footer', JemImageMetadata::normalise(['image' => ['footer' => $invalid]], 'category')['image']);
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testFooterRequiresDisplayedImageAndNonEmptyMatchingCaption(): void
    {
        // Unit tests have no Joomla runtime; keep the translation stub isolated.
        if (!class_exists('Joomla\\CMS\\Language\\Text')) {
            class_alias(ImageMetadataFooterTextStub::class, 'Joomla\\CMS\\Language\\Text');
        }
        $record = (object) ['image' => 'photo.jpg', 'image_metadata' => ['image' => ['caption' => 'Description', 'credit' => 'Credit', 'footer' => true, 'image' => 'photo.jpg']]];
        $image = ['original' => 'images/photo.jpg'];
        self::assertSame('', JemImageMetadata::footer($record, [], 'category'));
        self::assertStringContainsString('class="jem-image-credit__label"', JemImageMetadata::footer($record, $image, 'category'));
        self::assertStringContainsString('</span> Credit</div>', JemImageMetadata::footer($record, $image, 'category'));
        self::assertStringContainsString('&lt;Image credit&gt;', JemImageMetadata::footer($record, $image, 'category'));
        $record->image = 'other.jpg';
        self::assertSame('', JemImageMetadata::footer($record, $image, 'category'));
        $record->image = 'photo.jpg';
        $record->image_metadata['image']['credit'] = '';
        self::assertSame('', JemImageMetadata::footer($record, $image, 'category'));
    }

    public function testExistingCombinedCaptionsAreNotAutomaticallySplit(): void
    {
        $metadata = JemImageMetadata::normalise(['image' => ['caption' => 'Auditorium. Photo: Alice.']], 'category');
        self::assertSame('Auditorium. Photo: Alice.', $metadata['image']['caption']);
        self::assertArrayNotHasKey('credit', $metadata['image']);
    }

    public function testFourCanonicalTextValuesStayInsideTheTextColumn(): void
    {
        foreach ([str_repeat("\x01", 4096), str_repeat('"', 4096), str_repeat("\n\\", 2048)] as $text) {
            $entry = ['caption' => $text, 'credit' => $text, 'alt' => str_repeat('"', 255), 'footer' => true];
            $metadata = JemImageMetadata::normalise(['datimage' => $entry, 'fullimage' => $entry], 'event');
            self::assertLessThan(65535, strlen(json_encode($metadata)));
            self::assertSame($metadata, JemImageMetadata::normalise($metadata, 'event'));
        }
    }

    public function testCaptionAlignmentIsAllowlistedAndDefaultsToLeft(): void
    {
        $stored = ['image' => ['caption' => 'Description', 'align' => 'right']];
        self::assertSame('right', JemImageMetadata::merge($stored, ['image' => ['align' => 'center']], 'category', false)['image']['align']);
        self::assertSame('center', JemImageMetadata::merge($stored, ['image' => ['align' => 'center']], 'category', true)['image']['align']);
        self::assertArrayNotHasKey('align', JemImageMetadata::merge($stored, ['image' => ['align' => 'left']], 'category', true)['image']);
        foreach (['justify', '<script>', [], 'RIGHT'] as $invalid) {
            self::assertArrayNotHasKey('align', JemImageMetadata::normalise(['image' => ['align' => $invalid]], 'category')['image']);
        }
        self::assertStringContainsString('jem-image-caption__text--center', JemImageMetadata::figure('<img>', 'Text', 'event', 'Credit', 'center'));
        self::assertStringNotContainsString('jem-image-caption__text--', JemImageMetadata::figure('<img>', 'Text', 'event', '', 'unsafe'));
    }
}
