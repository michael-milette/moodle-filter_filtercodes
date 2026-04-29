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
 * Unit tests for login conditional tags.
 *
 * Tests conditionals based on user login status (logged in, logged out, guest).
 *
 * @package    filter_filtercodes
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Unit tests for FilterCodes conditional logged in/out tags.
 *
 * Test conditional tags like {ifloggedin}, {ifloggedout}, {ifguest}, etc.
 *
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class conditional_loggedin_test extends \advanced_testcase {
    /**
     * Setup the test framework
     *
     * @return void
     */
    public function setUp(): void {
        global $PAGE;
        parent::setUp();

        $this->resetAfterTest(true);

        // Enable FilterCodes filter at top level.
        filter_set_global_state('filtercodes', TEXTFILTER_ON);

        $PAGE->set_url(new \moodle_url('/'));
    }

    /**
     * Test ifloggedin tag when logged in.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifloggedin_when_logged_in(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifloggedin}You are logged in{/ifloggedin}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            'You are logged in',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are logged in', $filtered)
        );
    }

    /**
     * Test ifloggedin tag when logged out.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifloggedin_when_logged_out(): void {
        $this->setUser(null);

        $before = '{ifloggedin}You are logged in{/ifloggedin}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            '',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered)
        );
    }

    /**
     * Test ifloggedin tag when logged in as guest.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifloggedin_as_guest(): void {
        $this->setGuestUser();

        $before = '{ifloggedin}You are logged in{/ifloggedin}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Guest should NOT see ifloggedin content.
        $this->assertEquals(
            '',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered)
        );
    }

    /**
     * Test ifloggedout tag when logged out.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifloggedout_when_logged_out(): void {
        $this->setUser(null);

        $before = '{ifloggedout}You are logged out{/ifloggedout}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            'You are logged out',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are logged out', $filtered)
        );
    }

    /**
     * Test ifloggedout tag when logged in.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifloggedout_when_logged_in(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifloggedout}You are logged out{/ifloggedout}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            '',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered)
        );
    }

    /**
     * Test ifloggedout tag when logged in as guest.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifloggedout_as_guest(): void {
        $this->setGuestUser();

        $before = '{ifloggedout}You are logged out{/ifloggedout}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Guest should see ifloggedout content.
        $this->assertEquals(
            'You are logged out',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are logged out', $filtered)
        );
    }

    /**
     * Test ifguest tag when logged in as guest.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifguest_as_guest(): void {
        $this->setGuestUser();

        $before = '{ifguest}You are a guest{/ifguest}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            'You are a guest',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are a guest', $filtered)
        );
    }

    /**
     * Test ifguest tag when logged in as regular user.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifguest_as_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifguest}You are a guest{/ifguest}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            '',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered)
        );
    }

    /**
     * Test ifguest tag when logged out.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifguest_when_logged_out(): void {
        $this->setUser(null);

        $before = '{ifguest}You are a guest{/ifguest}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            '',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered)
        );
    }

    /**
     * Test combined ifloggedin and ifloggedout tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_combined_loggedin_loggedout(): void {
        // Test when logged in.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifloggedin}Welcome back!{/ifloggedin}{ifloggedout}Please log in{/ifloggedout}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals(
            'Welcome back!',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Welcome back!', $filtered)
        );

        // Test when logged out.
        $this->setUser(null);
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals(
            'Please log in',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Please log in', $filtered)
        );
    }

    /**
     * Test ifloggedinas tag (requires login as functionality).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifloggedinas(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifloggedinas}You are logged in as someone else{/ifloggedinas}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // When not logged in as, should be empty.
        $this->assertEquals(
            '',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered)
        );
    }

    /**
     * Test ifnotloggedinas tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_ifnotloggedinas(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifnotloggedinas}You are yourself{/ifnotloggedinas}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // When not logged in as, should show content.
        $this->assertEquals(
            'You are yourself',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'You are yourself', $filtered)
        );
    }

    /**
     * Test nested conditional tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_nested_conditionals(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifloggedin}Outer{ifnotloggedinas}Inner{/ifnotloggedinas}{/ifloggedin}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'Outer',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Outer', $filtered)
        );
        $this->assertStringContainsString(
            'Inner',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Inner', $filtered)
        );
    }

    /**
     * Test conditional with other content.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_conditional_with_other_content(): void {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'John']);
        $this->setUser($user);

        $before = 'Hello {ifloggedin}{firstname}{/ifloggedin}{ifloggedout}Guest{/ifloggedout}!';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            'Hello John!',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Hello John!', $filtered)
        );
    }

    /**
     * Test multiple conditional tags in same text.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_multiple_conditionals(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifloggedin}A{/ifloggedin} and {ifloggedin}B{/ifloggedin} but {ifloggedout}C{/ifloggedout}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            'A and B but ',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'A and B but ', $filtered)
        );
    }

    /**
     * Test empty conditional tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_empty_conditional_tags(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $before = '{ifloggedin}{/ifloggedin}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            '',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", '', $filtered)
        );
    }
}
