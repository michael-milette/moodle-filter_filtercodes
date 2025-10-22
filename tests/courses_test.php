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
 * Unit tests for FilterCodes course tags.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */

namespace filter_filtercodes;

/**
 * Unit tests for FilterCodes course tags.
 *
 * Test course-related tags like {coursename}, {courseid}, {coursesummary}, etc.
 *
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class courses_test extends \advanced_testcase {
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
     * Test coursename tag in a course.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursename_in_course_context(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Test Course Name']);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{coursename}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals('Test Course Name', $filtered,
        sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Test Course Name', $filtered));
    }

    /**
     * Test coursename tag outside of a course.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursename_on_site_page(): void {
        global $SITE;
        $filtered = format_text('{coursename}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals($SITE->fullname, $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $SITE->fullname, $filtered));
    }

    /**
     * Test coursename tag with course ID parameter.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursename_with_id(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Specific Course']);

        $filtered = format_text("{coursename {$course->id}}", FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals('Specific Course', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Specific Course', $filtered));
    }

    /**
     * Test courseshortname tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_courseshortname(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course([
            'shortname' => 'TESTCOURSE101',
        ]);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{courseshortname}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals('TESTCOURSE101', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'TESTCOURSE101', $filtered));
    }

    /**
     * Test courseid tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_courseid(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{courseid}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals($course->id, $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $course->id, $filtered));

        // Test encoded version.
        $filtered = format_text('%7Bcourseid%7D', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals($course->id, $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $course->id, $filtered));
    }

    /**
     * Test coursecontextid tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursecontextid(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{coursecontextid}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals($context->id, $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $context->id, $filtered));

        // Test encoded version.
        $filtered = format_text('%7Bcoursecontextid%7D', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals($context->id, $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $context->id, $filtered));
    }

    /**
     * Test courseidnumber tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_courseidnumber(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course(['idnumber' => 'COURSE-123']);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{courseidnumber}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals('COURSE-123', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'COURSE-123', $filtered));
    }

    /**
     * Test coursesummary tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursesummary(): void {
        global $PAGE;
        $summary = 'This is a test course summary with <b>HTML</b>.';
        $course = $this->getDataGenerator()->create_course(['summary' => $summary]);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{coursesummary}', FORMAT_HTML, ['context' => $context]);
        $this->assertStringContainsString('This is a test course summary', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'This is a test course summary', $filtered));
    }

    /**
     * Test coursesummary tag with course ID parameter.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursesummary_with_id(): void {
        $summary = 'Summary for specific course.';
        $course = $this->getDataGenerator()->create_course(['summary' => $summary]);

        $filtered = format_text("{coursesummary {$course->id}}", FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertStringContainsString('Summary for specific course', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Summary for specific course', $filtered));
    }

    /**
     * Test courseparticipantcount tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_courseparticipantcount(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        // Enrol some users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);

        $filtered = format_text('{courseparticipantcount}', FORMAT_HTML, ['context' => $context]);
        $this->assertIsNumeric($filtered);
        $this->assertGreaterThanOrEqual(2, (int)$filtered);
    }

    /**
     * Test coursecount students tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursecount_students(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        // Enrol some students.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');

        // Ensure role assignments exist for students in this course context.
        global $DB;
        $role = $DB->get_record('role', ['shortname' => 'student']);
        role_assign($role->id, $user1->id, $context->id);
        role_assign($role->id, $user2->id, $context->id);

        $filtered = format_text('{coursecount students}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(2, (int)$filtered);
    }

    /**
     * Test coursecount students:active tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursecount_students_active(): void {
        global $PAGE;
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        // Enrol active and suspended students.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        // Ensure role assignments exist for students in this course context.
        $role = $DB->get_record('role', ['shortname' => 'student']);
        role_assign($role->id, $user1->id, $context->id);
        role_assign($role->id, $user2->id, $context->id);

        // Ensure the user_enrolments statuses are explicitly set: user1 active (0), user2 suspended.
        $DB->execute("UPDATE {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid SET ue.status = :s WHERE ue.userid = :uid AND e.courseid = :cid", ['s' => 0, 'uid' => $user1->id, 'cid' => $course->id]);
        $DB->execute("UPDATE {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid SET ue.status = :s WHERE ue.userid = :uid AND e.courseid = :cid", ['s' => ENROL_USER_SUSPENDED, 'uid' => $user2->id, 'cid' => $course->id]);

        // Diagnostic: inspect user_enrolments rows and statuses.
        $rows = $DB->get_records_sql("SELECT ue.userid, ue.status FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = :courseid", ['courseid' => $course->id]);
        // Convert to a simple array for assertion messages.
        $statuses = array_map(function($r) { return $r->status; }, $rows);
        $this->assertNotEmpty($rows, 'No user_enrolments rows found for course');
        // Count active enrolments as per filter (ue.status = 0).
        $activecount = $DB->count_records_sql("SELECT COUNT(DISTINCT ue.userid)
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE ue.status = 0 AND e.courseid = :courseid", ['courseid' => $course->id]);
        $this->assertEquals(1, (int)$activecount, sprintf("Active enrolment count mismatch expected 1 got %d (statuses: %s)", $activecount, implode(',', $statuses)));

        // Dump diagnostics to a temp file for investigation.
        $debug = [];
        $debug['courseid'] = $course->id;
        $debug['rows'] = $rows;
        $debug['activecount'] = $activecount;
        $debugfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'filtercodes_coursecount_active_debug.log';
        file_put_contents($debugfile, print_r($debug, true));

        $filtered = format_text('{coursecount students:active}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(1, (int)$filtered);
    }

    /**
     * Test coursestartdate tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursestartdate(): void {
        global $PAGE;
        $startdate = time() - DAYSECS * 7; // 7 days ago.
        $course = $this->getDataGenerator()->create_course(['startdate' => $startdate]);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{coursestartdate}', FORMAT_HTML, ['context' => $context]);
        $this->assertNotEmpty($filtered,
            sprintf("Should not be empty\nActual: '%s'", $filtered));
        $this->assertIsString($filtered);
    }

    /**
     * Test courseenddate tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_courseenddate(): void {
        global $PAGE;
        $enddate = time() + DAYSECS * 30; // 30 days from now.
        $course = $this->getDataGenerator()->create_course(['enddate' => $enddate]);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{courseenddate}', FORMAT_HTML, ['context' => $context]);
        $this->assertNotEmpty($filtered,
            sprintf("Should not be empty\nActual: '%s'", $filtered));
        $this->assertIsString($filtered);
    }

    /**
     * Test courseenrolmentdate tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_courseenrolmentdate(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);
        $filtered = format_text('{courseenrolmentdate}', FORMAT_HTML, ['context' => $context]);

        $this->assertNotEmpty($filtered, sprintf("Should not be empty\nActual: '%s'", $filtered));
        $this->assertIsString($filtered);
    }

    /**
     * Test mycourses tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_mycourses(): void {
        $user = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'My Course 1']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'My Course 2']);

        $this->getDataGenerator()->enrol_user($user->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user->id, $course2->id);

        $this->setUser($user);

        $filtered = format_text('{mycourses}', FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString('My Course 1', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'My Course 1', $filtered));
        $this->assertStringContainsString('My Course 2', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'My Course 2', $filtered));
        $this->assertStringContainsString('<ul', $filtered,
            sprintf("Should contain %s\nActual: '%s'", '<ul', $filtered));
        $this->assertStringContainsString('<li', $filtered,
            sprintf("Should contain %s\nActual: '%s'", '<li', $filtered));
    }

    /**
     * Test courseimage-url tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_courseimage_url(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{courseimage-url}', FORMAT_HTML, ['context' => $context]);

        // Should return either an http(s) URL or a data: URI (base64 image). Accept both.
        $this->assertTrue(
            (strpos($filtered, 'http') === 0) || (strpos($filtered, 'data:') === 0),
            sprintf("Should start with 'http' or 'data:'\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test sectionid tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_sectionid(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);

        $filtered = format_text('{sectionid}', FORMAT_HTML, ['context' => $context]);
        $this->assertIsString($filtered);

        // Test encoded version.
        $filtered = format_text('%7Bsectionid%7D', FORMAT_HTML, ['context' => $context]);
        $this->assertIsString($filtered);
    }

    /**
     * Test multiple course tags in one string.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_multiple_course_tags(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Test Course',
            'shortname' => 'TC101',
        ]);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $text = 'Course: {coursename} ({courseshortname}) - ID: {courseid}';
        $filtered = format_text($text, FORMAT_HTML, ['context' => $context]);

        $this->assertStringContainsString('Test Course', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Test Course', $filtered));
        $this->assertStringContainsString('TC101', $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'TC101', $filtered));
        $this->assertStringContainsString((string)$course->id, $filtered,
            sprintf("Should contain %s\nActual: '%s'", (string)$course->id, $filtered));
    }

    /**
     * Test course tags outside of course context (should show site values).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_course_tags_outside_course(): void {
        global $SITE;

        $filtered = format_text('{coursename}', FORMAT_HTML, ['context' => \context_system::instance()]);
        // Should default to site name.
        $this->assertEquals($SITE->fullname, $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $SITE->fullname, $filtered));

        $filtered = format_text('{courseid}', FORMAT_HTML, ['context' => \context_system::instance()]);
        // Should be site ID (1).
        $this->assertEquals('1', $filtered, sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '1', $filtered));
    }
}
