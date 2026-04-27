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
 * Unit tests for UI element tags.
 *
 * Tests UI-related tags including cards, progress indicators, charts, and display elements.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Test UI element tags.
 *
 * @copyright  2017-2025 TNG Consulting Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ui_elements_test extends \advanced_testcase {

    /**
     * Setup test framework.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        filter_set_global_state('filtercodes', TEXTFILTER_ON);
        $this->setAdminUser();
    }

    /**
     * Test teamcards tag.
     */
    public function test_teamcards() {
        global $CFG, $DB;

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $CFG->coursecontact = $teacherrole->id;

        $user1 = $this->getDataGenerator()->create_user([
            'firstname' => 'Alice',
            'lastname' => 'Smith',
            'email' => 'alice@example.com',
        ]);
        $user2 = $this->getDataGenerator()->create_user([
            'firstname' => 'Bob',
            'lastname' => 'Jones',
            'email' => 'bob@example.com',
        ]);
        role_assign($teacherrole->id, $user1->id, $context->id);
        role_assign($teacherrole->id, $user2->id, $context->id);

        $text = '{teamcards}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertStringContainsString('Alice', $result,
            sprintf("Should contain assigned course contact\nActual: '%s'", $result));
        $this->assertStringContainsString('Bob', $result,
            sprintf("Should contain assigned course contact\nActual: '%s'", $result));
    }

    /**
     * Test coursecards tag.
     */
    public function test_coursecards() {
        // Create some courses.
        $course1 = $this->getDataGenerator()->create_course([
            'fullname' => 'Test Course Alpha',
            'shortname' => 'TC-ALPHA',
            'visible' => 1,
        ]);
        $course2 = $this->getDataGenerator()->create_course([
            'fullname' => 'Test Course Beta',
            'shortname' => 'TC-BETA',
            'visible' => 1,
        ]);

        $text = '{coursecards}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain course names (visible courses).
        $this->assertStringContainsString('Test Course Alpha', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Test Course Alpha', $result));
        $this->assertStringContainsString('Test Course Beta', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Test Course Beta', $result));
    }

    /**
     * Test coursecard tag with specific course ID.
     */
    public function test_coursecard_with_id() {
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Specific Course Card',
            'shortname' => 'SC-CARD',
            'summary' => 'This is a test course summary.',
        ]);

        $text = '{coursecard ' . $course->id . '}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain course information.
        $this->assertStringContainsString('Specific Course Card', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Specific Course Card', $result));
    }

    /**
     * Test coursecardsbyenrol tag - shows courses user is enrolled in.
     */
    public function test_coursecardsbyenrol() {
        global $USER;

        $course1 = $this->getDataGenerator()->create_course([
            'fullname' => 'Enrolled Course One',
            'shortname' => 'EC-ONE',
        ]);
        $course2 = $this->getDataGenerator()->create_course([
            'fullname' => 'Enrolled Course Two',
            'shortname' => 'EC-TWO',
        ]);

        // Enrol admin in first course only.
        $this->getDataGenerator()->enrol_user($USER->id, $course1->id, 'student');

        $text = '{coursecardsbyenrol}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain enrolled course.
        $this->assertStringContainsString('Enrolled Course One', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Enrolled Course One', $result));
        $this->assertStringNotContainsString('Enrolled Course Two', $result,
            sprintf("Should not contain courses the user is not enrolled in\nActual: '%s'", $result));
    }

    /**
     * Test courseprogress tag.
     */
    public function test_courseprogress() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Progress Test Course',
            'enablecompletion' => 1,
        ]);

        // Enrol user.
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{courseprogress}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should contain progress information (could be 0% or formatted).
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test courseprogresspercent tag.
     */
    public function test_courseprogresspercent() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Progress Percent Course',
            'enablecompletion' => 1,
        ]);

        // Enrol user.
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{courseprogresspercent}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        $this->assertMatchesRegularExpression('/^\d{1,3}$/', $result,
            sprintf("Should return a numeric completion percentage\nActual: '%s'", $result));
    }

    /**
     * Test courseprogressbar tag.
     */
    public function test_courseprogressbar() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Progress Bar Course',
            'enablecompletion' => 1,
        ]);

        // Enrol user.
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{courseprogressbar}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should contain progress bar HTML.
        $this->assertStringContainsString('progress', strtolower($result),
            sprintf("Should contain %s\nActual: '%s'", 'progress', $result));
    }

    /**
     * Test categorycards tag.
     */
    public function test_categorycards() {
        // Create some categories.
        $cat1 = $this->getDataGenerator()->create_category([
            'name' => 'Category Alpha',
            'description' => 'First test category',
        ]);
        $cat2 = $this->getDataGenerator()->create_category([
            'name' => 'Category Beta',
            'description' => 'Second test category',
        ]);

        $text = '{categorycards}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain category names.
        $this->assertStringContainsString('Category Alpha', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Category Alpha', $result));
        $this->assertStringContainsString('Category Beta', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Category Beta', $result));
    }

    /**
     * Test mycourses tag (list format).
     */
    public function test_mycourses_list() {
        global $USER;

        $course1 = $this->getDataGenerator()->create_course([
            'fullname' => 'My Course One',
            'shortname' => 'MC-ONE',
        ]);
        $course2 = $this->getDataGenerator()->create_course([
            'fullname' => 'My Course Two',
            'shortname' => 'MC-TWO',
        ]);

        // Enrol user in both courses.
        $this->getDataGenerator()->enrol_user($USER->id, $course1->id, 'student');
        $this->getDataGenerator()->enrol_user($USER->id, $course2->id, 'student');

        $text = '{mycourses}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain enrolled course names.
        $this->assertStringContainsString('My Course One', $result,
            sprintf("Should contain %s\nActual: '%s'", 'My Course One', $result));
        $this->assertStringContainsString('My Course Two', $result,
            sprintf("Should contain %s\nActual: '%s'", 'My Course Two', $result));
    }

    /**
     * Test mycoursescards tag (card format).
     */
    public function test_mycoursescards() {
        global $USER;

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Card Format Course',
            'shortname' => 'CFC',
        ]);

        // Enrol user.
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $text = '{mycoursescards}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain course in card format.
        $this->assertStringContainsString('Card Format Course', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Card Format Course', $result));
    }

    /**
     * Test courserequest tag/link.
     */
    public function test_courserequest() {
        global $CFG;

        // Enable course requests.
        $CFG->enablecourserequests = 1;

        $text = '{courserequest}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain course request link or button.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('course', strtolower($result),
            sprintf("Should contain %s\nActual: '%s'", 'course', $result));
    }

    /**
     * Test chart radial tag.
     */
    public function test_chart_radial() {
        $text = '{chart radial 75}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain chart-related markup.
        $this->assertStringContainsString('fc-chart-pie', $result,
            sprintf("Should contain chart wrapper\nActual: '%s'", $result));
        $this->assertStringContainsString('chart-area', $result,
            sprintf("Should contain Moodle chart markup\nActual: '%s'", $result));
        $this->assertStringNotContainsString('{chart radial 75}', $result,
            sprintf("Tag should have been processed\nActual: '%s'", $result));
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test chart pie tag.
     */
    public function test_chart_pie() {
        $text = '{chart pie 60}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain chart-related markup.
        $this->assertStringContainsString('fc-chart-pie', $result,
            sprintf("Should contain chart wrapper\nActual: '%s'", $result));
        $this->assertStringContainsString('chart-area', $result,
            sprintf("Should contain Moodle chart markup\nActual: '%s'", $result));
        $this->assertStringNotContainsString('{chart pie 60}', $result,
            sprintf("Tag should have been processed\nActual: '%s'", $result));
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test chart progressbar tag.
     */
    public function test_chart_progressbar() {
        $text = '{chart progressbar 45}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain progress bar markup.
        $this->assertStringContainsString('45', $result,
            sprintf("Should contain %s\nActual: '%s'", '45', $result));
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test chart progresspie tag.
     */
    public function test_chart_progresspie() {
        $text = '{chart progresspie 80}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain progress pie markup.
        $this->assertStringContainsString('80', $result,
            sprintf("Should contain %s\nActual: '%s'", '80', $result));
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test dashboard_siteinfo tag.
     */
    public function test_dashboard_siteinfo() {
        $text = '{dashboard_siteinfo}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertStringContainsString('fcdashboard-siteinfo', $result,
            sprintf("Should contain site information dashboard markup\nActual: '%s'", $result));
        $this->assertStringContainsString('Available disk space', $result,
            sprintf("Should contain dashboard content\nActual: '%s'", $result));
    }

    /**
     * Test keyboard tag for keyboard input display.
     */
    public function test_keyboard_display() {
        $text = '{keyboard}Ctrl+C{/keyboard}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should wrap text in keyboard styling.
        $this->assertStringContainsString('Ctrl+C', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Ctrl+C', $result));
        $this->assertStringContainsString('kbd', $result,
            sprintf("Should contain %s\nActual: '%s'", 'kbd', $result));
    }

    /**
     * Test empty keyboard tag.
     */
    public function test_keyboard_empty() {
        $text = '{keyboard}{/keyboard}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertStringContainsString('kbd', $result,
            sprintf("Empty keyboard tags should still render keyboard markup\nActual: '%s'", $result));
    }
}
