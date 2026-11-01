<?php

declare(strict_types=1);

/**
 * Exercise the real Categories constructor, getData and pagination with an isolated query.
 */
final class CategoriesPaginationModel extends JemModelCategories
{
    protected $option = 'com_jem';

    /**
     * Count the synthetic rows rather than querying an installed JEM database.
     *
     * @return int Fixture total.
     */
    public function getTotal()
    {
        return (int) $this->getDatabase()->setQuery('SELECT COUNT(*) FROM page_rows')->loadResult();
    }

    /**
     * Replace only the domain query; production getData still applies pagination.
     *
     * @param bool $emptycat Whether empty categories are included.
     * @param int|null $parent_id Optional parent category.
     * @return \Joomla\Database\QueryInterface Ordered fixture rows.
     */
    protected function _buildQuerySubCategories($emptycat, $parent_id = null)
    {
        return $this->getDatabase()->getQuery(true)
            ->select('id, id AS slug, catname, description')->from('page_rows')->order('id');
    }
}

/** Category routing boundary; SEO routing is outside this pagination regression. */
final class JemHelperRoute
{
    /**
     * Build a synthetic category link.
     *
     * @param mixed $id Category identifier.
     * @return string Relative category URL.
     */
    public static function getCategoryRoute($id): string
    {
        return 'index.php?option=com_jem&view=category&id=' . $id;
    }
}
