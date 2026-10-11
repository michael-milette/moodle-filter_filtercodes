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

#[\PHPUnit\Framework\Attributes\CoversClass(\filter_filtercodes\text_filter::class)]
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
     * Test that a quote in a button URL cannot introduce an unwanted attribute.
     *
     * @return void
     */
    public function test_button_tag_url_cannot_inject_attributes(): void {
        $before = '{button #" onmouseover="alert(1)}x{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringNotContainsString(
            'onmouseover=',
            $filtered,
            sprintf("Should not contain an event handler\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'href="#"',
            $filtered,
            sprintf("Should still link\nActual: '%s'", $filtered)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('button_unsafe_scheme_provider')]
    /**
     * Test that button URLs using a scheme which can run script are dropped.
     *
     * @dataProvider button_unsafe_scheme_provider
     * @param string $url URL to place in the tag.
     * @return void
     */
    public function test_button_tag_rejects_unsafe_scheme(string $url): void {
        $before = '{button ' . $url . '}x{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringNotContainsString(
            'href=',
            $filtered,
            sprintf("Should not produce a link for '%s'\nActual: '%s'", $url, $filtered)
        );
    }

    /**
     * Data provider of URL schemes which must never reach the href attribute.
     *
     * @return array
     */
    public static function button_unsafe_scheme_provider(): array {
        return [
            'javascript' => ['javascript:alert(1)'],
            'mixed case' => ['JaVaScRiPt:alert(1)'],
            'encoded tab' => ['java&#9;script:alert(1)'],
            'leading space' => ['  javascript:alert(1)'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'data html' => ['data:text/html;base64,PHN2Zz4='],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('button_safe_url_provider')]
    /**
     * Test that button URLs which are merely unusual continue to work.
     *
     * @dataProvider button_safe_url_provider
     * @param string $url URL to place in the tag.
     * @param string $expected Expected href value.
     * @return void
     */
    public function test_button_tag_allows_safe_url(string $url, string $expected): void {
        $before = '{button ' . $url . '}x{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'href="' . $expected . '"',
            $filtered,
            sprintf("Should link to '%s'\nActual: '%s'", $expected, $filtered)
        );
    }

    /**
     * Data provider of URLs which must continue to produce a link.
     *
     * @return array
     */
    public static function button_safe_url_provider(): array {
        return [
            'absolute' => ['https://example.com/a', 'https://example.com/a'],
            'root relative' => ['/local/page.php', '/local/page.php'],
            'relative' => ['page.php?a=1', 'page.php?a=1'],
            'anchor' => ['#top', '#top'],
            'protocol relative' => ['//cdn.example.com/x', '//cdn.example.com/x'],
            'mailto' => ['mailto:someone@example.com', 'mailto:someone@example.com'],
            'tel' => ['tel:+15551234567', 'tel:+15551234567'],
            'scheme name in query' => [
                'https://example.com/s?q=javascript:void',
                'https://example.com/s?q=javascript:void',
            ],
        ];
    }

    /**
     * Test that a scheme which is not in the Allowed URL schemes setting is dropped by default.
     *
     * ftp is a perfectly safe scheme but is not part of the default allow list, so the button must
     * not link to it unless an administrator adds it.
     *
     * @return void
     */
    public function test_button_tag_rejects_scheme_not_in_allowed_list(): void {
        $before = '{button ftp://files.example.com/x}x{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringNotContainsString(
            'href=',
            $filtered,
            sprintf("ftp is not in the default allow list and should not link\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test that a scheme added to the Allowed URL schemes setting is honoured.
     *
     * @return void
     */
    public function test_button_tag_allows_configured_extra_scheme(): void {
        set_config('allowedurlschemes', "http\nhttps\nmailto\ntel\nsms\nftp", 'filter_filtercodes');

        $before = '{button ftp://files.example.com/x}x{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'href="ftp://files.example.com/x"',
            $filtered,
            sprintf("ftp should link once added to the allow list\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test that clearing the Allowed URL schemes setting disallows every scheme, leaving only
     * relative links working. Clearing the setting must fail closed, never allow everything.
     *
     * @return void
     */
    public function test_button_tag_allowedurlschemes_empty_allows_only_relative(): void {
        set_config('allowedurlschemes', '', 'filter_filtercodes');

        // With no schemes allowed, even a normally safe scheme such as mailto is dropped.
        $mailto = format_text('{button mailto:a@example.com}x{/button}', FORMAT_HTML, [
            'context' => \context_system::instance(),
        ]);
        $this->assertStringNotContainsString(
            'href=',
            $mailto,
            sprintf("An empty setting must not allow any scheme\nActual: '%s'", $mailto)
        );

        // A relative link has no scheme and therefore still works.
        $relative = format_text('{button /local/page.php}x{/button}', FORMAT_HTML, [
            'context' => \context_system::instance(),
        ]);
        $this->assertStringContainsString(
            'href="/local/page.php"',
            $relative,
            sprintf("Relative links have no scheme and should still work\nActual: '%s'", $relative)
        );
    }

    /**
     * Test that an Allowed URL schemes setting which has never been saved uses the default list.
     *
     * A site upgraded through the web interface has no value for a new setting until the
     * administrator saves the new settings page. Existing buttons must keep working until then.
     *
     * @return void
     */
    public function test_button_tag_allowedurlschemes_unset_uses_default(): void {
        unset_config('allowedurlschemes', 'filter_filtercodes');

        $https = format_text('{button https://example.com/a}x{/button}', FORMAT_HTML, [
            'context' => \context_system::instance(),
        ]);
        $this->assertStringContainsString(
            'href="https://example.com/a"',
            $https,
            sprintf("An unsaved setting should allow the default schemes\nActual: '%s'", $https)
        );

        $ftp = format_text('{button ftp://files.example.com/x}x{/button}', FORMAT_HTML, [
            'context' => \context_system::instance(),
        ]);
        $this->assertStringNotContainsString(
            'href=',
            $ftp,
            sprintf("An unsaved setting should only allow the default schemes\nActual: '%s'", $ftp)
        );
    }

    /**
     * Test that the Allowed URL schemes setting tolerates capitals, trailing colons and commas.
     *
     * @return void
     */
    public function test_button_tag_allowedurlschemes_lenient_format(): void {
        set_config('allowedurlschemes', "HTTPS:\r\nmailto:, Tel", 'filter_filtercodes');

        foreach (['https://example.com/a', 'mailto:a@example.com', 'tel:+15551234567'] as $url) {
            $filtered = format_text('{button ' . $url . '}x{/button}', FORMAT_HTML, [
                'context' => \context_system::instance(),
            ]);
            $this->assertStringContainsString(
                'href="' . $url . '"',
                $filtered,
                sprintf("%s should be allowed\nActual: '%s'", $url, $filtered)
            );
        }
    }

    /**
     * Test that the default of the Allowed URL schemes setting matches the default used by the filter
     * when the setting has not been saved.
     *
     * @return void
     */
    public function test_allowedurlschemes_default_matches_filter(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');

        $this->setAdminUser();
        $page = admin_get_root(true, true)->locate('filtersettingfiltercodes');
        $this->assertNotNull($page, 'The FilterCodes settings page should exist');
        // Moodle keys a page's settings by plugin name followed directly by the setting name.
        $key = 'filter_filtercodesallowedurlschemes';
        $this->assertTrue(isset($page->settings->$key), 'The Allowed URL schemes setting should exist');
        $this->assertSame(
            \filter_filtercodes\text_filter::DEFAULT_URL_SCHEMES,
            $page->settings->$key->get_defaultsetting(),
            'settings/general.php and text_filter::DEFAULT_URL_SCHEMES must have the same default'
        );
    }

    /**
     * Test that an ampersand in a button URL is not double encoded.
     *
     * @return void
     */
    public function test_button_tag_ampersand_not_double_encoded(): void {
        $before = '{button /course/view.php?id=1&amp;x=2}ok{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'href="/course/view.php?id=1&amp;x=2"',
            $filtered,
            sprintf("Ampersand should be encoded exactly once\nActual: '%s'", $filtered)
        );
        $this->assertStringNotContainsString(
            '&amp;amp;',
            $filtered,
            sprintf("Ampersand should not be double encoded\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test that the documented way of opening a button in a new tab still works.
     *
     * @return void
     */
    public function test_button_tag_target_attribute(): void {
        $before = '{button https://example.com" target="_blank}Example{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'target="_blank"',
            $filtered,
            sprintf("Should open in a new tab\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'rel="noopener noreferrer"',
            $filtered,
            sprintf("Should protect the opener\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'href="https://example.com"',
            $filtered,
            sprintf("Should keep the URL\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test which additional button attributes are kept and which are discarded.
     *
     * @return void
     */
    public function test_button_tag_attribute_allowlist(): void {
        $before = '{button /x" title="Hello" class="btn btn-danger}Go{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'title="Hello"',
            $filtered,
            sprintf("Should keep the title\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'class="btn btn-danger"',
            $filtered,
            sprintf("Should keep the author class\nActual: '%s'", $filtered)
        );
        $this->assertStringNotContainsString(
            'btn-primary',
            $filtered,
            sprintf("Author class should replace the default\nActual: '%s'", $filtered)
        );

        $before = '{button /x" style="width:99vw" formaction="//evil.example}Go{/button}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringNotContainsString(
            'style=',
            $filtered,
            sprintf("Should discard style\nActual: '%s'", $filtered)
        );
        $this->assertStringNotContainsString(
            'formaction=',
            $filtered,
            sprintf("Should discard formaction\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test that a button can open a Bootstrap modal, but cannot use other Bootstrap toggles.
     *
     * @return void
     */
    public function test_button_tag_bootstrap_modal_toggle(): void {
        foreach (['data-bs-toggle', 'data-toggle'] as $attribute) {
            $before = '{button #mymodal" ' . $attribute . '="modal}Open{/button}';
            $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertStringContainsString(
                'href="#mymodal" ' . $attribute . '="modal"',
                $filtered,
                sprintf("%s=\"modal\" should be kept\nActual: '%s'", $attribute, $filtered)
            );

            $before = '{button #x" ' . $attribute . '="popover}Open{/button}';
            $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertStringNotContainsString(
                $attribute . '=',
                $filtered,
                sprintf("%s should only allow modal\nActual: '%s'", $attribute, $filtered)
            );
        }
    }

    /**
     * Test editingtoggle tag.
     *
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

    #[\PHPUnit\Framework\Attributes\DataProvider('qrcode_injection_provider')]
    /**
     * Test that QR code content cannot add attributes to the generated image.
     *
     * @dataProvider qrcode_injection_provider
     * @param string $content Content to place between the tags.
     * @return void
     */
    public function test_qrcode_tag_cannot_inject_attributes(string $content): void {
        $before = '{qrcode}' . $content . '{/qrcode}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // An escaped value reads onerror=&quot;, so a literal quote here means the attribute broke out.
        $this->assertStringNotContainsString(
            'onerror="',
            $filtered,
            sprintf("Should not produce an event handler\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'alt="',
            $filtered,
            sprintf("Should still have alternative text\nActual: '%s'", $filtered)
        );
    }

    /**
     * Data provider of QR code content which must not escape the alt attribute.
     *
     * @return array
     */
    public static function qrcode_injection_provider(): array {
        return [
            'plain quote' => ['x" onerror="alert(1)" a="'],
            'encoded quote' => ['x&quot; onerror=&quot;alert(1)'],
        ];
    }

    /**
     * Test showmore tag.
     *
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
