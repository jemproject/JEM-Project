<?php
declare(strict_types=1);

/** Isolated rendering of the real quick-action method; never boot Joomla or import data. */
namespace Joomla\CMS {
    final class Factory
    {
        /** @return object Minimal application exposing the fixture language direction. */
        public static function getApplication(): object
        {
            return new class {
                /** @return object Minimal language service for the renderer. */
                public function getLanguage(): object
                {
                    return new class {
                        /** @return bool Left-to-right fixture direction. */
                        public function isRTL(): bool
                        {
                            return false;
                        }
                    };
                }
            };
        }
    }
}
namespace Joomla\CMS\Language {
    final class Text
    {
        public static string $confirmation = '';

        /**
         * Supply the confirmation without a Joomla translation service.
         *
         * @param string $key Translation key.
         * @return string Fixture confirmation.
         */
        public static function _(string $key): string
        {
            return self::$confirmation;
        }
    }
}
namespace Joomla\CMS\HTML {
    final class HTMLHelper
    {
        /**
         * Render a synthetic token, without loading images or starting a session.
         *
         * @param string $key Requested HTML helper.
         * @param mixed ...$arguments Unused image-helper arguments.
         * @return string Inert fixture markup.
         */
        public static function _(string $key, ...$arguments): string
        {
            return $key === 'form.token' ? '<input type="hidden" name="fixture_token" value="1">' : '';
        }
    }
}
namespace {
    define('_JEXEC', 1);

    class JemAdminView
    {
    }

    $root = dirname(__DIR__, 2);
    require $root . '/admin/views/main/view.html.php';

    final class MainQuickActionFixture extends JemViewMain
    {
        /**
         * Render both production task buttons in the actual template's outer form.
         *
         * @param string $form Opening form tag from the main template.
         * @return void
         */
        public function renderFixture(string $form): void
        {
            echo '<!doctype html><html><head><meta charset="utf-8"></head><body>', $form;
            $this->quickiconPostButton('sampledata.load', 'unused.svg', 'Load sample data');
            $this->quickiconPostButton('frontendmenu.create', 'unused.svg', 'Create frontend menu');
            echo \Joomla\CMS\HTML\HTMLHelper::_('form.token');
            echo '</form></body></html>';
        }
    }

    $template = file_get_contents($root . '/admin/views/main/tmpl/default.php');
    if (!preg_match('/<form[^\r\n]*id="application-form"[^\r\n]*/', $template, $match)) {
        throw new RuntimeException('The containing application form was not found');
    }
    $form = preg_replace('/action=".*?"/', 'action="about:blank"', $match[0]);
    $language = parse_ini_file($root . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
    $fixtures = [];
    foreach ([
        'english' => $language['COM_JEM_SAMPLEDATA_CONFIRM'],
        'translated' => '¿Importar? "Sin deshacer" & l\'avertissement <contenido> — Back up first.',
    ] as $name => $confirmation) {
        \Joomla\CMS\Language\Text::$confirmation = $confirmation;
        ob_start();
        (new MainQuickActionFixture())->renderFixture($form);
        $fixtures[] = ['name' => $name, 'confirmation' => $confirmation, 'html' => ob_get_clean()];
    }
    echo json_encode($fixtures, JSON_THROW_ON_ERROR);
}
