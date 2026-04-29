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
 * Unit tests for FilterCodes role-based conditional tags.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */

namespace filter_filtercodes;

/**
 * Unit tests for FilterCodes role-based conditional tags.
 *
 * Test role conditional tags like {ifstudent}, {ifteacher}, {ifadmin}, etc.
 *
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conditional_roles_test extends \advanced_testcase {
    /**
     * Setup the test framework
     *
     * @return void
     */
    public function setUp(): void {
        global $PAGE;
        parent::setUp();

        $this->resetAfterTest(true);

        // Enable FilterCodes filter at top level.
        filter_set_global_state('filtercodes', TEXTFILTER_ON);

        $PAGE->set_url(new \moodle_url('/'));
    }

    /**
     * Test ifadmin tag as administrator.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifadmin_as_admin(): void {
        $this->setAdminUser();

        $before = '{ifadmin}You are an admin{/ifadmin}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals('You are an admin', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are an admin', $filtered));
    }

    /**
     * Test ifadmin tag as regular user.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifadmin_as_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifadmin}You are an admin{/ifadmin}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals('', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered));
    }

    /**
     * Test ifstudent tag as student in course.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifstudent_as_student(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifstudent}You are a student{/ifstudent}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('You are a student', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are a student', $filtered));
    }

    /**
     * Test ifstudent tag as teacher (should not show).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifstudent_as_teacher(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $before = '{ifstudent}You are a student{/ifstudent}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        // Teacher should not see student-only content.
        $this->assertEquals('', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered));
    }

    /**
     * Test ifminstudent tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifminstudent(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifminstudent}You are at least a student{/ifminstudent}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('You are at least a student', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are at least a student', $filtered));
    }

    /**
     * Test ifteacher tag as editing teacher.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifteacher_as_teacher(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifteacher}You are a teacher{/ifteacher}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('You are a teacher', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are a teacher', $filtered));
    }

    /**
     * Test ifminteacher tag as teacher (should show).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifminteacher_as_teacher(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifminteacher}You are at least a teacher{/ifminteacher}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('You are at least a teacher', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are at least a teacher', $filtered));
    }

    /**
     * Test ifminteacher tag as student (should not show).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifminteacher_as_student(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $before = '{ifminteacher}You are at least a teacher{/ifminteacher}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered));
    }

    /**
     * Test ifassistant tag (non-editing teacher).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifassistant(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'teacher');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifassistant}You are an assistant{/ifassistant}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        // Should show for non-editing teacher.
        $this->assertEquals('You are an assistant', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are an assistant', $filtered));
    }

    /**
     * Test ifminassistant tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifminassistant(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'teacher');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifminassistant}You are at least an assistant{/ifminassistant}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('You are at least an assistant', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are at least an assistant', $filtered));
    }

    /**
     * Test ifmanager tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifmanager(): void {
        $user = $this->getDataGenerator()->create_user();
        $managerrole = get_archetype_roles('manager');
        $managerrole = reset($managerrole);

        // Assign manager role at system level.
        role_assign($managerrole->id, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        $before = '{ifmanager}You are a manager{/ifmanager}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals('You are a manager', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are a manager', $filtered));
    }

    /**
     * Test ifminmanager tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifminmanager(): void {
        $user = $this->getDataGenerator()->create_user();
        $managerrole = get_archetype_roles('manager');
        $managerrole = reset($managerrole);

        role_assign($managerrole->id, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        $before = '{ifminmanager}You are at least a manager{/ifminmanager}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals('You are at least a manager', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are at least a manager', $filtered));
    }

    /**
     * Test ifcreator tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifcreator(): void {
        $user = $this->getDataGenerator()->create_user();
        $creatorrole = get_archetype_roles('coursecreator');
        $creatorrole = reset($creatorrole);

        role_assign($creatorrole->id, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        $before = '{ifcreator}You are a course creator{/ifcreator}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals('You are a course creator', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are a course creator', $filtered));
    }

    /**
     * Test ifmincreator tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifmincreator(): void {
        $user = $this->getDataGenerator()->create_user();
        $creatorrole = get_archetype_roles('coursecreator');
        $creatorrole = reset($creatorrole);

        role_assign($creatorrole->id, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        $before = '{ifmincreator}You are at least a creator{/ifmincreator}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals('You are at least a creator', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are at least a creator', $filtered));
    }

    /**
     * Test ifincohort tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifincohort(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort([
            'name' => 'Test Cohort',
            'idnumber' => 'TESTCOHORT',
        ]);
        cohort_add_member($cohort->id, $user->id);
        $this->setUser($user);

        // Test with cohort ID.
        $before = "{ifincohort {$cohort->id}}You are in the cohort{/ifincohort}";
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals('You are in the cohort', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are in the cohort', $filtered));

        // Test with cohort idnumber.
        $before = "{ifincohort TESTCOHORT}You are in the cohort{/ifincohort}";
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals('You are in the cohort', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are in the cohort', $filtered));
    }

    /**
     * Test ifnotincohort tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifnotincohort(): void {
        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->setUser($user);

        // User is NOT in the cohort.
        $before = "{ifnotincohort {$cohort->id}}You are not in the cohort{/ifnotincohort}";
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals('You are not in the cohort', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are not in the cohort', $filtered));
    }

    /**
     * Test combined role conditionals.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_combined_role_conditionals(): void {
        $this->setAdminUser();

        $text = '{ifadmin}Admin{/ifadmin} {ifstudent}Student{/ifstudent}';
        $filtered = format_text($text, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Admin should see admin content but not student content.
        $this->assertEquals('Admin ', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Admin ', $filtered));
    }

    /**
     * Test ifcustomrole tag when user has the specified role.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifcustomrole_with_role(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifcustomrole student}You have the student role{/ifcustomrole}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('You have the student role', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You have the student role', $filtered));
        $this->assertStringNotContainsString('{ifcustomrole', $filtered,
            'Raw {ifcustomrole} opening tag leaked into output');
    }

    /**
     * Test ifcustomrole tag when user does not have the specified role.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifcustomrole_without_role(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifcustomrole student}You have the student role{/ifcustomrole}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered));
        $this->assertStringNotContainsString('{ifcustomrole', $filtered,
            'Raw {ifcustomrole} opening tag leaked into output');
    }

    /**
     * Test ifnotcustomrole tag when user has the specified role (should hide).
     *
     * Regression test for Fix-356: a tag name typo caused {ifnotcustomrole} to be
     * skipped entirely, leaving raw tags in the output.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifnotcustomrole_with_role(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifnotcustomrole student}You do NOT have the student role{/ifnotcustomrole}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered));
        $this->assertStringNotContainsString('{ifnotcustomrole', $filtered,
            'Raw {ifnotcustomrole} opening tag leaked into output (Fix-356 regression)');
        $this->assertStringNotContainsString('{/ifnotcustomrole}', $filtered,
            'Raw {/ifnotcustomrole} closing tag leaked into output (Fix-356 regression)');
    }

    /**
     * Test ifnotcustomrole tag when user does not have the specified role (should show).
     *
     * Regression test for Fix-356.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifnotcustomrole_without_role(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);
        $before = '{ifnotcustomrole student}You do NOT have the student role{/ifnotcustomrole}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

        $this->assertEquals('You do NOT have the student role', $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You do NOT have the student role', $filtered));
        $this->assertStringNotContainsString('{ifnotcustomrole', $filtered,
            'Raw {ifnotcustomrole} opening tag leaked into output (Fix-356 regression)');
        $this->assertStringNotContainsString('{/ifnotcustomrole}', $filtered,
            'Raw {/ifnotcustomrole} closing tag leaked into output (Fix-356 regression)');
    }

    /**
     * Catch-all regression test: parametric role tags must never leak raw into output.
     *
     * The Fix-356 bug (tag name typo passed to if_tag) caused {ifnotcustomrole} to
     * be skipped entirely, leaving raw tags in output. This test exercises every
     * parametric role tag in both true and false branches and asserts no raw tag
     * remnants survive filtering, regardless of whether the user has the role.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_parametric_role_tags_never_leak_raw(): void {
        global $PAGE;
        $course = $this->getDataGenerator()->create_course();

        // User WITH the student role (course context).
        $studentuser = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($studentuser->id, $course->id, 'student');

        // User WITHOUT the student role.
        $otheruser = $this->getDataGenerator()->create_user();

        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);

        $tags = ['ifcustomrole', 'ifnotcustomrole', 'ifhasarolename'];
        foreach ([$studentuser, $otheruser] as $user) {
            $this->setUser($user);
            foreach ($tags as $tag) {
                $before = '{' . $tag . ' student}content{/' . $tag . '}';
                $filtered = format_text($before, FORMAT_HTML, ['context' => $context]);

                $this->assertStringNotContainsString('{' . $tag, $filtered,
                    sprintf("Raw '{%s}' opening tag leaked for user id %d\nInput: '%s'\nOutput: '%s'",
                        $tag, $user->id, $before, $filtered));
                $this->assertStringNotContainsString('{/' . $tag . '}', $filtered,
                    sprintf("Raw '{/%s}' closing tag leaked for user id %d\nInput: '%s'\nOutput: '%s'",
                        $tag, $user->id, $before, $filtered));
            }
        }
    }

    /**
     * Test hierarchical role conditionals.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_hierarchical_roles(): void {
        $this->setAdminUser();

        // Admin should see all "min" content.
        $tests = [
            '{ifminmanager}' => true,
            '{ifmincreator}' => true,
            '{ifminteacher}' => true,
        ];

        foreach ($tests as $tag => $shouldShow) {
            $before = $tag . 'Content' . str_replace('ifmin', '/ifmin', $tag);
            $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);
            if ($shouldShow) {
                $this->assertStringContainsString('Content', $filtered, "Failed for: $tag");
                $this->assertStringNotContainsString($tag, $filtered, "Opening tag was not removed for: $tag");
            } else {
                $this->assertEquals('', $filtered,
                    sprintf("Content should be hidden for %s outside a course context\nActual: '%s'", $tag, $filtered));
            }
        }
    }
}
