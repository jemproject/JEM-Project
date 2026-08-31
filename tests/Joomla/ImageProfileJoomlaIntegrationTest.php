<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/JoomlaTestCase.php';

final class ImageProfileJoomlaIntegrationTest extends JoomlaTestCase
{
    private static string $temporaryRoot;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::bootJoomlaSite();

        require_once self::joomlaRoot() . '/components/com_jem/helpers/helper.php';
        require_once self::joomlaRoot() . '/components/com_jem/classes/image.class.php';
        require_once self::joomlaRoot() . '/components/com_jem/classes/imagepublicationpolicy.class.php';
        require_once self::joomlaRoot() . '/administrator/components/com_jem/models/housekeeping.php';

        self::$temporaryRoot = sys_get_temp_dir() . '/jem-image-profile-integration-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir(self::$temporaryRoot, 0777, true));
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$temporaryRoot) && is_dir(self::$temporaryRoot)) {
            foreach ((array) glob(self::$temporaryRoot . '/*') as $path) {
                if (is_file($path)) {
                    unlink($path);
                } elseif (is_dir($path)) {
                    foreach ((array) glob($path . '/*') as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    rmdir($path);
                }
            }
            rmdir(self::$temporaryRoot);
        }

        parent::tearDownAfterClass();
    }

    public function testInstalledDatabaseContainsEveryImageProfileSetting(): void
    {
        $keys = array(
            'image_min_dimension',
            'image_max_dimension',
            'image_event_intro_mode',
            'image_event_intro_required',
            'image_event_intro_default_dimension',
            'image_event_intro_ratio',
            'image_event_intro_custom_ratio',
            'image_event_full_mode',
            'image_event_full_required',
            'image_event_full_default_dimension',
            'image_event_full_ratio',
            'image_event_full_custom_ratio',
            'image_venue_mode',
            'image_venue_required',
            'image_venue_default_dimension',
            'image_venue_ratio',
            'image_venue_custom_ratio',
            'image_category_mode',
            'image_category_required',
            'image_category_default_dimension',
            'image_category_ratio',
            'image_category_custom_ratio',
        );

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('keyname'))
            ->from($db->quoteName('#__jem_config'))
            ->whereIn($db->quoteName('keyname'), $keys);
        $db->setQuery($query);

        self::assertSame($keys, array_values(array_intersect($keys, (array) $db->loadColumn())));
    }

    #[DataProvider('transformationProvider')]
    public function testRealProfileTransformationCreatesExactOriginalAndThumbnail(
        int $sourceWidth,
        int $sourceHeight,
        string $mode,
        int $ratioWidth,
        int $ratioHeight
    ): void {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            self::markTestSkipped('GD PNG support is required.');
        }

        $token = bin2hex(random_bytes(4));
        $source = self::$temporaryRoot . '/source-' . $token . '.png';
        $target = self::$temporaryRoot . '/target-' . $token . '.png';
        $thumbnailFolder = self::$temporaryRoot . '/small-' . $token;
        $thumbnail = $thumbnailFolder . '/target-' . $token . '.png';
        $image = imagecreatetruecolor($sourceWidth, $sourceHeight);
        $red = imagecolorallocate($image, 220, 20, 60);
        imagefill($image, 0, 0, $red);
        self::assertTrue(imagepng($image, $source));
        imagedestroy($image);

        $settings = $this->settings(array(
            'image_event_intro_mode' => $mode,
            'image_event_intro_ratio' => $ratioWidth . '_' . $ratioHeight,
        ));

        self::assertTrue(JemImage::copyForProfile(
            $source,
            $target,
            $thumbnail,
            $settings,
            JemImageProfilePolicy::EVENT_INTRO
        ));
        self::assertFileExists($target);
        self::assertFileExists($thumbnail);

        $size = getimagesize($target);
        self::assertIsArray($size);
        self::assertTrue(JemImageProfilePolicy::isExactRatio(
            (int) $size[0],
            (int) $size[1],
            $ratioWidth,
            $ratioHeight
        ));
        self::assertLessThanOrEqual(1600, max((int) $size[0], (int) $size[1]));
        self::assertGreaterThanOrEqual(64, min((int) $size[0], (int) $size[1]));

        unlink($source);
        unlink($target);
        unlink($thumbnail);
        rmdir($thumbnailFolder);
    }

    public static function transformationProvider(): array
    {
        return array(
            'centred crop to horizontal ratio' => array(800, 600, 'crop', 16, 9),
            'black padding to horizontal ratio' => array(600, 800, 'pad', 16, 9),
            'centred crop to vertical ratio' => array(800, 600, 'crop', 9, 16),
        );
    }

    public function testRequiredPublicationUsesTheActualEventFilesButNeverBlocksDrafts(): void
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            self::markTestSkipped('GD PNG support is required.');
        }

        $folder = 'integration-' . bin2hex(random_bytes(5));
        $imageFolder = JemEventImagePath::absoluteImageFolder($folder);
        self::assertTrue(mkdir($imageFolder, 0777, true));
        $filename = 'required.png';
        $image = imagecreatetruecolor(64, 64);
        self::assertTrue(imagepng($image, $imageFolder . $filename));
        imagedestroy($image);

        $settings = $this->settings(array(
            'image_event_intro_required' => 1,
            'image_event_full_required' => 1,
        ));
        $event = (object) array(
            'id' => 0,
            'published' => 1,
            'image_path' => $folder,
            'datimage' => $filename,
            'fullimage' => '',
        );

        self::assertSame(
            array(JemImageProfilePolicy::EVENT_FULL),
            JemImagePublicationPolicy::missingForRecord('event', $event, $settings)
        );

        $event->published = 0;
        self::assertSame(array(), JemImagePublicationPolicy::missingForRecord('event', $event, $settings));

        unlink($imageFolder . $filename);
        rmdir($imageFolder);
    }

    public function testInstalledHousekeepingAuditReturnsOnlyBoundedOpaqueCandidates(): void
    {
        $model = new JemModelHousekeeping();
        $report = $model->auditImageProfiles('size', 'desc', 0, 5);

        self::assertSame((int) $report['candidate_total'], (int) $report['pending']);
        self::assertSame(
            (int) $report['total'],
            (int) $report['valid'] + (int) $report['pending'] + (int) $report['blocked']
        );
        self::assertLessThanOrEqual(5, count($report['candidates']));
        self::assertSame('size', $report['ordering']);
        self::assertSame('desc', $report['direction']);

        foreach ($report['candidates'] as $candidate) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', (string) $candidate['identifier']);
            self::assertStringNotContainsString('..', (string) $candidate['path']);
            self::assertDoesNotMatchRegularExpression('/^(?:[a-z]:|[\\\\\/])/i', (string) $candidate['path']);
            self::assertGreaterThan(0, (int) $candidate['target_width']);
            self::assertGreaterThan(0, (int) $candidate['target_height']);
        }
    }

    public function testInstalledHousekeepingRejectsPathsAndOversizedSelections(): void
    {
        $model = new JemModelHousekeeping();

        try {
            $model->normaliseImageProfiles(array('C:\\images\\jem\\events\\example.jpg'));
            self::fail('A raw image path was accepted as a candidate identifier.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('identifier', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $model->normaliseImageProfiles(array_map(static function ($index) {
            return str_pad(dechex($index), 64, '0', STR_PAD_LEFT);
        }, range(1, 26)));
    }

    private function settings(array $overrides = array()): object
    {
        return (object) array_merge(array(
            'image_filetypes' => 'jpg,gif,png,webp',
            'sizelimit' => 4096,
            'image_min_dimension' => 64,
            'image_max_dimension' => 1600,
            'imagewidth' => 120,
            'imagehight' => 90,
            'gddisabled' => 1,
            'image_event_intro_mode' => 'none',
            'image_event_intro_ratio' => '16_9',
            'image_event_intro_custom_ratio' => '16:9',
            'image_event_intro_required' => 0,
            'image_event_full_mode' => 'none',
            'image_event_full_ratio' => '16_9',
            'image_event_full_custom_ratio' => '16:9',
            'image_event_full_required' => 0,
        ), $overrides);
    }
}
