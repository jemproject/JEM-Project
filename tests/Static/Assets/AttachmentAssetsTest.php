<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AttachmentAssetsTest extends TestCase
{
    public function testAttachmentJavascriptKeepsDynamicRowBehaviour(): void
    {
        $script = $this->read(JEM_TEST_ROOT . '/media/js/attachments.js');

        foreach (array(
            '.attachment-add',
            '.attachment-remove-row',
            '.attachment-move-up',
            '.attachment-move-down',
            '.clear-attach-field',
            'appendAttachmentRow',
            'updateAttachmentOrdering',
            'clearAttachmentRow',
            'ajaxattachremove',
        ) as $contract) {
            self::assertStringContainsString($contract, $script);
        }
    }

    public function testAttachmentCssKeepsResponsiveGridAndActions(): void
    {
        $css = $this->read(JEM_TEST_ROOT . '/media/css/jem-attachments.css');

        foreach (array(
            '.jem-attachments-tab',
            '--jem-attachment-row-bg',
            'container-type: inline-size',
            '.jem-attachments-global-options',
            '.jem-attachment-status-row',
            '.clear-attach-field',
            '@container (max-width: 620px)',
            'grid-template-columns: 8.5rem max-content minmax(0, 1fr) max-content',
        ) as $contract) {
            self::assertStringContainsString($contract, $css);
        }
    }

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
