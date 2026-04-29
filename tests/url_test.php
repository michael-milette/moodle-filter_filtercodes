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
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Test URL and encoding tags.
 *
 * @copyright  2017-2026 TNG Consulting Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
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
    public function test_pagepath(): void {
        global $PAGE;

        // Set a page URL.
        $PAGE->set_url('/course/view.php', ['id' => 2]);

        $text = '{pagepath}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            '/course/view.php?id=2',
            $result,
            sprintf("Should contain current page path\nActual: '%s'", $result)
        );
    }

    /**
     * Test thisurl tag.
     */
    public function test_thisurl(): void {
        global $PAGE;

        // Set a page URL.
        $PAGE->set_url('/course/view.php', ['id' => 2]);

        $text = '{thisurl}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain the current URL.
        $this->assertNotEmpty(
            $result,
            sprintf("Should not be empty\nActual: '%s'", $result)
        );
        $this->assertStringContainsString(
            'course',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'course', $result)
        );
    }

    /**
     * Test thisurl_enc tag (encoded current URL).
     */
    public function test_thisurl_enc(): void {
        global $PAGE;

        // Set a page URL with parameters.
        $PAGE->set_url('/course/view.php', ['id' => 5, 'section' => 3]);

        $text = '{thisurl_enc}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain URL-encoded current URL.
        $this->assertNotEmpty(
            $result,
            sprintf("Should not be empty\nActual: '%s'", $result)
        );
        $this->assertEquals(
            rawurlencode('http://localhost/course/view.php?id=2'),
            $result,
            sprintf("Should contain the encoded current URL\nActual: '%s'", $result)
        );
    }

    /**
     * Test urlencode tag.
     */
    public function test_urlencode(): void {
        $text = '{urlencode}hello world & test{/urlencode}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            urlencode('hello world &amp; test'),
            $result,
            sprintf("Should encode spaces and special characters\nActual: '%s'", $result)
        );
    }

    /**
     * Test urlencode with special characters.
     */
    public function test_urlencode_special_chars(): void {
        $text = '{urlencode}name=value&foo=bar{/urlencode}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            urlencode('name=value&amp;foo=bar'),
            $result,
            sprintf("Should encode = and & symbols\nActual: '%s'", $result)
        );
    }

    /**
     * Test rawurlencode tag.
     */
    public function test_rawurlencode(): void {
        $text = '{rawurlencode}hello world & test{/rawurlencode}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            rawurlencode('hello world &amp; test'),
            $result,
            sprintf("Should encode spaces as %%20 and encode special characters\nActual: '%s'", $result)
        );
    }

    /**
     * Test rawurlencode with special characters.
     */
    public function test_rawurlencode_special_chars(): void {
        $text = '{rawurlencode}path/to/file.php?id=5{/rawurlencode}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            rawurlencode('path/to/file.php?id=5'),
            $result,
            sprintf("Should encode /, ?, and = characters\nActual: '%s'", $result)
        );
    }

    /**
     * Test difference between urlencode and rawurlencode.
     */
    public function test_encode_difference(): void {
        $input = 'hello world';

        $text1 = '{urlencode}' . $input . '{/urlencode}';
        $result1 = format_text($text1, FORMAT_HTML, ['filter' => true]);

        $text2 = '{rawurlencode}' . $input . '{/rawurlencode}';
        $result2 = format_text($text2, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals('hello+world', $result1);
        $this->assertEquals('hello%20world', $result2);
    }

    /**
     * Test referer tag.
     */
    public function test_referer(): void {
        global $CFG;

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/from';

        $text = '{referer}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals($CFG->wwwroot . '/from', $result);
    }

    /**
     * Test referrer tag (alternative spelling).
     */
    public function test_referrer(): void {
        global $CFG;

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/referrer';

        $text = '{referrer}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals($CFG->wwwroot . '/referrer', $result);
    }

    /**
     * Test protocol tag (HTTP/HTTPS).
     */
    public function test_protocol(): void {
        $text = '{protocol}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should return http or https.
        $this->assertNotEmpty(
            $result,
            sprintf("Should not be empty\nActual: '%s'", $result)
        );
        $this->assertMatchesRegularExpression('/https?/', $result);
    }

    /**
     * Test ipaddress tag.
     */
    public function test_ipaddress(): void {
        $text = '{ipaddress}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should return user's IP address.
        $this->assertNotEmpty(
            $result,
            sprintf("Should not be empty\nActual: '%s'", $result)
        );
    }

    /**
     * Test sesskey tag.
     */
    public function test_sesskey(): void {
        $text = '{sesskey}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should return session key.
        $this->assertNotEmpty(
            $result,
            sprintf("Should not be empty\nActual: '%s'", $result)
        );
        $this->assertEquals(sesskey(), $result);
    }

    /**
     * Test sesskey consistency.
     */
    public function test_sesskey_consistency(): void {
        $text = '{sesskey}';
        $result1 = format_text($text, FORMAT_HTML, ['filter' => true]);
        $result2 = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Session key should be consistent within same session.
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test wwwcontactform tag.
     */
    public function test_wwwcontactform(): void {
        global $CFG;

        $text = '{wwwcontactform}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals($CFG->wwwroot . '/local/contact/index.php', $result);
    }

    /**
     * Test multiple URL tags together.
     */
    public function test_multiple_url_tags(): void {
        $text = 'Protocol: {protocol}, IP: {ipaddress}, Session: {sesskey}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should process all tags.
        $this->assertStringContainsString(
            'Protocol:',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Protocol:', $result)
        );
        $this->assertStringContainsString(
            'IP:',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'IP:', $result)
        );
        $this->assertStringContainsString(
            'Session:',
            $result,
            sprintf("Should contain %s\nActual: '%s'", 'Session:', $result)
        );
        // Tags should be replaced.
        $this->assertStringNotContainsString('{protocol}', $result);
        $this->assertStringNotContainsString('{ipaddress}', $result);
        $this->assertStringNotContainsString('{sesskey}', $result);
    }

    /**
     * Test empty encoding.
     */
    public function test_urlencode_empty(): void {
        $text = '{urlencode}{/urlencode}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            '',
            $result,
            sprintf("Empty urlencode content should encode to an empty string\nActual: '%s'", $result)
        );
    }

    /**
     * Test encoding with nested braces.
     */
    public function test_urlencode_complex(): void {
        $text = '{urlencode}url?param={value}{/urlencode}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals(
            urlencode('url?param={value}'),
            $result,
            sprintf("Should encode the entire string including braces\nActual: '%s'", $result)
        );
    }
}
