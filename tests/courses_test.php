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
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */

namespace filter_filtercodes;

#[\PHPUnit\Framework\Attributes\CoversClass(\filter_filtercodes\text_filter::class)]
/**
 * Unit tests for FilterCodes course tags.
 *
 * Test course-related tags like {coursename}, {courseid}, {coursesummary}, etc.
 *
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
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
     * @return void
     */
    public function test_coursename_in_course_context(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Test Course Name']);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text(
            '{coursename}',
            FORMAT_HTML,
            ['context' => $context]
        );
        $this->assertEquals(
            'Test Course Name',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Test Course Name', $filtered)
        );
    }

    /**
     * Test coursename tag outside of a course.
     *
     * @return void
     */
    public function test_coursename_on_site_page(): void {
        global $SITE;
        $filtered = format_text('{coursename}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals(
            $SITE->fullname,
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $SITE->fullname, $filtered)
        );
    }

    /**
     * Test coursename tag with course ID parameter.
     *
     * @return void
     */
    public function test_coursename_with_id(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Specific Course']);

        $filtered = format_text(
            "{coursename {$course->id}}",
            FORMAT_HTML,
            ['context' => \context_system::instance()]
        );
        $this->assertEquals(
            'Specific Course',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Specific Course', $filtered)
        );
    }

    /**
     * Test courseshortname tag.
     *
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
        $this->assertEquals(
            'TESTCOURSE101',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'TESTCOURSE101', $filtered)
        );
    }

    /**
     * Test courseid tag.
     *
     * @return void
     */
    public function test_courseid(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{courseid}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(
            $course->id,
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $course->id, $filtered)
        );

        // Test encoded version.
        $filtered = format_text('%7Bcourseid%7D', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(
            $course->id,
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $course->id, $filtered)
        );
    }

    /**
     * Test coursecontextid tag.
     *
     * @return void
     */
    public function test_coursecontextid(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{coursecontextid}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(
            $context->id,
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $context->id, $filtered)
        );

        // Test encoded version.
        $filtered = format_text('%7Bcoursecontextid%7D', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(
            $context->id,
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $context->id, $filtered)
        );
    }

    /**
     * Test courseidnumber tag.
     *
     * @return void
     */
    public function test_courseidnumber(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course(['idnumber' => 'COURSE-123']);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{courseidnumber}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(
            'COURSE-123',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'COURSE-123', $filtered)
        );
    }

    /**
     * Test coursesummary tag.
     *
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
        $this->assertStringContainsString(
            'This is a test course summary',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'This is a test course summary', $filtered)
        );
    }

    /**
     * Test coursesummary tag with course ID parameter.
     *
     * @return void
     */
    public function test_coursesummary_with_id(): void {
        $summary = 'Summary for specific course.';
        $course = $this->getDataGenerator()->create_course(['summary' => $summary]);

        $filtered = format_text("{coursesummary {$course->id}}", FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertStringContainsString(
            'Summary for specific course',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Summary for specific course', $filtered)
        );
    }

    /**
     * Test courseparticipantcount tag.
     *
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
        $DB->set_field_select(
            'user_enrolments',
            'status',
            0,
            'userid = :uid AND enrolid IN (SELECT id FROM {enrol} WHERE courseid = :cid)',
            ['uid' => $user1->id, 'cid' => $course->id]
        );
        $DB->set_field_select(
            'user_enrolments',
            'status',
            ENROL_USER_SUSPENDED,
            'userid = :uid AND enrolid IN (SELECT id FROM {enrol} WHERE courseid = :cid)',
            ['uid' => $user2->id, 'cid' => $course->id]
        );

        $rows = $DB->get_records_sql(
            "SELECT ue.userid, ue.status FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid"
                . " WHERE e.courseid = :courseid",
            ['courseid' => $course->id]
        );
        $statuses = array_map(
            function ($r) {
                return $r->status;
            },
            $rows
        );
        $this->assertNotEmpty($rows, 'No user_enrolments rows found for course');
        $activecount = $DB->count_records_sql("SELECT COUNT(DISTINCT ue.userid)
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE ue.status = 0 AND e.courseid = :courseid", ['courseid' => $course->id]);
        $this->assertEquals(
            1,
            (int)$activecount,
            sprintf("Active enrolment count mismatch expected 1 got %d (statuses: %s)", $activecount, implode(',', $statuses))
        );

        $filtered = format_text('{coursecount students:active}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(1, (int)$filtered);
    }

    /**
     * Test coursestartdate tag.
     *
     * @return void
     */
    public function test_coursestartdate(): void {
        global $PAGE;
        $startdate = time() - DAYSECS * 7; // 7 days ago.
        $course = $this->getDataGenerator()->create_course(['startdate' => $startdate]);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{coursestartdate %Y-%m-%d}', FORMAT_HTML, ['context' => $context]);
        $expected = userdate($startdate, '%Y-%m-%d');
        $this->assertEquals(
            $expected,
            $filtered,
            sprintf("Course start date should use the configured format\nExpected: '%s'\nActual: '%s'", $expected, $filtered)
        );
    }

    /**
     * Test courseenddate tag.
     *
     * @return void
     */
    public function test_courseenddate(): void {
        global $PAGE;
        $enddate = time() + DAYSECS * 30; // 30 days from now.
        $course = $this->getDataGenerator()->create_course(['enddate' => $enddate]);
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);

        $filtered = format_text('{courseenddate %Y-%m-%d}', FORMAT_HTML, ['context' => $context]);
        $expected = userdate($enddate, '%Y-%m-%d');
        $this->assertEquals(
            $expected,
            $filtered,
            sprintf("Course end date should use the configured format\nExpected: '%s'\nActual: '%s'", $expected, $filtered)
        );
    }

    /**
     * Test courseenrolmentdate tag.
     *
     * @return void
     */
    public function test_courseenrolmentdate(): void {
        global $PAGE, $CFG;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        // Force %d to keep its leading zero so the regex below is deterministic.
        $CFG->nofixday = true;

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_course($course);
        $filtered = format_text('{courseenrolmentdate %Y-%m-%d}', FORMAT_HTML, ['context' => $context]);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $filtered,
            sprintf("Course enrolment date should be formatted as YYYY-MM-DD\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test mycourses tag.
     *
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

        $this->assertStringContainsString(
            'My Course 1',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'My Course 1', $filtered)
        );
        $this->assertStringContainsString(
            'My Course 2',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'My Course 2', $filtered)
        );
        $this->assertStringContainsString(
            '<ul',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", '<ul', $filtered)
        );
        $this->assertStringContainsString(
            '<li',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", '<li', $filtered)
        );
    }

    /**
     * Test courseimage-url tag.
     *
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
     * @return void
     */
    public function test_sectionid(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);

        $filtered = format_text('{sectionid}', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(
            '',
            $filtered,
            sprintf("Section ID should be empty outside an activity context\nActual: '%s'", $filtered)
        );

        // Test encoded version.
        $filtered = format_text('%7Bsectionid%7D', FORMAT_HTML, ['context' => $context]);
        $this->assertEquals(
            '',
            $filtered,
            sprintf("Encoded section ID should be empty outside an activity context\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test multiple course tags in one string.
     *
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

        $this->assertStringContainsString(
            'Test Course',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Test Course', $filtered)
        );
        $this->assertStringContainsString(
            'TC101',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'TC101', $filtered)
        );
        $this->assertStringContainsString(
            (string)$course->id,
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", (string)$course->id, $filtered)
        );
    }

    /**
     * Test course tags outside of course context (should show site values).
     *
     * @return void
     */
    public function test_course_tags_outside_course(): void {
        global $SITE;

        $filtered = format_text('{coursename}', FORMAT_HTML, ['context' => \context_system::instance()]);
        // Should default to site name.
        $this->assertEquals(
            $SITE->fullname,
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $SITE->fullname, $filtered)
        );

        $filtered = format_text('{courseid}', FORMAT_HTML, ['context' => \context_system::instance()]);
        // Should be site ID (1).
        $this->assertEquals('1', $filtered, sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '1', $filtered));
    }
    /**
     * Create a course used by the course ID tag visibility tests.
     *
     * @param int $visible Whether the course is visible.
     * @return object The new course.
     */
    private function create_tagged_course($visible) {
        return $this->getDataGenerator()->create_course([
            'fullname' => 'Follow Up Course',
            'summary' => 'Follow up summary text.',
            'startdate' => mktime(0, 0, 0, 1, 15, 2030),
            'visible' => $visible,
        ]);
    }

    /**
     * Filter every course ID tag for a course and return the combined output.
     *
     * @param object $course The course to describe.
     * @return string Filtered output.
     */
    private function filter_course_id_tags($course) {
        $tags = '{coursename ' . $course->id . '}'
            . '{coursesummary ' . $course->id . '}'
            . '{coursestartdate ' . $course->id . '}';
        return format_text($tags, FORMAT_HTML, ['context' => \context_system::instance()]);
    }

    /**
     * Test that course ID tags do not describe a hidden course to an ordinary user.
     *
     * @return void
     */
    public function test_course_id_tags_omit_hidden_course(): void {
        set_config('allowhiddencoursetags', 0, 'filter_filtercodes');
        $course = $this->create_tagged_course(0);
        $this->setUser($this->getDataGenerator()->create_user());

        $filtered = $this->filter_course_id_tags($course);

        $this->assertStringNotContainsString(
            'Follow Up Course',
            $filtered,
            sprintf("Should not reveal the name of a hidden course
Actual: '%s'", $filtered)
        );
        $this->assertStringNotContainsString(
            'Follow up summary text.',
            $filtered,
            sprintf("Should not reveal the summary of a hidden course
Actual: '%s'", $filtered)
        );
        $this->assertStringNotContainsString(
            '2030',
            $filtered,
            sprintf("Should not reveal the start date of a hidden course
Actual: '%s'", $filtered)
        );
    }

    /**
     * Test that hidden courses are still described when the site allows it.
     *
     * This is the behaviour existing sites keep when they upgrade.
     *
     * @return void
     */
    public function test_course_id_tags_describe_hidden_course_when_allowed(): void {
        set_config('allowhiddencoursetags', 1, 'filter_filtercodes');
        $course = $this->create_tagged_course(0);
        $this->setUser($this->getDataGenerator()->create_user());

        $filtered = $this->filter_course_id_tags($course);

        $this->assertStringContainsString(
            'Follow Up Course',
            $filtered,
            sprintf("Should describe the hidden course
Actual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'Follow up summary text.',
            $filtered,
            sprintf("Should include the summary
Actual: '%s'", $filtered)
        );
    }

    /**
     * Test that users allowed to see hidden courses still get a description of them.
     *
     * @return void
     */
    public function test_course_id_tags_describe_hidden_course_for_privileged_user(): void {
        set_config('allowhiddencoursetags', 0, 'filter_filtercodes');
        $course = $this->create_tagged_course(0);

        // The setUp method already signed in as an administrator.
        $filtered = $this->filter_course_id_tags($course);

        $this->assertStringContainsString(
            'Follow Up Course',
            $filtered,
            sprintf("Should describe the hidden course
Actual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'Follow up summary text.',
            $filtered,
            sprintf("Should include the summary
Actual: '%s'", $filtered)
        );
    }

    /**
     * Test that visible courses are unaffected by the hidden course restriction.
     *
     * @return void
     */
    public function test_course_id_tags_describe_visible_course(): void {
        set_config('allowhiddencoursetags', 0, 'filter_filtercodes');
        $course = $this->create_tagged_course(1);
        $this->setUser($this->getDataGenerator()->create_user());

        $filtered = $this->filter_course_id_tags($course);

        $this->assertStringContainsString(
            'Follow Up Course',
            $filtered,
            sprintf("Should describe a visible course
Actual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'Follow up summary text.',
            $filtered,
            sprintf("Should include the summary
Actual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            '2030',
            $filtered,
            sprintf("Should include the start date
Actual: '%s'", $filtered)
        );
    }
}
