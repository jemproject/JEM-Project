<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NativeModalSelectorTest extends TestCase
{
    public function testSingleItemSelectorsUseJoomlaModalSelectField(): void
    {
        foreach (array(
            'site/models/fields/modal/venue.php',
            'admin/models/fields/modal/venue.php',
            'admin/models/fields/venue.php',
            'admin/models/fields/categories.php',
            'admin/models/fields/event.php',
        ) as $relativePath) {
            $code = $this->read($relativePath);

            self::assertStringContainsString('ModalSelectField', $code, $relativePath);
            self::assertStringContainsString('return parent::getInput();', $code, $relativePath);
            self::assertStringContainsString('$this->iconSelect', $code, $relativePath);
            self::assertStringNotContainsString('bootstrap.renderModal', $code, $relativePath);
            self::assertStringNotContainsString('button2-left', $code, $relativePath);
            self::assertStringNotContainsString('.modal(', $code, $relativePath);
        }
    }

    public function testChooserViewsSupportNativePostMessageAndLegacyOverrides(): void
    {
        foreach (array(
            'admin/views/categoryelement/tmpl/default.php',
            'admin/views/venueelement/tmpl/default.php',
            'admin/views/eventelement/tmpl/default.php',
            'site/views/editevent/tmpl/choosevenue.php',
            'site/views/editevent/tmpl/responsive/choosevenue.php',
        ) as $relativePath) {
            $code = $this->read($relativePath);

            self::assertStringContainsString("useScript('modal-content-select')", $code, $relativePath);
            self::assertStringContainsString('data-content-select', $code, $relativePath);
            self::assertStringContainsString('data-id=', $code, $relativePath);
            self::assertStringContainsString('data-title=', $code, $relativePath);
            self::assertStringContainsString('JoomlaExpectingPostMessage', $code, $relativePath);
        }
    }

    public function testSpecialSelectorsUseNativeBootstrapWithoutJquery(): void
    {
        foreach (array(
            'site/models/fields/modal/users.php',
            'admin/models/fields/imageselect.php',
            'admin/views/attendee/tmpl/default.php',
        ) as $relativePath) {
            $code = $this->read($relativePath);

            self::assertStringContainsString('bootstrap.Modal.getInstance', $code, $relativePath);
            self::assertStringNotContainsString('jquery.framework', strtolower($code), $relativePath);
            self::assertStringNotContainsString("useScript('jquery')", $code, $relativePath);
            self::assertStringNotContainsString('jQuery(', $code, $relativePath);
            self::assertStringNotContainsString('$(', $code, $relativePath);
            self::assertStringNotContainsString('SqueezeBox', $code, $relativePath);
        }
    }

    public function testAttendeeViewsDoNotCallRemovedSqueezeBoxApi(): void
    {
        foreach (array(
            'site/views/attendees/tmpl/default.php',
            'site/views/attendees/tmpl/responsive/default.php',
        ) as $relativePath) {
            self::assertStringNotContainsString('SqueezeBox', $this->read($relativePath), $relativePath);
        }
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
