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
 * Settings for global custom tags for FilterCodes.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// FilterCodes plugin.
$name = 'filter_filtercodes';

// Page Table of contents.
$title = get_string('globaltagheadingtitle', $name);
$description = get_string('globaltagheadingdesc', $name);
$setting = new admin_setting_heading($name . '/globaltagheadingtitle', $title, $description);
$settings->add($setting);

// Number of tags.
$title = get_string('globaltagcount', $name);
$description = get_string('globaltagcountdesc', $name);
$default = 0;
$choices = [];
for ($i = 0; $i <= 100; $i++) {
    $choices[$i] = $i;
}
$settings->add(new admin_setting_configselect($name . '/globaltagcount', $title, $description, $default, $choices));

// This is the descriptors for each FilterCodes tag.
$tagtitle = get_string('globaltagnametitle', $name);
$tagdescription = get_string('globaltagnamedesc', $name);
$contenttitle = get_string('globaltagcontenttitle', $name);
$contentdescription = get_string('globaltagcontentdesc', $name);
$format = get_string('htmlformat');
$default = '';

$globaltagcount = get_config($name, 'globaltagcount');
for ($i = 1; $i <= $globaltagcount; $i++) {
    // Tag name.
    $setting = new admin_setting_configtext(
        $name . '/globalname' . $i,
        $tagtitle .  get_config($name, 'globalname' . $i),
        $tagdescription,
        $default,
        PARAM_ALPHANUM
    );
    $settings->add($setting);

    // Tag content editor.
    if (($editor = get_config($name, 'globaleditor' . $i)) == '') {
        // First time. Initialize to Yes.
        set_config('globaleditor' . $i, $editor = 1, $name);
    }

    if (empty($editor)) {
        // Plain text area.
        $setting = new admin_setting_configtextarea(
            $name . '/globalcontent' . $i,
            $contenttitle,
            $contentdescription,
            $default,
            PARAM_RAW
        );
    } else {
        // Rich text area.
        $setting = new admin_setting_confightmleditor(
            $name . '/globalcontent' . $i,
            $contenttitle,
            $contentdescription,
            $default,
            PARAM_RAW
        );
    }
    $settings->add($setting);

    // Content editor type.
    $setting = new admin_setting_configcheckbox($name . '/globaleditor' . $i, $format, '', 1);
    $settings->add($setting);
}
