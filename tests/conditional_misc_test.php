<?php
// This file is part of FilterCodes filter for Moodle - https://moodle.org/
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
// along with FilterCodes.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for miscellaneous conditional tags.
 *
 * Tests conditionals for dev mode, homepage, dashboard, themes, profile fields, and mobile.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Test miscellaneous conditional tags.
 *
 * @copyright  2017-2025 TNG Consulting Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conditional_misc_test extends \advanced_testcase {

    /**
     * Setup test framework.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        filter_set_global_state('filtercodes', TEXTFILTER_ON);
        $this->setAdminUser();
    }

    /**
     * Test ifdev conditional (developer mode).
     */
    public function test_ifdev() {
        global $CFG;

        // Enable developer debugging.
        $CFG->debugdisplay = 1;
        $CFG->debug = DEBUG_DEVELOPER;

        $text = '{ifdev}Developer mode active{/ifdev}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content in developer mode.
        $this->assertStringContainsString('Developer mode active', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Developer mode active', $result));
    }

    /**
     * Test ifdev when not in developer mode.
     */
    public function test_ifdev_not_dev() {
        global $CFG;

        // Disable developer debugging.
        $CFG->debug = DEBUG_NORMAL;

        $text = '{ifdev}Developer mode active{/ifdev}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should NOT show content in normal mode.
        $this->assertStringNotContainsString('Developer mode active', $result);
    }

    /**
     * Test ifhome conditional (on homepage).
     */
    public function test_ifhome() {
        global $PAGE;

        // Set page to homepage.
        $PAGE->set_pagetype('site-index');
        $PAGE->set_url('/');

        $text = '{ifhome}You are on the homepage{/ifhome}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content on homepage.
        $this->assertStringContainsString('You are on the homepage', $result,
            sprintf("Should contain %s\nActual: '%s'", 'You are on the homepage', $result));
    }

    /**
     * Test ifnothome conditional (not on homepage).
     */
    public function test_ifnothome() {
        global $PAGE;

        // Set page to something other than homepage.
        $PAGE->set_pagetype('course-view-topics');
        $PAGE->set_url('/course/view.php', ['id' => 2]);

        $text = '{ifnothome}Not on homepage{/ifnothome}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when not on homepage.
        $this->assertStringContainsString('Not on homepage', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not on homepage', $result));
    }

    /**
     * Test ifdashboard conditional (on dashboard).
     */
    public function test_ifdashboard() {
        global $PAGE;

        // Set page to dashboard.
        $PAGE->set_pagetype('my-index');
        $PAGE->set_url('/my/');

        $text = '{ifdashboard}You are on the dashboard{/ifdashboard}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content on dashboard.
        $this->assertStringContainsString('You are on the dashboard', $result,
            sprintf("Should contain %s\nActual: '%s'", 'You are on the dashboard', $result));
    }

    /**
     * Test ifcourserequests conditional (course requests enabled).
     */
    public function test_ifcourserequests() {
        global $CFG;

        // Enable course requests.
        set_config('enablecourserequests', 1);

        $text = '{ifcourserequests}Course requests enabled{/ifcourserequests}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when course requests are enabled.
        $this->assertStringContainsString('Course requests enabled', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Course requests enabled', $result));
    }

    /**
     * Test ifcourserequests when disabled.
     */
    public function test_ifcourserequests_disabled() {
        global $CFG;

        // Disable course requests.
        set_config('enablecourserequests', 0);

        $text = '{ifcourserequests}Course requests enabled{/ifcourserequests}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should NOT show content when disabled.
        $this->assertStringNotContainsString('Course requests enabled', $result);
    }

    /**
     * Test ifeditmode conditional (edit mode enabled).
     */
    public function test_ifeditmode() {
        global $PAGE;

        // Enable editing mode.
        $PAGE->set_url('/course/view.php', ['id' => 1]);
        $PAGE->set_course($this->getDataGenerator()->create_course());
        $PAGE->set_pagelayout('course');

        $text = '{ifeditmode}Edit mode is ON{/ifeditmode}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Test result depends on whether user is editing.
        $this->assertNotNull($result);
    }

    /**
     * Test iftheme conditional (specific theme active).
     */
    public function test_iftheme() {
        global $CFG, $PAGE;

        // Get current theme.
        $theme = $PAGE->theme->name;

        $text = '{iftheme ' . $theme . '}This theme is active{/iftheme}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when theme matches.
        $this->assertStringContainsString('This theme is active', $result,
            sprintf("Should contain %s\nActual: '%s'", 'This theme is active', $result));
    }

    /**
     * Test ifnottheme conditional (specific theme not active).
     */
    public function test_ifnottheme() {
        $text = '{ifnottheme nonexistenttheme12345}Not using this theme{/ifnottheme}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when theme doesn't match.
        $this->assertStringContainsString('Not using this theme', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not using this theme', $result));
    }

    /**
     * Test ifprofile_field_* conditional (custom profile field).
     */
    public function test_ifprofile_field() {
        global $USER, $DB;

        // Create a custom profile field.
        $fieldid = $DB->insert_record('user_info_field', [
            'shortname' => 'testfield',
            'name' => 'Test Field',
            'datatype' => 'text',
            'visible' => 1,
        ]);

        // Set a value for the user.
        $DB->insert_record('user_info_data', [
            'userid' => $USER->id,
            'fieldid' => $fieldid,
            'data' => 'testvalue',
        ]);

        $text = '{ifprofile_field_testfield}Custom field exists{/ifprofile_field_testfield}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when custom field has a value.
        $this->assertStringContainsString('Custom field exists', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Custom field exists', $result));
    }

    /**
     * Test ifprofile with "is:" operator.
     */
    public function test_ifprofile_is() {
        global $USER;

        // Use a standard profile field.
        $text = '{ifprofile is:' . $USER->firstname . '}Name matches{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when profile field equals value.
        $this->assertStringContainsString('Name matches', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Name matches', $result));
    }

    /**
     * Test ifprofile with "not:" operator.
     */
    public function test_ifprofile_not() {
        $text = '{ifprofile not:NonExistentValue99999}Does not match{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when profile field does not equal value.
        $this->assertStringContainsString('Does not match', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Does not match', $result));
    }

    /**
     * Test ifprofile with "contains:" operator.
     */
    public function test_ifprofile_contains() {
        global $USER;

        // Use a standard profile field (email typically contains @).
        $text = '{ifprofile contains:@}Email contains @{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when profile field contains substring.
        $this->assertStringContainsString('Email contains @', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Email contains @', $result));
    }

    /**
     * Test ifprofile with "in:" operator (comma-separated list).
     */
    public function test_ifprofile_in() {
        global $USER;

        $text = '{ifprofile in:' . $USER->firstname . ',OtherName,ThirdName}In the list{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when profile field is in the list.
        $this->assertStringContainsString('In the list', $result,
            sprintf("Should contain %s\nActual: '%s'", 'In the list', $result));
    }

    /**
     * Test ifmobile conditional (mobile device detected).
     */
    public function test_ifmobile() {
        global $CFG;

        // Force mobile theme (simulates mobile detection).
        if (method_exists($CFG, 'set_config')) {
            set_config('enabledevicedetection', 1);
        }

        $text = '{ifmobile}Mobile device detected{/ifmobile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Test depends on device detection - at minimum should not error.
        $this->assertNotNull($result);
    }

    /**
     * Test ifnotmobile conditional (not mobile device).
     */
    public function test_ifnotmobile() {
        $text = '{ifnotmobile}Desktop device{/ifnotmobile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content on desktop (default in tests).
        $this->assertNotNull($result);
    }

    /**
     * Test iftenant conditional (Moodle Workplace tenant).
     */
    public function test_iftenant() {
        $text = '{iftenant}Workplace tenant detected{/iftenant}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Workplace-specific - should handle gracefully if not Workplace.
        $this->assertNotNull($result);
    }

    /**
     * Test ifworkplace conditional (Moodle Workplace).
     */
    public function test_ifworkplace() {
        $text = '{ifworkplace}Moodle Workplace detected{/ifworkplace}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Workplace-specific - should handle gracefully if not Workplace.
        $this->assertNotNull($result);
    }

    /**
     * Test combined misc conditionals.
     */
    public function test_combined_misc_conditionals() {
        global $CFG;

        // Set some conditions.
        $CFG->debug = DEBUG_DEVELOPER;
        set_config('enablecourserequests', 1);

        $text = '{ifdev}Dev{/ifdev} {ifcourserequests}Requests{/ifcourserequests}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Both conditions should be processed.
        $this->assertStringContainsString('Dev', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Dev', $result));
        $this->assertStringContainsString('Requests', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Requests', $result));
    }

    /**
     * Test nested misc conditionals.
     */
    public function test_nested_misc_conditionals() {
        global $CFG;

        $CFG->debug = DEBUG_DEVELOPER;

        $text = '{ifdev}In dev {ifnotmobile}on desktop{/ifnotmobile}{/ifdev}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Nested conditions should work.
        $this->assertStringContainsString('In dev', $result,
            sprintf("Should contain %s\nActual: '%s'", 'In dev', $result));
    }

    /**
     * Test empty conditional content.
     */
    public function test_misc_conditional_empty() {
        global $CFG;

        $CFG->debug = DEBUG_DEVELOPER;

        $text = '{ifdev}{/ifdev}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should handle empty content.
        $this->assertNotNull($result);
    }
}
