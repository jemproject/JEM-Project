<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Jem51SampleShowcaseTest extends TestCase
{
    private function sql(): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');
    }

    private function insertedRowCount(string $table, string $sql): int
    {
        self::assertMatchesRegularExpression(
            '/INSERT INTO `' . preg_quote($table, '/') . '` .*? VALUES\R(.*?);\R/s',
            $sql
        );
        preg_match('/INSERT INTO `' . preg_quote($table, '/') . '` .*? VALUES\R(.*?);\R/s', $sql, $matches);
        preg_match_all('/^\(/m', $matches[1], $rows);

        return count($rows[0]);
    }

    public function testShowcaseDataIsExplicitlyDeclaredInSql(): void
    {
        $sql = $this->sql();

        foreach (array(
            '-- JEM 5.1 functional Sample Data showcase',
            'INSERT INTO `#__jem_venues`',
            'INSERT INTO `#__jem_venue_capacity_profiles`',
            'INSERT INTO `#__jem_venue_spaces`',
            'INSERT INTO `#__jem_venue_layouts`',
            'INSERT INTO `#__jem_venue_profile_spaces`',
            'INSERT INTO `#__jem_venue_capacity_areas`',
            'INSERT INTO `#__jem_event_space_layouts`',
            'INSERT INTO `#__jem_links`',
            'INSERT INTO `#__jem_attachments`',
            "'sample_showcase_catalog'",
        ) as $expected) {
            self::assertStringContainsString($expected, $sql);
        }

        self::assertStringNotContainsString('sampledata-universities.json', $sql);
        self::assertStringNotContainsString('INSERT INTO `#__jem_capacity_pools`', $sql);
        self::assertStringNotContainsString('INSERT INTO `#__jem_event_prices`', $sql);
    }

    public function testEveryEventStartsWithTheColouredDemoMediaNotice(): void
    {
        $sql = $this->sql();

        self::assertStringContainsString(
            '<p class="jem-sample-notice" style="color:#a33b20;"><strong>JEM demonstration event and media.</strong> This event and all its images exist only to demonstrate JEM features.</p>',
            $sql
        );
        self::assertStringContainsString('<p><strong>What this example demonstrates:</strong>', $sql);
        self::assertStringContainsString("WHERE e.`introtext` NOT LIKE '%jem-sample-notice%'", $sql);
    }

    public function testCompactUniversityProgrammeCoversTheThreeAdvancedCapacityModes(): void
    {
        $sql = $this->sql();

        foreach (array(
            'JEM Classic Registration Seminar',
            'JEM Configured Capacity Workshop',
            'JEM Capacity by Area Conference',
        ) as $title) {
            self::assertStringContainsString($title, $sql);
        }

        self::assertStringContainsString('CURDATE() + INTERVAL 7 DAY', $sql);
        self::assertStringContainsString('CURDATE() + INTERVAL 14 DAY', $sql);
        self::assertStringContainsString('CURDATE() + INTERVAL 21 DAY', $sql);
        self::assertStringContainsString("`capacity_mode` = 'configured'", $sql);
        self::assertStringContainsString("`capacity_mode` = 'areas'", $sql);
        self::assertStringNotContainsString("`pricing_mode` = 'single'", $sql);
        self::assertStringNotContainsString("`pricing_mode` = 'multiple'", $sql);
    }

    public function testCapacityAreaConferenceContainsAnOrderedProgramme(): void
    {
        $sql = $this->sql();

        self::assertStringContainsString('Opening Keynote', $sql);
        self::assertStringContainsString('Space Scheduling Lab', $sql);
        self::assertStringContainsString("'18:15:00', '19:00:00'", $sql);
        self::assertStringContainsString("'19:15:00', '20:30:00'", $sql);
        self::assertMatchesRegularExpression("/\\(22, 10, .*?'Opening Keynote'.*?, 21, 1, 0,/", $sql);
        self::assertMatchesRegularExpression("/\\(23, 11, .*?'Space Scheduling Lab'.*?, 21, 2, 0,/", $sql);
        self::assertStringContainsString('an ordered child event contained within its parent programme and schedule.', $sql);
        self::assertStringContainsString('"new_events":5,"programme_items":2', $sql);
    }

    public function testThreeUniversityVenuesUseCitiesOutsideTheMuseumSet(): void
    {
        $sql = $this->sql();

        foreach (array(
            'Universidade de Lisboa',
            'University of Bologna',
            'University of Vienna',
            "'Lisbon', 'University campus'",
            "'Bologna', 'University campus'",
            "'Vienna', 'University campus'",
        ) as $venue) {
            self::assertStringContainsString($venue, $sql);
        }

        self::assertStringContainsString('"faculty_subvenues":2', $sql);
        self::assertStringNotContainsString('Demo Faculty of', $sql);
        self::assertMatchesRegularExpression("/\\(10, 'Faculty of Arts'.*?, 9, 1\\),/", $sql);
        self::assertMatchesRegularExpression("/\\(11, 'Faculty of Science'.*?, 9, 2\\);/", $sql);
    }

    public function testAdvancedVenueProfilesAndCapacityVariantsArePresentWithoutCommerce(): void
    {
        $sql = $this->sql();

        self::assertSame(5, $this->insertedRowCount('#__jem_venue_capacity_profiles', $sql));
        self::assertSame(4, $this->insertedRowCount('#__jem_venue_spaces', $sql));
        self::assertSame(5, $this->insertedRowCount('#__jem_venue_layouts', $sql));
        self::assertSame(3, $this->insertedRowCount('#__jem_venue_capacity_areas', $sql));
        self::assertSame(4, $this->insertedRowCount('#__jem_event_space_layouts', $sql));
        self::assertStringContainsString("(3, 9, 'lecture', 'Lecture'", $sql);
        self::assertStringContainsString("(3, 3, 2, 3, 0)", $sql);
        self::assertStringContainsString('One physical space reused by two venue profiles with different layouts.', $sql);
        self::assertStringContainsString('"profile":"advanced"', $sql);
        self::assertStringContainsString('"commercial_examples":0', $sql);
        self::assertStringNotContainsString('Pricing and capacity proposal', $sql);
        self::assertStringNotContainsString("`prices_include_tax` = 1", $sql);
        self::assertStringNotContainsString("`management_fee_value` = '1.50'", $sql);
        self::assertStringNotContainsString("(7, 'Main'", $sql);
    }

    public function testArchiveContainsTheCompleteStructuredMediaTree(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP zip extension is required to inspect sampledata.zip.');
        }

        $zip = new ZipArchive();
        self::assertTrue($zip->open(JEM_TEST_ROOT . '/admin/assets/sampledata.zip'));

        $required = array(
            'images/jem/venues/7/venue/sample-campus-historic.webp',
            'images/jem/venues/8/venue/sample-campus-modern.webp',
            'images/jem/venues/9/venue/sample-campus-urban.webp',
            'images/jem/venues/8/profiles/1/sample-profile-capacity.webp',
            'images/jem/venues/8/spaces/1/space/sample-space-seminar.webp',
            'images/jem/venues/8/spaces/1/layouts/1/layout/sample-layout-conference.webp',
            'images/jem/venues/9/profiles/2/sample-profile-capacity.webp',
            'images/jem/venues/9/spaces/2/space/sample-space-aula-magna.webp',
            'images/jem/venues/9/spaces/2/layouts/2/layout/sample-layout-theatre.webp',
            'images/jem/venues/9/spaces/2/layouts/2/areas/1/sample-area-guests.webp',
            'images/jem/venues/9/spaces/2/layouts/2/areas/2/sample-area-students.webp',
            'images/jem/venues/9/spaces/2/layouts/2/areas/3/sample-area-staff.webp',
            'images/jem/events/19/sample-event-lecture.webp',
            'images/jem/events/20/sample-event-conference.webp',
            'images/jem/events/21/sample-event-theatre.webp',
            'attachment-event19-sample-classic-registration-checklist.txt',
            'attachment-event20-sample-configured-capacity.csv',
            'attachment-event21-sample-capacity-areas.csv',
        );

        $missing = array_values(array_filter($required, static fn (string $entry): bool => $zip->locateName($entry) === false));
        $structuredWebp = 0;
        $packagedThumbnails = array();
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $entry = (string) $zip->getNameIndex($index);
            if (preg_match('#^images/jem/(?:events|venues)/(?:small/)?[^/].*\.webp$#', $entry)) {
                ++$structuredWebp;
            }
            if (preg_match('#^images/jem/(?:events|venues)/small/#', $entry)) {
                $packagedThumbnails[] = $entry;
            }
        }

        $normaliseLineEndings = static fn (string $contents): string => str_replace(
            array("\r\n", "\r"),
            "\n",
            $contents
        );
        self::assertSame(
            $normaliseLineEndings($this->sql()),
            $normaliseLineEndings((string) $zip->getFromName('sampledata.sql'))
        );
        $zip->close();

        self::assertSame(array(), $missing, "sampledata.zip is missing showcase assets:\n" . implode("\n", $missing));
        self::assertSame(15, $structuredWebp, 'The archive must include only the compact showcase originals.');
        self::assertSame(array(), $packagedThumbnails, 'JEM must generate Sample Data thumbnails after import.');
    }

    public function testSampleDataModelCopiesStructuredMediaRecursively(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/sampledata.php');

        self::assertStringContainsString('$this->filelist[\'folder\'] . \'/images/jem\'', $model);
        self::assertStringContainsString('$this->copyDirectoryContents($structuredImageBase', $model);
        self::assertStringContainsString('$this->createStructuredImageThumbnails($structuredImageBase)', $model);
        self::assertStringContainsString('JemEventImagePath::createThumbnail(', $model);
        self::assertStringContainsString('JemVenueImagePath::createThumbnail(', $model);
        self::assertStringContainsString('private function copyDirectoryContents', $model);
        self::assertStringContainsString('COM_JEM_SAMPLEDATA_UNABLE_TO_COPY_IMAGE', $model);
    }
}
