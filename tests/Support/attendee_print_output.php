<?php

declare(strict_types=1);

// Only date/footer presentation is stubbed; the print layouts themselves are real.
final class JemOutput
{
    public static function formatLongDateTime(...$arguments): string
    {
        return 'Test date';
    }

    public static function footer(): string
    {
        return '';
    }
}

final class AttendeePrintOutput
{
    public object $event;
    public object $settings;
    public object $jemsettings;
    public int $enableemailaddress = 1;
    public bool $isPriced = false;

    public function __construct(public array $rows)
    {
        $this->event = (object) ['title' => 'Test event', 'dates' => '', 'times' => '',
            'enddates' => '', 'endtimes' => '', 'waitinglist' => 0];
        $this->settings = new Joomla\Registry\Registry();
        $this->jemsettings = (object) ['regallowcomments' => 0];
    }

    public function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public function render(string $file): string
    {
        ob_start();
        try {
            include $file;
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
