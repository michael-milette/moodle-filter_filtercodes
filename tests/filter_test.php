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
 * Unit tests for FilterCodes filter.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers filter_filtercodes
 */

namespace filter_filtercodes;


/**
 * Unit tests for FilterCodes filter.
 *
 * Test that the filter produces the right content. Note that this currently
 * only tests some of the filter logic. Future releases will test more of the tags.
 *
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filter_test extends \advanced_testcase {
    /**
     * @var text_filter $filter The filter to test.
     */
    private \filter_filtercodes\text_filter $filter;

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
        $this->filter = new \filter_filtercodes\text_filter();
    }

    /**
     * Assert that the filter produces the expected output.
     *
     * @param string $before The text before filtering.
     * @param string $after The expected text after filtering.
     *
     * @return void
     */
    private function assert_filter_eq(
        string $before,
        string $after,
    ): void {
        $filtered = $this->filter->filter($before, ['no-cache' => true]);
        $this->assertEquals($after, $filtered);
    }

    /**
     * Test the ifingroup tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     *
     * @return void
     */
    public function test_filtercode_ifingroup(): void {
        global $PAGE, $USER;

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        // Test the ifingroup tag with a grouping that does not exist.
        $this->assert_filter_eq(
            '{ifingroup none}Hello World{/ifingroup}',
            ''
        );

        // Set up a group.
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $PAGE->course->id,
            'idnumber' => 'testgroup',
            'name' => 'Test Group',
        ]);

        // Test the ifingroup tag with a group not assigned to the user.
        $this->assert_filter_eq(
            '{ifingroup ' . $group->id . '}Hello World{/ifingroup}',
            '',
        );

        $this->getDataGenerator()->create_group_member([
            'groupid' => $group->id,
            'userid' => $USER->id,
        ]);

        // Test the ifingroup tag with a group assigned to the user.
        $this->assert_filter_eq(
            'Finally {ifingroup ' . $group->id . '}Hello World{/ifingroup}',
            'Finally Hello World',
        );
        $this->assert_filter_eq(
            'Finally with idnumber {ifingroup ' . $group->idnumber . '}Hello World{/ifingroup}',
            'Finally with idnumber Hello World',
        );

        // True in true.
        $this->assert_filter_eq(
            '{ifingroup ' . $group->id . '}Hello {ifingroup ' . $group->id . '}World{/ifingroup}.{/ifingroup}',
            'Hello World.',
        );

        // False in true.
        $this->assert_filter_eq(
            '{ifingroup ' . $group->id . '}Hello {ifingroup none}World{/ifingroup}.{/ifingroup}',
            'Hello .',
        );

        // True in false.
        $this->assert_filter_eq(
            '{ifingroup none}Hello {ifingroup ' . $group->id . '}World{/ifingroup}.{/ifingroup}',
            '',
        );

        // False in false.
        $this->assert_filter_eq(
            '{ifingroup none}Hello {ifingroup none}World{/ifingroup}.{/ifingroup}',
            '',
        );

        // Side by side with ifingrouping.
        $this->assert_filter_eq(
            '{ifingroup ' . $group->id . '}Hello{/ifingroup} {ifingroup none}World{/ifingroup}.',
            'Hello .',
        );

        // Test partials.
        $this->assert_filter_eq(
            '{ifingroup ' . $group->id . '}Hello {ifingroup none}World{/ifingroup}.',
            'Hello {ifingroup none}World.',
        );
        $this->assert_filter_eq(
            '{ifingroup none}Hello World{/ifingroup}{/ifingroup}.',
            '{/ifingroup}.',
        );
        $this->assert_filter_eq(
            '{ifingroup none}Hello {ifingroup ' . $group->id . '}World{/ifingroup}.',
            '.',
        );
        $this->assert_filter_eq(
            '{ifingroup none}Hello {ifingroup none}World{/ifingroup}.',
            '.',
        );
        $this->assert_filter_eq(
            '{ifingroup ' . $group->id . '}Hello {ifingroup none}{ifingroup none}World{/ifingroup}.',
            'Hello {ifingroup none}{ifingroup none}World.',
        );
    }

    /**
     * Test the ifingrouping tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     *
     * @return void
     */
    public function test_ifingrouping(): void {
        global $PAGE, $USER;

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        // Test the ifingrouping tag with a grouping that does not exist.
        $this->assert_filter_eq(
            '{ifingrouping none}Hello World{/ifingrouping}',
            ''
        );

        // Set up a grouping.
        $grouping = $this->getDataGenerator()->create_grouping([
            'courseid' => $PAGE->course->id,
            'idnumber' => 'testgrouping',
            'name' => 'Test Grouping',
        ]);
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $PAGE->course->id,
            'idnumber' => 'testgroup',
            'name' => 'Test Group',
        ]);
        $this->getDataGenerator()->create_grouping_group([
            'groupingid' => $grouping->id,
            'groupid' => $group->id,
        ]);

        // Test the ifingrouping tag with a grouping not assigned to the user.
        $this->assert_filter_eq(
            '{ifingrouping ' . $grouping->id . '}Hello World{/ifingrouping}',
            '',
        );

        // Assign the user to the group.
        $this->getDataGenerator()->create_group_member([
            'groupid' => $group->id,
            'userid' => $USER->id,
        ]);

        // Test the ifingrouping tag with a grouping assigned to the user.
        $this->assert_filter_eq(
            'Finally {ifingrouping ' . $grouping->id . '}Hello World{/ifingrouping}',
            'Finally Hello World',
        );

        $this->assert_filter_eq(
            'Finally with idnumber {ifingrouping ' . $grouping->idnumber . '}Hello World{/ifingrouping}',
            'Finally with idnumber Hello World',
        );

        // True in true.
        $this->assert_filter_eq(
            '{ifingrouping ' . $grouping->id . '}Hello {ifingrouping ' . $grouping->id . '}World{/ifingrouping}.{/ifingrouping}',
            'Hello World.',
        );

        // False in true.
        $this->assert_filter_eq(
            '{ifingrouping ' . $grouping->id . '}Hello {ifingrouping none}World{/ifingrouping}.{/ifingrouping}',
            'Hello .',
        );

        // True in false.
        $this->assert_filter_eq(
            '{ifingrouping none}Hello {ifingrouping ' . $grouping->id . '}World{/ifingrouping}.{/ifingrouping}',
            '',
        );

        // False in false.
        $this->assert_filter_eq(
            '{ifingrouping none}Hello {ifingrouping none}World{/ifingrouping}.{/ifingrouping}',
            '',
        );

        // Side by side.
        $this->assert_filter_eq(
            '{ifingrouping ' . $grouping->id . '}Hello{/ifingrouping} {ifingrouping none}World{/ifingrouping}.',
            'Hello .',
        );

        // Test partials.
        $this->assert_filter_eq(
            '{ifingrouping ' . $grouping->id . '}Hello {ifingrouping none}World{/ifingrouping}.',
            'Hello {ifingrouping none}World.',
        );
        $this->assert_filter_eq(
            '{ifingrouping none}Hello World{/ifingrouping}{/ifingrouping}.',
            '{/ifingrouping}.',
        );
        $this->assert_filter_eq(
            '{ifingrouping none}Hello {ifingrouping ' . $grouping->id . '}World{/ifingrouping}.',
            '.',
        );
        $this->assert_filter_eq(
            '{ifingrouping none}Hello {ifingrouping none}World{/ifingrouping}.',
            '.',
        );
        $this->assert_filter_eq(
            '{ifingrouping ' . $grouping->id . '}Hello {ifingrouping none}{ifingrouping none}World{/ifingrouping}.',
            'Hello {ifingrouping none}{ifingrouping none}World.',
        );
    }

    /**
     * Test the ifprofile tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     *
     * @return void
     */
    public function test_ifprofile(): void {
        global $USER;

        // Set up a user with specific profile fields.
        $USER->city = 'New York';
        $USER->country = 'US';
        $USER->email = 'testuser@example.com';

        // Test the 'is' condition.
        $this->assert_filter_eq(
            '{ifprofile city is "New York"}Welcome to New York{/ifprofile}',
            'Welcome to New York'
        );
        $this->assert_filter_eq(
            '{ifprofile city is "Los Angeles"}Welcome to LA{/ifprofile}',
            ''
        );

        // Test the 'not' condition.
        $this->assert_filter_eq(
            '{ifprofile city not "Los Angeles"}Not in LA{/ifprofile}',
            'Not in LA'
        );
        $this->assert_filter_eq(
            '{ifprofile city not "New York"}Not in NY{/ifprofile}',
            ''
        );

        // Test the 'contains' condition.
        $this->assert_filter_eq(
            '{ifprofile email contains "example.com"}Valid email{/ifprofile}',
            'Valid email'
        );
        $this->assert_filter_eq(
            '{ifprofile email contains "invalid.com"}Invalid email{/ifprofile}',
            ''
        );

        // Test the 'in' condition.
        $this->assert_filter_eq(
            '{ifprofile country in "US,CA"}North America{/ifprofile}',
            'North America'
        );
        $this->assert_filter_eq(
            '{ifprofile country in "UK,FR"}Europe{/ifprofile}',
            ''
        );

        // Nested conditions.
        $this->assert_filter_eq(
            '{ifprofile city is "New York"}{ifprofile country is "US"}Welcome to the US{/ifprofile}{/ifprofile}',
            'Welcome to the US'
        );
    }

    /**
     * Filter test.
     *
     * @covers \filter_filtercodes
     *
     * @return void
     */
    public function test_filtercodes(): void {
        global $CFG, $USER, $DB, $PAGE;

        $tests = [
            [
                'before' => 'No langx tags',
                'after'  => 'No langx tags',
            ],
            [
                'before' => '{langx es}Todo el texto está en español{/langx}',
                'after'  => '<span lang="es">Todo el texto está en español</span>',
            ],
            [
                'before' => '{langx fr}Ceci est du texte en français{/langx}',
                'after'  => '<span lang="fr">Ceci est du texte en français</span>',
            ],
            [
                'before' => 'Some non-filtered content plus some content in Spanish' .
                        ' ({langx es}mejor dicho, en español{/langx})',
                'after' => 'Some non-filtered content plus some content in Spanish' .
                        ' (<span lang="es">mejor dicho, en español</span>)',
            ],
            [
                'before' => 'Some non-filtered content plus some content in French ({langx fr}mieux en français{/langx})',
                'after'  => 'Some non-filtered content plus some content in French (<span lang="fr">mieux en français</span>)',
            ],
            [
                'before' => '{langx es}Algo de español{/langx}{langx fr}Quelque chose en français{/langx}',
                'after'  => '<span lang="es">Algo de español</span><span lang="fr">Quelque chose en français</span>',
            ],
            [
                'before' => 'Non-filtered {begin}{langx es}Algo de español{/langx}{langx fr}Quelque chose en français{/langx}' .
                        ' Non-filtered{end}',
                'after'  => 'Non-filtered {begin}<span lang="es">Algo de español</span><span lang="fr">Quelque chose en français' .
                        '</span> Non-filtered{end}',
            ],
            [
                'before' => '{langx}Bad filter syntax{langx}',
                'after'  => '{langx}Bad filter syntax{langx}',
            ],
            [
                'before' => '{langx}Bad filter syntax{langx}{langx es}Algo de español{/langx}',
                'after'  => '{langx}Bad filter syntax{langx}<span lang="es">Algo de español</span>',
            ],
            [
                'before' => 'Before {langx}Bad filter syntax{langx} {langx es}Algo de español{/langx} After',
                'after'  => 'Before {langx}Bad filter syntax{langx} <span lang="es">Algo de español</span> After',
            ],
            [
                'before' => 'Before {langx non-existent-language}Some content{/langx} After',
                'after'  => 'Before <span lang="non-existent-language">Some content</span> After',
            ],
            [
                'before' => 'Before {langx en_ca}Some content{/langx} After',
                'after'  => 'Before {langx en_ca}Some content{/langx} After',
            ],
            [
                'before' => 'Before {langx en-ca}Some content{/langx} After',
                'after'  => 'Before <span lang="en-ca">Some content</span> After',
            ],
            [
                'before' => 'Before{nbsp}: Some content After',
                'after'  => 'Before&nbsp;: Some content After',
            ],
            [
                'before' => 'Before{-}: Some content After',
                'after'  => 'Before&shy;: Some content After',
            ],
            [
                'before' => '{firstname}',
                'after'  => $USER->firstname,
            ],
            [
                'before' => '{lastname}',
                'after'  => $USER->lastname,
            ],
            [
                'before' => '{alternatename}',
                'after'  => (!is_null($USER->alternatename) && !empty(trim($USER->alternatename))) ?
                        $USER->alternatename : $USER->firstname,
            ],
            [
                'before' => '{fullname}',
                'after'  => $USER->firstname . ' ' . $USER->lastname,
            ],
            [
                'before' => '{getstring}help{/getstring}',
                'after'  => 'Help',
            ],
            [
                'before' => '{getstring:filter_filtercodes}pluginname{/getstring}',
                'after'  => 'Filter Codes',
            ],
            [
                'before' => '{city}',
                'after'  => $USER->city,
            ],
            [
                'before' => '{country}',
                'after'  => !empty($USER->country) ? get_string($USER->country, 'countries') : '',
            ],
            [
                'before' => '{email}',
                'after'  => $USER->email,
            ],
            [
                'before' => '{userid}',
                'after'  => $USER->id,
            ],
            [
                'before' => '%7Buserid%7D',
                'after'  => $USER->id,
            ],
            [
                'before' => '{idnumber}',
                'after'  => $USER->idnumber,
            ],
            [
                'before' => '{institution}',
                'after'  => $USER->institution,
            ],
            [
                'before' => '{department}',
                'after'  => $USER->department,
            ],
            [
                'before' => '{usercount}',
                'after'  => $DB->count_records('user', ['deleted' => 0]) - 2,
            ],
            [
                'before' => '{usersactive}',
                'after'  => $DB->count_records('user', ['deleted' => 0, 'suspended' => 0, 'confirmed' => 1]) - 2,
            ],
            [
                'before' => '{courseid}',
                'after'  => $PAGE->course->id,
            ],
            [
                'before' => '{courseidnumber}',
                'after'  => $PAGE->course->idnumber,
            ],
            [
                'before' => '%7Bcourseid%7D',
                'after'  => $PAGE->course->id,
            ],
            [
                'before' => '{coursename}',
                'after'  => $PAGE->course->fullname,
            ],
            [
                'before' => '{courseshortname}',
                'after'  => $PAGE->course->shortname,
            ],
            [
                'before' => '{coursecount}',
                'after'  => $DB->count_records('course', []) - 1,
            ],
            [
                'before' => '{coursesactive}',
                'after'  => $DB->count_records('course', ['visible' => 1]) - 1,
            ],
            [
                'before' => '{coursesummary}',
                'after'  => $PAGE->course->summary,
            ],
            [
                'before' => '{siteyear}',
                'after'  => date('Y'),
            ],
            [
                'before' => '{editingtoggle}',
                'after'  => ($PAGE->user_is_editing() ? 'off' : 'on'),
            ],
            [
                'before' => '{wwwroot}',
                'after'  => $CFG->wwwroot,
            ],
            [
                'before' => '{wwwcontactform}',
                'after'  => $CFG->wwwroot . '/local/contact/index.php',
            ],
            [
                'before' => '{protocol}',
                'after'  => 'http' . (is_https() ? 's' : ''),
            ],
            [
                'before' => '{pagepath}',
                'after'  => '/?',
            ],
            [
                'before' => '{ipaddress}',
                'after'  => getremoteaddr(),
            ],
            [
                'before' => '{sesskey}',
                'after'  => sesskey(),
            ],
            [
                'before' => '%7Bsesskey%7D',
                'after'  => sesskey(),
            ],
            [
                'before' => '{coursemoduleid}',
                'after'  => (isset($PAGE->cm->id) ? $PAGE->cm->id : '{coursemoduleid}'),
            ],
            [
                'before' => '{sectionid}',
                'after'  => @$PAGE->cm->sectionnum,
            ],
            [
                'before' => '%7Bsectionid%7D',
                'after'  => @$PAGE->cm->sectionnum,
            ],
            [
                'before' => '{readonly}',
                'after'  => 'readonly="readonly"',
            ],
            [
                'before' => '{fa fa-icon-name}',
                'after'  => '<span class="fa fa-icon-name" aria-hidden="true"></span>',
            ],
            [
                'before' => '{glyphicon glyphicon-name}',
                'after'  => '<span class="glyphicon glyphicon-name" aria-hidden="true"></span>',
            ],
            [
                'before' => '{ifingrouping a}{ifingrouping b}Hello World{/ifingrouping}{/ifingrouping}',
                'after'  => '',
            ],
            [
                'before' => '{ifingroup a}{ifingroup b}Hello World{/ifingroup}{/ifingroup}',
                'after'  => '',
            ],
            [
                'before' => '{ifnotingrouping a}{ifnotingrouping b}Hello World{/ifnotingrouping}{/ifnotingrouping}',
                'after'  => 'Hello World',
            ],
            [
                'before' => '{ifnotingroup a}{ifnotingroup b}Hello World{/ifnotingroup}{/ifnotingroup}',
                'after'  => 'Hello World',
            ],
            [
                'before' => '{ifnotingrouping a}{ifnotingrouping b}Hello World{/ifnotingrouping}',
                'after'  => '{ifnotingrouping b}Hello World',
            ],
            [
                'before' => '{ifactivitycompleted 123456}Hello World{/ifactivitycompleted}',
                'after'  => '{ifactivitycompleted 123456}Hello World{/ifactivitycompleted}',
            ],
            [
                'before' => '{ifnotactivitycompleted 123456}Hello World{/ifnotactivitycompleted}',
                'after'  => '{ifnotactivitycompleted 123456}Hello World{/ifnotactivitycompleted}',
            ],
        ];

        foreach ($tests as $test) {
            $filtered = format_text($test['before'], FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertEquals($test['after'], $filtered);
        }
    }
}
