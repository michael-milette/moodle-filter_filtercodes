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
 * Unit tests for the FilterCodes upgrade steps.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_filter_filtercodes_upgrade
 */

namespace filter_filtercodes;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/filter/filtercodes/db/upgrade.php');

#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_filter_filtercodes_upgrade')]
/**
 * Unit tests for the FilterCodes upgrade steps.
 *
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_filter_filtercodes_upgrade
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Version of the plugin released before the hidden course setting was introduced.
     */
    const VERSION_BEFORE_HIDDEN_COURSE_SETTING = 2026072200;

    /**
     * Test that an existing site keeps describing hidden courses after upgrading.
     *
     * New installations default to not describing them. Sites which upgrade must keep the
     * behaviour they already had, so that pages promoting a hidden course keep working.
     *
     * @return void
     */
    public function test_upgrade_keeps_hidden_course_tags_working(): void {
        $this->resetAfterTest(true);

        unset_config('allowhiddencoursetags', 'filter_filtercodes');
        set_config('version', self::VERSION_BEFORE_HIDDEN_COURSE_SETTING, 'filter_filtercodes');

        xmldb_filter_filtercodes_upgrade(self::VERSION_BEFORE_HIDDEN_COURSE_SETTING);

        $this->assertEquals(
            1,
            get_config('filter_filtercodes', 'allowhiddencoursetags'),
            'An upgraded site should continue to describe hidden courses.'
        );
    }

    /**
     * Test that the upgrade does not overwrite a choice the administrator already made.
     *
     * @return void
     */
    public function test_upgrade_respects_an_existing_setting(): void {
        $this->resetAfterTest(true);

        set_config('allowhiddencoursetags', 0, 'filter_filtercodes');
        set_config('version', self::VERSION_BEFORE_HIDDEN_COURSE_SETTING, 'filter_filtercodes');

        xmldb_filter_filtercodes_upgrade(self::VERSION_BEFORE_HIDDEN_COURSE_SETTING);

        $this->assertEquals(
            0,
            get_config('filter_filtercodes', 'allowhiddencoursetags'),
            'The upgrade should not change a setting the administrator has already chosen.'
        );
    }
}
