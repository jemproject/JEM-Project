<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UpdateCheckViewContractTest extends TestCase
{
    public function testUpdatecheckUsesAdministratorTemplateThemeVariables(): void
    {
        $code = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/views/updatecheck/tmpl/default.php'
        );

        foreach (array(
            '--card-body-bg',
            '--card-header-bg',
            '--card-header-color',
            '--body-bg',
            '--body-color',
            '--border-color',
            '--secondary-color',
        ) as $variable) {
            self::assertStringContainsString(
                'var(' . $variable,
                $code,
                'The update check view must inherit ' . $variable . ' from the administrator template.'
            );
        }

        self::assertStringNotContainsString(
            '--jem-updatecheck-bg: var(--bs-body-bg',
            $code
        );
        self::assertStringNotContainsString(
            '--jem-updatecheck-color: var(--bs-body-color',
            $code
        );
    }

    public function testAvailableUpdateLinksToJoomlaUpdateManagerFromStatus(): void
    {
        $code = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/views/updatecheck/tmpl/default.php'
        );

        self::assertSame(
            1,
            substr_count($code, 'index.php?option=com_installer&view=update&filter[search]=JEM'),
            'The Joomla update manager link should be shown once, in the status notice.'
        );
        self::assertMatchesRegularExpression(
            '/<div class="jem-updatecheck-status-action">.*?COM_JEM_UPDATECHECK_UPDATE.*?<\/div>/s',
            $code
        );
        self::assertStringContainsString(
            '<?php if ((int) $update->current === -1) : ?>',
            $code
        );

        $language = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini'
        );
        self::assertStringContainsString(
            'COM_JEM_UPDATECHECK_UPDATE="Open Joomla Updates"',
            $language
        );
    }

    public function testInformationLinksUseSecondaryButtonStyle(): void
    {
        $code = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/views/updatecheck/tmpl/default.php'
        );

        self::assertStringContainsString(
            '<a class="btn btn-secondary" href="https://www.joomlaeventmanager.net/"',
            $code
        );
        self::assertStringNotContainsString(
            '<a class="btn btn-primary" href="https://www.joomlaeventmanager.net/"',
            $code
        );
    }

    public function testReleaseDateIsNotRepeatedInVersionNotes(): void
    {
        $code = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/views/updatecheck/tmpl/default.php'
        );

        self::assertSame(
            1,
            substr_count($code, "Text::_('COM_JEM_UPDATECHECK_RELEASE_DATE')"),
            'The release date should be shown in the server details only, not repeated in version notes.'
        );
        self::assertStringNotContainsString('$notesDate', $code);
        self::assertStringNotContainsString("Text::_('COM_JEM_UPDATECHECK_NOTES')", $code);
        self::assertStringContainsString('<div class="jem-updatecheck-notes">', $code);
        self::assertStringContainsString(
            "Text::_('COM_JEM_UPDATECHECK_STABLE_CHANGELOG')",
            $code
        );
        self::assertStringContainsString(
            "Text::_('COM_JEM_UPDATECHECK_BETA_CHANGELOG')",
            $code
        );
        self::assertStringContainsString(
            "preg_match('/(?:alpha|beta|rc)/i', (string) \$notesVersion)",
            $code
        );
        self::assertStringContainsString(
            '$changelogUrl   = $isPrerelease ? $update->betachangelog : $update->stablechangelog;',
            $code
        );
        self::assertStringContainsString('jemUpdatecheckRenderNote($note)', $code);
        self::assertStringNotContainsString(
            '<a class="btn btn-secondary" href="<?php echo htmlspecialchars((string) $update->info',
            $code
        );
    }
}
