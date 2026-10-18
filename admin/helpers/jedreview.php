<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Local, per-user community card preferences; no tracking or external requests. */
final class JemJedReview
{
    public const URL = 'https://extensions.joomla.org/extension/jem/';
    public static function cards(): array
    {
        return [
            'donate' => [
                'url' => 'https://www.joomlaeventmanager.net/project/donate',
                'title' => 'COM_JEM_MAIN_DONATE',
                'description' => 'COM_JEM_MAIN_DONATE_TEXT',
                'button' => 'COM_JEM_COMMUNITY_DONATE_BUTTON',
                'icon' => 'icon-48-donate.svg',
            ],
            'jed' => [
                'url' => self::URL,
                'title' => 'COM_JEM_JED_REVIEW_TITLE',
                'description' => 'COM_JEM_JED_REVIEW_DESCRIPTION',
                'button' => 'COM_JEM_JED_REVIEW_BUTTON',
                'icon' => 'icon-48-jed-review.svg',
            ],
        ];
    }

    private static function profileKey(string $card): string
    {
        if (!isset(self::cards()[$card])) {
            throw new InvalidArgumentException('Invalid community card.');
        }

        // Preserve the preference already used by the initial JED card.
        return $card === 'jed' ? 'com_jem.jed_review_dismissed' : 'com_jem.donate_card_dismissed';
    }

    public static function allowed(object $app, bool $migrationPending): bool
    {
        $user = $app->getIdentity();

        return $app->isClient('administrator')
            && (int) $user->id > 0
            && $user->authorise('core.manage', 'com_jem')
            && !$migrationPending;
    }

    public static function dismissed(object $db, int $userId, string $card = 'jed'): bool
    {
        self::requireUser($userId);
        $query = $db->getQuery(true)
            ->select($db->quoteName('profile_value'))
            ->from($db->quoteName('#__user_profiles'))
            ->where($db->quoteName('user_id') . ' = ' . $userId)
            ->where($db->quoteName('profile_key') . ' = ' . $db->quote(self::profileKey($card)));

        return (string) $db->setQuery($query)->loadResult() === '1';
    }

    public static function save(object $db, int $userId, string $action, string $card = 'jed'): void
    {
        self::requireUser($userId);
        $profileKey = self::profileKey($card);
        if (!in_array($action, ['dismiss', 'show'], true)) {
            throw new InvalidArgumentException('Invalid invitation action.');
        }

        $db->transactionStart();
        try {
            // Serialise requests for this user without rewriting their account or other preferences.
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('id') . ' = ' . $userId);
            if (!(int) $db->setQuery((string) $query . ' FOR UPDATE')->loadResult()) {
                throw new RuntimeException('User no longer exists.');
            }

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__user_profiles'))
                ->where($db->quoteName('user_id') . ' = ' . $userId)
                ->where($db->quoteName('profile_key') . ' = ' . $db->quote($profileKey));
            $db->setQuery($query)->execute();
            if ($action === 'dismiss') {
                $profile = (object) [
                    'user_id' => $userId,
                    'profile_key' => $profileKey,
                    'profile_value' => '1',
                    'ordering' => 0,
                ];
                $db->insertObject('#__user_profiles', $profile);
            }
            $db->transactionCommit();
        } catch (Throwable $error) {
            $db->transactionRollback();
            throw $error;
        }
    }

    private static function requireUser(int $userId): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('An authenticated user is required.');
        }
    }
}
