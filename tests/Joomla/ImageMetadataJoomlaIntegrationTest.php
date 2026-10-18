<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\Database\DatabaseInterface;

require_once __DIR__ . '/JoomlaTestCase.php';

/** Installed-package checks. Test metadata writes are always rolled back. */
final class ImageMetadataJoomlaIntegrationTest extends JoomlaTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::bootJoomlaSite();
        $language = \Joomla\CMS\Language\Language::getInstance('en-GB');
        Factory::$language = $language; // Match normal application initialisation used by Text::_().
        Factory::getApplication()->loadLanguage($language);
        Factory::getApplication()->loadDocument();
        Factory::getApplication()->createExtensionNamespaceMap();
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        require_once JPATH_SITE . '/components/com_jem/classes/imagemetadata.class.php';
        require_once JPATH_SITE . '/components/com_jem/classes/output.class.php';
        Factory::getApplication()->getLanguage()->load('com_jem', JPATH_SITE, 'en-GB', true);
    }

    public function testInstalledSchemaAndFilesMatchTheEnhancement(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        foreach (['events', 'categories', 'venues'] as $resource) {
            self::assertArrayHasKey('image_metadata', $db->getTableColumns('#__jem_' . $resource));
        }
        foreach (['classes/imagemetadata.class.php', 'models/fields/jemimagemetadata.php'] as $path) {
            self::assertSame(hash_file('sha256', JEM_TEST_ROOT . '/site/' . $path), hash_file('sha256', JPATH_SITE . '/components/com_jem/' . $path));
        }
        self::assertSame(hash_file('sha256', JEM_TEST_ROOT . '/media/css/image-metadata.css'), hash_file('sha256', JPATH_SITE . '/media/com_jem/css/image-metadata.css'));
    }

    public function testRealJoomlaFormsRenderAndFilterBothProfiles(): void
    {
        $registry = JemConfig::getInstance()->toRegistry();
        $original = $registry->get('operating_profile');
        try {
            foreach (['essential', 'advanced'] as $profile) {
                $registry->set('operating_profile', $profile); // In memory only: do not store Settings.
                foreach (['event', 'category', 'venue'] as $context) {
                    foreach (['site', 'admin'] as $client) {
                        $base = $client === 'site' ? JPATH_SITE : JPATH_ADMINISTRATOR;
                        $form = Form::getInstance('image_metadata_' . $profile . '_' . $context . '_' . $client,
                            $base . '/components/com_jem/models/forms/' . $context . '.xml', ['control' => 'jform']);
                        $slot = JemImageMetadata::slots($context)[0];
                        $value = [$slot => ['alt' => 'Accessible image', 'caption' => 'Existing credit']];
                        self::assertTrue($form->bind(['image_metadata' => json_encode($value)]));
                        $html = $form->getInput('image_metadata');
                        self::assertStringContainsString('name="jform[image_metadata][' . $slot . '][alt]"', $html);
                        self::assertStringContainsString('Accessible image', $html);
                        self::assertSame($profile === 'advanced', str_contains($html, '<details'));
                        if ($profile === 'advanced') {
                            self::assertStringContainsString('class="jem-image-metadata__details" open', $html);
                        }
                        self::assertSame($profile === 'advanced', str_contains($html, '[caption]'));
                        self::assertSame($profile === 'advanced', str_contains($html, '[credit]'));
                        self::assertSame($profile === 'advanced', str_contains($html, '[footer]'));
                        self::assertSame($profile === 'advanced', str_contains($html, '[align]'));
                        self::assertSame($profile === 'advanced', str_contains($html, '[credit_label]'));
                        self::assertSame($profile === 'advanced', str_contains($html, '[credit_label_custom]'));
                        if ($profile === 'advanced') {
                            self::assertStringContainsString('value="image_credit" selected', $html);
                            self::assertStringContainsString('data-jem-credit-custom', $html);
                        }
                        self::assertSame($client === 'admin', str_contains($html, 'hide-aware-inline-help d-none'));
                        self::assertStringContainsString('Brief image description for accessibility.', $html);
                        self::assertStringNotContainsString('Do not enter HTML or copyright credits here.', $html);
                        if ($profile === 'essential') {
                            self::assertStringContainsString('jem-image-metadata__notice', $html);
                        }
                        $filtered = $form->filter(['image_metadata' => $value]);
                        self::assertSame($value, $filtered['image_metadata']);
                        $field = $form->getField('image_metadata');
                        foreach (JemImageMetadata::slots($context) as $imageSlot) {
                            $panel = $field->renderSlot($imageSlot);
                            self::assertSame(1, substr_count($panel, 'name="jform[image_metadata][' . $imageSlot . '][alt]"'));
                            self::assertStringContainsString('<legend class="visually-hidden">', $panel);
                            self::assertStringContainsString('id="jform_image_metadata_' . $imageSlot . '_alt-desc"', $panel);
                            $altPanel = $field->renderSlot($imageSlot, 'alt');
                            $detailsPanel = $field->renderSlot($imageSlot, 'details');
                            self::assertStringContainsString('[' . $imageSlot . '][alt]', $altPanel);
                            self::assertStringNotContainsString('[caption]', $altPanel);
                            self::assertStringContainsString('class="jem-image-upload-row"', $altPanel);
                            self::assertStringContainsString('class="jem-image-upload-label"', $altPanel);
                            self::assertStringContainsString('class="jem-image-upload-control"', $altPanel);
                            self::assertStringNotContainsString('[alt]', $detailsPanel);
                            self::assertSame($profile === 'advanced', str_contains($detailsPanel, '[caption]'));
                            self::assertSame($profile === 'advanced' ? 2 : 0, substr_count($detailsPanel, 'class="jem-image-metadata__card"'));
                            preg_match_all('/\bid="([^"]+)"/', $altPanel . $detailsPanel, $ids);
                            self::assertSame(count($ids[1]), count(array_unique($ids[1])), 'Split panels must not duplicate IDs.');
                            if ($profile === 'advanced') {
                                self::assertStringContainsString('<option value="left" selected>', $detailsPanel);
                                self::assertSame(2, substr_count($detailsPanel, '<label class="visually-hidden"'));
                                self::assertStringNotContainsString('>Text</label>', $detailsPanel);
                            }
                            foreach (array_diff(JemImageMetadata::slots($context), [$imageSlot]) as $otherSlot) {
                                self::assertStringNotContainsString('[' . $otherSlot . ']', $panel);
                            }
                        }
                        self::assertSame('', $field->renderSlot('invalid'));
                        self::assertSame('', $field->renderSlot($slot, 'invalid'));
                    }
                }
            }
        } finally {
            $registry->set('operating_profile', $original);
        }
    }

    public function testImageResolutionUsesGlobalMaximumAndCompactHelp(): void
    {
        require_once JPATH_SITE . '/components/com_jem/classes/imagecamera.class.php';
        foreach (['event_intro', 'event_full', 'venue', 'category'] as $profile) {
            foreach ([1930, 2560] as $maximum) {
                $settings = clone JemHelper::config();
                $settings->image_max_dimension = $maximum;
                $settings->{'image_' . $profile . '_dimension_mandatory'} = 0;
                $settings->{'image_' . $profile . '_ratio_mandatory'} = 0;
                $html = JemImageCamera::resolutionControl('test_max_dimension', 'test-resolution', $profile, $settings);
                self::assertSame(1, substr_count($html, 'data-jem-image-resolution-value="' . $maximum . '"'));
                self::assertStringContainsString('data-jem-image-resolution-label="' . $maximum . '"', $html);
                self::assertStringContainsString('max="' . $maximum . '"', $html);
                self::assertStringContainsString('Max. file size:', $html);
                self::assertStringNotContainsString('Settings maximum:', $html);
                self::assertStringNotContainsString('This does not change the global setting.', $html);
            }
        }
    }

    public function testDatabaseRoundTripAndEssentialSavePreserveStoredCredits(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $registry = JemConfig::getInstance()->toRegistry();
        $original = $registry->get('operating_profile');
        $registry->set('operating_profile', 'essential');
        $db->transactionStart();
        try {
            foreach (['event' => 'events', 'category' => 'categories', 'venue' => 'venues'] as $context => $table) {
                $db->setQuery('SELECT id FROM ' . $db->quoteName('#__jem_' . $table) . ' WHERE id > 1 ORDER BY id', 0, 1);
                $id = (int) $db->loadResult();
                self::assertGreaterThan(0, $id, 'This local integration fixture needs a ' . $context);
                $slot = JemImageMetadata::slots($context)[0];
                $metadata = [$slot => ['alt' => 'Original alt', 'caption' => 'Original credit', 'credit' => 'New separate credit', 'footer' => true, 'align' => 'right']];
                $update = (object) ['id' => $id, 'image_metadata' => json_encode($metadata)];
                $db->updateObject('#__jem_' . $table, $update, 'id');
                $data = ['id' => $id, 'image_metadata' => [$slot => ['alt' => 'Edited alt', 'caption' => 'Forged credit', 'credit' => 'Forged', 'footer' => 0, 'align' => 'left']]];
                JemImageMetadata::prepareSubmission($data, $context);
                $result = json_decode($data['image_metadata'], true);
                self::assertSame('Original credit', $result[$slot]['caption']);
                self::assertSame('Edited alt', $result[$slot]['alt']);
                self::assertSame('New separate credit', $result[$slot]['credit']);
                self::assertTrue($result[$slot]['footer']);
                self::assertSame('right', $result[$slot]['align']);
                // Save-as-copy carries an independently permission-checked source ID.
                $copy = ['id' => 0];
                JemImageMetadata::prepareSubmission($copy, $context, $id);
                self::assertSame('Original credit', json_decode($copy['image_metadata'], true)[$slot]['caption']);
            }
        } finally {
            $db->transactionRollback();
            $registry->set('operating_profile', $original);
        }
    }

    public function testInstalledFlyerShowsCreditsOnlyWhenRequested(): void
    {
        $event = (object) ['id' => 0, 'title' => 'Event', 'datimage' => 'photo.jpg',
            'image_metadata' => ['datimage' => ['alt' => 'Custom alternative', 'caption' => '<strong>Photo credit</strong>']]];
        $image = ['original' => 'images/nonexistent-metadata-test.jpg', 'thumb' => '', 'width' => 100, 'height' => 100];
        $plain = (string) JemOutput::flyer($event, $image, 'event');
        $captioned = (string) JemOutput::flyer($event, $image, 'event', null, true);
        self::assertStringContainsString('alt="Custom alternative"', $plain);
        self::assertStringNotContainsString('<figcaption', $plain);
        self::assertStringContainsString('<span class="jem-image-caption__text"><strong>Photo credit</strong></span>', $captioned);
    }

    public function testCreditPlacementDoesNotMoveTheCaptionOrDuplicateTheCredit(): void
    {
        $image = ['original' => 'images/example.jpg', 'thumb' => '', 'width' => 100, 'height' => 100];
        foreach (['event' => 'datimage', 'category' => 'image', 'venue' => 'locimage'] as $context => $slot) {
            $record = (object) ['id' => 0, 'title' => 'Event', 'catname' => 'Category', 'venue' => 'Venue',
                $slot => 'example.jpg', 'image_metadata' => [$slot => ['caption' => 'Image description', 'credit' => 'Author credit']]];
            $inline = JemOutput::flyer($record, $image, $context, $slot, true);
            self::assertStringContainsString('jem-image-caption__text', $inline);
            self::assertSame(1, substr_count($inline, 'Author credit'));
            self::assertStringContainsString('Image credit:', $inline);
            self::assertSame('', JemOutput::imageCaptionFooter($record, $image, $context, $slot));
            $record->image_metadata[$slot]['footer'] = true;
            $inline = JemOutput::flyer($record, $image, $context, $slot, true);
            self::assertStringContainsString('Image description', $inline);
            self::assertStringNotContainsString('Author credit', $inline);
            $footer = JemOutput::imageCaptionFooter($record, $image, $context, $slot);
            self::assertStringContainsString('jem-image-credit--footer', $footer);
            self::assertStringContainsString('<span class="jem-image-credit__label">Image credit:</span> Author credit', $footer);
            self::assertSame(1, substr_count($footer, 'Author credit'));
            self::assertSame('', JemOutput::imageCaptionFooter($record, [], $context, $slot));
        }
    }

    public function testBothEnlargementModesUseSafeMatchingCaptions(): void
    {
        $settings = JemHelper::config();
        $original = [$settings->flyer, $settings->gddisabled, $settings->lightbox];
        $image = ['original' => 'media/com_jem/images/blank.webp', 'thumb' => 'media/com_jem/images/blank.webp'];
        self::assertFileExists(JPATH_SITE . '/' . $image['thumb']);
        $title = 'Example <img src=x onerror=alert(1)>';
        $credit = 'Photo: <strong>Author</strong><script>bad()</script>';
        $records = [
            ['event', 'datimage', (object) ['id' => 0, 'title' => $title, 'datimage' => 'photo.webp',
                'fullimage' => 'full.webp', 'image_metadata' => [
                    'datimage' => ['caption' => $credit, 'alt' => 'Intro alt'],
                    'fullimage' => ['caption' => 'Full credit', 'alt' => 'Full alt']]]],
            ['category', 'image', (object) ['id' => 0, 'catname' => $title, 'image' => 'photo.webp',
                'image_metadata' => ['image' => ['caption' => $credit, 'alt' => 'Category alt']]]],
            ['venue', 'locimage', (object) ['id' => 0, 'venue' => $title, 'locimage' => 'photo.webp',
                'image_metadata' => ['locimage' => ['caption' => $credit, 'alt' => 'Venue alt']]]],
        ];
        try {
            $settings->flyer = 0;
            $settings->gddisabled = 1;
            foreach ([0, 1] as $mode) {
                $settings->lightbox = $mode;
                foreach ($records as [$context, $slot, $record]) {
                    $record->image_metadata[$slot]['credit'] = 'Example photographer';
                    $record->image_metadata[$slot]['credit_label'] = 'source';
                    $html = JemOutput::flyer($record, $image, $context, $slot);
                    self::assertStringNotContainsString('<script', $html);
                    self::assertStringNotContainsString('onclick=', $html);
                    $dom = new DOMDocument();
                    @$dom->loadHTML($html);
                    $anchor = $dom->getElementsByTagName('a')->item(0);
                    self::assertNotNull($anchor);
                    if ($mode === 0) {
                        self::assertSame('Source: Example photographer', $dom->getElementsByTagName('template')->item(1)->textContent);
                        self::assertSame('Photo: Author', $dom->getElementsByTagName('template')->item(0)->textContent);
                        self::assertStringContainsString($title, $anchor->getAttribute('data-jem-image-title'));
                    } else {
                        $inner = new DOMDocument();
                        $inner->loadHTML('<div>' . $anchor->getAttribute('data-title') . '</div>');
                        self::assertStringContainsString($title, $inner->textContent);
                        self::assertSame(0, $inner->getElementsByTagName('img')->length);
                        self::assertSame('Photo: Author', $inner->getElementsByTagName('span')->item(0)->textContent);
                        self::assertStringContainsString('Source: Example photographer', $inner->textContent);
                    }
                }
                $full = JemOutput::flyer($records[0][2], $image, 'event', 'fullimage');
                self::assertStringContainsString('Full credit', $full);
                self::assertStringNotContainsString('Author', $full);
            }
        } finally {
            [$settings->flyer, $settings->gddisabled, $settings->lightbox] = $original;
        }
    }
}
