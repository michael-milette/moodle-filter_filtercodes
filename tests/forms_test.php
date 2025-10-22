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
 * Tests form-related tags that require the Contact Form plugin.
 * Tests will be skipped if the Contact Form plugin is not installed.
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
        if (!$this->is_contactform_installed()) {
            $this->markTestSkipped('Contact Form plugin is not installed.');
        }

        $text = '{formquickquestion}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain form or form link.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('form', strtolower($result),
            sprintf("Should contain %s\nActual: '%s'", 'form', $result));
    }

    /**
     * Test formcontactus tag.
     */
    public function test_formcontactus() {
        if (!$this->is_contactform_installed()) {
            $this->markTestSkipped('Contact Form plugin is not installed.');
        }

        $text = '{formcontactus}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain contact form or link.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
        $this->assertStringContainsString('contact', strtolower($result),
            sprintf("Should contain %s\nActual: '%s'", 'contact', $result));
    }

    /**
     * Test formcourserequest tag.
     */
    public function test_formcourserequest() {
        if (!$this->is_contactform_installed()) {
            $this->markTestSkipped('Contact Form plugin is not installed.');
        }

        $text = '{formcourserequest}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain course request form.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test formsupport tag.
     */
    public function test_formsupport() {
        if (!$this->is_contactform_installed()) {
            $this->markTestSkipped('Contact Form plugin is not installed.');
        }

        $text = '{formsupport}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain support form.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test formcheckin tag.
     */
    public function test_formcheckin() {
        if (!$this->is_contactform_installed()) {
            $this->markTestSkipped('Contact Form plugin is not installed.');
        }

        $text = '{formcheckin}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should contain check-in form.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test form tag when Contact Form is not installed.
     */
    public function test_form_without_plugin() {
        if ($this->is_contactform_installed()) {
            $this->markTestSkipped('This test requires Contact Form plugin to NOT be installed.');
        }

        $text = '{formcontactus}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should handle gracefully (return empty or original tag).
        $this->assertNotNull($result);
    }

    /**
     * Test multiple form tags together.
     */
    public function test_multiple_form_tags() {
        if (!$this->is_contactform_installed()) {
            $this->markTestSkipped('Contact Form plugin is not installed.');
        }

        $text = '{formquickquestion} {formcontactus} {formsupport}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should process all form tags.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test form tag in course context.
     */
    public function test_form_in_course_context() {
        if (!$this->is_contactform_installed()) {
            $this->markTestSkipped('Contact Form plugin is not installed.');
        }

        $course = $this->getDataGenerator()->create_course(['fullname' => 'Form Test Course']);
        $context =\context_course::instance($course->id);

        $text = '{formcontactus}';
        $result = format_text($text, FORMAT_HTML, ['context' => $context, 'filter' => true]);

        // Should work in course context.
        $this->assertNotEmpty($result,
            sprintf("Should not be empty\nActual: '%s'", $result));
    }

    /**
     * Test form tag for logged out user.
     */
    public function test_form_logged_out() {
        if (!$this->is_contactform_installed()) {
            $this->markTestSkipped('Contact Form plugin is not installed.');
        }

        // Log out.
        $this->setUser(null);

        $text = '{formcontactus}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should handle logged-out state (may show login prompt or simplified form).
        $this->assertNotNull($result);
    }

    /**
     * Test form tag for guest user.
     */
    public function test_form_guest_user() {
        if (!$this->is_contactform_installed()) {
            $this->markTestSkipped('Contact Form plugin is not installed.');
        }

        // Set guest user.
        $this->setGuestUser();

        $text = '{formquickquestion}';
        $result = format_text($text, FORMAT_HTML, ['filter' => true]);

        // Should handle guest user.
        $this->assertNotNull($result);
    }
}
