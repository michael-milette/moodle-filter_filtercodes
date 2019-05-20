<?php
// This file is part of FilterCodes for Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for FilterCodes filter.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2019 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/filter/filtercodes/filter.php');

/**
 * Unit tests for FilterCodes filter.
 *
 * Test that the filter produces the right content. Note that this currently
 * only tests some of the filter logic. Future releases will test more of the tags.
 *
 * @copyright  2017-2019 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_filtercodes_testcase extends advanced_testcase {

    /**
     * @var filter_filtercode $filter Instance of filtercodes.
     */
    protected $filter;

    /**
     * Setup the test framework
     *
     * @return void
     */
    protected function setUp() {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->filter = new filter_filtercodes(context_system::instance(), array());
    }

    /**
     * Filter test.
     *
     * @return void
     */
    public function test_filter_filtercodes() {
        global $CFG, $USER, $DB;

        $this->setadminuser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        filter_set_local_state('filtercodes', $context->id, TEXTFILTER_ON);

        $tests = array(
            array (
                'before' => 'No langx tags',
                'after'  => 'No langx tags',
            ),
            array (
                'before' => '{langx es}Todo el texto está en español{langx}',
                'after'  => '<span lang="es">Todo el texto está en español<span>',
            ),
            array (
                'before' => '{langx fr}Ceci est du texte en français{langx}',
                'after'  => '<span lang="fr">Ceci est du texte en français<span>',
            ),
            array (
                'before' => 'Some non-filtered content plus some content in Spanish' .
                        ' ({langx es}mejor dicho, en español{langx})',
                'after' => 'Some non-filtered content plus some content in Spanish' .
                        ' (<span lang="es">mejor dicho, en español</span>)',
            ),
            array (
                'before' => 'Some non-filtered content plus some content in French' .
                        ' ({langx fr}mieux en français){langx}',
                'after'  => 'Some non-filtered content plus some content in French' .
                        ' (<span lang="fr">mieux en français</span>)',
            ),
            array (
                'before' => '{langx es}Algo en español{langx}{langx fr}Quelque chose en français{langx}',
                'after'  => '<span lang="es">Algo en español</span><span lang="fr">Quelque chose en français</span>',
            ),
            array (
                'before' => 'Non-filtered {begin}{langx es}Algo en español{langx}{langx fr}Quelque chose en français{langx}'.
                        'Non-filtered{end}',
                'after'  => 'Non-filtered {begin}<span lang="es">Algo en español</span><span lang="fr">Quelque chose en français'.
                        '</span> Non-filtered{end}',
            ),
            array (
                'before' => '{langx}Bad filter syntax{langx}',
                'after'  => '{langx}Bad filter syntax{langx}',
            ),
            array (
                'before' => '{langx}Bad filter syntax{langx}{langx es}Algo de español{langx}',
                'after'  => '{langx}Bad filter syntax{langx}<span lang="es">Algo en español</span>',
            ),
            array (
                'before' => 'Before {langx}Bad filter syntax{langx}{langx es}Algo de español{langx} After',
                'after'  => 'Before {langx}Bad filter syntax{langx}<span lang="es">Algo en español</span> After',
            ),
            array (
                'before' => 'Before {langx non-existent-language}Some content{langx} After',
                'after'  => 'Before <span lang="non-existent-language">Some content</span> After',
            ),
            array (
                'before' => 'Before {langx en_ca}Some content{langx} After',
                'after'  => 'Before <span lang="en_ca"}Some content</span> After',
            ),
            array (
                'before' => 'Before {langx en-ca}Some content{langx} After',
                'after'  => 'Before <span lang="en-ca"}Some content</span> After',
            ),
            array (
                'before' => 'Before{nbsp}: Some content After',
                'after'  => 'Before&nbsp;: Some content After',
            ),
            array (
                'before' => '{firstname}',
                'after'  => $USER->firstname,
            ),
            array (
                'before' => '{lastname}',
                'after'  => $USER->lastname,
            ),
            array (
                'before' => '{{alternatename}}',
                'after'  => $USER->alternatename,
            ),
            array (
                'before' => '{fullname}',
                'after'  => $USER->lastname . ' ' . $USER->lastname,
            ),
            array (
                'before' => '{getstring}help{/getstring}',
                'after'  => 'Help',
            ),
            array (
                'before' => '{getstring}help{/getstring}',
                'after'  => 'Help',
            ),
            array (
                'before' => '{city}',
                'after'  => $USER->city,
            ),
            array (
                'before' => '{country}',
                'after'  => get_string($USER->country, 'countries'),
            ),
            array (
                'before' => '{email}',
                'after'  => $USER->email,
            ),
            array (
                'before' => '{userid}',
                'after'  => $USER->id,
            ),
            array (
                'before' => '%7Buserid%7D',
                'after'  => $USER->id,
            ),
            array (
                'before' => '{idnumber}',
                'after'  => $USER->idnumber,
            ),
            array (
                'before' => '{institution}',
                'after'  => $USER->institution,
            ),
            array (
                'before' => '{department}',
                'after'  => $USER->department,
            ),
            array (
                'before' => '{usercount}',
                'after'  => $DB->count_records('user', array('deleted' => 0)) - 2,
            ),
            array (
                'before' => '{usersactive}',
                'after'  => $DB->count_records('user', array('deleted' => 0, 'suspended' => 0, 'confirmed' => 1)) - 2,
            ),
            array (
                'before' => '{course}',
                'after'  => $course->id,
            ),
            array (
                'before' => '%7Bcourseid%7D',
                'after'  => $course->id,
            ),
            array (
                'before' => '{coursename}',
                'after'  => $course->fullname,
            ),
            array (
                'before' => '{courseshortname}',
                'after'  => $course->shortname,
            ),
            array (
                'before' => '{coursecount}',
                'after'  => $DB->count_records('course', array()) - 1,
            ),
            array (
                'before' => '{coursesactive}',
                'after'  => $DB->count_records('course', array('visible' => 1)) - 1,
            ),
            array (
                'before' => '{siteyear}',
                'after'  => date('Y'),
            ),
            array (
                'before' => '{wwwroot}',
                'after'  => $CFG->wwwroot,
            ),
            array (
                'before' => '{protocol}',
                'after'  => 'http' . (is_https() ? 's' : ''),
            ),
            array (
                'before' => '{pagepath}',
                'after'  => $PAGE->url->out_as_local_url(),
            ),
            array (
                'before' => '{ipaddress}',
                'after'  => getremoteaddr(),
            ),
            array (
                'before' => '{sesskey}',
                'after'  => sesskey(),
            ),
            array (
                'before' => '%7Bsesskey%7D',
                'after'  => sesskey(),
            ),
            array (
                'before' => '{sectionid}',
                'after'  => @$PAGE->cm->sectionnum,
            ),
            array (
                'before' => '%7Bsectionid%7D',
                'after'  => @$PAGE->cm->sectionnum,
            ),
            array (
                'before' => '{readonly}',
                'after'  => 'readonly="readonly"',
            ),
            array (
                'before' => '{fa fa-icon-name}',
                'after'  => '<span class="fa-icon-name" aria-hidden="true"></span>',
            ),
            array (
                'before' => '{glyphicon glyphion-name}',
                'after'  => '<span class="glyphicon glyphion-nam" aria-hidden="true"></span>',
            ),
        );

        foreach ($tests as $test) {
            $this->assertEquals($test['after'], $this->filter->filter($test['before']));
        }
    }
}
