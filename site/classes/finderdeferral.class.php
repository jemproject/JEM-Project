<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

/** Keep Finder's implicitly committing SQL outside JEM event transactions. */
final class JemFinderDeferral
{
    private static int $depth = 0;
    private static bool $rollbackOnly = false;

    /** @var array<int, array{callback: callable, accessChanged: bool}> */
    private static array $pending = [];

    /**
     * Begin a scope after its JEM event transaction has started.
     *
     * @return void
     */
    public static function begin(): void
    {
        if (self::$depth === 0) {
            self::$pending = [];
            self::$rollbackOnly = false;
        }

        ++self::$depth;
    }

    /**
     * Queue only this plugin's work, retaining the latest row and any access change.
     *
     * @param int $eventId Persisted event ID.
     * @param callable $callback Index callback receiving the combined access-change flag.
     * @param bool $accessChanged Whether this save changed the event access level.
     * @return bool True when handled by the active scope; false means index immediately.
     */
    public static function defer(int $eventId, callable $callback, bool $accessChanged): bool
    {
        if (self::$depth === 0) {
            return false;
        }

        if ($eventId > 0) {
            self::$pending[$eventId] = [
                'callback' => $callback,
                'accessChanged' => $accessChanged || (self::$pending[$eventId]['accessChanged'] ?? false),
            ];
        }

        return true;
    }

    /**
     * Finish after commit or rollback; only the outer successful scope may index.
     *
     * @param bool $committed True only after the owning database transaction committed.
     * @return void
     */
    public static function end(bool $committed): void
    {
        if (self::$depth === 0) {
            return;
        }

        self::$rollbackOnly = self::$rollbackOnly || !$committed;
        if (--self::$depth > 0) {
            return;
        }

        $pending = self::$pending;
        $publish = !self::$rollbackOnly;
        self::$pending = [];
        self::$rollbackOnly = false;
        if (!$publish) {
            return;
        }

        $notify = true;
        foreach ($pending as $eventId => $entry) {
            try {
                ($entry['callback'])($entry['accessChanged']);
            } catch (Throwable $error) {
                self::reportIndexFailure($eventId, $error, $notify);
                $notify = false;
            }
        }
    }

    /**
     * Report an index failure without changing the result of a committed event save.
     *
     * @param int $eventId Event whose indexing failed.
     * @param Throwable $error Indexing error.
     * @param bool $notify Whether this batch still needs its single user warning.
     * @return void
     */
    private static function reportIndexFailure(int $eventId, Throwable $error, bool $notify): void
    {
        try {
            Log::add(
                'Finder update failed after the JEM event commit (event ' . $eventId . '): ' . $error->getMessage(),
                Log::ERROR,
                'com_jem.finder'
            );
        } catch (Throwable $ignored) {
            // Logging cannot undo a committed event save.
        }

        if ($notify) {
            try {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_FINDER_INDEX_FAILED_AFTER_SAVE'), 'warning');
            } catch (Throwable $ignored) {
                // A failed notification must not turn the committed save into a failure.
            }
        }
    }
}
