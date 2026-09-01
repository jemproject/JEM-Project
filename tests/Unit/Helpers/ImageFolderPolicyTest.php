<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!class_exists('Joomla\\CMS\\Filter\\OutputFilter')) {
    final class ImageFolderOutputFilterStub
    {
        public static function stringURLSafe($value): string
        {
            return (string) $value;
        }
    }

    class_alias(ImageFolderOutputFilterStub::class, 'Joomla\\CMS\\Filter\\OutputFilter');
}

if (!class_exists('Joomla\\Filesystem\\File')) {
    final class ImageFolderFileStub
    {
        public static function makeSafe($value): string
        {
            return (string) $value;
        }
    }

    class_alias(ImageFolderFileStub::class, 'Joomla\\Filesystem\\File');
}

require_once JEM_TEST_ROOT . '/site/classes/imagefolderpolicy.class.php';
require_once JEM_TEST_ROOT . '/site/classes/eventimagepath.class.php';
require_once JEM_TEST_ROOT . '/site/classes/venueimagepath.class.php';
require_once JEM_TEST_ROOT . '/site/classes/categoryimagepath.class.php';

final class ImageFolderPolicyTest extends TestCase
{
    public function testCustomPatternDepthIsDerivedFromThePattern(): void
    {
        self::assertSame(4, JemImageFolderPolicy::depth(
            '{year}/{month}/{category_alias}/{venue_alias}'
        ));
        self::assertTrue(JemImageFolderPolicy::isWithinMaximumDepth(
            '{year}/{month}/{category_alias}/{venue_alias}'
        ));
    }

    public function testAllImageObjectsPreserveAValidFourLevelPattern(): void
    {
        $pattern = 'one/two/three/four';
        $record = new stdClass();

        self::assertSame(
            $pattern,
            JemEventImagePath::configuredFolderFromEvent(
                $record,
                $this->settings('event', $pattern)
            )
        );
        self::assertSame(
            $pattern,
            JemVenueImagePath::configuredFolderFromVenue(
                $record,
                $this->settings('venue', $pattern)
            )
        );
        self::assertSame(
            $pattern,
            JemCategoryImagePath::configuredFolderFromCategory(
                $record,
                $this->settings('category', $pattern)
            )
        );
    }

    public function testResolvedPathsOverTheInternalLimitAreRejectedWithoutTruncation(): void
    {
        $pattern = 'one/two/three/four/five/six/seven/eight/nine';

        self::assertSame(9, JemImageFolderPolicy::depth($pattern));
        self::assertFalse(JemImageFolderPolicy::isWithinMaximumDepth($pattern));
        self::assertSame('', JemImageFolderPolicy::resolvedFolderOrRoot($pattern));
    }

    private function settings(string $object, string $pattern): object
    {
        return new class(array(
            $object . '_image_subfolder_enabled' => 1,
            $object . '_image_subfolder_preset' => 'custom',
            $object . '_image_subfolder_pattern' => $pattern,
        )) {
            private array $values;

            public function __construct(array $values)
            {
                $this->values = $values;
            }

            public function get(string $key, $default = null)
            {
                return $this->values[$key] ?? $default;
            }
        };
    }
}
