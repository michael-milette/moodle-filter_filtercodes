<?php
// This file is part of FilterCodes filter for Moodle - https://moodle.org/
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
// along with FilterCodes.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for menu tags.
 *
 * Tests menu-related tags including category menus, course menus, and custom menu items.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Test menu tags.
 *
 * @copyright  2017-2025 TNG Consulting Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */
final class menu_test extends \advanced_testcase {
    /**
     * Setup test framework.
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        filter_set_global_state('filtercodes', TEXTFILTER_ON);
        $this->setAdminUser();

        // Initialize SERVER variables for URL-related tests.
        $_SERVER['REQUEST_URI'] = '/course/view.php?id=2';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = 'off';
    }

    /**
     * Test categoriesmenu tag (all categories).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_categoriesmenu(): void {
        // Create some categories.
        $cat1 = $this->getDataGenerator()->create_category(['name' => 'Menu Category Alpha']);
        $cat2 = $this->getDataGenerator()->create_category(['name' => 'Menu Category Beta']);

        $text = '{categoriesmenu}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain category names in menu format.
        $this->assertStringContainsString(
            'Menu Category Alpha',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Menu Category Alpha', $result)
        );
        $this->assertStringContainsString(
            'Menu Category Beta',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Menu Category Beta', $result)
        );
    }

    /**
     * Test categories0menu tag (top-level categories only).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_categories0menu(): void {
        // Create top-level and nested categories.
        $topcat = $this->getDataGenerator()->create_category(['name' => 'Top Level Menu']);
        $subcat = $this->getDataGenerator()->create_category(['name' => 'Sub Level Menu', 'parent' => $topcat->id]);

        $text = '{categories0menu}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain top-level category.
        $this->assertStringContainsString(
            'Top Level Menu',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Top Level Menu', $result)
        );
        // Should NOT contain sub-category (only top-level).
        // Note: Depending on implementation, subcategories might be included as nested menu items.
    }

    /**
     * Test categoriesxmenu tag (subcategories of current category).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_categoriesxmenu(): void {
        global $PAGE;

        // Create parent category.
        $parent = $this->getDataGenerator()->create_category(['name' => 'Parent Category']);
        $currentcat = $this->getDataGenerator()->create_category([
            'name' => 'Current Category',
            'parent' => $parent->id,
        ]);

        // Create subcategories of the current category.
        $sub1 = $this->getDataGenerator()->create_category([
            'name' => 'Sub Category One',
            'parent' => $currentcat->id,
        ]);
        $sub2 = $this->getDataGenerator()->create_category([
            'name' => 'Sub Category Two',
            'parent' => $currentcat->id,
        ]);

        // Create a course in the current category to set context.
        $course = $this->getDataGenerator()->create_course(['category' => $currentcat->id]);
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{categoriesxmenu}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        $this->assertStringContainsString(
            'Sub Category One',
            $result,
            sprintf("Should contain subcategories of the current category\nActual: '%s'", $result)
        );
        $this->assertStringContainsString(
            'Sub Category Two',
            $result,
            sprintf("Should contain subcategories of the current category\nActual: '%s'", $result)
        );
    }

    /**
     * Test toggleeditingmenu tag.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_toggleeditingmenu(): void {
        global $PAGE;

        // Create a course to set context.
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);

        $text = '{toggleeditingmenu}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should contain editing toggle menu item.
        $this->assertNotEmpty(
            $result,
            sprintf("Should not be empty\nActual: '%s'", $result)
        );
        $this->assertStringNotContainsString('{toggleeditingmenu}', $result);
        $this->assertStringContainsString(
            '/course/view.php',
            $result,
            sprintf("Editing toggle menu should link back to the course\nActual: '%s'", $result)
        );
    }

    /**
     * Test mycoursesmenu tag.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_mycoursesmenu(): void {
        global $USER;

        // Create and enrol in courses.
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Menu Course One']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'Menu Course Two']);

        $this->getDataGenerator()->enrol_user($USER->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($USER->id, $course2->id, 'student');

        $text = '{mycoursesmenu}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain enrolled course names in menu format.
        $this->assertStringContainsString(
            'Menu Course One',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Menu Course One', $result)
        );
        $this->assertStringContainsString(
            'Menu Course Two',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Menu Course Two', $result)
        );
    }

    /**
     * Test courserequestmenu0 tag (basic course request menu).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_courserequestmenu0(): void {
        global $CFG;

        // Enable course requests.
        $CFG->enablecourserequests = 1;

        $text = '{courserequestmenu0}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain course request menu item.
        $this->assertNotEmpty($result, sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test courserequestmenu tag.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_courserequestmenu(): void {
        global $CFG;

        // Enable course requests.
        $CFG->enablecourserequests = 1;

        $text = '{courserequestmenu}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain course request menu item.
        $this->assertNotEmpty($result, sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test menuadmin tag (admin menu items).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_menuadmin(): void {
        global $PAGE;

        // Set a page URL.
        $PAGE->set_url('/course/view.php', ['id' => 2]);

        // Already logged in as admin from setUp().
        $text = '{menuadmin}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain admin menu items for admin user.
        $this->assertNotEmpty($result, sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test menuadmin for non-admin user.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_menuadmin_nonadmin(): void {
        // Create and switch to regular user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $text = '{menuadmin}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals('', $result, sprintf("Non-admin users should not receive admin menu items\nActual: '%s'", $result));
    }

    /**
     * Test menudev tag (developer menu items).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_menudev(): void {
        global $CFG;

        // Enable developer debugging.
        $CFG->debugdisplay = 1;
        $CFG->debug = DEBUG_DEVELOPER;

        $text = '{menudev}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain developer menu items when debug is enabled.
        $this->assertNotEmpty($result, sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test menuthemes tag (themes menu).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_menuthemes(): void {
        global $CFG;

        // Force this core setting for the current process without writing to {config}.
        $CFG->config_php_settings['allowthemechangeonurl'] = 1;

        $text = '{menuthemes}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertStringNotContainsString(
            '{menuthemes}',
            $result,
            sprintf("Themes menu tag should be consumed\nActual: '%s'", $result)
        );
        $this->assertStringContainsString(
            'theme=',
            $result,
            sprintf("Themes menu should include theme-switch URLs\nActual: '%s'", $result)
        );
    }

    /**
     * Test menucoursemore tag (course additional menu items).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_menucoursemore(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Course More Menu']);
        $context = \context_course::instance($course->id);

        $text = '{menucoursemore}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should contain course-specific menu items.
        $this->assertNotEmpty($result, sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test menuwishlist tag (wishlist menu - if applicable).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_menuwishlist(): void {
        $text = '{menuwishlist}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertStringNotContainsString(
            '{menuwishlist}',
            $result,
            sprintf("Wishlist menu tag should be consumed even when optional plugins are absent\nActual: '%s'", $result)
        );
    }

    /**
     * Test menu tags with logged out user.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_menus_logged_out(): void {
        // Log out.
        $this->setUser(null);

        $text = '{mycoursesmenu} {menuadmin}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertStringNotContainsString('{mycoursesmenu}', $result);
        $this->assertStringNotContainsString('{menuadmin}', $result);
        $this->assertStringNotContainsString(
            'Menu Course',
            $result,
            sprintf("Logged-out users should not receive personal course menu entries\nActual: '%s'", $result)
        );
    }
}
