<?php

declare(strict_types=1);

use JemProject\Tests\AttendeeCsv\AttendeeCsv;
use JemProject\Tests\AttendeeCsv\JemConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verify the exported registration identity and surrounding CSV columns. */
final class AttendeeCsvRegistrationIdTest extends TestCase
{
    /** @return void Load the production exporter with isolated data/configuration boundaries. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/attendee_csv_environment.php';
    }

    /** @return iterable<string, array{string, bool, string}> Supported pricing, comments and delimiter options. */
    public static function exportOptions(): iterable
    {
        $modes = is_file(JEM_TEST_ROOT . '/site/classes/pricingquote.class.php')
            ? ['classic', 'single', 'multiple', 'priced']
            : ['classic'];
        foreach ($modes as $mode) {
            foreach ([false, true] as $comments) {
                foreach ([';', ','] as $separator) {
                    yield $mode . '/comments=' . (int) $comments . '/separator=' . $separator => [$mode, $comments, $separator];
                }
            }
        }
    }

    /**
     * Export distinct registration/user IDs and parse the CSV as a consumer would.
     *
     * @param string $mode Event pricing mode.
     * @param bool $comments Whether the optional comment column is enabled.
     * @param string $separator Administrator-selected CSV separator.
     * @return void
     */
    #[DataProvider('exportOptions')]
    public function testRegIdMatchesRegistrationWithoutChangingOtherColumns(string $mode, bool $comments, string $separator): void
    {
        JemConfig::$settings = ['regallowcomments' => $comments, 'csv_separator' => $separator];
        $model = new AttendeeCsv();
        $model->pricingMode = $mode;
        $states = [
            [51, 259, -1, 0, 'COM_JEM_ATTENDEES_NOT_ATTENDING'],
            [45, 256, 1, 0, 'COM_JEM_ATTENDEES_ATTENDING'],
            [47, 258, 0, 0, 'COM_JEM_ATTENDEES_INVITED'],
            [46, 257, 1, 1, 'COM_JEM_ATTENDEES_ON_WAITINGLIST'],
        ];
        foreach ($states as [$id, $uid, $status, $waiting]) {
            $model->rows[] = (object) [
                'id' => $id, 'uid' => $uid, 'status' => $status, 'waiting' => $waiting,
                'name' => 'Name, "quoted"', 'username' => 'user' . $uid, 'email' => 'user' . $uid . '@example.invalid',
                'uregdate' => '', 'places' => 2, 'comment' => '<b>Comment; "quoted"</b>',
                'currency' => 'EUR', 'grand_total' => '20.00',
            ];
        }

        ob_start();
        try {
            $result = $model->getCsv();
            $csv = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertTrue($result);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        try {
            $header = ['COM_JEM_NAME', 'COM_JEM_USERNAME', 'COM_JEM_EMAIL', 'COM_JEM_REGDATE',
                $mode === 'classic' ? 'COM_JEM_ATTENDEES_PLACES' : 'COM_JEM_PRICED_REGISTRATION_ORDER',
                'COM_JEM_HEADER_WAITINGLIST_STATUS'];
            if ($comments) {
                $header[] = 'COM_JEM_COMMENT';
            }
            $header[] = 'Reg.ID';
            self::assertSame($header, fgetcsv($stream, 0, $separator, '"', ''));

            foreach ($states as [$id, $uid, $status, $waiting, $label]) {
                $expected = ['Name, "quoted"', 'user' . $uid, 'user' . $uid . '@example.invalid', '',
                    $mode === 'classic' ? '2' : '2x Admission (Room A) | EUR 20.00', $label];
                if ($comments) {
                    $expected[] = 'Comment; "quoted"';
                }
                $expected[] = (string) $id;
                self::assertSame($expected, fgetcsv($stream, 0, $separator, '"', ''), 'Reg.ID must identify the registration, not its user');
            }
            self::assertFalse(fgetcsv($stream, 0, $separator, '"', ''));
        } finally {
            fclose($stream);
        }
    }
}
