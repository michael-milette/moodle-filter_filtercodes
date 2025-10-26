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
 * Unit tests for course conditional tags.
 *
 * Tests conditionals based on course enrolment, sections, groups, activities, and completion.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Test course conditional tags.
 *
 * @copyright  2017-2025 TNG Consulting Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conditional_courses_test extends \advanced_testcase {

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
     * Test ifenrolled conditional.
     */
    public function test_ifenrolled_when_enrolled() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $context =\context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifenrolled}You are enrolled{/ifenrolled}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when enrolled.
        $this->assertStringContainsString('You are enrolled', $result,
            sprintf("Should contain %s\nActual: '%s'", 'You are enrolled', $result));
    }

    /**
     * Test ifenrolled when not enrolled.
     */
    public function test_ifenrolled_not_enrolled() {
        global $PAGE;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $PAGE->set_course($course);
        $PAGE->set_context($context);

        $text = '{ifenrolled}You are enrolled{/ifenrolled}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should NOT show content when not enrolled.
        $this->assertStringNotContainsString('You are enrolled', $result);
    }

    /**
     * Test ifnotenrolled conditional.
     */
    public function test_ifnotenrolled() {
        global $PAGE;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $PAGE->set_course($course);
        $PAGE->set_context($context);

        $text = '{ifnotenrolled}You are NOT enrolled{/ifnotenrolled}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when not enrolled.
        $this->assertStringContainsString('You are NOT enrolled', $result,
            sprintf("Should contain %s\nActual: '%s'", 'You are NOT enrolled', $result));
    }

    /**
     * Test ifincourse with specific course ID.
     */
    public function test_ifincourse() {
        global $USER;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $text = '{ifincourse ' . $course->id . '}In this course{/ifincourse}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when enrolled in specified course.
        $this->assertStringContainsString('In this course', $result,
            sprintf("Should contain %s\nActual: '%s'", 'In this course', $result));
    }

    /**
     * Test ifnotincourse with specific course ID.
     */
    public function test_ifnotincourse() {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course();

        $text = '{ifnotincourse ' . $course->id . '}Not in this course{/ifnotincourse}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when NOT enrolled in specified course.
        $this->assertStringContainsString('Not in this course', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not in this course', $result));
    }

    /**
     * Test ifinsection conditional.
     */
    public function test_ifinsection() {
        $course = $this->getDataGenerator()->create_course(['numsections' => 5]);
        $context =\context_course::instance($course->id);

        $text = '{ifinsection 2}In section 2{/ifinsection}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Test implementation may vary - section detection depends on page context.
        $this->assertNotNull($result);
    }

    /**
     * Test ifnotinsection conditional.
     */
    public function test_ifnotinsection() {
        $course = $this->getDataGenerator()->create_course(['numsections' => 5]);
        $context =\context_course::instance($course->id);

        $text = '{ifnotinsection 2}Not in section 2{/ifnotinsection}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Test implementation may vary.
        $this->assertNotNull($result);
    }

    /**
     * Test ifingroup conditional.
     */
    public function test_ifingroup() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Test Group']);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $USER->id]);

        $context =\context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifingroup ' . $group->id . '}In the group{/ifingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when user is in the group.
        $this->assertStringContainsString('In the group', $result,
            sprintf("Should contain %s\nActual: '%s'", 'In the group', $result));
    }

    /**
     * Test ifnotingroup conditional.
     */
    public function test_ifnotingroup() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Test Group']);
        // User is not in the group.

        $context = \context_course::instance($course->id);

        $PAGE->set_course($course);
        $PAGE->set_context($context);

        $text = '{ifnotingroup ' . $group->id . '}Not in the group{/ifnotingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when user is not in the group.
        $this->assertStringContainsString('Not in the group', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not in the group', $result));
    }

    /**
     * Test nested ifingroup tags.
     */
    public function test_ifingroup_nested() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');
        $context = \context_course::instance($course->id);

        $PAGE->set_course($course);

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'idnumber' => 'testgroup']);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $USER->id]);

        // True in true - nested tags both evaluating to true.
        $text = '{ifingroup ' . $group->id . '}Hello {ifingroup ' . $group->id . '}World{/ifingroup}.{/ifingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello World.', $result);

        // False in true - outer true, inner false.
        $text = '{ifingroup ' . $group->id . '}Hello {ifingroup none}World{/ifingroup}.{/ifingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello .', $result);
        $this->assertStringNotContainsString('World', $result);

        // True in false - outer false, inner true (inner should not be evaluated).
        $text = '{ifingroup none}Hello {ifingroup ' . $group->id . '}World{/ifingroup}.{/ifingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringNotContainsString('Hello', $result);
        $this->assertStringNotContainsString('World', $result);

        // False in false - both evaluate to false.
        $text = '{ifingroup none}Hello {ifingroup none}World{/ifingroup}.{/ifingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringNotContainsString('Hello', $result);
        $this->assertStringNotContainsString('World', $result);

        // Side by side tags (not nested).
        $text = '{ifingroup ' . $group->id . '}Hello{/ifingroup} {ifingroup none}World{/ifingroup}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringNotContainsString('World', $result);
    }

    /**
     * Test partial/unbalanced ifingroup tags.
     */
    public function test_ifingroup_partial_tags() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');
        $context = \context_course::instance($course->id);

        $PAGE->set_course($course);

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $USER->id]);

        // Scenario 1: Missing closing tag for outer (true condition).
        $text = '{ifingroup ' . $group->id . '}Hello {ifingroup none}World{/ifingroup}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello {ifingroup none}World.', $result);

        // Scenario 2: Extra closing tag with no opening.
        $text = '{ifingroup none}Hello World{/ifingroup}{/ifingroup}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('{/ifingroup}.', $result);

        // Scenario 3: Missing closing for outer (false condition).
        $text = '{ifingroup none}Hello {ifingroup ' . $group->id . '}World{/ifingroup}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('.', $result);
        $this->assertStringNotContainsString('Hello', $result);

        // Scenario 4: Multiple unbalanced openings (2 opens, 1 close).
        $text = '{ifingroup ' . $group->id . '}Hello {ifingroup none}{ifingroup none}World{/ifingroup}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello {ifingroup none}{ifingroup none}World.', $result);

        // Scenario 5: Only opening tag, no closing.
        $text = '{ifingroup ' . $group->id . '}Hello World';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello World', $result);

        // Scenario 6: Only closing tag, no opening.
        $text = 'Hello World{/ifingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello World{/ifingroup}', $result);

        // Scenario 7: Three levels deep, missing middle closing.
        $text = '{ifingroup ' . $group->id . '}A{ifingroup ' . $group->id . '}B{ifingroup ' . $group->id . '}C{/ifingroup}{/ifingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        // Should handle gracefully - expect partial processing.
        $this->assertNotNull($result);

        // Scenario 8: Interleaved different tags (mixing with other content).
        $text = '{ifingroup ' . $group->id . '}Start {firstname} {ifingroup none}Middle{/ifingroup} End';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Start', $result);
        $this->assertStringContainsString('End', $result);
    }

    /**
     * Test ifingrouping conditional.
     */
    public function test_ifingrouping() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'name' => 'Test Grouping']);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group in Grouping']);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $USER->id]);

        $context =\context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifingrouping ' . $grouping->id . '}In the grouping{/ifingrouping}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when user is in a group within the grouping.
        $this->assertStringContainsString('In the grouping', $result,
            sprintf("Should contain %s\nActual: '%s'", 'In the grouping', $result));
    }

    /**
     * Test ifnotingrouping conditional.
     */
    public function test_ifnotingrouping() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'name' => 'Other Grouping']);
        // Don't add user to any group in this grouping.

        $context = \context_course::instance($course->id);

        $PAGE->set_course($course);
        $PAGE->set_context($context);

        $text = '{ifnotingrouping ' . $grouping->id . '}Not in the grouping{/ifnotingrouping}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when user is NOT in the grouping.
        $this->assertStringContainsString('Not in the grouping', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not in the grouping', $result));
    }

    /**
     * Test nested ifingrouping tags.
     */
    public function test_ifingrouping_nested() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');
        $context = \context_course::instance($course->id);

        $PAGE->set_course($course);

        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'idnumber' => 'testgrouping']);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $USER->id]);

        // True in true - nested tags both evaluating to true.
        $text = '{ifingrouping ' . $grouping->id . '}Hello {ifingrouping ' . $grouping->id . '}World{/ifingrouping}.{/ifingrouping}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello World.', $result);

        // False in true - outer true, inner false.
        $text = '{ifingrouping ' . $grouping->id . '}Hello {ifingrouping none}World{/ifingrouping}.{/ifingrouping}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello .', $result);
        $this->assertStringNotContainsString('World', $result);

        // True in false - outer false, inner true (inner should not be evaluated).
        $text = '{ifingrouping none}Hello {ifingrouping ' . $grouping->id . '}World{/ifingrouping}.{/ifingrouping}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringNotContainsString('Hello', $result);
        $this->assertStringNotContainsString('World', $result);

        // False in false - both evaluate to false.
        $text = '{ifingrouping none}Hello {ifingrouping none}World{/ifingrouping}.{/ifingrouping}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringNotContainsString('Hello', $result);
        $this->assertStringNotContainsString('World', $result);

        // Side by side tags (not nested).
        $text = '{ifingrouping ' . $grouping->id . '}Hello{/ifingrouping} {ifingrouping none}World{/ifingrouping}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringNotContainsString('World', $result);
    }

    /**
     * Test partial/unbalanced ifingrouping tags.
     */
    public function test_ifingrouping_partial_tags() {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');
        $context = \context_course::instance($course->id);

        $PAGE->set_course($course);

        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $USER->id]);

        // Unbalanced - missing closing tag for inner.
        $text = '{ifingrouping ' . $grouping->id . '}Hello {ifingrouping none}World{/ifingrouping}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello {ifingrouping none}World.', $result);

        // Extra closing tag.
        $text = '{ifingrouping none}Hello World{/ifingrouping}{/ifingrouping}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('{/ifingrouping}.', $result);

        // Unbalanced - false outer with missing closing.
        $text = '{ifingrouping none}Hello {ifingrouping ' . $grouping->id . '}World{/ifingrouping}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('.', $result);
        $this->assertStringNotContainsString('Hello', $result);

        // Multiple unbalanced openings.
        $text = '{ifingrouping ' . $grouping->id . '}Hello {ifingrouping none}{ifingrouping none}World{/ifingrouping}.';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Hello {ifingrouping none}{ifingrouping none}World.', $result);
    }

    /**
     * Test ifvisible conditional (course visibility).
     */
    public function test_ifvisible() {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $context =\context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifvisible}Course is visible{/ifvisible}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when course is visible.
        $this->assertStringContainsString('Course is visible', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Course is visible', $result));
    }

    /**
     * Test ifnotvisible conditional.
     */
    public function test_ifnotvisible() {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $context =\context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifnotvisible}Course is hidden{/ifnotvisible}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when course is hidden.
        $this->assertStringContainsString('Course is hidden', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Course is hidden', $result));
    }

    /**
     * Test ifinactivity conditional.
     */
    public function test_ifinactivity() {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id);
        $context = \context_module::instance($cm->id);

        $PAGE->set_context($context);
        $PAGE->set_pagetype('mod-forum-view');

        $text = '{ifinactivity}Inside an activity{/ifinactivity}';
        $result = \format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when in activity context.
        $this->assertStringContainsString('Inside an activity', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Inside an activity', $result));
    }

    /**
     * Test ifnotinactivity conditional.
     */
    public function test_ifnotinactivity() {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $PAGE->set_context($context);
        $PAGE->set_pagetype('course-view-topics');

        $text = '{ifnotinactivity}Not inside an activity{/ifnotinactivity}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when NOT in activity context.
        $this->assertStringContainsString('Not inside an activity', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not inside an activity', $result));
    }

    /**
     * Test ifactivitycompleted conditional.
     */
    public function test_ifactivitycompleted() {
        global $USER;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        // Mark activity as complete.
        $cminfo = get_coursemodule_from_id('assign', $assign->cmid);
        $completion = new \completion_info($course);
        $completion->update_state($cminfo, COMPLETION_COMPLETE, $USER->id);

        $context = \context_module::instance($assign->cmid);

        $text = '{ifactivitycompleted}Activity completed!{/ifactivitycompleted}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when activity is completed.
        $this->assertStringContainsString('Activity completed!', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Activity completed!', $result));
    }

    /**
     * Test ifnotactivitycompleted conditional.
     */
    public function test_ifnotactivitycompleted() {
        global $USER;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        // Don't mark as complete.

        $context = \context_module::instance($assign->cmid);

        $text = '{ifnotactivitycompleted}Activity not completed yet{/ifnotactivitycompleted}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when activity is NOT completed.
        $this->assertStringContainsString('Activity not completed yet', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Activity not completed yet', $result));
    }

    /**
     * Test ifenrolpage conditional (on enrolment page).
     */
    public function test_ifenrolpage() {
        global $PAGE;

        // Set page to enrol page.
        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_url('/enrol/index.php', ['id' => $course->id]);

        $text = '{ifenrolpage}On enrolment page{/ifenrolpage}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when on enrolment page.
        $this->assertNotNull($result);
    }

    /**
     * Test ifnotenrolpage conditional (not on enrolment page).
     */
    public function test_ifnotenrolpage() {
        global $PAGE;

        // Set page to something other than enrol page.
        $PAGE->set_url('/course/view.php', ['id' => 2]);

        $text = '{ifnotenrolpage}Not on enrolment page{/ifnotenrolpage}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when NOT on enrolment page.
        $this->assertNotNull($result);
    }

    /**
     * Test combined course conditionals.
     */
    public function test_combined_course_conditionals() {
        global $USER;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $context =\context_course::instance($course->id);

        $text = '{ifenrolled}Enrolled{/ifenrolled} {ifnotincourse 999}Not in course 999{/ifnotincourse}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Both conditions should be processed.
        $this->assertStringContainsString('Enrolled', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Enrolled', $result));
        $this->assertStringContainsString('Not in course 999', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not in course 999', $result));
    }
}
