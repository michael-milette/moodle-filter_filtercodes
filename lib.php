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
 * Library of functions for filtercodes.
 *
 * @package   filter_filtercodes
 * @copyright 2016-2025 TNG Consulting Inc. (https://tngconsulting.ca)
 * @author    Michael Milette
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to apply Moodle filters to custom menu.
 * @return string Empty string.
 */
function filter_filtercodes_render_navbar_output() {
    // Note: This function is only supported as of Moodle 3.2+ in themes based on Bootstrapbase and Boost.
    // If enabled in plugin settings.
    if (get_config('filter_filtercodes', 'enable_customnav')) {
        global $CFG, $PAGE;

        // Don't filter menus on Theme Settings page or it will filter the custommenuitems field in the page and loose the tags.
        if ($PAGE->pagetype != 'admin-setting-themesettings' && stripos($CFG->custommenuitems, '{') !== false) {
            // Don't apply auto-linking filters.
            $filtermanager = filter_manager::instance();
            $filteroptions = ['originalformat' => FORMAT_HTML, 'noclean' => true];
            $skipfilters = ['activitynames', 'data', 'glossary', 'sectionnames', 'bookchapters'];

            // Filter Custom Menu.
            $CFG->custommenuitems = $filtermanager->filter_text(
                $CFG->custommenuitems,
                $PAGE->context,
                $filteroptions,
                $skipfilters
            );
        }
    }
    return '';
}
