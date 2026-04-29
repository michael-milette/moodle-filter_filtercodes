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
 * Unit tests for FilterCodes content tags.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */

namespace filter_filtercodes;

/**
 * Unit tests for FilterCodes content tags.
 *
 * Test content-related tags like {getstring}, {fa...}, {glyphicon...}, {help}, {info}, etc.
 *
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */
final class content_test extends \advanced_testcase {
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
     * Test getstring tag with default component.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_getstring_default_component(): void {
        $before = '{getstring}help{/getstring}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            'Help',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Help', $filtered)
        );
    }

    /**
     * Test getstring tag with specified component.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_getstring_with_component(): void {
        $before = '{getstring:filter_filtercodes}pluginname{/getstring}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            'Filter Codes',
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", 'Filter Codes', $filtered)
        );
    }

    /**
     * Test FontAwesome icon tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_fontawesome_icons(): void {
        $tests = [
            '{fa fa-home}',
            '{fas fa-user}',
            '{fab fa-github}',
            '{fa-solid fa-heart}',
            '{fa-brands fa-twitter}',
        ];

        foreach ($tests as $tag) {
            $filtered = format_text($tag, FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertStringContainsString('<span class="', $filtered, "Failed for: $tag");
            $this->assertStringContainsString(
                'aria-hidden="true"',
                $filtered,
                sprintf("Should contain %s\nActual: '%s'", 'aria-hidden="true"', $filtered)
            );
        }
    }

    /**
     * Test glyphicon tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_glyphicon(): void {
        $before = '{glyphicon glyphicon-star}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            '<span class="glyphicon glyphicon-star"',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", '<span class="glyphicon glyphicon-star"', $filtered)
        );
        $this->assertStringContainsString(
            'aria-hidden="true"',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'aria-hidden="true"', $filtered)
        );
    }

    /**
     * Test note tag (should not display).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_note_tag(): void {
        $before = 'Before {note}This is a note{/note} After';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Note content should not be displayed.
        $this->assertStringNotContainsString('This is a note', $filtered);
        $this->assertStringContainsString(
            'Before',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Before', $filtered)
        );
        $this->assertStringContainsString(
            'After',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'After', $filtered)
        );
    }

    /**
     * Test help tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_help_tag(): void {
        $before = '{help}Help text here{/help}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should create a help icon/link.
        $this->assertNotEmpty(
            $filtered,
            sprintf("Should not be empty\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'Help text here',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Help text here', $filtered)
        );
    }

    /**
     * Test info tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_info_tag(): void {
        $before = '{info}Info text here{/info}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should create an info icon/link.
        $this->assertNotEmpty(
            $filtered,
            sprintf("Should not be empty\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'Info text here',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Info text here', $filtered)
        );
    }

    /**
     * Test alert tag with different styles.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_alert_tags(): void {
        $styles = ['primary', 'success', 'warning', 'danger', 'info', 'border'];

        foreach ($styles as $style) {
            $before = "{alert $style}Alert message{/alert}";
            $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

            $this->assertStringContainsString('Alert message', $filtered, "Failed for style: $style");
            $this->assertStringContainsString(
                'alert',
                $filtered,
                sprintf("Should contain %s\nActual: '%s'", 'alert', $filtered)
            );
        }
    }

    /**
     * Test alert tag without style (should default).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_alert_default(): void {
        $before = '{alert}Default alert{/alert}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'Default alert',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Default alert', $filtered)
        );
    }

    /**
     * Test highlight tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_highlight_tag(): void {
        $before = 'This is {highlight}highlighted text{/highlight} here';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'highlighted text',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'highlighted text', $filtered)
        );
        // Should contain some highlighting markup.
        $this->assertNotEquals('This is highlighted text here', $filtered);
    }

    /**
     * Test marktext tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_marktext_tag(): void {
        $before = '{marktext}Marked text{/marktext}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'Marked text',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Marked text', $filtered)
        );
        $this->assertStringContainsString(
            'fc-marktext',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'fc-marktext', $filtered)
        );
    }

    /**
     * Test markborder tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_markborder_tag(): void {
        $before = '{markborder}Bordered text{/markborder}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'Bordered text',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Bordered text', $filtered)
        );
        $this->assertStringContainsString(
            'fc-markborder',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'fc-markborder', $filtered)
        );
    }

    /**
     * Test label tag with different types.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_label_tags(): void {
        $types = ['info', 'important', 'secondary', 'success', 'warning', 'primary', 'danger'];

        foreach ($types as $type) {
            $before = "{label $type}Label text{/label}";
            $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

            $this->assertStringContainsString('Label text', $filtered, "Failed for type: $type");
        }
    }

    /**
     * Test label tag without type (should default to info).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_label_default(): void {
        $before = '{label}Default label{/label}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'Default label',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Default label', $filtered)
        );
    }

    /**
     * Test button tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_button_tag(): void {
        $before = '{button https://example.com}Click Me{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'Click Me',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Click Me', $filtered)
        );
        $this->assertStringContainsString(
            'https://example.com',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'https://example.com', $filtered)
        );
        $this->assertStringContainsString(
            'btn',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'btn', $filtered)
        );
    }

    /**
     * Test editingtoggle tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_editingtoggle(): void {
        global $PAGE;

        $filtered = format_text('{editingtoggle}', FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should be either 'on' or 'off'.
        $this->assertTrue($filtered === 'on' || $filtered === 'off');
    }

    /**
     * Test lang tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_lang_tag(): void {
        $filtered = format_text('{lang}', FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should return a 2-letter language code.
        $this->assertEquals(2, strlen($filtered));
        $this->assertMatchesRegularExpression('/^[a-z]{2}$/', $filtered);
    }

    /**
     * Test recaptcha tag when logged in (should be blank).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_recaptcha_when_logged_in(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $filtered = format_text('{recaptcha}', FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertEquals(
            '',
            $filtered,
            sprintf("Logged-in users should not receive a recaptcha\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test formsesskey tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_formsesskey(): void {
        $filtered = format_text('{formsesskey}', FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should contain a hidden input field with sesskey.
        $this->assertNotEmpty(
            $filtered,
            sprintf("Should not be empty\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'type="hidden"',
            $filtered,
            sprintf("Should contain hidden input field\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'name="sesskey"',
            $filtered,
            sprintf("Should contain sesskey name\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'M.cfg.sesskey',
            $filtered,
            sprintf("Should contain JavaScript to set sesskey\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test urlencode tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_urlencode_tag(): void {
        $before = '{urlencode}hello world & stuff{/urlencode}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Note: format_text() converts & to &amp; before the filter runs, so we expect the encoded version of &amp;.
        $expected = urlencode('hello world &amp; stuff');
        $this->assertEquals(
            $expected,
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $expected, $filtered)
        );
    }

    /**
     * Test rawurlencode tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_rawurlencode_tag(): void {
        $before = '{rawurlencode}hello world & stuff{/rawurlencode}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Note: format_text() converts & to &amp; before the filter runs, so we expect the encoded version of &amp;.
        $expected = rawurlencode('hello world &amp; stuff');
        $this->assertEquals(
            $expected,
            $filtered,
            sprintf("Assertion failed\nExpected: '%s'\nActual: '%s'", $expected, $filtered)
        );
    }

    /**
     * Test keyboard tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_keyboard_tag(): void {
        $before = '{keyboard}Ctrl+C{/keyboard}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'Ctrl+C',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Ctrl+C', $filtered)
        );
        // Should have some visual formatting.
        $this->assertNotEquals('Ctrl+C', $filtered);
    }

    /**
     * Test qrcode tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_qrcode_tag(): void {
        $before = '{qrcode}https://example.com{/qrcode}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should generate an image or SVG.
        $this->assertNotEmpty(
            $filtered,
            sprintf("Should not be empty\nActual: '%s'", $filtered)
        );
        $this->assertTrue(
            strpos($filtered, '<img') !== false || strpos($filtered, '<svg') !== false,
            'QR code should generate an image or SVG'
        );
    }

    /**
     * Test showmore tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_showmore_tag(): void {
        $before = '{showmore}Hidden content here{/showmore}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'Hidden content here',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Hidden content here', $filtered)
        );
        // Should have toggle functionality.
        $this->assertNotEquals('Hidden content here', $filtered);
    }

    /**
     * Test multiple content tags in one string.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_multiple_content_tags(): void {
        $before = '{fa fa-home} {getstring}help{/getstring} {nbsp} {highlight}important{/highlight}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'fa-home',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'fa-home', $filtered)
        );
        $this->assertStringContainsString(
            'Help',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'Help', $filtered)
        );
        $this->assertStringContainsString(
            '&nbsp;',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", '&nbsp;', $filtered)
        );
        $this->assertStringContainsString(
            'important',
            $filtered,
            sprintf("Should contain %s\nActual: '%s'", 'important', $filtered)
        );
    }

    /**
     * Test global custom tags (if configured).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_global_tag(): void {
        // Unconfigured global tags should remain available for later filters/content handling.
        $before = '{global_customtag}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should either be replaced or remain as is if not configured.
        $this->assertEquals(
            $before,
            $filtered,
            sprintf("Unset global tags should remain unchanged\nActual: '%s'", $filtered)
        );
    }
}
