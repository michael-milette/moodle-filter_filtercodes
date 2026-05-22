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
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Test course conditional tags.
 *
 * @copyright  2017-2026 TNG Consulting Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
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
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifenrolled_when_enrolled(): void {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifenrolled}You are enrolled{/ifenrolled}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when enrolled.
        $this->assertStringContainsString(
            'You are enrolled',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'You are enrolled', $result)
        );
    }

    /**
     * Test ifenrolled when not enrolled.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifenrolled_not_enrolled(): void {
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
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotenrolled(): void {
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
        $this->assertStringContainsString(
            'You are NOT enrolled',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'You are NOT enrolled', $result)
        );
    }

    /**
     * Test ifincourse with specific course ID.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifincourse(): void {
        global $USER;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $text = '{ifincourse ' . $course->id . '}In this course{/ifincourse}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when enrolled in specified course.
        $this->assertStringContainsString(
            'In this course',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'In this course', $result)
        );
    }

    /**
     * Test ifnotincourse with specific course ID.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotincourse(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course();

        $text = '{ifnotincourse ' . $course->id . '}Not in this course{/ifnotincourse}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when NOT enrolled in specified course.
        $this->assertStringContainsString(
            'Not in this course',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not in this course', $result)
        );
    }

    /**
     * Test ifinsection conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifinsection(): void {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course(['numsections' => 5]);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'section' => 2]);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id);
        $context = \context_module::instance($cm->id);
        $PAGE->set_cm($cm, $course);

        $text = '{ifinsection}In a section{/ifinsection}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        $this->assertEquals(
            'In a section',
            $result,
            sprintf("Section content should show in an activity section\nActual: '%s'", $result)
        );
    }

    /**
     * Test ifnotinsection conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotinsection(): void {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course(['numsections' => 5]);
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);
        $PAGE->set_context($context);

        $text = '{ifnotinsection}Not in an activity section{/ifnotinsection}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        $this->assertEquals(
            'Not in an activity section',
            $result,
            sprintf("Non-section content should show on the course page\nActual: '%s'", $result)
        );
    }

    /**
     * Test ifingroup conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifingroup(): void {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Test Group']);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $USER->id]);

        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifingroup ' . $group->id . '}In the group{/ifingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when user is in the group.
        $this->assertStringContainsString(
            'In the group',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'In the group', $result)
        );
    }

    /**
     * Test ifnotingroup conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotingroup(): void {
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
        $this->assertStringContainsString(
            'Not in the group',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not in the group', $result)
        );
    }

    /**
     * Test nested ifingroup tags.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifingroup_nested(): void {
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
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifingroup_partial_tags(): void {
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
        $text = '{ifingroup ' . $group->id . '}A{ifingroup ' . $group->id . '}B'
            . '{ifingroup ' . $group->id . '}C{/ifingroup}{/ifingroup}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString(
            'AB',
            $result,
            sprintf("Outer true conditions should preserve reachable content\nActual: '%s'", $result)
        );
        $this->assertStringContainsString(
            'C',
            $result,
            sprintf("Innermost content should not be discarded\nActual: '%s'", $result)
        );
        $this->assertStringNotContainsString(
            '{/ifingroup}',
            $result,
            sprintf("Closing tags should be consumed when possible\nActual: '%s'", $result)
        );

        // Scenario 8: Interleaved different tags (mixing with other content).
        $text = '{ifingroup ' . $group->id . '}Start {firstname} {ifingroup none}Middle{/ifingroup} End';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('Start', $result);
        $this->assertStringContainsString('End', $result);
    }

    /**
     * Test ifingrouping conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifingrouping(): void {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'name' => 'Test Grouping']);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Group in Grouping']);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $USER->id]);

        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifingrouping ' . $grouping->id . '}In the grouping{/ifingrouping}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when user is in a group within the grouping.
        $this->assertStringContainsString(
            'In the grouping',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'In the grouping', $result)
        );
    }

    /**
     * Test ifnotingrouping conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotingrouping(): void {
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
        $this->assertStringContainsString(
            'Not in the grouping',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not in the grouping', $result)
        );
    }

    /**
     * Test nested ifingrouping tags.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifingrouping_nested(): void {
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
        $text = '{ifingrouping ' . $grouping->id . '}Hello '
            . '{ifingrouping ' . $grouping->id . '}World{/ifingrouping}.{/ifingrouping}';
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
     * Test sequential ifingrouping blocks where one contains a nested same-type block.
     * Regression test for GitHub issues #366 and #367.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifingrouping_sequential_with_nested(): void {
        global $USER, $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');
        $context = \context_course::instance($course->id);

        $PAGE->set_course($course);

        // Two groupings: user is in groupingA but not groupingB.
        $groupinga = $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'idnumber' => 'groupA']);
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $groupinga->id, 'groupid' => $groupa->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $USER->id]);

        $groupingb = $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'idnumber' => 'groupB']);

        // Issue #366: {groupA}A{/} {groupB}{groupA}B{/}{/} {groupA}C{/}
        // User is in A but not B. Expected: A and C visible, B hidden.
        $gaid = $groupinga->id;
        $gbid = $groupingb->id;
        $text = "{ifingrouping $gaid}A{/ifingrouping}"
            . "{ifingrouping $gbid}{ifingrouping $gaid}B{/ifingrouping}{/ifingrouping}"
            . "{ifingrouping $gaid}C{/ifingrouping}";
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('A', $result, 'First A-block should be visible');
        $this->assertStringNotContainsString('B', $result, 'B inside false outer should be hidden');
        $this->assertStringContainsString('C', $result, 'Last A-block should be visible');
        $this->assertStringNotContainsString('{ifingrouping', $result, 'No raw tags in output');

        // Issue #367: same outer tag used twice, second one wrapping an inner tag.
        // {groupA}A{/} {groupA}{groupB}B{/}{/} {groupB}C{/}
        // User is in A but not B. Expected: A visible, B hidden (outer A false for B? no — outer IS true),
        // inner B false so B not shown; C hidden (not in B).
        $text = "{ifingrouping $gaid}A{/ifingrouping}"
            . "{ifingrouping $gaid}{ifingrouping $gbid}B{/ifingrouping}{/ifingrouping}"
            . "{ifingrouping $gbid}C{/ifingrouping}";
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);
        $this->assertStringContainsString('A', $result, 'First block (in A) should be visible');
        $this->assertStringNotContainsString('B', $result, 'Inner B (not in groupB) should be hidden');
        $this->assertStringNotContainsString('C', $result, 'Standalone B block should be hidden (not in groupB)');
        $this->assertStringNotContainsString('{ifingrouping', $result, 'No raw tags in output');
    }

    /**
     * Test partial/unbalanced ifingrouping tags.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifingrouping_partial_tags(): void {
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
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifvisible(): void {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course(['visible' => 1]);
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifvisible}Course is visible{/ifvisible}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when course is visible.
        $this->assertStringContainsString(
            'Course is visible',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Course is visible', $result)
        );
    }

    /**
     * Test ifnotvisible conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotvisible(): void {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course(['visible' => 0]);
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);

        $text = '{ifnotvisible}Course is hidden{/ifnotvisible}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when course is hidden.
        $this->assertStringContainsString(
            'Course is hidden',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Course is hidden', $result)
        );
    }

    /**
     * Test ifinactivity conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifinactivity(): void {
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
        $this->assertStringContainsString(
            'Inside an activity',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Inside an activity', $result)
        );
    }

    /**
     * Test ifnotinactivity conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotinactivity(): void {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $PAGE->set_context($context);
        $PAGE->set_pagetype('course-view-topics');

        $text = '{ifnotinactivity}Not inside an activity{/ifnotinactivity}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should show content when NOT in activity context.
        $this->assertStringContainsString(
            'Not inside an activity',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not inside an activity', $result)
        );
    }

    /**
     * Test ifactivitycompleted conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifactivitycompleted(): void {
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
        $this->assertStringContainsString(
            'Activity completed!',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Activity completed!', $result)
        );
    }

    /**
     * Test ifnotactivitycompleted conditional.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotactivitycompleted(): void {
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
        $this->assertStringContainsString(
            'Activity not completed yet',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Activity not completed yet', $result)
        );
    }

    /**
     * Test ifactivitycompleted with COMPLETION_COMPLETE_FAIL (issue #346).
     *
     * A graded activity that the user failed must NOT be treated as completed.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifactivitycompleted_fail_state(): void {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        // Force a failed completion state by writing directly to the completion table.
        // update_state() with TRACKING_AUTOMATIC recomputes via internal_get_state and
        // would discard COMPLETION_COMPLETE_FAIL when no grade-based rules are configured.
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $assign->cmid,
            'userid' => $USER->id,
            'completionstate' => COMPLETION_COMPLETE_FAIL,
            'overrideby' => null,
            'timemodified' => time(),
        ]);
        \cache_helper::purge_by_definition('core', 'completion');

        $context = \context_module::instance($assign->cmid);

        $text = '{ifactivitycompleted ' . $assign->cmid . '}YES{/ifactivitycompleted}'
              . '{ifnotactivitycompleted ' . $assign->cmid . '}NO{/ifnotactivitycompleted}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        $this->assertStringNotContainsString(
            'YES',
            $result,
            sprintf("Failed activity must not be considered completed.\nActual: '%s'", $result)
        );
        $this->assertStringContainsString(
            'NO',
            $result,
            sprintf("Failed activity must be considered not completed.\nActual: '%s'", $result)
        );
    }

    /**
     * Test ifenrolpage conditional (on enrolment page).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifenrolpage(): void {
        global $PAGE;

        // Set page to enrol page.
        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_url('/enrol/index.php', ['id' => $course->id]);
        $PAGE->set_pagetype('enrol-index');

        $text = '{ifenrolpage}On enrolment page{/ifenrolpage}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            'On enrolment page',
            $result,
            sprintf("Enrol page content should show on enrolment page\nActual: '%s'", $result)
        );
    }

    /**
     * Test ifnotenrolpage conditional (not on enrolment page).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotenrolpage(): void {
        global $PAGE;

        // Set page to something other than enrol page.
        $PAGE->set_url('/course/view.php', ['id' => 2]);
        $PAGE->set_pagetype('course-view-topics');

        $text = '{ifnotenrolpage}Not on enrolment page{/ifnotenrolpage}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            'Not on enrolment page',
            $result,
            sprintf("Non-enrol page content should show away from enrolment page\nActual: '%s'", $result)
        );
    }

    /**
     * Test combined course conditionals.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_combined_course_conditionals(): void {
        global $USER;

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($USER->id, $course->id, 'student');

        $context = \context_course::instance($course->id);

        $text = '{ifenrolled}Enrolled{/ifenrolled} {ifnotincourse 999}Not in course 999{/ifnotincourse}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Both conditions should be processed.
        $this->assertStringContainsString(
            'Enrolled',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Enrolled', $result)
        );
        $this->assertStringContainsString(
            'Not in course 999',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not in course 999', $result)
        );
    }
}
