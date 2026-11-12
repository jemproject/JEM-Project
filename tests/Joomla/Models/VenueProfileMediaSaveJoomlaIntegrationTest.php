<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

/** Profile persistence and failure recovery against an explicitly writable Joomla site. */
final class VenueProfileMediaSaveJoomlaIntegrationTest extends JoomlaTestCase
{
    private DatabaseDriver $db;
    private int $venueId;
    private array $files = [];
    private static mixed $originalSession;

    /** Load the installed candidate, with a CLI session for isolated database tests. */
    public static function setUpBeforeClass(): void
    {
        if (getenv('JEM_TEST_WRITABLE') !== '1') {
            self::markTestSkipped('Set JEM_TEST_WRITABLE=1 for disposable profile fixtures.');
        }
        self::bootJoomlaSite();
        self::$originalSession = Factory::getApplication()->getSession();
        Factory::getApplication()->setSession(Factory::getContainer()->get('session.cli'));
        require_once JPATH_SITE . '/components/com_jem/factory.php';
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        require_once JPATH_SITE . '/components/com_jem/classes/image.class.php';
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/classes/venuecapacity.class.php';
        self::assertSame(
            hash_file('sha256', JEM_TEST_ROOT . '/admin/classes/venuecapacity.class.php'),
            hash_file('sha256', (new ReflectionClass(JemVenueCapacityService::class))->getFileName())
        );
    }

