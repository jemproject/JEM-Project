<?php

declare(strict_types=1);

/** Controlled fetch boundaries; getData remains the actual inherited production method. */
final class EventelementCategoryFixture extends JemModelEventelement
{
    public array $rows = [];
    public array $missingCategories = [];
    public array $loads = [];
    public array $categoryCalls = [];
    public array $fetchResults = [];
    public int $offset = 0;
    public int $limit = 5;

    /** @return void Avoid the installed application/database constructor in this focused test. */
    public function __construct()
    {
    }

    /**
     * Supply pagination inputs without an installed application.
     *
     * @param string|null $property State key.
     * @param mixed $default Default value.
     * @return mixed Requested pagination value or default.
     */
    public function getState($property = null, $default = null)
    {
        return match ($property) {
            'limitstart' => $this->offset,
            'limit' => $this->limit,
            default => $default,
        };
    }

    /** @return string Identifiable query boundary; this fixture does not test SQL or permissions. */
    protected function _buildQuery()
    {
        return 'controlled event selector query';
    }

    /**
     * Return fresh first-stage rows, or a queued failure result.
     *
     * @param mixed $query Query from the production getData method.
     * @param int $limitstart Pagination offset.
     * @param int $limit Page size.
     * @return array|false|null Controlled data-fetch result.
     */
    protected function _getList($query, $limitstart = 0, $limit = 0)
    {
        $this->loads[] = [$query, $limitstart, $limit];
        if ($this->fetchResults !== []) {
            $result = array_shift($this->fetchResults);
            if ($result instanceof Throwable) {
                throw $result;
            }

            return $result;
        }

        return array_map(static fn (int $id): object => (object) ['id' => $id, 'title' => 'Event ' . $id], $this->rows);
    }

    /**
     * Simulate category loss between the first and second fetch stages.
     *
     * @param int $id Event ID.
     * @return array Category metadata, or an empty list for a removed relation.
     */
    public function getCategories($id)
    {
        $this->categoryCalls[] = $id;

        return in_array($id, $this->missingCategories, true)
            ? [] : [(object) ['id' => 7, 'catname' => 'First'], (object) ['id' => 9, 'catname' => 'Second']];
    }
}
