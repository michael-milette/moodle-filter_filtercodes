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
 * Unit tests for FilterCodes system information tags.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */

namespace filter_filtercodes;

/**
 * Unit tests for FilterCodes system information tags.
 *
 * Test system-related tags like {usercount}, {siteyear}, {wwwroot}, etc.
 *
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class system_info_test extends \advanced_testcase {
    /**
     * Setup the test framework
     *
     * @return void
     */
    public function setUp(): void {
        global $PAGE;
        parent::setUp();

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Enable FilterCodes filter at top level.
        filter_set_global_state('filtercodes', TEXTFILTER_ON);

        $PAGE->set_url(new \moodle_url('/'));
    }

    /**
     * Test usercount tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_usercount(): void {
        global $DB;

        // Create some test users.
        $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->create_user();

        $filtered = format_text('{usercount}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $expected = $DB->count_records('user', ['deleted' => 0]) - 2; // Minus admin and guest.

        $this->assertEquals($expected, $filtered,
            sprintf("Tag {usercount} failed\nExpected: %d\nActual: '%s'", $expected, $filtered));
    }

    /**
     * Test usersactive tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_usersactive(): void {
        global $DB;

        // Create active and suspended users.
        $this->getDataGenerator()->create_user(['suspended' => 0]);
        $this->getDataGenerator()->create_user(['suspended' => 1]);

        $filtered = format_text('{usersactive}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $expected = $DB->count_records('user', ['deleted' => 0, 'suspended' => 0, 'confirmed' => 1]) - 2;

        $this->assertEquals($expected, $filtered,
            sprintf("Tag {usersactive} failed\nExpected: %d\nActual: '%s'", $expected, $filtered));
    }

    /**
     * Test siteyear tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_siteyear(): void {
        $filtered = format_text('{siteyear}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $expected = date('Y');
        $this->assertEquals($expected, $filtered,
            sprintf("Tag {siteyear} failed\nExpected: '%s'\nActual: '%s'", $expected, $filtered));
    }

    /**
     * Test sitename tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_sitename(): void {
        global $SITE;

        $filtered = format_text('{sitename}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals($SITE->fullname, $filtered,
            sprintf("Tag {sitename} failed\nExpected: '%s'\nActual: '%s'", $SITE->fullname, $filtered));
    }

    /**
     * Test wwwroot tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_wwwroot(): void {
        global $CFG;

        $filtered = format_text('{wwwroot}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals($CFG->wwwroot, $filtered,
            sprintf("Tag {wwwroot} failed\nExpected: '%s'\nActual: '%s'", $CFG->wwwroot, $filtered));
    }

    /**
     * Test protocol tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_protocol(): void {
        $filtered = format_text('{protocol}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $expected = 'http' . (is_https() ? 's' : '');
        $this->assertEquals($expected, $filtered,
            sprintf("Tag {protocol} failed\nExpected: '%s'\nActual: '%s'", $expected, $filtered));
    }

    /**
     * Test coursecount tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursecount(): void {
        global $DB;

        // Create some test courses.
        $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_course();

        $filtered = format_text('{coursecount}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $expected = $DB->count_records('course', []) - 1; // Minus frontpage.

        $this->assertEquals($expected, $filtered,
            sprintf("Tag {coursecount} failed\nExpected: %d\nActual: '%s'", $expected, $filtered));
    }

    /**
     * Test coursesactive tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_coursesactive(): void {
        global $DB;

        // Create visible and hidden courses.
        $this->getDataGenerator()->create_course(['visible' => 1]);
        $this->getDataGenerator()->create_course(['visible' => 0]);

        $filtered = format_text('{coursesactive}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $expected = $DB->count_records('course', ['visible' => 1]) - 1; // Minus frontpage.

        $this->assertEquals($expected, $filtered,
            sprintf("Tag {coursesactive} failed\nExpected: %d\nActual: '%s'", $expected, $filtered));
    }

    /**
     * Test ipaddress tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ipaddress(): void {
        $filtered = format_text('{ipaddress}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $expected = getremoteaddr();

        $this->assertEquals($expected, $filtered,
            sprintf("Tag {ipaddress} failed\nExpected: '%s'\nActual: '%s'", $expected, $filtered));
    }

    /**
     * Test sesskey tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_sesskey(): void {
        $filtered = format_text('{sesskey}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $expected = sesskey();
        $this->assertEquals($expected, $filtered,
            sprintf("Tag {sesskey} failed\nExpected: '%s'\nActual: '%s'", $expected, $filtered));

        // Test encoded version.
        $filtered = format_text('%7Bsesskey%7D', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals($expected, $filtered,
            sprintf("Tag %%7Bsesskey%%7D (encoded) failed\nExpected: '%s'\nActual: '%s'", $expected, $filtered));
    }

    /**
     * Test pagepath tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_pagepath(): void {
        $filtered = format_text('{pagepath}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals('/?', $filtered,
            sprintf("Tag {pagepath} failed\nExpected: '/?' (or empty)\nActual: '%s'", $filtered));
    }

    /**
     * Test sitesummary tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_sitesummary(): void {
        global $SITE;

        $SITE->summary = 'A useful site summary';
        $filtered = format_text('{sitesummary}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals('A useful site summary', $filtered,
            sprintf("Tag {sitesummary} should return the site summary\nActual: '%s'", $filtered));
    }

    /**
     * Test diskfreespace tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_diskfreespace(): void {
        $filtered = format_text('{diskfreespace}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertNotEmpty($filtered,
            sprintf("Tag {diskfreespace} should not be empty\nActual: '%s'", $filtered));
        $this->assertStringNotContainsString('{diskfreespace}', $filtered);

        $filtered = format_text('{diskfreespacedata}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertNotEmpty($filtered,
            sprintf("Tag {diskfreespacedata} should not be empty\nActual: '%s'", $filtered));
        $this->assertStringNotContainsString('{diskfreespacedata}', $filtered);
    }

    /**
     * Test support tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_support_tags(): void {
        global $CFG;

        $CFG->supportname = 'Help Desk';
        $CFG->supportemail = 'helpdesk@example.com';
        $CFG->supportpage = 'https://example.com/support';

        $this->assertEquals('Help Desk', format_text('{supportname}', FORMAT_HTML, ['context' => \context_system::instance()]));
        $this->assertEquals('helpdesk@example.com', format_text('{supportemail}', FORMAT_HTML, ['context' => \context_system::instance()]));
        $this->assertEquals('https://example.com/support', format_text('{supportpage}', FORMAT_HTML, ['context' => \context_system::instance()]));
    }

    /**
     * Test filtercodes version tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_filtercodes_version(): void {
        $filtered = format_text('{filtercodes}', FORMAT_HTML, ['context' => \context_system::instance()]);
        // Should contain version information.
        $this->assertNotEmpty($filtered,
            sprintf("Tag {filtercodes} should not be empty\nActual: '%s'", $filtered));
        $this->assertMatchesRegularExpression('/^\d/', $filtered,
            sprintf("Tag {filtercodes} should start with the plugin release/version\nActual: '%s'", $filtered));
    }

    /**
     * Test readonly tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_readonly(): void {
        $filtered = format_text('{readonly}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $expected = 'readonly="readonly"';
        $this->assertEquals($expected, $filtered,
            sprintf("Tag {readonly} failed\nExpected: '%s'\nActual: '%s'", $expected, $filtered));
    }

    /**
     * Test usersonline tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_usersonline(): void {
        $filtered = format_text('{usersonline}', FORMAT_HTML, ['context' => \context_system::instance()]);
        // Should return a number (at least 1 since admin is online).
        $this->assertIsNumeric($filtered,
            sprintf("Tag {usersonline} should be numeric\nActual: '%s'", $filtered));
        $this->assertGreaterThanOrEqual(0, (int)$filtered,
            sprintf("Tag {usersonline} should be >= 0\nActual: %d", (int)$filtered));
    }

    /**
     * Test now tag with date format.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_now_tag(): void {
        global $CFG;

        // Test basic now tag.
        $filtered = format_text('{now}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertNotEmpty($filtered,
            sprintf("Tag {now} should not be empty\nActual: '%s'", $filtered));

        // Force %d to keep its leading zero so the regex below is deterministic.
        $CFG->nofixday = true;

        // Test with format.
        $filtered = format_text('{now %Y-%m-%d}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $filtered,
            sprintf("Tag {now %%Y-%%m-%%d} should match date format\nActual: '%s'", $filtered));
    }

    /**
     * Test multiple system tags in one string.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_multiple_system_tags(): void {
        global $CFG;

        $text = 'Site: {sitename} at {wwwroot} - Year: {siteyear}';
        $filtered = format_text($text, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString($CFG->wwwroot, $filtered,
            sprintf("Output should contain wwwroot '%s'\nActual: '%s'", $CFG->wwwroot, $filtered));
        $this->assertStringContainsString(date('Y'), $filtered,
            sprintf("Output should contain year '%s'\nActual: '%s'", date('Y'), $filtered));
    }
}
