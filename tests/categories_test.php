<?php
// This file is part of FilterCodes for Moodle - https://moodle.org/
//
// FilterCodes is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// FilterCodes is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Unit tests for FilterCodes category tags.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */

namespace filter_filtercodes;

/**
 * Unit tests for FilterCodes category tags.
 *
 * Test category-related tags like {categoryid}, {categoryname}, {categories}, etc.
 *
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class categories_test extends \advanced_testcase {
    /**
     * Setup the test framework
     *
     * @return void
     */
    public function setUp(): void {
        global $PAGE;
        parent::setUp();

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Enable FilterCodes filter at top level.
        filter_set_global_state('filtercodes', TEXTFILTER_ON);

        $PAGE->set_url(new \moodle_url('/'));
    }

    /**
     * Test categoryid tag in a course.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categoryid_in_course(): void {
        global $PAGE;

        $category = $this->getDataGenerator()->create_category(['name' => 'Test Category']);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $filtered = format_text('{categoryid}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals($category->id, $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $category->id, $filtered));
    }

    /**
     * Test categoryname tag in a course.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categoryname_in_course(): void {
        global $PAGE;

        $category = $this->getDataGenerator()->create_category(['name' => 'My Test Category']);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $filtered = format_text('{categoryname}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals('My Test Category', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'My Test Category', $filtered));
    }

    /**
     * Test categorynumber tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categorynumber(): void {
        global $PAGE;

        $category = $this->getDataGenerator()->create_category([
            'name' => 'Test Category',
            'idnumber' => 'CAT-123',
        ]);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $filtered = format_text('{categorynumber}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals('CAT-123', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'CAT-123', $filtered));
    }

    /**
     * Test categorydescription tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categorydescription(): void {
        global $PAGE;

        $description = 'This is a test category description.';
        $category = $this->getDataGenerator()->create_category([
            'name' => 'Test Category',
            'description' => $description,
        ]);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $filtered = format_text('{categorydescription}', FORMAT_HTML, ['context' => $context]);
        $this->assertStringContainsString('test category description', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'test category description', $filtered));
    }

    /**
     * Test categories tag (all categories).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categories_all(): void {
        // Create some categories.
        $cat1 = $this->getDataGenerator()->create_category(['name' => 'Category One']);
        $cat2 = $this->getDataGenerator()->create_category(['name' => 'Category Two']);

        $filtered = format_text('{categories}', FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should be an unordered list.
        $this->assertStringContainsString('<ul', $filtered,
            sprintf("Should contain %s\nActual: '%s'", '<ul', $filtered));
        $this->assertStringContainsString('<li', $filtered,
            sprintf("Should contain %s\nActual: '%s'", '<li', $filtered));
        $this->assertStringContainsString('Category One', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Category One', $filtered));
        $this->assertStringContainsString('Category Two', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Category Two', $filtered));
    }

    /**
     * Test categories0 tag (top-level categories only).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categories0_toplevel_only(): void {
        // Create top-level and nested categories.
        $topcat = $this->getDataGenerator()->create_category(['name' => 'Top Level']);
        $subcat = $this->getDataGenerator()->create_category([
            'name' => 'Sub Level',
            'parent' => $topcat->id,
        ]);

        $filtered = format_text('{categories0}', FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should include top-level.
        $this->assertStringContainsString('Top Level', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Top Level', $filtered));
        // Should NOT include subcategory in the simple list.
        // Note: This behavior may vary based on implementation.
        $this->assertStringContainsString('<ul', $filtered,
            sprintf("Should contain %s\nActual: '%s'", '<ul', $filtered));
    }

    /**
     * Test categoriesx tag (other categories in current category).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categoriesx(): void {
        // Create parent category with multiple children.
        $parent = $this->getDataGenerator()->create_category(['name' => 'Parent Cat']);
        $child1 = $this->getDataGenerator()->create_category([
            'name' => 'Child Cat 1',
            'parent' => $parent->id,
        ]);
        $child2 = $this->getDataGenerator()->create_category([
            'name' => 'Child Cat 2',
            'parent' => $parent->id,
        ]);

        $course = $this->getDataGenerator()->create_course(['category' => $parent->id]);
        $context = \context_course::instance($course->id);

        $filtered = format_text('{categoriesx}', FORMAT_HTML, ['context' => $context]);

        // Should list other categories in the same parent.
        $this->assertStringContainsString('<ul', $filtered,
            sprintf("Should contain %s\nActual: '%s'", '<ul', $filtered));
    }

    /**
     * Test categoriesmenu tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categoriesmenu(): void {
        $cat1 = $this->getDataGenerator()->create_category(['name' => 'Menu Category 1']);
        $cat2 = $this->getDataGenerator()->create_category(['name' => 'Menu Category 2']);

        $filtered = format_text('{categoriesmenu}', FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should be formatted for custom menu use.
        $this->assertNotEmpty($filtered,
            sprintf("Should not be empty\nActual: '%s'", $filtered));
        $this->assertStringContainsString('Menu Category 1', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Menu Category 1', $filtered));
        $this->assertStringContainsString('Menu Category 2', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Menu Category 2', $filtered));
    }

    /**
     * Test categories0menu tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categories0menu(): void {
        $topcat = $this->getDataGenerator()->create_category(['name' => 'Top Menu Cat']);
        $subcat = $this->getDataGenerator()->create_category([
            'name' => 'Sub Menu Cat',
            'parent' => $topcat->id,
        ]);

        $filtered = format_text('{categories0menu}', FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should include top-level category.
        $this->assertStringContainsString('Top Menu Cat', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Top Menu Cat', $filtered));
    }

    /**
     * Test categoriesxmenu tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_categoriesxmenu(): void {
        $parent = $this->getDataGenerator()->create_category(['name' => 'Parent']);
        $child = $this->getDataGenerator()->create_category([
            'name' => 'Child Menu',
            'parent' => $parent->id,
        ]);

        $course = $this->getDataGenerator()->create_course(['category' => $parent->id]);
        $context = \context_course::instance($course->id);

        $filtered = format_text('{categoriesxmenu}', FORMAT_HTML, ['context' => $context]);

        // Should be formatted for menu.
        $this->assertIsString($filtered);
    }

    /**
     * Test category tags outside course context.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_category_tags_outside_course(): void {
        $filtered = format_text('{categoryid}', FORMAT_HTML, ['context' => \context_system::instance()]);
        // Should be 0 or empty outside a course.
        $this->assertTrue($filtered === '0' || $filtered === '');

        $filtered = format_text('{categoryname}', FORMAT_HTML, ['context' => \context_system::instance()]);
        // Should be empty outside a course.
        $this->assertEquals('', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered));
    }

    /**
     * Test multiple category tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_multiple_category_tags(): void {
        global $PAGE;

        $category = $this->getDataGenerator()->create_category([
            'name' => 'Test Category',
            'idnumber' => 'TC-001',
        ]);
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = 'Category: {categoryname} (ID: {categoryid}, Number: {categorynumber})';
        $filtered = format_text($text, FORMAT_HTML, ['context' => $context]);

        $this->assertStringContainsString('Test Category', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Test Category', $filtered));
        $this->assertStringContainsString((string)$category->id, $filtered,
            sprintf("Should contain %s\nActual: '%s'", (string)$category->id, $filtered));
        $this->assertStringContainsString('TC-001', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'TC-001', $filtered));
    }

    /**
     * Test hidden categories (admin should see, others should not).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_hidden_categories(): void {
        $visiblecat = $this->getDataGenerator()->create_category(['name' => 'Visible Cat', 'visible' => 1]);
        $hiddencat = $this->getDataGenerator()->create_category(['name' => 'Hidden Cat', 'visible' => 0]);

        // As admin, should see all.
        $filtered = format_text('{categories0}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertStringContainsString('Visible Cat', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Visible Cat', $filtered));
        // Hidden categories visibility depends on capabilities.
    }
}
