<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImageMetadataContractsTest extends TestCase
{
    public function testImageTextDoesNotSetTheFigureWidth(): void
    {
        $css = file_get_contents(JEM_TEST_ROOT . '/media/css/image-metadata.css');
        self::assertMatchesRegularExpression('/#jem \\.jem-image-caption\\s*\\{[^}]*inline-size: 0;[^}]*min-inline-size: 100%;[^}]*max-inline-size: 100%;/s', $css);
        self::assertStringNotContainsString('max-width: 36rem', $css);
        self::assertStringContainsString('.jem-category-overview-panel:has(.jem-image-figure)', $css);
        self::assertStringContainsString('.jem-venue-overview-panel:has(.jem-image-figure)', $css);
        self::assertMatchesRegularExpression('/\\.jem-image-credit--footer\\s*\\{[^}]*width: 100%;/s', $css);
        self::assertStringContainsString('padding-inline-end: calc(3rem + 5px)', $css);
    }

    public function testCreditLabelFlowsThroughAllRenderersAndReset(): void
    {
        foreach (['site/views/event/tmpl/default.php', 'site/views/event/tmpl/responsive/default.php', 'site/classes/output.class.php'] as $path) {
            $source = file_get_contents(JEM_TEST_ROOT . '/' . $path);
            self::assertStringContainsString("\$metadata['credit'] ?? '', \$metadata)", $source);
            self::assertStringContainsString("\$metadata['align'] ?? 'left', \$metadata)", $source);
        }
        $output = file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
        self::assertStringContainsString("JemImageMetadata::creditHtml(\$metadata['credit'] ?? '', \$metadata)", $output);
        $field = file_get_contents(JEM_TEST_ROOT . '/site/models/fields/jemimagemetadata.php');
        self::assertStringContainsString('data-jem-reset-value="image_credit"', $field);
        self::assertStringContainsString('com_jem/image-metadata.js', $field);
        $reset = file_get_contents(JEM_TEST_ROOT . '/media/js/other.js');
        self::assertStringContainsString("field.dataset.jemResetValue || 'left'", $reset);
    }

    public function testCreditPlacementHasConsistentLabelsAndAccessibleHelp(): void
    {
        foreach (['admin', 'site'] as $client) {
            $strings = parse_ini_file(JEM_TEST_ROOT . '/' . $client . '/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
            self::assertSame('Credit position', $strings['COM_JEM_IMAGE_METADATA_CREDIT_POSITION']);
            self::assertSame('Below image', $strings['COM_JEM_IMAGE_METADATA_POSITION_BELOW']);
            self::assertSame('Bottom of section', $strings['COM_JEM_IMAGE_METADATA_POSITION_SECTION']);
            self::assertSame('Caption', $strings['COM_JEM_IMAGE_METADATA_CAPTION']);
            self::assertSame('Photo credit / licence', $strings['COM_JEM_IMAGE_METADATA_CREDITS']);
            self::assertSame('Caption and credit (optional)', $strings['COM_JEM_IMAGE_METADATA_DETAILS']);
        }
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/fields/jemimagemetadata.php');
        self::assertStringContainsString("Text::_('COM_JEM_IMAGE_METADATA_CREDIT_POSITION_DESC')", $source);
        self::assertStringContainsString('data-jem-reset-value="0"', $source);
        self::assertStringContainsString('data-jem-credit-editor', $source);
        self::assertStringContainsString('data-jem-caption-editor', $source);
        self::assertStringNotContainsString('data-jem-credit-preview', $source);
        self::assertStringContainsString('jem-image-metadata__details', $source);
        self::assertStringContainsString("\$hasText ? ' open' : ''", $source);
        self::assertStringContainsString("useScript('tinymce')", $source);
        self::assertStringNotContainsString('type="checkbox"', $source);
        self::assertSame(2, substr_count($source, "\$id . '_footer-desc'"));
        self::assertStringContainsString("' aria-describedby=\"' . \$escape(\$id . '_footer-desc')", $source);
    }

    public function testFrontendImagePanelsPlaceMetadataAndClearAfterUploadControls(): void
    {
        foreach (['edit.php', 'responsive/edit.php'] as $layout) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/editevent/tmpl/' . $layout);
            self::assertMatchesRegularExpression('/"control control"\s+"metadata metadata"\s+"actions actions"/', $source);
            self::assertMatchesRegularExpression('/"control"\s+"metadata"\s+"actions"/', $source);
            self::assertMatchesRegularExpression('/\.jem-editevent-image-field > \.jem-image-metadata--details\s*\{\s*grid-area: metadata;/', $source);
            self::assertMatchesRegularExpression('/\.jem-editevent-image-field > \.jem-image-actions--last\s*\{\s*grid-area: actions;/', $source);
        }
    }

    public function testEditorsSplitAltAndDetailsWithoutDuplicatingFields(): void
    {
        $admin = file_get_contents(JEM_TEST_ROOT . '/admin/layouts/image/editor.php');
        self::assertStringContainsString("renderSlot(\$selectField, 'alt')", $admin);
        self::assertStringContainsString("renderSlot(\$selectField, 'details')", $admin);
        self::assertLessThan(strpos($admin, 'class="jem-image-actions'), strpos($admin, "renderSlot(\$selectField, 'details')"));
        self::assertLessThan(strpos($admin, 'foreach ($extraRows'), strpos($admin, "renderSlot(\$selectField, 'alt')"));
        foreach ([
            'editevent/tmpl/edit.php' => ['datimage', 'fullimage'],
            'editevent/tmpl/responsive/edit.php' => ['datimage', 'fullimage'],
            'editcategory/tmpl/edit.php' => ['image'],
            'editvenue/tmpl/edit_extended.php' => ['locimage'],
            'editvenue/tmpl/responsive/edit_extended.php' => ['locimage'],
        ] as $template => $slots) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $template);
            foreach ($slots as $slot) {
                self::assertSame(1, substr_count($source, "renderSlot('" . $slot . "', 'alt')"));
                self::assertSame(1, substr_count($source, "renderSlot('" . $slot . "', 'details')"));
                self::assertStringNotContainsString("renderSlot('" . $slot . "');", $source);
                $detailsPosition = strpos($source, "renderSlot('" . $slot . "', 'details')");
                $clearPosition = strpos($source, 'data-jem-image-select="jform_' . $slot . '"');
                self::assertLessThan($clearPosition, $detailsPosition, 'Clear follows metadata cards.');
            }
        }
    }

    public function testEveryResourceHasStorageAndServerSideNormalisation(): void
    {
        $install = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');
        $installer = file_get_contents(JEM_TEST_ROOT . '/script.php');
        self::assertSame(3, substr_count($install, '`image_metadata` TEXT NULL DEFAULT NULL'));
        self::assertSame(3, substr_count($update, 'ADD COLUMN `image_metadata` TEXT NULL DEFAULT NULL'));
        self::assertSame(3, substr_count($installer, "'image_metadata' => \"TEXT NULL DEFAULT NULL\""));
        foreach (['event', 'category', 'venue'] as $context) {
            $table = file_get_contents(JEM_TEST_ROOT . '/admin/tables/' . $context . '.php');
            self::assertStringContainsString("JemImageMetadata::prepareStore(\$this, '" . $context . "');", $table);
            $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/' . $context . '.php');
            self::assertStringContainsString("JemImageMetadata::prepareSubmission(\$data, '" . $context . "',", $model);
            $controller = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/' . $context . '.php');
            self::assertStringContainsString("setState('image_metadata.copy_source', \$recordId)", $controller);
        }
    }

    public function testAllSixFormsExposeTheSameCompoundField(): void
    {
        foreach (['admin', 'site'] as $client) {
            foreach (['event', 'category', 'venue'] as $context) {
                $form = simplexml_load_file(JEM_TEST_ROOT . '/' . $client . '/models/forms/' . $context . '.xml');
                $fields = $form->xpath('//field[@name="image_metadata"]');
                self::assertCount(1, $fields);
                self::assertSame('jemimagemetadata', (string) $fields[0]['type']);
                self::assertSame($context, (string) $fields[0]['context']);
                self::assertSame('raw', (string) $fields[0]['filter']);
            }
        }
    }

    public function testBothDetailLayoutsOptInWithoutChangingDefaultFlyerBehaviour(): void
    {
        foreach (['event', 'category', 'venue'] as $context) {
            foreach (['', 'responsive/'] as $layout) {
                $template = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $context . '/tmpl/' . $layout . 'default.php');
                $arguments = $context === 'event' ? "'event', \$detailImageField, true" : "'" . $context . "', null, true";
                self::assertStringContainsString($arguments, $template);
            }
        }
        $output = file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
        self::assertStringContainsString('$showCaption = false', $output);
        self::assertStringContainsString('JemImageMetadata::resolve($data, $type, (string) $id)', $output);
        self::assertStringContainsString("JemImageMetadata::figure(\$output, \$metadata['caption'], \$type,", $output);
        foreach (['event', 'category', 'venue'] as $context) {
            foreach (['', 'responsive/'] as $layout) {
                $template = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $context . '/tmpl/' . $layout . 'default.php');
                self::assertStringContainsString('JemOutput::imageCaptionFooter(', $template);
                if ($context === 'event') {
                    self::assertStringContainsString("JemImageMetadata::lightboxTitle(\$this->item->title, \$metadata['caption'],", $template);
                }
            }
        }
    }

    public function testCategoryNodesAndQueriesCarryMetadataRatherThanDroppingIt(): void
    {
        $categories = file_get_contents(JEM_TEST_ROOT . '/site/classes/categories.class.php');
        self::assertStringContainsString('public $image_metadata = null;', $categories);
        $event = file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        self::assertSame(2, substr_count($event, 'l.image_metadata AS venue_image_metadata'));
        self::assertSame(2, substr_count($event, 'a.image_metadata'));
        $model = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        self::assertStringContainsString('JemImageMetadata::inheritCategory($data, $category);', $model);
        self::assertStringContainsString("'image_path', 'image_metadata', 'fullimage_layout'", $model);
    }
}
