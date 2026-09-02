<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BackendSearchToolsTest extends TestCase
{
    /**
     * @return array<string, array{template: string, view: string, form: string, model: string}>
     */
    private function listViews(): array
    {
        return array(
            'events' => array(
                'template' => 'admin/views/events/tmpl/default.php',
                'view'     => 'admin/views/events/view.html.php',
                'form'     => 'admin/models/forms/filter_events.xml',
                'model'    => 'admin/models/events.php',
            ),
            'venues' => array(
                'template' => 'admin/views/venues/tmpl/default.php',
                'view'     => 'admin/views/venues/view.html.php',
                'form'     => 'admin/models/forms/filter_venues.xml',
                'model'    => 'admin/models/venues.php',
            ),
            'categories' => array(
                'template' => 'admin/views/categories/tmpl/default.php',
                'view'     => 'admin/views/categories/view.html.php',
                'form'     => 'admin/models/forms/filter_categories.xml',
                'model'    => 'admin/models/categories.php',
            ),
            'types' => array(
                'template' => 'admin/views/types/tmpl/default.php',
                'view'     => 'admin/views/types/view.html.php',
                'form'     => 'admin/models/forms/filter_types.xml',
                'model'    => 'admin/models/types.php',
            ),
        );
    }

    public function testBackendListsUseTheNativeJoomlaSearchToolsLayout(): void
    {
        foreach ($this->listViews() as $name => $paths) {
            $template = (string) file_get_contents(JEM_TEST_ROOT . '/' . $paths['template']);

            self::assertStringContainsString("LayoutHelper::render('joomla.searchtools.default'", $template, $name);
            self::assertStringContainsString("HTMLHelper::_('searchtools.sort'", $template, $name);
            self::assertStringNotContainsString('id="filter-bar"', $template, $name);
            self::assertStringNotContainsString("HTMLHelper::_('grid.sort'", $template, $name);
            self::assertStringNotContainsString('name="filter_order"', $template, $name);
            self::assertStringNotContainsString('name="filter_order_Dir"', $template, $name);
            self::assertStringContainsString('$this->filterForm->renderControlFields()', $template, $name);
        }
    }

    public function testFilterFormsAreValidAndExposeSearchOrderingAndLimit(): void
    {
        foreach ($this->listViews() as $name => $paths) {
            $form = simplexml_load_file(JEM_TEST_ROOT . '/' . $paths['form']);

            self::assertInstanceOf(SimpleXMLElement::class, $form, $name);
            self::assertNotEmpty($form->xpath('/form/fields[@name="filter"]/field[@name="search"]'), $name);
            self::assertNotEmpty($form->xpath('/form/fields[@name="list"]/field[@name="fullordering"]'), $name);
            self::assertNotEmpty($form->xpath('/form/fields[@name="list"]/field[@name="limit"]'), $name);
        }
    }

    public function testViewsProvideSearchToolsStateAndKeepTheDisplayedOrderingSynchronized(): void
    {
        foreach ($this->listViews() as $name => $paths) {
            $view = (string) file_get_contents(JEM_TEST_ROOT . '/' . $paths['view']);

            self::assertStringContainsString("get('FilterForm')", $view, $name);
            self::assertStringContainsString("get('ActiveFilters')", $view, $name);
            self::assertStringContainsString("get('Total')", $view, $name);
            self::assertStringContainsString('setValue(', $view, $name);
            self::assertStringContainsString("'fullordering'", $view, $name);
        }
    }

    public function testLegacyFilterSynchronizationDoesNotReenterStatePopulation(): void
    {
        foreach ($this->listViews() as $name => $paths) {
            $model = (string) file_get_contents(JEM_TEST_ROOT . '/' . $paths['model']);

            self::assertStringContainsString('$value = $this->state->get($legacyState, $default);', $model, $name);
            self::assertStringContainsString(
                '$this->setState($legacyState, $this->state->get(\'filter.\' . $name, $default));',
                $model,
                $name
            );
            self::assertStringNotContainsString('$value = $this->getState($legacyState, $default);', $model, $name);
            self::assertStringNotContainsString(
                '$this->setState($legacyState, $this->getState(\'filter.\' . $name, $default));',
                $model,
                $name
            );
        }
    }

    public function testEventFiltersIncludeFeaturedAndVenueWithoutBreakingLegacyLinks(): void
    {
        $form = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/filter_events.xml');
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/events.php');

        self::assertStringContainsString('name="featured"', $form);
        self::assertStringContainsString('name="venue_id"', $form);
        self::assertStringContainsString("'featured'      => array('filter_featured'", $model);
        self::assertStringContainsString("'venue_id'      => array('filter_venue_id'", $model);
        self::assertStringContainsString("a.featured = ' . (int) \$featured", $model);
        self::assertStringContainsString("a.locid = ' . \$venueId", $model);
    }

    public function testOrderingOptionsAreWhitelistedByTheirListModels(): void
    {
        foreach ($this->listViews() as $name => $paths) {
            $form = simplexml_load_file(JEM_TEST_ROOT . '/' . $paths['form']);
            $model = (string) file_get_contents(JEM_TEST_ROOT . '/' . $paths['model']);
            $options = $form->xpath('/form/fields[@name="list"]/field[@name="fullordering"]/option');

            self::assertNotEmpty($options, $name);

            foreach ($options as $option) {
                $ordering = preg_replace('/\s+(ASC|DESC)$/i', '', (string) $option['value']);

                self::assertStringContainsString("'" . $ordering . "'", $model, $name . ': ' . $ordering);
            }
        }
    }

    public function testJemLanguageKeysUsedByFilterFormsAreDefined(): void
    {
        $language = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini');
        preg_match_all('/^(COM_JEM_[A-Z0-9_]+)=/m', $language, $definedMatches);
        $defined = array_fill_keys($definedMatches[1], true);

        foreach ($this->listViews() as $name => $paths) {
            $form = (string) file_get_contents(JEM_TEST_ROOT . '/' . $paths['form']);
            preg_match_all('/COM_JEM_[A-Z0-9_]+/', $form, $usedMatches);

            foreach (array_unique($usedMatches[0]) as $key) {
                self::assertArrayHasKey($key, $defined, $name . ': ' . $key);
            }
        }
    }
}
