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
 * Processes additions and removals from Wishlist for FilterCodes.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Ensure the user is logged in.
require_login();

// Get the action to be taken.
$action = required_param('action', PARAM_TEXT);
$url = $CFG->wwwroot;

if (($courseid = optional_param('courseid', -1, PARAM_INT)) != -1) {
    //
    // This is a wishlist item.
    //

    // Get the user's current wishlist from the user_preferences table.
    $wishlistrecord = $DB->get_record('user_preferences', ['userid' => $USER->id, 'name' => 'filter_filtercodes_wishlist']);
    $wishlistcourses = $wishlistrecord ? explode(',', $wishlistrecord->value) : [];

    $updatedb = false;
    $key = false;

    if ($action == 'add') {
        // Add the new course ID to the wishlist if it's not already present.
        if (!in_array($courseid, $wishlistcourses)) {
            $wishlistcourses[] = $courseid;
            $newwishlistvalue = implode(',', $wishlistcourses);
            $updatedb = true;
        }
    } else if ($action == 'remove') {
        // Remove the course ID from the wishlist if it's present.
        if (($key = array_search($courseid, $wishlistcourses)) !== false) {
            unset($wishlistcourses[$key]);
            $newwishlistvalue = implode(',', $wishlistcourses);
            $updatedb = true;
        }
    }

    if ($updatedb) {
        // Update the user_preferences table with the new wishlist.
        if ($wishlistrecord) {
            $wishlistrecord->value = $newwishlistvalue;
            $DB->update_record('user_preferences', $wishlistrecord);
        } else {
            $record = new stdClass();
            $record->userid = $USER->id;
            $record->name = 'filter_filtercodes_wishlist';
            $record->value = $newwishlistvalue;
            $DB->insert_record('user_preferences', $record);
        }
    }

    // Redirect the user back to the course page.
    $url = new moodle_url('/course/view.php', ['id' => $courseid]);
}

redirect($url);
