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
 * Helper trait for FilterCodes tests to provide better error messages.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Trait to add helper methods for better test assertions.
 */
trait test_helper_trait {
    /**
     * Assert that a FilterCodes tag was replaced correctly.
     *
     * @param string $expected The expected output after tag replacement
     * @param string $tag The FilterCodes tag being tested
     * @param string $actual The actual filtered output
     * @param string $message Optional additional message
     * @return void
     */
    protected function asserttagreplaced(string $expected, string $tag, string $actual, string $message = ''): void {
        $fullmessage = sprintf(
            "FilterCodes tag replacement failed for '%s'\nExpected: '%s'\nActual: '%s'%s",
            $tag,
            $expected,
            $actual,
            $message ? "\n" . $message : ''
        );
        $this->assertEquals($expected, $actual, $fullmessage);
    }

    /**
     * Assert that a FilterCodes tag output contains expected text.
     *
     * @param string $needle The text that should be contained
     * @param string $tag The FilterCodes tag being tested
     * @param string $haystack The actual filtered output
     * @param string $message Optional additional message
     * @return void
     */
    protected function asserttagcontains(string $needle, string $tag, string $haystack, string $message = ''): void {
        $fullmessage = sprintf(
            "FilterCodes tag '%s' output should contain '%s'\nActual output: '%s'%s",
            $tag,
            $needle,
            $haystack,
            $message ? "\n" . $message : ''
        );
        $this->assertStringContainsString($needle, $haystack, $fullmessage);
    }

    /**
     * Assert that a FilterCodes tag output does not contain text.
     *
     * @param string $needle The text that should NOT be contained
     * @param string $tag The FilterCodes tag being tested
     * @param string $haystack The actual filtered output
     * @param string $message Optional additional message
     * @return void
     */
    protected function asserttagnotcontains(string $needle, string $tag, string $haystack, string $message = ''): void {
        $fullmessage = sprintf(
            "FilterCodes tag '%s' output should NOT contain '%s'\nActual output: '%s'%s",
            $tag,
            $needle,
            $haystack,
            $message ? "\n" . $message : ''
        );
        $this->assertStringNotContainsString($needle, $haystack, $fullmessage);
    }

    /**
     * Assert that FilterCodes tag output is not empty.
     *
     * @param string $tag The FilterCodes tag being tested
     * @param string $actual The actual filtered output
     * @param string $message Optional additional message
     * @return void
     */
    protected function asserttagnotempty(string $tag, string $actual, string $message = ''): void {
        $fullmessage = sprintf(
            "FilterCodes tag '%s' should not return empty\nActual output: '%s'%s",
            $tag,
            $actual,
            $message ? "\n" . $message : ''
        );
        $this->assertNotEmpty($actual, $fullmessage);
    }

    /**
     * Filter text through FilterCodes and Moodle filters.
     *
     * @param string $text The text containing FilterCodes tags
     * @return string The filtered text
     */
    protected function filtertext(string $text): string {
        return format_text($text, FORMAT_HTML, ['context' => \context_system::instance()]);
    }
}
