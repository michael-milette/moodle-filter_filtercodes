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
 * Settings page for FilterCodes.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2021 TNG Consulting Inc. - www.tngcosulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Option to enable experimental support for filtercodes in custom navigation menu.
if ($CFG->branch >= 32 && $CFG->branch <= 34) { // Only supported in Moodle 3.2 to 3.4.
    // See https://github.com/michael-milette/moodle-filter_filtercodes/issues/67 for details.
    $default = 0;
    $name = 'filter_filtercodes/enable_customnav';
    $title = get_string('enable_customnav', 'filter_filtercodes');
    $description = get_string('enable_customnav_description', 'filter_filtercodes');
    $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
} else { // Disable for all other versions of Moodle.
    set_config('disabled_customnav', 0, 'filter_filtercodes');
    $name = 'filter_filtercodes/disabled_customnav';
    $title = '';
    $description = get_string('disabled_customnav_description', 'filter_filtercodes');
    $setting = new admin_setting_heading($name, $title, $description);
}
$settings->add($setting);

// Option to use alternative braces to escape tags.
$default = '1';
$name = 'filter_filtercodes/escapebraces';
$title = get_string('escapebraces', 'filter_filtercodes');
$description = get_string('escapebraces_desc', 'filter_filtercodes');
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$settings->add($setting);

// Hide completed courses in {mycoursesmenu} tags.
$default = '0';
$name = 'filter_filtercodes/hidecompletedcourses';
$title = get_string('hidecompletedcourses', 'filter_filtercodes');
$description = get_string('hidecompletedcourses_desc', 'filter_filtercodes');
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$settings->add($setting);

// Restrict {ifprofilefied} tag to only access to visible fields.
$default = '0';
$name = 'filter_filtercodes/ifprofilefiedonlyvisible';
$title = get_string('ifprofilefiedonlyvisible', 'filter_filtercodes');
$description = get_string('ifprofilefiedonlyvisible_desc', 'filter_filtercodes');
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$settings->add($setting);

// Option to enable scrape tag.
$default = 0; // Default is disabled.
$name = 'filter_filtercodes/enable_scrape';
$title = get_string('enable_scrape', 'filter_filtercodes');
$description = get_string('enable_scrape_description', 'filter_filtercodes');
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$settings->add($setting);

// Option to show teachers profile picture.
$default = 0; // Default is to not show profile picture.
$name = 'filter_filtercodes/courseteachershowpic';
$title = get_string('courseteachershowpic', 'filter_filtercodes');
$description = get_string('courseteachershowpic_desc', 'filter_filtercodes');
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$settings->add($setting);

// Option to select link type for {teacher} tag.
$default = ''; // Default is to not link the teachers name.
$name = 'filter_filtercodes/courseteacherlinktype';
$title = get_string('courseteacherlinktype', 'filter_filtercodes');
$description = get_string('courseteacherlinktype_desc', 'filter_filtercodes');
$choices = ['' => get_string('none'),
        'email' => get_string('issueremail', 'badges'),
        'message' => get_string('message', 'message'),
        'profile' => get_string('profile')];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$settings->add($setting);
