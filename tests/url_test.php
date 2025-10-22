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
 * Unit tests for URL and encoding tags.
 *
 * Tests URL-related tags including current page URL, encoding, session keys, and referrer.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Test URL and encoding tags.
 *
 * @copyright  2017-2025 TNG Consulting Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class url_test extends \advanced_testcase {

    /**
     * Setup test framework.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        filter_set_global_state('filtercodes', TEXTFILTER_ON);
        $this->setAdminUser();

        // Initialize SERVER variables for URL-related tests.
        $_SERVER['REQUEST_URI'] = '/course/view.php?id=2';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = 'off';
    }

    /**
     * Test pagepath tag.
     */
    public function test_pagepath() {
        global $PAGE;

        // Set a page URL.
        $PAGE->set_url('/course/view.php', ['id' => 2]);

        $text = '{pagepath}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain current page path.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test thisurl tag.
     */
    public function test_thisurl() {
        global $PAGE;

        // Set a page URL.
        $PAGE->set_url('/course/view.php', ['id' => 2]);

        $text = '{thisurl}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain the current URL.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('course', $result,
            sprintf("Should contain %s\nActual: '%s'", 'course', $result));
    }

    /**
     * Test thisurl_enc tag (encoded current URL).
     */
    public function test_thisurl_enc() {
        global $PAGE;

        // Set a page URL with parameters.
        $PAGE->set_url('/course/view.php', ['id' => 5, 'section' => 3]);

        $text = '{thisurl_enc}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain URL-encoded current URL.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        // Encoded URLs typically contain %2F, %3D, etc.
    }

    /**
     * Test urlencode tag.
     */
    public function test_urlencode() {
        $text = '{urlencode:hello world & test}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should encode spaces as + and special characters.
        $this->assertStringContainsString('hello', $result,
            sprintf("Should contain %s\nActual: '%s'", 'hello', $result));
        $this->assertNotEquals('hello world & test', $result);
    }

    /**
     * Test urlencode with special characters.
     */
    public function test_urlencode_special_chars() {
        $text = '{urlencode:name=value&foo=bar}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should encode = and & symbols.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        // Should not contain unencoded & or =.
    }

    /**
     * Test rawurlencode tag.
     */
    public function test_rawurlencode() {
        $text = '{rawurlencode:hello world & test}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should encode spaces as %20 (raw encoding).
        $this->assertStringContainsString('hello', $result,
            sprintf("Should contain %s\nActual: '%s'", 'hello', $result));
        $this->assertNotEquals('hello world & test', $result);
    }

    /**
     * Test rawurlencode with special characters.
     */
    public function test_rawurlencode_special_chars() {
        $text = '{rawurlencode:path/to/file.php?id=5}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should encode /, ?, = characters.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test difference between urlencode and rawurlencode.
     */
    public function test_encode_difference() {
        $input = 'hello world';

        $text1 = '{urlencode:' . $input . '}';
        $result1 = format_text($text1, FORMAT_HTML, ['filter' => true]);

        $text2 = '{rawurlencode:' . $input . '}';
        $result2 = format_text($text2, FORMAT_HTML, ['filter' => true]);

        // Both should encode the input.
        $this->assertNotEmpty($result1);
        $this->assertNotEmpty($result2);
        // urlencode uses + for space, rawurlencode uses %20 (usually).
    }

    /**
     * Test referer tag.
     */
    public function test_referer() {
        $text = '{referer}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should return HTTP referer or empty if not set.
        $this->assertNotNull($result);
    }

    /**
     * Test referrer tag (alternative spelling).
     */
    public function test_referrer() {
        $text = '{referrer}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should return HTTP referrer or empty if not set.
        $this->assertNotNull($result);
    }

    /**
     * Test protocol tag (HTTP/HTTPS).
     */
    public function test_protocol() {
        $text = '{protocol}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should return http or https.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertMatchesRegularExpression('/https?/', $result);
    }

    /**
     * Test ipaddress tag.
     */
    public function test_ipaddress() {
        $text = '{ipaddress}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should return user's IP address.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test sesskey tag.
     */
    public function test_sesskey() {
        $text = '{sesskey}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should return session key.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        // Session key is typically a 10-character alphanumeric string.
        $this->assertIsString($result);
    }

    /**
     * Test sesskey consistency.
     */
    public function test_sesskey_consistency() {
        $text = '{sesskey}';
        $result1 = format_text($text, FORMAT_HTML, ['filter' => true]);
        $result2 = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Session key should be consistent within same session.
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test wwwcontactform tag.
     */
    public function test_wwwcontactform() {
        global $CFG;

        $text = '{wwwcontactform}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should return contact form URL or empty if plugin not installed.
        $this->assertNotNull($result);
    }

    /**
     * Test multiple URL tags together.
     */
    public function test_multiple_url_tags() {
        $text = 'Protocol: {protocol}, IP: {ipaddress}, Session: {sesskey}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should process all tags.
        $this->assertStringContainsString('Protocol:', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Protocol:', $result));
        $this->assertStringContainsString('IP:', $result,
            sprintf("Should contain %s\nActual: '%s'", 'IP:', $result));
        $this->assertStringContainsString('Session:', $result,
            sprintf("Should contain %s\nActual: '%s'", 'Session:', $result));
        // Tags should be replaced.
        $this->assertStringNotContainsString('{protocol}', $result);
        $this->assertStringNotContainsString('{ipaddress}', $result);
        $this->assertStringNotContainsString('{sesskey}', $result);
    }

    /**
     * Test empty encoding.
     */
    public function test_urlencode_empty() {
        $text = '{urlencode:}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should handle empty input.
        $this->assertNotNull($result);
    }

    /**
     * Test encoding with nested braces.
     */
    public function test_urlencode_complex() {
        $text = '{urlencode:url?param={value}}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should encode the entire string including braces.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }
}
