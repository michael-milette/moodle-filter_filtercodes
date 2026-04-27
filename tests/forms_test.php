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
 * Unit tests for form tags.
 *
 * Tests form-related tags that generate Contact Form-compatible markup.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Test form tags.
 *
 * @copyright  2017-2025 TNG Consulting Inc.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class forms_test extends \advanced_testcase {

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
     * Check if Contact Form plugin is installed.
     *
     * @return bool True if installed, false otherwise.
     */
    private function is_contactform_installed() {
        global $CFG;
        return file_exists($CFG->dirroot . '/local/contact/version.php');
    }

    /**
     * Test formquickquestion tag.
     */
    public function test_formquickquestion() {
        $text = '{formquickquestion}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain form or form link.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('form', strtolower($result),
            sprintf("Should contain %s\nActual: '%s'", 'form', $result));
        $this->assertStringContainsString('formquickquestion', $result,
            sprintf("Should contain the form class\nActual: '%s'", $result));
    }

    /**
     * Test formcontactus tag.
     */
    public function test_formcontactus() {
        $text = '{formcontactus}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain contact form or link.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('contact', strtolower($result),
            sprintf("Should contain %s\nActual: '%s'", 'contact', $result));
        $this->assertStringContainsString('action=', $result,
            sprintf("Should contain a form action\nActual: '%s'", $result));
    }

    /**
     * Test formcourserequest tag.
     */
    public function test_formcourserequest() {
        $text = '{formcourserequest}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain course request form.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('formcourserequest', $result);
    }

    /**
     * Test formsupport tag.
     */
    public function test_formsupport() {
        $text = '{formsupport}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain support form.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('formsupport', $result);
    }

    /**
     * Test formcheckin tag.
     */
    public function test_formcheckin() {
        $text = '{formcheckin}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain check-in form.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('formcheckin', $result);
    }

    /**
     * Test form tag when Contact Form is not installed.
     */
    public function test_form_without_plugin() {
        $text = '{formcontactus}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertStringContainsString('/local/contact/index.php', $result,
            sprintf("Form action should target the Contact Form endpoint regardless of install state\nActual: '%s'", $result));
    }

    /**
     * Test multiple form tags together.
     */
    public function test_multiple_form_tags() {
        $text = '{formquickquestion} {formcontactus} {formsupport}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should process all form tags.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('class="cf formquickquestion"', $result,
            sprintf("Should render quick question form wrapper\nActual: '%s'", $result));
        $this->assertStringContainsString('class="cf formcontactus"', $result,
            sprintf("Should render contact form wrapper\nActual: '%s'", $result));
        $this->assertStringContainsString('class="cf formsupport"', $result,
            sprintf("Should render support form wrapper\nActual: '%s'", $result));
    }

    /**
     * Test form tag in course context.
     */
    public function test_form_in_course_context() {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Form Test Course']);
        $context =\context_course::instance($course->id);

        $text = '{formcontactus}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should work in course context.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('formcontactus', $result);
    }

    /**
     * Test form tag for logged out user.
     */
    public function test_form_logged_out() {
        // Log out.
        $this->setUser(null);

        $text = '{formcontactus}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertStringContainsString('formcontactus', $result,
            sprintf("Contact form should render for logged-out users\nActual: '%s'", $result));
    }

    /**
     * Test form tag for guest user.
     */
    public function test_form_guest_user() {
        // Set guest user.
        $this->setGuestUser();

        $text = '{formquickquestion}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        $this->assertEquals('', $result,
            sprintf("Quick question form should be hidden from guest users\nActual: '%s'", $result));
    }
}
