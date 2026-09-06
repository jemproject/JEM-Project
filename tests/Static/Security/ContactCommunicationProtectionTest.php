<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ContactCommunicationProtectionTest extends TestCase
{
    public function testEventContactEmailUsesJoomlaCloakingWithMailtoLinks(): void
    {
        foreach ($this->eventTemplates() as $path) {
            $template = $this->read($path);

            self::assertStringContainsString(
                "HTMLHelper::_('email.cloak', \$contact->conemail, true)",
                $template,
                $path
            );
        }
    }

    public function testTelephoneAndMobileUseTheProtectedCallableLink(): void
    {
        foreach ($this->eventTemplates() as $path) {
            $template = $this->read($path);

            self::assertStringContainsString(
                'JemOutput::protectedTelephoneLink($contact->contelephone)',
                $template,
                $path
            );
            self::assertStringContainsString(
                'JemOutput::protectedTelephoneLink($contact->conmobile)',
                $template,
                $path
            );
            self::assertStringNotContainsString('$this->escape($contact->contelephone)', $template, $path);
            self::assertStringNotContainsString('$this->escape($contact->conmobile)', $template, $path);
        }
    }

    public function testTelephonePayloadIsEncodedAndClientTargetIsRestricted(): void
    {
        $output = $this->read('site/classes/output.class.php');
        $script = $this->read('media/js/contact-protection.js');

        self::assertStringContainsString('static public function protectedTelephoneLink(', $output);
        self::assertStringContainsString("preg_replace('/[^0-9+*#,;]/'", $output);
        self::assertStringContainsString('strrev(base64_encode((string) $payload))', $output);
        self::assertStringContainsString('data-jem-protected-contact=', $output);
        self::assertStringContainsString("'media/com_jem/js/contact-protection.js'", $output);
        self::assertStringContainsString("link.href = 'tel:' + payload.target", $script);
        self::assertStringContainsString('link.textContent = payload.label', $script);
        self::assertStringContainsString('/^[0-9+*#,;]+$/.test(payload.target)', $script);
        self::assertStringNotContainsString('innerHTML', $script);
    }

    /**
     * @return array<int, string>
     */
    private function eventTemplates(): array
    {
        return array(
            'site/views/event/tmpl/default.php',
            'site/views/event/tmpl/responsive/default.php',
        );
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
