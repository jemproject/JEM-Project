<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminImageEditorTabTest extends TestCase
{
    public function testImageIsTheSecondMainTabInAllThreeEditors(): void
    {
        $event = $this->read('/admin/views/event/tmpl/edit.php');
        $venue = $this->read('/admin/views/venue/tmpl/edit.php');
        $category = $this->read('/admin/views/category/tmpl/edit.php');

        $this->assertTabOrder($event, "'info'", "'image'", "'advanced'");
        $this->assertTabOrder($venue, "'info'", "'image'", "'capacity'");
        $this->assertTabOrder($category, "'details'", "'image'", null);

        self::assertSame(2, substr_count($event, "LayoutHelper::render(\n                    'image.editor'"));
        self::assertSame(1, substr_count($venue, "'image.editor'"));
        self::assertSame(1, substr_count($category, "'image.editor'"));
        self::assertStringNotContainsString('id="image-event-header"', $event);
        self::assertStringNotContainsString('id="image-event-header"', $venue);
        self::assertStringNotContainsString('id="category-image-header"', $category);
    }

    public function testBackendFormsExposeTheSharedServerFileAndCameraFields(): void
    {
        $event = $this->read('/admin/models/forms/event.xml');
        $venue = $this->read('/admin/models/forms/venue.xml');
        $category = $this->read('/admin/models/forms/category.xml');

        self::assertStringContainsString('name="datimage" type="imageselectevent"', $event);
        self::assertStringContainsString('name="userfile" type="jemimagefile"', $event);
        self::assertStringContainsString('name="fullimage" type="imageselectevent"', $event);
        self::assertStringContainsString('name="fulluserfile" type="jemimagefile"', $event);
        self::assertStringContainsString('name="locimage" type="imageselectevent"', $venue);
        self::assertStringContainsString('name="userfile" type="jemimagefile"', $venue);
        self::assertStringContainsString('name="image" type="imageselectcategory"', $category);
        self::assertStringContainsString('name="userfile" type="jemimagefile"', $category);
        self::assertStringContainsString('enctype="multipart/form-data"', $this->read('/admin/views/category/tmpl/edit.php'));
    }

    public function testBackendSaveUsesTheProfileValidatorsAndExclusiveSources(): void
    {
        $event = $this->read('/admin/tables/event.php');
        $venue = $this->read('/admin/tables/venue.php');
        $category = $this->read('/admin/models/category.php');
        $layout = $this->read('/admin/layouts/image/editor.php');

        self::assertStringContainsString('if ($backend || $jemsettings->imageenabled == 2', $event);
        self::assertStringContainsString('JemImageProfilePolicy::EVENT_INTRO', $event);
        self::assertStringContainsString('JemImageProfilePolicy::EVENT_FULL', $event);
        self::assertStringContainsString('if ($backend || $jemsettings->imageenabled == 2', $venue);
        self::assertStringContainsString('JemImageProfilePolicy::VENUE', $venue);
        self::assertStringContainsString('protected function prepareImage(', $category);
        self::assertStringContainsString('JemImageProfilePolicy::CATEGORY', $category);
        self::assertStringContainsString('File::makeSafe(basename($image))', $category);
        self::assertStringContainsString('data-jem-image-select=', $layout);
        self::assertStringContainsString('data-jem-image-file=', $layout);
        self::assertStringContainsString('jem-image-selected-preview', $layout);
    }

    public function testCancelBypassesPublicationImageValidation(): void
    {
        $script = $this->read('/media/js/image-publication.js');
        $policy = $this->read('/site/classes/imagepublicationpolicy.class.php');

        self::assertStringContainsString('function isCancelTask(form)', $script);
        self::assertStringContainsString('/(^|\\.)(cancel|close)$/', $script);
        self::assertStringContainsString('if (isCancelTask(form))', $script);
        self::assertStringContainsString("'jform_userfile', 'removeimage'", $policy);
    }

    public function testRepeatedTabHeadingsWereRemoved(): void
    {
        $event = $this->read('/admin/views/event/tmpl/edit.php');
        $venue = $this->read('/admin/views/venue/tmpl/edit.php');
        $capacity = $this->read('/admin/views/event/tmpl/edit_capacity.php');
        $categoryView = $this->read('/admin/views/category/view.html.php');

        self::assertStringNotContainsString("Text::_('COM_JEM_NEW_EVENT')", $event);
        self::assertStringNotContainsString("Text::_('COM_JEM_NEW_VENUE')", $venue);
        self::assertStringNotContainsString(
            '<legend><?php echo Text::_(\'COM_JEM_EVENT_VENUE_CAPACITY_TAB\'); ?></legend>',
            $capacity
        );
        self::assertStringContainsString("Text::_(\$isNew ? 'COM_JEM_ADD_CATEGORY' : 'COM_JEM_EDIT_CATEGORY')", $categoryView);
    }

    public function testFirstEditorTabUsesTheSharedDetailsLabelInFrontendAndBackend(): void
    {
        foreach (array(
            '/admin/views/event/tmpl/edit.php',
            '/admin/views/venue/tmpl/edit.php',
            '/admin/views/category/tmpl/edit.php',
            '/site/views/editevent/tmpl/edit.php',
            '/site/views/editevent/tmpl/responsive/edit.php',
            '/site/views/editvenue/tmpl/edit.php',
            '/site/views/editvenue/tmpl/responsive/edit.php',
            '/site/views/editcategory/tmpl/edit.php',
        ) as $path) {
            self::assertStringContainsString("Text::_('COM_JEM_DETAILS')", $this->read($path), $path);
        }
    }

    public function testBackendCameraButtonKeepsItsNeutralButtonAndHoverTreatment(): void
    {
        $css = $this->read('/media/css/backend.css');

        self::assertStringContainsString(
            '.jem-admin-image-tab .jem-camera-button.jem-image-action-button {',
            $css
        );
        self::assertStringContainsString('background-color: var(--bs-btn-bg);', $css);
        self::assertStringContainsString('border: 1px solid var(--bs-btn-border-color);', $css);
        self::assertStringContainsString(
            '.jem-admin-image-tab .jem-camera-button.jem-image-action-button:hover,',
            $css
        );
        self::assertStringContainsString('background-color: var(--bs-btn-hover-bg);', $css);
        self::assertStringContainsString(
            '.jem-admin-image-tab .jem-camera-button.jem-image-action-button:focus-visible {',
            $css
        );
    }

    private function assertTabOrder(string $template, string $first, string $second, ?string $third): void
    {
        $firstPosition = strpos($template, "uitab.addTab',");
        $firstPosition = strpos($template, $first, $firstPosition ?: 0);
        $secondPosition = strpos($template, $second, ($firstPosition ?: 0) + 1);

        self::assertNotFalse($firstPosition);
        self::assertNotFalse($secondPosition);
        self::assertLessThan($secondPosition, $firstPosition);

        if ($third !== null) {
            $thirdPosition = strpos($template, $third, $secondPosition + 1);
            self::assertNotFalse($thirdPosition);
            self::assertLessThan($thirdPosition, $secondPosition);
        }
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . $path);
    }
}
