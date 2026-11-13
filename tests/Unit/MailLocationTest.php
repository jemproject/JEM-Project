<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/maillocation.class.php';

/** Optional location formatting independent of Joomla and message transport. */
final class MailLocationTest extends TestCase
{
    /** @return iterable<string,array{string,?string,?string,string}> Empty and partial locations. */
    public static function locations(): iterable
    {
        yield 'missing' => ["Venue: {venue} / {city}\nNext", null, null, 'Next'];
        yield 'spaces' => ["Venue: {venue} / {city}\nNext", '  ', "\t", 'Next'];
        yield 'venue only' => ["Venue: {venue} / {city}\nNext", 'Hall', '', "Venue: {venue}\nNext"];
        yield 'city only' => ["Venue: {venue} / {city}\nNext", '', 'Madrid', "Venue: {city}\nNext"];
        yield 'both' => ["Venue: {venue} / {city}\nNext", 'Hall', 'Madrid', "Venue: {venue} / {city}\nNext"];
        yield 'Spanish CRLF' => ["Date\r\nLugar: {venue} / {city}\r\nNext", null, null, "Date\r\nNext"];
        yield 'German reversed' => ["Ort: {city}, {venue}\n", 'Hall', '', "Ort: {venue}\n"];
        yield 'Japanese final row' => ['場所：{venue} / {city}', '', '', ''];
        yield 'indent and dash' => ["  Lugar: {venue} — {city}\n", '', 'Madrid', "  Lugar: {city}\n"];
        yield 'HTML is not a text row' => ['<p>Venue: {venue} / {city}</p>', '', '', '<p>Venue: {venue} / {city}</p>'];
        yield 'additional variable' => ['Venue: {venue} / {city} {event_title}', '', '', 'Venue: {venue} / {city} {event_title}'];
        yield 'other variables' => ['Venue: {event_title} / {comment}', '', '', 'Venue: {event_title} / {comment}'];
        yield 'duplicate variable' => ['Venue: {venue} / {venue}', '', '', 'Venue: {venue} / {venue}'];
        yield 'static content' => ["Description:\nVenue:  / \nEnd", '', '', "Description:\nVenue:  / \nEnd"];
    }

    /** @param string $template Input. @param ?string $venue Venue. @param ?string $city City. @param string $expected Output. @return void */
    #[DataProvider('locations')]
    public function testOptionalLocationRow(string $template, ?string $venue, ?string $city, string $expected): void
    {
        self::assertSame($expected, JemMailLocation::prepare($template, $venue, $city));
    }

    /** Removing two positional markers must not change the meaning of any later argument. */
    public function testLegacyArgumentsRemainInTheirOriginalPositions(): void
    {
        $template = "Hello %s\nVenue: %s / %s\nPlaces: %d\nDescription: %s\nLink: %s\n100%% ready";
        $content = "Venue:  / \nLiteral %s {venue} <b>description</b>";
        $args = ['Ana', '', '', 3, $content, 'https://example.invalid/?a=1&b=2'];
        self::assertSame("Hello Ana\nPlaces: 3\nDescription: " . $content
            . "\nLink: https://example.invalid/?a=1&b=2\n100% ready", JemMailLocation::renderLegacy($template, $args, 1));
        $args[1] = 'Main Hall';
        self::assertStringContainsString("Venue: Main Hall\nPlaces: 3", JemMailLocation::renderLegacy($template, $args, 1));
        $args[2] = 'Madrid';
        self::assertSame(vsprintf($template, $args), JemMailLocation::renderLegacy($template, $args, 1));
    }

    /** Numbered translations can reorder fields and Joomla aliases without shifting subsequent data. */
    public function testReorderedLegacyTranslations(): void
    {
        $template = 'Lugar: %3$s / %2$s' . "\n" . 'Hola %1$s: %4$d plazas';
        self::assertSame('Hola Ana: 2 plazas', JemMailLocation::renderLegacy($template, ['Ana', '', '', 2], 1));
        self::assertSame("Lugar: Madrid\nHola Ana: 2 plazas", JemMailLocation::renderLegacy($template, ['Ana', '', 'Madrid', 2], 1));
        $alias = "Lugar: [[%2:venue]] / [[%3:city]]\nHola [[%1:name]]";
        self::assertSame('Hola Ana', JemMailLocation::renderLegacy($alias, ['Ana', '', ''], 1));
    }

    /** Unusual legacy custom formats remain untouched instead of receiving an incomplete index conversion. */
    public function testCustomPrecisionAndWidthKeepExistingPrintfBehaviour(): void
    {
        $template = "Price: %.2f\nVenue: %s / %s\nCode: %04d";
        $args = [1.25, '', '', 7];
        self::assertSame(vsprintf($template, $args), JemMailLocation::renderLegacy($template, $args, 1));
    }
}
