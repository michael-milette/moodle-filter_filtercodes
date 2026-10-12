<?php
// This file is part of the FilterCodes plugin for Moodle - https://moodle.org/
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
// along with FilterCodes.  If not, see <https://www.gnu.org/licenses/>.

/**
 * FilterCodes filter upgrade code.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade code for the FilterCodes filter plugin.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_filter_filtercodes_upgrade($oldversion = 0) {

    // Moodle v2.7.0 release upgrade line.
    // Upgrade steps below.

    if ($oldversion < 2026101200) {
        // Tags which take a course ID used to describe hidden courses to everyone. New sites now
        // restrict that, but existing sites keep the old behaviour so that pages which promote a
        // hidden course carry on working. Administrators can change this in the plugin settings.
        if (get_config('filter_filtercodes', 'allowhiddencoursetags') === false) {
            set_config('allowhiddencoursetags', 1, 'filter_filtercodes');
        }

        upgrade_plugin_savepoint(true, 2026101200, 'filter', 'filtercodes');
    }

    return true;
}
