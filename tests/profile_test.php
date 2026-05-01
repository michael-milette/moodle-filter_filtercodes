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
 * Unit tests for FilterCodes profile tags.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */

namespace filter_filtercodes;
use context_system;

/**
 * Unit tests for FilterCodes profile tags.
 *
 * Test profile-related tags like {firstname}, {lastname}, {email}, etc.
 *
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profile_test extends \advanced_testcase {
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
     * Test basic profile tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_basic_profile_tags(): void {
        global $USER;

        // Create a test user with specific data.
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'username' => 'johndoe',
            'idnumber' => 'ID12345',
            'city' => 'Toronto',
            'country' => 'CA',
            'institution' => 'Test University',
            'department' => 'Computer Science',
        ]);

        $this->setUser($user);

        $tests = [
            [
                'before' => '{firstname}',
                'after'  => $USER->firstname,
            ],
            [
                'before' => '{lastname}',
                'after'  => $USER->lastname,
            ],
            [
                'before' => '{surname}',
                'after'  => $USER->lastname,
            ],
            [
                'before' => '{fullname}',
                'after'  => $USER->firstname . ' ' . $USER->lastname,
            ],
            [
                'before' => '{email}',
                'after'  => $USER->email,
            ],
            [
                'before' => '{username}',
                'after'  => $USER->username,
            ],
            [
                'before' => '{userid}',
                'after'  => $USER->id,
            ],
            [
                'before' => '%7Buserid%7D',
                'after'  => $USER->id,
            ],
            [
                'before' => '{idnumber}',
                'after'  => $USER->idnumber,
            ],
            [
                'before' => '{city}',
                'after'  => $USER->city,
            ],
            [
                'before' => '{country}',
                'after'  => !empty($USER->country) ? get_string($USER->country, 'countries') : '',
            ],
            [
                'before' => '{institution}',
                'after'  => $USER->institution,
            ],
            [
                'before' => '{department}',
                'after'  => $USER->department,
            ],
        ];

        foreach ($tests as $test) {
            $filtered = format_text($test['before'], FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertEquals(
                $test['after'],
                $filtered,
                sprintf(
                    "Tag replacement failed for '%s'\nExpected: '%s'\nActual: '%s'",
                    $test['before'],
                    $test['after'],
                    $filtered
                )
            );
        }
    }

    /**
     * Test alternatename tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_alternatename(): void {
        global $USER;

        // Test with alternatename set.
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'John',
            'alternatename' => 'Johnny',
        ]);
        $this->setUser($user);

        $filtered = format_text('{alternatename}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals(
            'Johnny',
            $filtered,
            sprintf("Tag {alternatename} failed\nExpected: 'Johnny'\nActual: '%s'", $filtered)
        );

        // Test with empty alternatename (should fall back to firstname).
        $user2 = $this->getDataGenerator()->create_user([
            'firstname' => 'Jane',
            'alternatename' => '',
        ]);
        $this->setUser($user2);

        $filtered = format_text('{alternatename}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals(
            'Jane',
            $filtered,
            sprintf("Tag {alternatename} (fallback) failed\nExpected: 'Jane'\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test middlename tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_middlename(): void {
        global $USER;

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'John',
            'middlename' => 'Andrew',
            'lastname' => 'Doe',
        ]);
        $this->setUser($user);

        $filtered = format_text('{middlename}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals(
            $USER->middlename,
            $filtered,
            sprintf("Tag {middlename} failed\nExpected: '%s'\nActual: '%s'", $USER->middlename, $filtered)
        );
    }

    /**
     * Test phonetic name tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_phonetic_names(): void {
        global $USER;

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'firstnamephonetic' => 'ジョン',
            'lastnamephonetic' => 'ドウ',
        ]);
        $this->setUser($user);

        $filtered = format_text('{firstnamephonetic}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals(
            $USER->firstnamephonetic,
            $filtered,
            sprintf("Tag {firstnamephonetic} failed\nExpected: '%s'\nActual: '%s'", $USER->firstnamephonetic, $filtered)
        );

        $filtered = format_text('{lastnamephonetic}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals(
            $USER->lastnamephonetic,
            $filtered,
            sprintf("Tag {lastnamephonetic} failed\nExpected: '%s'\nActual: '%s'", $USER->lastnamephonetic, $filtered)
        );
    }

    /**
     * Test timezone tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_timezone(): void {
        global $USER;

        $user = $this->getDataGenerator()->create_user([
            'timezone' => 'America/Toronto',
        ]);
        $this->setUser($user);

        $filtered = format_text('{timezone}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertEquals(
            $USER->timezone,
            $filtered,
            sprintf("Tag {timezone} failed\nExpected: '%s'\nActual: '%s'", $USER->timezone, $filtered)
        );
    }

    /**
     * Test preferredlanguage tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_preferredlanguage(): void {
        global $USER;

        $user = $this->getDataGenerator()->create_user([
            'lang' => 'en',
        ]);
        $this->setUser($user);

        $filtered = format_text('{preferredlanguage}', FORMAT_HTML, ['context' => \context_system::instance()]);
        // Should contain the language string wrapped in a span with lang attribute.
        $this->assertStringContainsString(
            'English',
            $filtered,
            sprintf("Tag {preferredlanguage} should contain 'English'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'lang="en"',
            $filtered,
            sprintf("Tag {preferredlanguage} should contain lang attribute\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test userdescription tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_userdescription(): void {
        global $USER;

        $description = 'This is my test description.';
        $user = $this->getDataGenerator()->create_user([
            'description' => $description,
        ]);
        $this->setUser($user);

        $filtered = format_text('{userdescription}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertStringContainsString(
            $description,
            $filtered,
            sprintf("Tag {userdescription} should contain '%s'\nActual: '%s'", $description, $filtered)
        );
    }

    /**
     * Test webpage tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_webpage(): void {
        global $USER, $DB;

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);
        $this->setUser($user);

        $fieldid = $DB->insert_record('user_info_field', [
            'shortname' => 'webpage',
            'name' => 'Web page',
            'datatype' => 'text',
            'visible' => 1,
        ]);
        $DB->insert_record('user_info_data', [
            'userid' => $USER->id,
            'fieldid' => $fieldid,
            'data' => 'https://example.com',
        ]);

        $filtered = format_text('{webpage}', FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            'https://example.com',
            trim($filtered),
            sprintf("Tag {webpage} should resolve the custom profile field\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test user picture tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_userpicture_tags(): void {
        global $USER;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Test userpictureurl with different sizes.
        $sizes = ['sm', 'md', 'lg'];
        foreach ($sizes as $size) {
            $filtered = format_text("{userpictureurl $size}", FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertNotEmpty($filtered, sprintf("Tag {userpictureurl %s} returned empty\nActual: '%s'", $size, $filtered));
            $this->assertStringContainsString(
                'http',
                $filtered,
                sprintf("Tag {userpictureurl %s} should contain URL\nActual: '%s'", $size, $filtered)
            );
        }

        // Test userpictureimg with different sizes.
        foreach ($sizes as $size) {
            $filtered = format_text("{userpictureimg $size}", FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertStringContainsString(
                '<img',
                $filtered,
                sprintf("Tag {userpictureimg %s} should contain <img tag\nActual: '%s'", $size, $filtered)
            );
            $this->assertStringContainsString(
                'src=',
                $filtered,
                sprintf("Tag {userpictureimg %s} should contain src attribute\nActual: '%s'", $size, $filtered)
            );
        }
    }

    /**
     * Test profile when logged out.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_profile_when_logged_out(): void {
        // Set as guest user (not logged in).
        $this->setGuestUser();

        // Profile tags should handle guest users gracefully.
        // The filter checks isloggedin() and isguestuser() internally.
        $tests = [
            '{firstname}',
            '{lastname}',
            '{email}',
            '{username}',
        ];

        foreach ($tests as $tag) {
            $filtered = format_text($tag, FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertEquals(
                '',
                $filtered,
                sprintf("Tag %s should be blank for guest users\nActual: '%s'", $tag, $filtered)
            );
        }
    }

    /**
     * Test multiple profile tags in one string.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_multiple_profile_tags(): void {
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
        ]);
        $this->setUser($user);

        $text = 'Hello {firstname} {lastname}, your email is {email}';
        $filtered = format_text($text, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            'Hello John Doe, your email is john@example.com',
            $filtered,
            sprintf("Multiple profile tags should be replaced in one pass\nActual: '%s'", $filtered)
        );
    }
}
