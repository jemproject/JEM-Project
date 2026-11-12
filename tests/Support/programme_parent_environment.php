<?php

declare(strict_types=1);

/** Controlled identity; real frontend access policy still checks stored ownership and access. */
final class JemFactory
{
    public static int $userId = 5;
    public static bool $canEditAny = false;

    /** @return object Author with event editing permission limited to owned fixtures. */
    public static function getUser(): object
    {
        return new class (self::$userId) {
            /** @param int $id Fixture identity. */
            public function __construct(public int $id)
            {
            }
            /** @return array Authorised viewing levels. */
            public function getAuthorisedViewLevels(): array
            {
                return [1];
            }
            /** @return bool Ownership boundary used by the actual frontend access decision. */
            public function can($action, $type, $id, $owner, ...$args): bool
            {
                return $this->id > 0 && ($owner === $this->id || JemFactory::$canEditAny);
            }
        };
    }
}

/** Visibility fixtures only; the candidate SQL, saved-parent lookup and validation remain real. */
final class JemHelper
{
    /** @param array $levels View levels. @return array Visible venue ids in this fixture. */
    public static function getVisibleVenueHierarchyIds($levels): array
    {
        return [1];
    }
    /** @param string $alias SQL event alias. @return string Fixed-clock publication window. */
    public static function getEventPublicationWhere($alias): string
    {
        return "$alias.published=1 AND ($alias.publish_up IS NULL OR $alias.publish_up <= '2026-11-12 12:00:00')"
            . " AND ($alias.publish_down IS NULL OR $alias.publish_down > '2026-11-12 12:00:00')";
    }
}
