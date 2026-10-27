<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class HousekeepingImageCleanupJoomlaIntegrationTest extends JoomlaTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::bootJoomlaSite();

        if (!is_file(JPATH_SITE . '/components/com_jem/classes/imageprofilepolicy.class.php')) {
            self::markTestSkipped('The installed JEM version does not provide the image profile classes required by this model.');
        }

        $path = getenv('JEM_TEST_SOURCE') === '1'
            ? JEM_TEST_ROOT . '/admin/models/housekeeping.php'
            : JPATH_ADMINISTRATOR . '/components/com_jem/models/housekeeping.php';
        require_once $path;
    }

    public function testLinkImageReferencesIncludeEncodedPathsAndMediaFragments(): void
    {
        $extract = new ReflectionMethod(JemModelHousekeeping::class, 'extractLinkImages');
        $rows = array(
            '{"image":"images/jem/events/event-prado-social.webp"}',
            '{"image":"images\\/jem\\/events\\/2026\\/club\\/Poster.JPG#joomlaImage://local-images"}',
            '{"image":"images/jem/events/Unused%20Photo%20(1).JPG"}',
            '{"image":"images/jem/events/party#1.jpg"}',
            '{"image":"images/jem/events/event-prado-social.webp"}',
            '{"image":"images/jem/venues/other.jpg"}',
            '{"image":""}',
            '{broken json',
        );

        self::assertSame(
            array('event-prado-social.webp', '2026/club/Poster.JPG', 'Unused Photo (1).JPG', 'party#1.jpg'),
            $extract->invoke(null, $rows, 'events')
        );
    }

    public function testCleanupRecognisesImageNamesButNotControlFiles(): void
    {
        $candidate = new ReflectionMethod(JemModelHousekeeping::class, 'isImageCandidate');

        self::assertTrue($candidate->invoke(null, 'Unused Photo (1).JPG'));
        self::assertTrue($candidate->invoke(null, '2026/club/poster.webp'));
        self::assertFalse($candidate->invoke(null, 'index.html'));
        self::assertFalse($candidate->invoke(null, 'script.php'));
        self::assertFalse($candidate->invoke(null, '.hidden.jpg'));
    }

    public function testAssignedImagesRemainProtectedWithEncodedOrWindowsCaseVariants(): void
    {
        $unassigned = new ReflectionMethod(JemModelHousekeeping::class, 'unassignedImages');
        $available = array('Unused Photo (1).JPG', '2026/club/Poster.JPG', 'unrelated.png');
        $assigned = array('Unused%20Photo%20(1).JPG', '2026\\club\\Poster.JPG');

        self::assertSame(array('unrelated.png'), $unassigned->invoke(null, $available, $assigned));

        if (PHP_OS_FAMILY === 'Windows') {
            self::assertSame(
                array('unrelated.png'),
                $unassigned->invoke(null, $available, array('unused photo (1).jpg', '2026/CLUB/poster.jpg'))
            );
        }
    }

    public function testStoredLinkImagesNeverEnterTheUnusedImageList(): void
    {
        $model = new JemModelHousekeeping();
        $links = new ReflectionMethod(JemModelHousekeeping::class, 'getLinkImages');
        $unused = new ReflectionMethod(JemModelHousekeeping::class, 'getImages');

        foreach (array(
            JemModelHousekeeping::EVENTS,
            JemModelHousekeeping::VENUES,
            JemModelHousekeeping::CATEGORIES,
        ) as $type) {
            $unusedImages = $unused->invoke($model, $type);
            foreach ($links->invoke($model, $type) as $image) {
                self::assertNotContains($image, $unusedImages);
            }
        }
    }

    public function testCleanupResolvesOnlyDirectFilesInsideItsFolder(): void
    {
        $folder = sys_get_temp_dir() . '/jem-cleanup-' . bin2hex(random_bytes(6));
        $outside = $folder . '-outside.jpg';
        $photo = $folder . '/Unused Photo (1).JPG';
        $link = $folder . '/alias.jpg';

        self::assertTrue(mkdir($folder));
        try {
            self::assertNotFalse(file_put_contents($photo, 'test image'));
            self::assertNotFalse(file_put_contents($outside, 'outside'));
            $resolve = new ReflectionMethod(JemModelHousekeeping::class, 'containedExistingFile');

            self::assertSame(realpath($photo), $resolve->invoke(null, $folder, 'Unused Photo (1).JPG'));
            self::assertNull($resolve->invoke(null, $folder, '../' . basename($outside)));
            self::assertNull($resolve->invoke(null, $folder, 'missing.jpg'));

            if (@symlink($photo, $link)) {
                self::assertNull($resolve->invoke(null, $folder, 'alias.jpg'));
            }
        } finally {
            if (is_link($link)) {
                unlink($link);
            }
            if (is_file($photo)) {
                unlink($photo);
            }
            if (is_file($outside)) {
                unlink($outside);
            }
            rmdir($folder);
        }
    }
}
