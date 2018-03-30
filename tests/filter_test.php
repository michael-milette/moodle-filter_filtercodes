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
 * @copyright  2017-2018 TNG Consulting Inc. - www.tngconsulting.ca
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
 * @copyright  2017-2018 TNG Consulting Inc. - www.tngconsulting.ca
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
        global $CFG;

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
        );

        foreach ($tests as $test) {
            $this->assertEquals($test['after'], $this->filter->filter($test['before']));
        }
    }
}
