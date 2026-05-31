<?php
/**
 * Draws progress as [=====>-----] instead of PHPUnit's dot stream.
 */

declare(strict_types=1);

namespace JemProject\Tests\Support\PhpUnit;

final class ArrowProgressPrinter
{
    private int $total = 0;
    private int $current = 0;
    private bool $started = false;

    public function __construct(private readonly int $width = 28)
    {
    }

    public function start(int $total): void
    {
        $this->total = max(0, $total);
        $this->current = 0;
        $this->started = true;

        $this->render();
    }

    public function advance(): void
    {
        if (!$this->started) {
            return;
        }

        $this->current = min($this->total, $this->current + 1);

        $this->render();
    }

    private function render(): void
    {
        $percent = $this->total > 0 ? (int) round(($this->current / $this->total) * 100) : 100;
        $filled = $this->total > 0 ? (int) floor(($this->current / $this->total) * $this->width) : $this->width;
        $bar = $this->bar($filled);

        fwrite(STDOUT, sprintf("%3d/%-3d [%s] %3d%%%s", $this->current, $this->total, $bar, $percent, PHP_EOL));
    }

    private function bar(int $filled): string
    {
        $filled = max(0, min($this->width, $filled));

        if ($filled === 0) {
            return str_repeat('-', $this->width);
        }

        if ($filled >= $this->width) {
            return str_repeat('=', $this->width);
        }

        return str_repeat('=', max(0, $filled - 1)) . '>' . str_repeat('-', $this->width - $filled);
    }
}
