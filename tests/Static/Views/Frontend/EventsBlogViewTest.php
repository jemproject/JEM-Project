<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventsBlogViewTest extends TestCase
{
    public function testEventsBlogIsIntegratedAsAFirstClassMenuView(): void
    {
        $controller = $this->read('site/controller.php');
        $router = $this->read('site/router.php');
        $menu = $this->read('admin/controllers/frontendmenu.php');
        $layout = $this->read('site/views/eventsblog/tmpl/default.php');

        self::assertStringContainsString("case 'eventsblog':", $controller);
        self::assertStringContainsString("RouterViewConfiguration('eventsblog')", $router);
        self::assertStringContainsString("view=eventsblog", $menu);
        self::assertStringContainsString('name="blog_period"', $layout);
        self::assertStringContainsString('name="blog_category"', $layout);
        self::assertStringContainsString('name="blog_venue"', $layout);
        self::assertStringContainsString('name="blog_type"', $layout);
        self::assertStringContainsString('name="blog_country"', $layout);
        self::assertStringContainsString('blog_show_registration', $layout);
        self::assertSame(5, substr_count($layout, 'onchange="this.form.submit()"'));
        self::assertStringNotContainsString('COM_JEM_EVENTSBLOG_APPLY_FILTERS', $layout);
        self::assertStringNotContainsString('COM_JEM_EVENTSBLOG_RESET_FILTERS', $layout);

        $eventsModel = $this->read('site/models/eventslist.php');
        self::assertStringContainsString('a.registra_from,a.registra_until', $eventsModel);
        self::assertStringContainsString('a.unregistra_until', $eventsModel);
    }

    public function testEventsBlogHasBothLayoutStyleAssetsAndPageTextOptions(): void
    {
        $metadata = $this->read('site/views/eventsblog/tmpl/default.xml');
        $css = $this->read('media/css/eventsblog.css');
        $responsiveCss = $this->read('media/css/eventsblog-responsive.css');

        self::assertStringContainsString('name="blog_columns"', $metadata);
        self::assertStringContainsString('name="blog_image_fit"', $metadata);
        self::assertStringContainsString('name="blog_image_ratio_width"', $metadata);
        self::assertStringContainsString('name="blog_image_ratio_height"', $metadata);
        self::assertStringContainsString('name="blog_filter_categories"', $metadata);
        self::assertStringContainsString('name="blog_filter_venues"', $metadata);
        self::assertStringContainsString('name="blog_filter_types"', $metadata);
        self::assertStringContainsString('name="blog_filter_countries"', $metadata);
        self::assertSame(4, substr_count($metadata, 'multiple="true"'));
        self::assertStringContainsString('name="showintrotext"', $metadata);
        self::assertStringContainsString('name="introtext"', $metadata);
        self::assertStringContainsString('name="showfootertext"', $metadata);
        self::assertStringContainsString('name="footertext"', $metadata);
        self::assertStringContainsString('grid-template-columns', $css);
        self::assertStringContainsString('@media', $css);
        self::assertStringContainsString('object-fit: cover', $css);
        self::assertStringContainsString('object-position: center', $css);
        self::assertStringContainsString('jem-eventsblog-image-fit--height', $css);
        self::assertStringContainsString('jem-eventsblog-image-fit--width', $css);
        self::assertStringContainsString('--jem-eventsblog-image-ratio: 1 / 1', $css);
        self::assertStringContainsString('eventsblog.css', $responsiveCss);

        $model = $this->read('site/models/eventsblog.php');
        self::assertStringContainsString('filter.blog_allowed_categories', $model);
        self::assertStringContainsString('filter.blog_allowed_venues', $model);
        self::assertStringContainsString('filter.blog_allowed_types', $model);
        self::assertStringContainsString('filter.blog_allowed_countries', $model);
        self::assertStringContainsString('limitSelection', $model);
        self::assertStringContainsString('$gridColumns = max(3, min($columns, $rowCount))', $this->read('site/views/eventsblog/tmpl/default.php'));
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
