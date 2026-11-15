<?php
/** Render registration forms without changing a site or sending notifications. */
declare(strict_types=1);
define('_JEXEC', 1);
$source = dirname(__DIR__, 2);
class RegistrationLayoutText {
    public static array $strings = [];
    public static function _($key): string { return self::$strings[$key] ?? $key; }
    public static function sprintf($key, ...$args): string { return sprintf(self::_($key), ...$args); }
}
class RegistrationLayoutHtml { public static function _(...$args): string { return $args[0] === 'date' ? '05 December 2026 13:00' : ''; } }
class RegistrationLayoutRoute { public static function _($url): string { return $url; } }
class_alias(RegistrationLayoutText::class, 'Joomla\CMS\Language\Text');
class_alias(RegistrationLayoutHtml::class, 'Joomla\CMS\HTML\HTMLHelper');
class_alias(RegistrationLayoutRoute::class, 'Joomla\CMS\Router\Route');
RegistrationLayoutText::$strings = parse_ini_file($source . '/site/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
function Text($key): string { return RegistrationLayoutText::_($key); }
    $ui = $htmlPages = [];
    foreach (['default_regform.php', 'responsive/default_regform.php'] as $layout) {
        foreach (['priced_new', 'priced_existing', 'priced_waiting', 'priced_invited', 'priced_cancelled',
            'priced_closed', 'priced_areas', 'priced_series', 'priced_classic_event', 'unpriced_priced_event',
            'requested_no_answer', 'requested_no_answer_closed', 'classic_new', 'classic_existing', 'classic_areas'] as $case) {
            $view = new class {
                public $showRegForm = true, $print = false, $item, $registereduser = null, $registers = [],
                    $registration = null, $isregistered = false, $allowRegistration = true, $allowAnnulation = true,
                    $user, $jemsettings, $capacityRegistration, $root, $layout,
                    $dateUnregistationUntil = 1796475600, $cancellationDeadlineRendered = false;
                public function getModel($name): object { return new class { public function getListRecurrenceEventsbyId(...$args): array { return []; } }; }
                public function loadTemplate($name): string {
                    $base = $this->root . '/site/views/event/tmpl/';
                    return $this->render($base . (str_starts_with($this->layout, 'responsive/') ? 'responsive/' : '') . 'default_' . $name . '.php');
                }
                public function escape($text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
                public function render(string $path): string { ob_start(); include $path; return ob_get_clean(); }
            };
            $view->root = $source;
            $view->layout = $layout;
            $view->item = (object) ['id' => 11, 'did' => 11, 'maxplaces' => 20, 'booked' => 4, 'reservedplaces' => 0,
                'waitinglist' => 1, 'maxbookeduser' => 10, 'minbookeduser' => 1, 'seriesbooking' => $case === 'priced_series' ? 1 : 0,
                'singlebooking' => 1, 'unregistra' => 1, 'recurrence_type' => $case === 'priced_series' ? 1 : 0, 'recurrence_first_id' => 11,
                'requestanswer' => str_starts_with($case, 'requested_no_answer') ? 1 : 0, 'registra' => 1,
                'pricing_mode' => str_starts_with($case, 'classic_') || $case === 'priced_classic_event' ? 'classic' : 'multiple'];
            $view->user = (object) ['id' => 42];
            $view->jemsettings = (object) ['regallowcomments' => 1];
            $view->capacityRegistration = (object) ['enabled' => false, 'options' => []];
            if (!in_array($case, ['priced_new', 'classic_new', 'requested_no_answer', 'requested_no_answer_closed'], true)) {
                $status = match ($case) { 'priced_waiting' => 2, 'priced_invited' => 0, 'priced_cancelled' => -1, default => 1 };
                $view->registration = (object) ['id' => 101, 'status' => $status, 'places' => 4,
                    'pricing_mode' => in_array($case, ['unpriced_priced_event', 'classic_existing', 'classic_areas'], true) ? 'classic' : 'multiple',
                    'comment' => '<script>bad()</script>'];
                $view->isregistered = $status;
                $view->registers = [$view->registration];
                $view->registereduser = 0;
            }
            if (in_array($case, ['priced_areas', 'classic_areas'], true)) {
                $view->capacityRegistration->options = [
                    ['key' => 'area:1', 'current_quantity' => 2, 'space_name' => 'Hall', 'area_name' => 'A'],
                    ['key' => 'area:2', 'current_quantity' => 2, 'space_name' => 'Hall', 'area_name' => 'B']];
            }
            if ($case === 'classic_areas') {
                $view->capacityRegistration->enabled = true;
                $view->capacityRegistration->minimum = 1;
                $view->capacityRegistration->maximum = 10;
                $view->capacityRegistration->current = ['area:1' => 2, 'area:2' => 2];
                foreach ($view->capacityRegistration->options as &$option) {
                    $option['input_max'] = 10;
                    $option['remaining'] = 10;
                }
                unset($option);
            }
            $view->allowAnnulation = $case !== 'priced_closed';
            $view->allowRegistration = $case !== 'requested_no_answer_closed';
            $html = $view->render($source . '/site/views/event/tmpl/' . $layout);
            $doc = new DOMDocument();
            @$doc->loadHTML('<!doctype html><meta charset="utf-8">' . $html);
            $xpath = new DOMXPath($doc);
            $count = static fn(string $path): int => $xpath->query($path)->length;
            $key = $layout . '/' . $case;
            $ui[$key] = [
                'attend_enabled' => $count('//input[@name="reg_check" and @value="1" and not(@disabled)]') === 1,
                'editable_quantity' => $count('//input[@name="cancelplaces" and @type="number"]') === 1,
                'cancel_selected' => $count('//input[@name="reg_check" and @value="-1" and @checked]') === 1,
                'fixed_quantity' => $xpath->evaluate('string(//input[@name="cancelplaces" and @type="hidden"]/@value)'),
                'submit' => $count('//*[@id="jem_send_attend" and not(@disabled)]') === 1,
                'dialog' => $count('//dialog[contains(@class,"jem-cancellation-dialog")]') === 1,
                'editable_areas' => $count('//input[@data-cancel-area]'),
                'script' => $count('//script'),
                'series' => $count('//input[@name="reg_check_series"]'),
                'notice' => str_contains($html, Text('COM_JEM_PRICED_REGISTRATION_REQUIRES_ORDER')),
            ];
            $htmlPages[$key] = $html;
        }
    }
echo json_encode(['html' => $htmlPages], JSON_THROW_ON_ERROR);
