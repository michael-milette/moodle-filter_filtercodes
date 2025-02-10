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

use filter_filtercodes;

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
     * Filter test.
     *
     * @covers \filter_filtercodes
     *
     * @return void
     */
    public function test_filtercodes(): void {
        global $CFG, $USER, $DB, $PAGE;

        // Create a test course.
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

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
        ];

        foreach ($tests as $test) {
            $filtered = format_text($test['before'], FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertEquals($test['after'], $filtered);
        }
    }
}
