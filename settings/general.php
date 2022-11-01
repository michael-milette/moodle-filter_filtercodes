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
 * @copyright  2017-2022 TNG Consulting Inc. - www.tngcosulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Option to enable experimental support for filtercodes in custom navigation menu.
if (isset($CFG->branch) && $CFG->branch >= 32 && $CFG->branch <= 34) { // Only supported in Moodle 3.2 to 3.4.
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

// Option to optimize display if your theme uses narrow page width (e.g., Moodle 4.0 Boost).
$default = 0; // Default is to not show colour/pattern.
$name = 'filter_filtercodes/narrowpage';
$title = get_string('narrowpage', 'filter_filtercodes');
$description = get_string('narrowpage_desc', 'filter_filtercodes');
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
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

// Show hidden custom profile fields.
$default = '0';
$name = 'filter_filtercodes/showhiddenprofilefields';
$title = get_string('showhiddenprofilefields', 'filter_filtercodes');
$description = get_string('showhiddenprofilefields_desc', 'filter_filtercodes');
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

// Option to show contact's profile picture.
$default = 0; // Default is to not show profile picture.
$name = 'filter_filtercodes/coursecontactshowpic';
$title = get_string('coursecontactshowpic', 'filter_filtercodes');
$description = get_string('coursecontactshowpic_desc', 'filter_filtercodes');
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$settings->add($setting);

// Option to show contact's profile description.
$default = 0; // Default is to not show profile description.
$name = 'filter_filtercodes/coursecontactshowdesc';
$title = get_string('coursecontactshowdesc', 'filter_filtercodes');
$description = get_string('coursecontactshowdesc_desc', 'filter_filtercodes');
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$settings->add($setting);

// Option to select link type for {coursecontacts} tag.
$default = ''; // Default is to not link the teachers name.
$name = 'filter_filtercodes/coursecontactlinktype';
$title = get_string('coursecontactlinktype', 'filter_filtercodes');
$description = get_string('coursecontactlinktype_desc', 'filter_filtercodes');
$choices = ['' => get_string('none'),
        'email' => get_string('issueremail', 'badges'),
        'message' => get_string('message', 'message'),
        'profile' => get_string('profile'),
        'phone' => get_string('phone')];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$settings->add($setting);

// Option to show or hide background colour/pattern for {categorycards} tag.
$default = 0; // Default is to not show colour/pattern.
$name = 'filter_filtercodes/categorycardshowpic';
$title = get_string('categorycardshowpic', 'filter_filtercodes');
$description = get_string('categorycardshowpic_desc', 'filter_filtercodes');
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$settings->add($setting);

// Option to select link type for {teamcards} tag.
$default = ''; // Default is to not link the teachers name.
$name = 'filter_filtercodes/teamcardslinktype';
$title = get_string('teamcardslinktype', 'filter_filtercodes');
$description = get_string('teamcardslinktype_desc', 'filter_filtercodes');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$settings->add($setting);

// Option to select how to display user description for {teamcards} tag.
$default = ''; // Default is to not display the description field.
$name = 'filter_filtercodes/teamcardsformat';
$title = get_string('teamcardsformat', 'filter_filtercodes');
$description = get_string('teamcardsformat_desc', 'filter_filtercodes');
$choices = ['' => get_string('none'),
        'infoicon' => get_string('icon'),
        'brief' => get_string('brief', 'filter_filtercodes'),
        'verbose' => get_string('verbose', 'filter_filtercodes')];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$settings->add($setting);

// Number of cards to show for {coursecardsbyenrol} tag.
$default = 8; // Default is to not show colour/pattern.
$name = 'filter_filtercodes/coursecardsbyenrol';
$title = get_string('coursecardsbyenrol', 'filter_filtercodes');
$choices = range(0, 20);
$description = get_string('coursecardsbyenrol_desc', 'filter_filtercodes');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$settings->add($setting);