    /** Restore the original session after testing. */
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$originalSession)) {
            Factory::getApplication()->setSession(self::$originalSession);
        }
    }

    /** Create an unpublished venue owned only by this test. */
    protected function setUp(): void
    {
        $this->db = Factory::getContainer()->get(DatabaseDriver::class);
        $row = (object) ['venue' => 'QA2508 ' . bin2hex(random_bytes(6)), 'alias' => 'qa2508-' . bin2hex(random_bytes(6)),
            'published' => 0, 'country' => 'ES', 'capacity' => 6, 'created' => '2026-11-12 10:00:00', 'created_by' => 0];
        $this->db->insertObject('#__jem_venues', $row, 'id');
        $this->venueId = (int) $row->id;
    }

    /** Remove only fixture rows and files, including failed-save leftovers. */
    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $spaces = 'SELECT id FROM #__jem_venue_spaces WHERE venue_id = ' . $this->venueId;
        $layouts = 'SELECT id FROM #__jem_venue_layouts WHERE venue_space_id IN (' . $spaces . ')';
        foreach ([
            'DELETE FROM #__jem_venue_capacity_areas WHERE venue_layout_id IN (' . $layouts . ')',
            'DELETE FROM #__jem_venue_profile_spaces WHERE venue_profile_id IN (SELECT id FROM #__jem_venue_capacity_profiles WHERE venue_id = ' . $this->venueId . ')',
            'DELETE FROM #__jem_venue_layouts WHERE venue_space_id IN (' . $spaces . ')',
            'DELETE FROM #__jem_venue_spaces WHERE venue_id = ' . $this->venueId,
            'DELETE FROM #__jem_venue_capacity_profiles WHERE venue_id = ' . $this->venueId,
            'DELETE FROM #__jem_venues WHERE id = ' . $this->venueId,
        ] as $sql) {
            $this->db->setQuery($sql)->execute();
        }
        foreach ([JPATH_SITE . '/images/jem/venues/' . $this->venueId, JPATH_SITE . '/images/jem/venues/small/' . $this->venueId] as $folder) {
            if (is_dir($folder)) {
                Joomla\Filesystem\Folder::delete($folder);
            }
        }
    }

    /** Return a six-place hall with two separate capacity areas. */
    private function configuration(): array
    {
        return JemVenueCapacityService::normaliseFormData(['spaces' => [[
            'space_name' => 'Hall', 'layout_name' => 'Seated', 'layout_capacity' => 6,
            'areas' => [['name' => 'Floor', 'capacity' => 4, 'published' => 1], ['name' => 'Balcony', 'capacity' => 2, 'published' => 1]],
        ]]], 6, 6);
    }

    /** Save the same configuration/media boundary used by the venue model. */
    private function save(array $configuration, int $id = 0, bool $default = true, array $spaceFiles = [], array $layoutFiles = []): array
    {
        return JemVenueCapacityService::saveProfileConfiguration($this->venueId, $id, $configuration, 'Theatre', null, $default, 0, $spaceFiles, $layoutFiles);
    }

    /** Assert no second profile or unexpected space/layout/area was persisted. */
    private function assertCounts(int $profiles, int $spaces, int $layouts, int $areas): void
    {
        foreach ([
            ['jem_venue_capacity_profiles', 'venue_id = ' . $this->venueId, $profiles],
            ['jem_venue_spaces', 'venue_id = ' . $this->venueId, $spaces],
            ['jem_venue_layouts', 'venue_space_id IN (SELECT id FROM #__jem_venue_spaces WHERE venue_id = ' . $this->venueId . ')', $layouts],
            ['jem_venue_capacity_areas', 'venue_layout_id IN (SELECT l.id FROM #__jem_venue_layouts l JOIN #__jem_venue_spaces s ON s.id=l.venue_space_id WHERE s.venue_id = ' . $this->venueId . ')', $areas],
        ] as [$table, $where, $expected]) {
            self::assertSame($expected, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__' . $table . ' WHERE ' . $where)->loadResult());
        }
    }

    /** Empty media must still save successfully and a repeat must retain the IDs. */
    public function testSaveWithoutImagesAndRepeatKeepsIdentifiers(): void
    {
        $saved = $this->save($this->configuration());
        self::assertSame(6, (int) $saved['profile_capacity']);
        self::assertCount(2, $saved['spaces'][0]['areas']);
        $repeat = $this->save($saved, (int) $saved['profile_id']);
        foreach (['profile_id', 'profile_revision'] as $key) {
            self::assertSame($saved[$key], $repeat[$key]);
        }
        foreach (['space_id', 'layout_id', 'assignment_id'] as $key) {
            self::assertSame($saved['spaces'][0][$key], $repeat['spaces'][0][$key]);
        }
        $this->assertCounts(1, 1, 1, 2);
    }

    /** Media labels update without changing the physical layout revision. */
    public function testBothMediaObjectsSaveWithoutPhysicalRevision(): void
    {
        $saved = $this->save($this->configuration());
        foreach (['space' => 'jem_venue_spaces', 'layout' => 'jem_venue_layouts'] as $kind => $table) {
            $media = (object) ['id' => (int) $saved['spaces'][0][$kind . '_id'], 'image' => $kind . '.png'];
            $this->db->updateObject('#__' . $table, $media, 'id');
        }
        $saved = JemVenueCapacityService::getConfiguration($this->venueId, (int) $saved['profile_id']);
        $saved['spaces'][0]['space_image_alt'] = '<b>Hall</b>';
        $saved['spaces'][0]['layout_image_alt'] = 'Seating plan';
        $result = $this->save($saved, (int) $saved['profile_id']);
        self::assertSame('space.png', $result['spaces'][0]['space_image']);
        self::assertSame('layout.png', $result['spaces'][0]['layout_image']);
        self::assertSame('Hall', $result['spaces'][0]['space_image_alt']);
        self::assertSame('Seating plan', $result['spaces'][0]['layout_image_alt']);
        self::assertSame($saved['spaces'][0]['layout_id'], $result['spaces'][0]['layout_id']);
        self::assertSame($saved['profile_revision'], $result['profile_revision']);
        $this->assertCounts(1, 1, 1, 2);
    }

    /** A failed upload must roll back even creation of the first default profile. */
    public function testUploadFailureLeavesNoNewProfileAndRetrySucceeds(): void
    {
        try {
            $this->save($this->configuration(), 0, true, [], [['name' => 'bad.png', 'error' => UPLOAD_ERR_PARTIAL]]);
            self::fail('Expected upload failure.');
        } catch (RuntimeException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        $this->assertCounts(0, 0, 0, 0);
        self::assertSame(0, (int) JemVenueCapacityService::getDefaultConfiguration($this->venueId)['profile_id']);
        $this->save($this->configuration());
        $this->assertCounts(1, 1, 1, 2);
    }

    /** A database duplicate during space insertion must not replace the old default. */
    public function testDatabaseFailurePreservesExistingDefaultAndConfiguration(): void
    {
        $saved = $this->save($this->configuration());
        try {
            // The same new-space alias is unique per venue and forces a real SQL error.
            $this->save($this->configuration());
            self::fail('Expected duplicate space SQL failure.');
        } catch (Joomla\Database\Exception\ExecutionFailureException $error) {
            self::assertStringContainsString('Duplicate', $error->getMessage());
        }
        self::assertSame($saved, JemVenueCapacityService::getDefaultConfiguration($this->venueId));
        $this->assertCounts(1, 1, 1, 2);
    }

    /** Validation after creating a profile must also be inside its transaction. */
    public function testForeignSpaceFailureDoesNotLeaveEmptyProfile(): void
    {
        $configuration = $this->configuration();
        $configuration['spaces'][0]['space_id'] = 2147483647;
        try {
            $this->save($configuration);
            self::fail('Expected invalid ownership.');
        } catch (InvalidArgumentException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        $this->assertCounts(0, 0, 0, 0);
    }

    /** Deleting an old image is deferred until all profile media have been stored. */
    public function testLaterUploadFailureRetainsOldImageAndDatabaseValues(): void
    {
        $saved = $this->save($this->configuration());
        $space = $saved['spaces'][0];
        $folder = JemVenueImagePath::spaceFolder($this->venueId, (int) $space['space_id']);
        JemVenueImagePath::ensureFolders($folder);
        $path = JPATH_SITE . '/' . JemVenueImagePath::imagePath($folder, 'qa2508.png');
        $this->files[] = $path;
        file_put_contents($path, 'original image fixture');
        $row = (object) ['id' => (int) $space['space_id'], 'image' => 'qa2508.png'];
        $this->db->updateObject('#__jem_venue_spaces', $row, 'id');
        $saved = JemVenueCapacityService::getDefaultConfiguration($this->venueId);
        $submitted = $saved;
        $submitted['spaces'][0]['space_image_remove'] = 1;
        try {
            $this->save($submitted, (int) $saved['profile_id'], true, [], [['name' => 'bad.png', 'error' => UPLOAD_ERR_PARTIAL]]);
            self::fail('Expected later upload failure.');
        } catch (RuntimeException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        self::assertSame('original image fixture', file_get_contents($path));
        self::assertSame($saved, JemVenueCapacityService::getDefaultConfiguration($this->venueId));
        $result = $this->save($submitted, (int) $saved['profile_id']);
        self::assertSame('', $result['spaces'][0]['space_image']);
        self::assertFileDoesNotExist($path);
    }

    /** A changed layout must not overwrite the old revision used by existing events. */
    public function testNewLayoutRevisionPreservesOldAreasAndSnapshot(): void
    {
        $saved = $this->save($this->configuration());
        $oldLayoutId = (int) $saved['spaces'][0]['layout_id'];
        $snapshot = JemVenueCapacityService::buildEventSnapshot($this->venueId);
        $submitted = $saved;
        $submitted['spaces'][0]['areas'][0]['capacity'] = 3;
        $submitted['spaces'][0]['areas'][1]['capacity'] = 3;
        $result = $this->save($submitted, (int) $saved['profile_id']);
        self::assertNotSame($oldLayoutId, (int) $result['spaces'][0]['layout_id']);
        self::assertSame([4, 2], array_map('intval', $this->db->setQuery('SELECT capacity FROM #__jem_venue_capacity_areas WHERE venue_layout_id = ' . $oldLayoutId . ' ORDER BY ordering')->loadColumn()));
        self::assertSame($oldLayoutId, (int) $snapshot['spaces'][0]['layout']['id']);
        $this->assertCounts(1, 1, 2, 4);
    }

    /** Existing callers that save configuration without images retain their API. */
    public function testConfigurationOnlySaveAndNoOpRemainSupported(): void
    {
        $saved = JemVenueCapacityService::saveDefaultConfiguration($this->venueId, $this->configuration(), 'Theatre');
        $result = JemVenueCapacityService::saveProfileConfiguration($this->venueId, (int) $saved['profile_id'], $saved, 'Theatre');
        self::assertSame($saved, $result);
        $this->assertCounts(1, 1, 1, 2);
    }

    /** An internal default change must not commit a surrounding database transaction. */
    public function testDefaultSelectionRespectsCallerTransaction(): void
    {
        $this->db->transactionStart();
        try {
            JemVenueCapacityService::saveDefaultConfiguration($this->venueId, $this->configuration(), 'Theatre');
            $this->assertCounts(1, 1, 1, 2);
        } finally {
            $this->db->transactionRollback();
        }
        $this->assertCounts(0, 0, 0, 0);
    }

    /** A copy to a new revision must be removed if later media processing fails. */
    public function testCopiedRevisionImageIsRemovedOnLaterFailure(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD is needed to create the image fixture.');
        }
        $configuration = $this->configuration();
        $second = $configuration['spaces'][0];
        $second['space_name'] = 'Annex';
        $second['space_code'] = 'annex';
        $configuration['spaces'][] = $second;
        $saved = $this->save($configuration);
        $space = $saved['spaces'][0];
        $folder = JemVenueImagePath::layoutFolder($this->venueId, (int) $space['space_id'], (int) $space['layout_id']);
        JemVenueImagePath::ensureFolders($folder);
        $path = JPATH_SITE . '/' . JemVenueImagePath::imagePath($folder, 'qa2508.png');
        $this->files[] = $path;
        // A real PNG permits the normal thumbnail path to run when enabled.
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $path);
        imagedestroy($image);
        $row = (object) ['id' => (int) $space['layout_id'], 'image' => 'qa2508.png'];
        $this->db->updateObject('#__jem_venue_layouts', $row, 'id');
        $saved = JemVenueCapacityService::getDefaultConfiguration($this->venueId);
        $submitted = $saved;
        $submitted['spaces'][0]['areas'][0]['capacity'] = 3;
        $submitted['spaces'][0]['areas'][1]['capacity'] = 3;
        $imageRoot = JPATH_SITE . '/images/jem/venues/' . $this->venueId;
        $before = Joomla\Filesystem\Folder::files($imageRoot, '\\.png$', true, true);
        try {
            $this->save($submitted, (int) $saved['profile_id'], true, [], [1 => ['name' => 'bad.png', 'error' => UPLOAD_ERR_PARTIAL]]);
            self::fail('Expected upload failure after copying the first layout image.');
        } catch (RuntimeException $error) {
            self::assertNotSame('', $error->getMessage());
        }
        self::assertSame($before, Joomla\Filesystem\Folder::files($imageRoot, '\\.png$', true, true));
        self::assertFileExists($path);
        self::assertSame($saved, JemVenueCapacityService::getDefaultConfiguration($this->venueId));
        $this->assertCounts(1, 2, 2, 4);
    }
}
