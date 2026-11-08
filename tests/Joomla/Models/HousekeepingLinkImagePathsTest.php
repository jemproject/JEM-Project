<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Check the actual link path resolver independently from database and filesystem fixtures. */
final class HousekeepingLinkImagePathsTest extends TestCase
{
    /** @return ReflectionMethod Source resolver isolated from the Joomla model constructor. */
    private static function resolver(): ReflectionMethod
    {
        if (!class_exists('LinkImagePolicyProbe2478', false)) {
            $source = str_replace("\r\n", "\n", file_get_contents(JEM_TEST_ROOT . '/admin/models/housekeeping.php'));
            self::assertSame(1, preg_match('/^    private static function extractLinkImages\([^\n]*\n.*?^    }/ms', $source, $match));
            eval('class LinkImagePolicyProbe2478 {' . $match[0] . '}');
        }
        return new ReflectionMethod('LinkImagePolicyProbe2478', 'extractLinkImages');
    }

    /** @return iterable<string, array{array, array}> Stored JSON and expected paths. */
    public static function validPaths(): iterable
    {
        yield 'empty' => [[null, '', ' ', '{}', '[]', '{"image":null}'], []];
        yield 'normal' => [[json_encode(['image' => 'images/jem/events/photo.png'])], ['photo.png']];
        yield 'duplicates' => [['{"image":"images/jem/events/photo.png"}', '{"image":"images/jem/events/photo.png"}'], ['photo.png']];
        yield 'encoded' => [[json_encode(['image' => 'images/jem/events/a%20b.png'])], ['a b.png', 'a%20b.png']];
        yield 'encoded separators' => [[json_encode(['image' => 'images%5Cjem%5Cevents%5Cphoto.png'])], ['photo.png']];
        yield 'media fragment' => [[json_encode(['image' => 'images/jem/events/photo.png#joomlaImage://local-images/photo.png?width=1'])], ['photo.png']];
        yield 'literal hash' => [[json_encode(['image' => 'images/jem/events/party#1.png'])], ['party#1.png']];
        yield 'windows separators' => [[json_encode(['image' => 'images\\jem\\events\\photo.png'])], ['photo.png']];
        yield 'root slash' => [[json_encode(['image' => '/images/jem/events/photo.png'])], ['photo.png']];
        yield 'dot segments' => [[json_encode(['image' => './images/./jem/events/./photo.png'])], ['photo.png']];
        yield 'other area' => [[json_encode(['image' => 'images/jem/venues/photo.png'])], []];
        yield 'external' => [[json_encode(['image' => 'https://example.invalid/images/jem/events/photo.png'])], []];
        yield 'protocol relative' => [[json_encode(['image' => '//images/jem/events/photo.png'])], []];
        yield 'direct thumbnail' => [[json_encode(['image' => 'images/jem/events/small/photo.png'])], ['small/photo.png', 'photo.png']];
        if (PHP_OS_FAMILY === 'Windows') {
            yield 'Windows thumbnail case' => [[json_encode(['image' => 'IMAGES/JEM/EVENTS/Small/photo.png'])], ['Small/photo.png', 'photo.png']];
        }
        yield 'escaped key' => [['{"im\u0061ge":"images/jem/events/photo.png"}'], ['photo.png']];
    }

    /**
     * Resolve all supported representations without conflating local image areas or external hosts.
     * @param array $rows Stored parameter values.
     * @param array $expected Expected paths relative to events.
     * @return void
     */
    #[DataProvider('validPaths')]
    public function testLocalPathResolution(array $rows, array $expected): void
    {
        self::assertSame($expected, self::resolver()->invoke(null, $rows, 'events'));
    }

    /** @return iterable<string, array{string, string}> Bad JSON/image value and expected exception class. */
    public static function invalidPaths(): iterable
    {
        yield 'malformed JSON' => ['{"image":', JsonException::class];
        yield 'scalar parameters' => ['true', UnexpectedValueException::class];
        yield 'null parameters' => ['null', UnexpectedValueException::class];
        yield 'image array' => ['{"image":[]}', UnexpectedValueException::class];
        yield 'image number' => ['{"image":123}', UnexpectedValueException::class];
        yield 'traversal' => [json_encode(['image' => 'images/jem/events/../venues/photo.png']), UnexpectedValueException::class];
        yield 'null byte' => [json_encode(['image' => "images/jem/events/a\0.png"]), UnexpectedValueException::class];
    }

    /**
     * Refuse unreadable references instead of silently offering their files for deletion.
     * @param string $json Stored parameters.
     * @param string $exception Expected failure class.
     * @return void
     */
    #[DataProvider('invalidPaths')]
    public function testUnreadableReferenceFails(string $json, string $exception): void
    {
        $resolver = self::resolver();
        $this->expectException($exception);
        $resolver->invoke(null, [$json], 'events');
    }
}
