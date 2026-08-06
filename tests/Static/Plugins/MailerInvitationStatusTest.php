<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MailerInvitationStatusTest extends TestCase
{
    private string $mailer;
    private string $language;

    protected function setUp(): void
    {
        $this->mailer = (string) file_get_contents(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/mailer.php');
        $this->language = (string) file_get_contents(
            JEM_TEST_ROOT . '/plugins/plg_jem_mailer/language/en-GB/plg_jem_mailer.ini'
        );
    }

    public function testStatusZeroHasExplicitInvitationBranchesForUserAndAdminMail(): void
    {
        self::assertSame(2, substr_count($this->mailer, 'case  0: // invited, not answered yet'));
        self::assertSame(2, substr_count($this->mailer, 'switch ((int) $event->status)'));
        self::assertStringContainsString('PLG_JEM_MAILER_USER_REG_SELF_INVITATION_BODY_', $this->mailer);
        self::assertStringContainsString('PLG_JEM_MAILER_ADMIN_REG_SELF_INVITATION_BODY_', $this->mailer);
        self::assertSame(2, substr_count($this->mailer, '$bodyUsesActor = false;'));
    }

    public function testInvitationLanguageCoversOnBehalfAndSameAccountFlows(): void
    {
        foreach (array(
            'PLG_JEM_MAILER_USER_REG_INVITATION_BODY_A',
            'PLG_JEM_MAILER_USER_REG_INVITATION_BODY_B',
            'PLG_JEM_MAILER_USER_REG_SELF_INVITATION_BODY_9',
            'PLG_JEM_MAILER_USER_REG_SELF_INVITATION_BODY_A',
            'PLG_JEM_MAILER_ADMIN_REG_INVITATION_BODY_9',
            'PLG_JEM_MAILER_ADMIN_REG_INVITATION_BODY_A',
            'PLG_JEM_MAILER_ADMIN_REG_SELF_INVITATION_BODY_8',
            'PLG_JEM_MAILER_ADMIN_REG_SELF_INVITATION_BODY_9',
        ) as $key) {
            self::assertStringContainsString($key . '=', $this->language);
        }
    }

    public function testMailerNoLongerDescribesAValidRegistrationAsUndecided(): void
    {
        self::assertStringNotContainsString('undecided', strtolower($this->language));
        self::assertStringContainsString('Event Attendance Status Unknown', $this->language);
    }
}
