<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserFactoryInterface;

require_once dirname(__DIR__) . '/classes/backendacl.class.php';

/** Local authorship is chosen by the importer, never resolved from source-site IDs. */
final class JemImportAuthorHelper
{
    public const TYPES = array('events', 'venues', 'categories', 'types', 'attachments');

    /**
     * Check a local account's author eligibility independently of import permission.
     *
     * @param object $user Local Joomla user.
     * @param string $type Imported entity kind.
     * @return bool Whether this active account can be selected as author.
     */
    public static function isEligible(object $user, string $type): bool
    {
        if (!in_array($type, self::TYPES, true) || empty($user->id) || !empty($user->guest) || !empty($user->block)) {
            return false;
        }

        $authorise = static function (string $action) use ($user): bool {
            return (bool) $user->authorise($action, 'com_jem');
        };
        if ($authorise('core.admin')) {
            return true;
        }
        if ($type === 'categories') {
            return $authorise('jem.categories.access') && $authorise('core.create');
        }
        if ($type === 'attachments') {
            // Attachments have no standalone create permission; use content editors.
            return JemBackendAclPolicy::allows('event', 'edit', null, $user->id, $authorise)
                || JemBackendAclPolicy::allows('venue', 'edit', null, $user->id, $authorise)
                || ($authorise('jem.categories.access') && $authorise('core.edit'));
        }

        return JemBackendAclPolicy::allows(rtrim($type, 's'), 'create', null, $user->id, $authorise);
    }

    /**
     * Reject a missing, blocked or ineligible author before processing a CSV.
     *
     * @param string $type Imported entity kind.
     * @param int $id Selected local user ID.
     * @return void
     * @throws RuntimeException When the selection cannot be used.
     */
    public static function validate(string $type, int $id): void
    {
        $user = $id > 0 ? Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id) : null;
        if (!$user || (int) $user->id !== $id || !self::isEligible($user, $type)) {
            throw new RuntimeException(Text::_('COM_JEM_IMPORT_AUTHOR_INVALID'));
        }
    }

    /**
     * Build permission-filtered author choices without exposing email addresses.
     *
     * @return array<string, array<object>> User options grouped by entity kind.
     */
    public static function options(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $rows = $db->setQuery($db->getQuery(true)
            ->select($db->quoteName(array('id', 'name', 'username')))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('block') . ' = 0')
            ->order($db->quoteName('name') . ', ' . $db->quoteName('id')))->loadObjectList();
        $factory = Factory::getContainer()->get(UserFactoryInterface::class);
        $options = array_fill_keys(self::TYPES, array());
        foreach ($rows as $row) {
            $user = $factory->loadUserById((int) $row->id);
            foreach (self::TYPES as $type) {
                if (self::isEligible($user, $type)) {
                    $options[$type][] = (object) array(
                        'value' => (int) $row->id,
                        'text' => $row->name . ' (' . $row->username . ', #' . $row->id . ')',
                    );
                }
            }
        }

        return $options;
    }

    /**
     * Separate content authorship, original creation time and the importing actor.
     *
     * @param array $values Parsed source values.
     * @param string $type Imported entity kind.
     * @param object|null $stored Existing local record, or null for an insert.
     * @param int $author Selected local author for new content.
     * @param int $actor User executing the import.
     * @param string $now Current UTC SQL timestamp.
     * @return array Values with authoritative creation and modification metadata.
     */
    public static function normalise(array $values, string $type, ?object $stored, int $author, int $actor, string $now): array
    {
        $category = $type === 'categories';
        $created = $category ? 'created_time' : 'created';
        $creator = $category ? 'created_user_id' : 'created_by';
        $modified = $category ? 'modified_time' : 'modified';
        $modifier = $category ? 'modified_user_id' : 'modified_by';
        $values[$created] = $stored ? $stored->$created : self::creationDate($values[$created] ?? '', $now);
        $values[$creator] = $stored ? (int) $stored->$creator : $author;
        $values[$modified] = $stored ? $now : null;
        $values[$modifier] = $stored ? $actor : 0;
        if ($type === 'events') {
            $values['created_by_alias'] = $stored ? (string) ($stored->created_by_alias ?? '') : '';
        }

        return $values;
    }

    /**
     * Preserve a valid source creation date or use the current import timestamp.
     *
     * @param mixed $value Source creation date.
     * @param string $fallback UTC SQL timestamp for missing or invalid dates.
     * @return string Valid SQL creation timestamp.
     */
    private static function creationDate($value, string $fallback): string
    {
        if (str_contains((string) $value, "\0")) {
            return $fallback;
        }
        $value = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            $value .= ' 00:00:00';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value)) {
            return $fallback;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));

        return $date && $date->format('Y-m-d H:i:s') === $value && (int) $date->format('Y') > 0 ? $value : $fallback;
    }
}
