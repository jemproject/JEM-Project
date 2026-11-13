<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute the real include expressions from relocated layouts, preserving their local scope. */
final class LayoutDependencyResolutionTest extends TestCase
{
    private string $directory;

    /** @return void Create an isolated tree; installed Joomla overrides are never used or changed. */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/jem-layout-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/override/responsive', 0777, true);
    }

    /** @return void Remove only files created inside this test's temporary tree. */
    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory,
            FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    /** @return iterable<string,array{string,string,?string}> Layout, core dependency and optional relative override. */
    public static function dependencies(): iterable
    {
        yield 'admin/views/attendee/tmpl/default.php' => ['admin/views/attendee/tmpl/default.php', 'admin/views/attendee/tmpl/relocation.php', 'relocation.php'];
        yield 'admin/views/housekeeping/tmpl/images.php' => ['admin/views/housekeeping/tmpl/images.php', 'admin/views/housekeeping/tmpl/default.php', 'default.php'];
        yield 'site/common/views/tmpl/responsive/default_event_filters.php' => ['site/common/views/tmpl/responsive/default_event_filters.php', 'site/common/views/tmpl/default_event_filters.php', '../default_event_filters.php'];
        yield 'site/common/views/tmpl/responsive/default_jem_eventslist.php' => ['site/common/views/tmpl/responsive/default_jem_eventslist.php', 'site/common/views/tmpl/responsive/default_jem_eventslist_item.php', 'default_jem_eventslist_item.php'];
        yield 'site/views/annualcalendar/tmpl/default.php' => ['site/views/annualcalendar/tmpl/default.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/calendar/tmpl/default.php' => ['site/views/calendar/tmpl/default.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/calendar/tmpl/responsive/default.php' => ['site/views/calendar/tmpl/responsive/default.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/category/tmpl/calendar.php' => ['site/views/category/tmpl/calendar.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/category/tmpl/responsive/calendar.php' => ['site/views/category/tmpl/responsive/calendar.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/day/tmpl/responsive/timeline.php' => ['site/views/day/tmpl/responsive/timeline.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/day/tmpl/responsive/timetable.php' => ['site/views/day/tmpl/responsive/timetable.php', 'site/views/day/tmpl/timetable.php', '../timetable.php'];
        yield 'site/views/day/tmpl/timeline.php' => ['site/views/day/tmpl/timeline.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/day/tmpl/timetable.php' => ['site/views/day/tmpl/timetable.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/editcategory/tmpl/responsive/edit.php' => ['site/views/editcategory/tmpl/responsive/edit.php', 'site/views/editcategory/tmpl/edit.php', '../edit.php'];
        yield 'site/views/editevent/tmpl/responsive/choosearticle.php' => ['site/views/editevent/tmpl/responsive/choosearticle.php', 'site/views/editevent/tmpl/choosearticle.php', '../choosearticle.php'];
        yield 'site/views/event/tmpl/responsive/default_cancellation.php' => ['site/views/event/tmpl/responsive/default_cancellation.php', 'site/views/event/tmpl/default_cancellation.php', '../default_cancellation.php'];
        yield 'site/views/event/tmpl/responsive/default_capacityareas.php' => ['site/views/event/tmpl/responsive/default_capacityareas.php', 'site/views/event/tmpl/default_capacityareas.php', '../default_capacityareas.php'];
        yield 'site/views/event/tmpl/responsive/default_pricedregform.php' => ['site/views/event/tmpl/responsive/default_pricedregform.php', 'site/views/event/tmpl/default_pricedregform.php', '../default_pricedregform.php'];
        yield 'site/views/event/tmpl/responsive/default_venueconfiguration.php' => ['site/views/event/tmpl/responsive/default_venueconfiguration.php', 'site/views/event/tmpl/default_venueconfiguration.php', '../default_venueconfiguration.php'];
        yield 'site/views/venue/tmpl/calendar.php' => ['site/views/venue/tmpl/calendar.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/venue/tmpl/responsive/calendar.php' => ['site/views/venue/tmpl/responsive/calendar.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/venuesmap/tmpl/responsive/default.php' => ['site/views/venuesmap/tmpl/responsive/default.php', 'site/views/venuesmap/tmpl/default.php', '../default.php'];
        yield 'site/views/weekcal/tmpl/default.php' => ['site/views/weekcal/tmpl/default.php', 'site/helpers/calendardaylink.php', null];
        yield 'site/views/weekcal/tmpl/responsive/default.php' => ['site/views/weekcal/tmpl/responsive/default.php', 'site/helpers/calendardaylink.php', null];
    }

    /**
     * Check relocated layouts with a missing partial and then a supplied custom partial.
     *
     * @param string $layout Original JEM layout.
     * @param string $dependency Installed dependency, relative to the source tree.
     * @param ?string $relative Optional custom partial relative to the relocated layout.
     * @return void
     */
    #[DataProvider('dependencies')]
    public function testDependencySurvivesRelocation(string $layout, string $dependency, ?string $relative): void
    {
        self::assertFileExists(JEM_TEST_ROOT . '/' . $dependency);
        $statement = $this->includeStatement($layout, basename($dependency));
        $core = $this->directory . '/core/' . $dependency;
        mkdir(dirname($core), 0777, true);
        file_put_contents($core, '<?php echo "core:" . $displayData["title"];');
        $statement = str_replace(['JPATH_SITE', 'JPATH_ADMINISTRATOR'], ['$siteRoot', '$adminRoot'], $statement);
        // Mirror Joomla's component directories within the fixture, not within the installed site.
        $statement = str_replace(['/components/com_jem/'], ['/'], $statement);
        $probe = $this->directory . '/override/responsive/' . basename($layout);
        file_put_contents($probe, '<?php ' . $statement);
        $siteRoot = $this->directory . '/core/site';
        $adminRoot = $this->directory . '/core/admin';
        $displayData = ['title' => 'local item data'];
        ob_start();
        try {
            require $probe;
            self::assertSame('core:local item data', ob_get_contents());
        } finally {
            ob_end_clean();
        }

        if ($relative !== null) {
            $custom = dirname($probe) . '/' . $relative;
            file_put_contents($custom, '<?php echo "custom:" . $displayData["title"];');
            ob_start();
            try {
                require $probe;
                self::assertSame('custom:local item data', ob_get_contents());
            } finally {
                ob_end_clean();
            }
        }
    }

    /**
     * Extract the executable include statement rather than reimplementing its resolution in the test.
     *
     * @param string $layout Relative source path.
     * @param string $name Included dependency's filename.
     * @return string The original PHP statement.
     */
    private function includeStatement(string $layout, string $name): string
    {
        $tokens = token_get_all((string) file_get_contents(JEM_TEST_ROOT . '/' . $layout));
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || !in_array($token[0], [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
                continue;
            }
            $statement = '';
            do {
                $part = $tokens[$index++];
                $statement .= is_array($part) ? $part[1] : $part;
            } while ($part !== ';' && isset($tokens[$index]));
            if (str_contains($statement, "'" . '/' . $name . "'") || str_contains($statement, '/' . $name)) {
                return $statement;
            }
        }
        self::fail('Dependency statement not found in ' . $layout . ': ' . $name);
    }
}
