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
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Ensure the user is logged in and the request carries a valid session key (CSRF defense).
require_login();
require_sesskey();

// Get the action to be taken.
$action = required_param('action', PARAM_TEXT);
$url = $CFG->wwwroot;

// Get the course ID from the URL parameters.
$courseid = optional_param('courseid', -1, PARAM_INT);

// This is a wishlist item.
$wishlistrecord = $DB->get_record('user_preferences', ['userid' => $USER->id, 'name' => 'filter_filtercodes_wishlist']);
$wishlistcourses = $wishlistrecord ? explode(',', $wishlistrecord->value) : [];

if ($action == 'add') {
    if (!in_array($courseid, $wishlistcourses)) {
        $wishlistcourses[] = $courseid;
        $newwishlistvalue = implode(',', $wishlistcourses);
        set_user_preference('filter_filtercodes_wishlist', $newwishlistvalue, $USER->id);
    }
} else if ($action == 'remove') {
    if (($key = array_search($courseid, $wishlistcourses)) !== false) {
        unset($wishlistcourses[$key]);
        $newwishlistvalue = implode(',', $wishlistcourses);
        set_user_preference('filter_filtercodes_wishlist', $newwishlistvalue, $USER->id);
    }
}
$url = new moodle_url('/course/view.php', ['id' => $courseid]);

redirect($url);
