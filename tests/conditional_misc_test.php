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
 * @covers     \filter_filtercodes\text_filter
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
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifdev(): void {
        global $CFG;

        // Enable developer debugging.
        $CFG->debugdisplay = 1;
        $CFG->debug = DEBUG_DEVELOPER;

        $text = '{ifdev}Developer mode active{/ifdev}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content in developer mode.
        $this->assertStringContainsString(
            'Developer mode active',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Developer mode active', $result)
        );
    }

    /**
     * Test ifdev when not in developer mode.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifdev_not_dev(): void {
        global $CFG;

        // Disable developer debugging.
        $CFG->debug = DEBUG_NORMAL;
        $CFG->debugdisplay = 0;

        $text = '{ifdev}Developer mode active{/ifdev}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should NOT show content in normal mode.
        $this->assertStringNotContainsString('Developer mode active', $result);
    }

    /**
     * Test ifhome conditional (on homepage).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifhome(): void {
        global $PAGE;

        // Set page to homepage.
        $PAGE->set_pagetype('site-index');
        $PAGE->set_url('/');

        $text = '{ifhome}You are on the homepage{/ifhome}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content on homepage.
        $this->assertStringContainsString(
            'You are on the homepage',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'You are on the homepage', $result)
        );
    }

    /**
     * Test ifnothome conditional (not on homepage).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnothome(): void {
        global $PAGE;

        // Set page to something other than homepage.
        $PAGE->set_pagetype('course-view-topics');
        $PAGE->set_url('/course/view.php', ['id' => 2]);

        $text = '{ifnothome}Not on homepage{/ifnothome}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when not on homepage.
        $this->assertStringContainsString(
            'Not on homepage',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not on homepage', $result)
        );
    }

    /**
     * Test ifdashboard conditional (on dashboard).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifdashboard(): void {
        global $PAGE;

        // Set page to dashboard.
        $PAGE->set_pagetype('my-index');
        $PAGE->set_url('/my/');

        $text = '{ifdashboard}You are on the dashboard{/ifdashboard}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content on dashboard.
        $this->assertStringContainsString(
            'You are on the dashboard',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'You are on the dashboard', $result)
        );
    }

    /**
     * Test ifcourserequests conditional (course requests enabled).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifcourserequests(): void {
        global $CFG;

        // Enable course requests.
        $CFG->enablecourserequests = 1;

        $text = '{ifcourserequests}Course requests enabled{/ifcourserequests}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when course requests are enabled.
        $this->assertStringContainsString(
            'Course requests enabled',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Course requests enabled', $result)
        );
    }

    /**
     * Test ifcourserequests when disabled.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifcourserequests_disabled(): void {
        global $CFG;

        // Disable course requests.
        $CFG->enablecourserequests = 0;

        $text = '{ifcourserequests}Course requests enabled{/ifcourserequests}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should NOT show content when disabled.
        $this->assertStringNotContainsString('Course requests enabled', $result);
    }

    /**
     * Test ifeditmode conditional (edit mode enabled).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifeditmode(): void {
        global $PAGE;

        // Enable editing mode.
        $PAGE->set_url('/course/view.php', ['id' => 1]);
        $PAGE->set_course($this->getDataGenerator()->create_course());
        $PAGE->set_pagelayout('course');

        $text = '{ifeditmode}Edit mode is ON{/ifeditmode}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            '',
            $result,
            sprintf("Edit mode content should be hidden when editing is off\nActual: '%s'", $result)
        );
    }

    /**
     * Test iftheme conditional (specific theme active).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_iftheme(): void {
        global $CFG, $PAGE;

        // Get current theme.
        $theme = $PAGE->theme->name;

        $text = '{iftheme ' . $theme . '}This theme is active{/iftheme}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when theme matches.
        $this->assertStringContainsString(
            'This theme is active',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'This theme is active', $result)
        );
    }

    /**
     * Test ifnottheme conditional (specific theme not active).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnottheme(): void {
        $text = '{ifnottheme nonexistenttheme12345}Not using this theme{/ifnottheme}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should show content when theme doesn't match.
        $this->assertStringContainsString(
            'Not using this theme',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Not using this theme', $result)
        );
    }

    /**
     * Test ifprofile_field_* conditional (custom profile field).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifprofile_field(): void {
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
        $this->assertStringContainsString(
            'Custom field exists',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Custom field exists', $result)
        );
    }

    /**
     * Test ifprofile with "is" operator.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifprofile_is(): void {
        global $USER;

        $USER->city = 'Toronto';

        // Field equals value: content shown.
        $text = '{ifprofile city is "Toronto"}City match{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringContainsString('City match', $result);

        // Field does not equal value: content stripped.
        $text = '{ifprofile city is "Paris"}City match{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringNotContainsString('City match', $result);
    }

    /**
     * Test ifprofile with "not" operator.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifprofile_not(): void {
        global $USER;

        $USER->city = 'Toronto';

        // Field does not equal value: content shown.
        $text = '{ifprofile city not "Paris"}Not Paris{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringContainsString('Not Paris', $result);

        // Field equals value: content stripped.
        $text = '{ifprofile city not "Toronto"}Not Toronto{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringNotContainsString('Not Toronto', $result);
    }

    /**
     * Test ifprofile with "contains" operator.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifprofile_contains(): void {
        global $USER;

        $USER->email = 'user@example.com';

        // Field contains value: content shown.
        $text = '{ifprofile email contains "@example.com"}Has example domain{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringContainsString('Has example domain', $result);

        // Field does not contain value: content stripped.
        $text = '{ifprofile email contains "@other.com"}Has other domain{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringNotContainsString('Has other domain', $result);
    }

    /**
     * Test ifprofile with "in" operator (value contains field).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifprofile_in(): void {
        global $USER;

        $USER->country = 'CA';

        // Field is in the value list: content shown.
        $text = '{ifprofile country in "CA,US,UK"}North America or UK{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringContainsString('North America or UK', $result);

        // Field is not in the value list: content stripped.
        $text = '{ifprofile country in "FR,DE,IT"}Europe{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringNotContainsString('Europe', $result);
    }

    /**
     * Test nested ifprofile tags.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifprofile_nested(): void {
        global $USER;

        // Set up user with specific profile fields for testing.
        $USER->city = 'New York';
        $USER->country = 'US';
        $USER->email = 'testuser@example.com';

        // Test nested conditions - both true.
        $text = '{ifprofile city is "New York"}{ifprofile country is "US"}Welcome to NYC, USA{/ifprofile}{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringContainsString('Welcome to NYC, USA', $result);

        // Test nested conditions - outer true, inner false.
        $text = '{ifprofile city is "New York"}{ifprofile country is "CA"}Welcome to NYC, Canada{/ifprofile}{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringNotContainsString('Welcome to NYC, Canada', $result);

        // Test nested conditions - outer false, inner true (inner should not be evaluated).
        $text = '{ifprofile city is "Los Angeles"}{ifprofile country is "US"}Welcome to LA, USA{/ifprofile}{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringNotContainsString('Welcome to LA, USA', $result);

        // Test nested conditions - both false.
        $text = '{ifprofile city is "Paris"}{ifprofile country is "FR"}Bonjour Paris{/ifprofile}{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringNotContainsString('Bonjour Paris', $result);

        // Side-by-side (not nested): one true, one false.
        $text = '{ifprofile city is "New York"}Hello{/ifprofile} {ifprofile city is "Paris"}World{/ifprofile}.';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringNotContainsString('World', $result);
    }

    /**
     * Test partial/unbalanced ifprofile tags.
     *
     * Migration to the if_tag() helper unlocks graceful handling of incomplete
     * tag pairs that the previous innermost-first regex approach did not handle.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifprofile_partial_tags(): void {
        global $USER;

        $USER->city = 'New York';

        // Scenario 1: Only opening tag, no closing. Should not strip surrounding content.
        $text = '{ifprofile city is "New York"}Hello World';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringContainsString('Hello World', $result);

        // Scenario 2: Only closing tag, no opening. Helper should leave text alone.
        $text = 'Hello World{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringContainsString('Hello World', $result);

        // Scenario 3: Extra closing tag (one open, two close). Excess closer remains.
        $text = '{ifprofile city is "Paris"}Hello{/ifprofile}{/ifprofile}.';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringNotContainsString('Hello{/ifprofile}', $result);

        // Scenario 4: Three levels deep, balanced — confirms helper handles arbitrary depth
        // (the previous implementation capped iterations at 10).
        $text = '{ifprofile city is "New York"}A{ifprofile city is "New York"}B'
            . '{ifprofile city is "New York"}C{/ifprofile}{/ifprofile}{/ifprofile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);
        $this->assertStringContainsString('ABC', $result);
    }

    /**
     * Test ifmobile conditional (mobile device detected).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifmobile(): void {
        global $CFG;

        // Force mobile theme (simulates mobile detection).
        $CFG->enabledevicedetection = 1;

        $text = '{ifmobile}Mobile device detected{/ifmobile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            '',
            $result,
            sprintf("Default PHPUnit requests should not be detected as mobile\nActual: '%s'", $result)
        );
    }

    /**
     * Test ifnotmobile conditional (not mobile device).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifnotmobile(): void {
        $text = '{ifnotmobile}Desktop device{/ifnotmobile}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            'Desktop device',
            $result,
            sprintf("Default PHPUnit requests should be treated as non-mobile\nActual: '%s'", $result)
        );
    }

    /**
     * Test iftenant conditional (Moodle Workplace tenant).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_iftenant(): void {
        $text = '{iftenant 1}Classic tenant fallback detected{/iftenant}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            'Classic tenant fallback detected',
            $result,
            sprintf("Classic Moodle should simulate tenant 1 for compatible content\nActual: '%s'", $result)
        );
    }

    /**
     * Test ifworkplace conditional (Moodle Workplace).
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_ifworkplace(): void {
        $text = '{ifworkplace}Moodle Workplace detected{/ifworkplace}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            '',
            $result,
            sprintf("Classic Moodle should hide Workplace-only content\nActual: '%s'", $result)
        );
    }

    /**
     * Test combined misc conditionals.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_combined_misc_conditionals(): void {
        global $CFG;

        // Set some conditions.
        $CFG->debug = DEBUG_DEVELOPER;
        $CFG->debugdisplay = 1;
        $CFG->enablecourserequests = 1;

        $text = '{ifdev}Dev{/ifdev} {ifcourserequests}Requests{/ifcourserequests}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Both conditions should be processed.
        $this->assertStringContainsString(
            'Dev',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Dev', $result)
        );
        $this->assertStringContainsString(
            'Requests',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Requests', $result)
        );
    }

    /**
     * Test nested misc conditionals.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_nested_misc_conditionals(): void {
        global $CFG;

        $CFG->debug = DEBUG_DEVELOPER;

        $text = '{ifdev}In dev {ifnotmobile}on desktop{/ifnotmobile}{/ifdev}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Nested conditions should work.
        $this->assertStringContainsString(
            'In dev',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'In dev', $result)
        );
    }

    /**
     * Test empty conditional content.
     * @covers \filter_filtercodes\text_filter::filter
     */
    public function test_misc_conditional_empty(): void {
        global $CFG;

        $CFG->debug = DEBUG_DEVELOPER;

        $text = '{ifdev}{/ifdev}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            '',
            $result,
            sprintf("Empty conditional content should remain empty after tags are removed\nActual: '%s'", $result)
        );
    }
}
