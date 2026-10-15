<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttendeeVisibilityTest extends TestCase
{
    #[DataProvider('audiences')]
    public function testBothLayoutsRespectTheConfiguredAudience(
        int $audience,
        int $userId,
        ?int $status,
        bool $admin,
        bool $details,
        array $expected
    ): void {
        $view = new class {
            public $settings;
            public $user;
            public $registration;
            public $registers = null;
            public array $regs = [];
        };
        $view->settings = new class ($audience, $details) {
            public function __construct(private int $audience, private bool $details)
            {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'event_show_attendeenames' => $this->audience,
                    'event_show_more_attendeedetails' => $this->details,
                    default => $default,
                };
            }
        };
        $view->user = new class ($userId, $admin) {
            public function __construct(public int $id, private bool $admin)
            {
            }

            public function get(string $key): bool
            {
                return $key === 'guest' && $this->id === 0;
            }

            public function authorise(string $action, string $asset): bool
            {
                return $this->admin && $action === 'core.manage' && $asset === 'com_jem';
            }
        };
        $view->registration = $status === null ? false : (object) ['status' => $status];
        $view->regs['all'] = [
            (object) ['uid' => 11, 'status' => 1],
            (object) ['uid' => 12, 'status' => 2],
            (object) ['uid' => 13, 'status' => -1],
            (object) ['uid' => 14, 'status' => 0],
        ];
        $view->regs['attending'] = [$view->regs['all'][0]];

        // Execute the real view and layout gates, not a duplicate of their logic.
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/event/view.html.php');
        $prepare = $this->fragment($source, '$registers_array = array();', '//JemHelper::addLogEntry');
        $run = function () use ($prepare, $userId): void {
            $registration = $this->registration;
            eval($prepare);
        };
        $run->call($view);

        foreach (['default_attendees.php', 'responsive/default_attendees.php'] as $layout) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/event/tmpl/' . $layout);
            $gate = $this->fragment($source, '$showAttendenenames =', 'if ($showAttendenenames) :');
            $rowGate = $this->fragment($source, 'if($showAttendenenames==4)', "echo '<li class=");
            $render = function () use ($gate, $rowGate): array {
                if (!$this->registers) {
                    return [];
                }

                eval($gate);
                $visible = [];
                if ($showAttendenenames) {
                    // Keep continue statements inside the real foreach scope.
                    eval('foreach ($this->registers as $register) {' . $rowGate . '$visible[] = $register->uid; }');
                }

                return $visible;
            };
            self::assertSame($expected, $render->call($view), $layout);
        }
    }

    public static function audiences(): iterable
    {
        $users = [
            'guest' => [0, null, false],
            'logged in without registration' => [10, null, false],
            'confirmed' => [11, 1, false],
            'waiting' => [12, 2, false],
            'declined' => [13, -1, false],
            'invited' => [14, 0, false],
            'admin without registration' => [15, null, true],
        ];

        foreach ([0, 1, 2, 3, 4, 5, 99] as $audience) {
            foreach ($users as $name => [$id, $status, $admin]) {
                foreach ([false, true] as $details) {
                    $allowed = match ($audience) {
                        1 => $admin,
                        2 => $status === 1,
                        3 => true,
                        4, 5 => $id > 0,
                        default => false,
                    };
                    $expected = $allowed ? ($details && $id > 0 ? [11, 12, 13, 14] : [11]) : [];
                    if ($audience === 4) {
                        $expected = array_values(array_intersect($expected, [$id]));
                    } elseif ($audience === 2) {
                        $expected = array_values(array_diff($expected, [12]));
                    }

                    yield $audience . ' / ' . $name . ' / details ' . (int) $details
                        => [$audience, $id, $status, $admin, $details, $expected];
                }
            }
        }
    }

    public function testSettingsPreserveExistingValuesAndAddLoggedInUsers(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        $field = $xml->xpath('//field[@name="event_show_attendeenames"]')[0];
        self::assertSame('2', (string) $field['default']);
        $options = [];
        foreach ($field->option as $option) {
            $options[(int) $option['value']] = (string) $option;
        }
        ksort($options);
        self::assertSame([
            0 => 'COM_JEM_NOBODY',
            1 => 'COM_JEM_ONLYADMINS',
            2 => 'COM_JEM_ALLATTENDING',
            3 => 'COM_JEM_EVERYBODY',
            4 => 'COM_JEM_ONLYUSER',
            5 => 'COM_JEM_LOGGED_IN_USERS',
        ], $options);
        $language = parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
        self::assertSame('- Event attendees only -', $language['COM_JEM_ALLATTENDING']);
        self::assertSame('- Logged-in users -', $language['COM_JEM_LOGGED_IN_USERS']);
    }

    private function fragment(string $source, string $startMarker, string $endMarker): string
    {
        $start = strpos($source, $startMarker);
        self::assertNotFalse($start);
        $end = strpos($source, $endMarker, $start);
        self::assertNotFalse($end);

        return substr($source, $start, $end - $start);
    }
}
