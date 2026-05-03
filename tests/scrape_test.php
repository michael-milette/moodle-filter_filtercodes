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
 * Unit tests for FilterCodes scrape tag.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\scraper
 */

namespace filter_filtercodes;

/**
 * Unit tests for FilterCodes scrape tag.
 *
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\scraper
 */
final class scrape_test extends \advanced_testcase {
    /** @var array|null Original forced plugin settings for FilterCodes. */
    private $originalforcedsettings = null;

    /**
     * Setup the test framework.
     *
     * @return void
     */
    public function setUp(): void {
        global $CFG, $PAGE;

        parent::setUp();
        $this->resetAfterTest(false);
        $this->setAdminUser();

        $this->originalforcedsettings = isset($CFG->forced_plugin_settings['filter_filtercodes'])
            ? $CFG->forced_plugin_settings['filter_filtercodes']
            : null;

        $CFG->forced_plugin_settings['filter_filtercodes'] = [
            'enable_scrape' => 1,
            'scrape_cachettl' => 0,
            'scrape_maxbytes' => 1024000,
            'scrape_allowed_hosts' => '',
            // Tests assert against the contentmissing message, so opt in to the visible-failure mode.
            // Default site behavior is silent failure (scrape_show_missing => 0); it is exercised
            // explicitly by test_scrape_silent_failure_when_show_missing_disabled.
            'scrape_show_missing' => 1,
        ];

        $PAGE->set_url(new \moodle_url('/'));
    }

    /**
     * Cleanup the test framework.
     *
     * @return void
     */
    public function tearDown(): void {
        global $CFG;

        \cache::make('filter_filtercodes', 'scrape')->purge();
        if ($this->originalforcedsettings === null) {
            unset($CFG->forced_plugin_settings['filter_filtercodes']);
        } else {
            $CFG->forced_plugin_settings['filter_filtercodes'] = $this->originalforcedsettings;
        }
        $this->originalforcedsettings = null;
        parent::tearDown();
    }

    /**
     * Filter text through Moodle filters.
     *
     * @param string $text Text to filter.
     * @return string Filtered text.
     */
    private function filtertext($text) {
        $filter = new text_filter();
        return $filter->filter($text, ['context' => \context_system::instance()]);
    }

    /**
     * Set a forced FilterCodes plugin config value for the current test.
     *
     * @param string $name Config name.
     * @param mixed $value Config value.
     * @return void
     */
    private function setfiltercodesconfig($name, $value) {
        global $CFG;

        $CFG->forced_plugin_settings['filter_filtercodes'][$name] = $value;
    }

    /**
     * Skip tests that need Moodle's cURL mock hook when it is unavailable.
     *
     * @return void
     */
    private function requirecurlmock() {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        if (!method_exists('curl', 'mock_response')) {
            $this->markTestSkipped('Moodle cURL mock responses are not available on this core version.');
        }
    }

    /**
     * Test disabled scrape support leaves the tag unchanged.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_scrape_disabled_leaves_tag_unchanged(): void {
        $this->setfiltercodesconfig('enable_scrape', 0);

        $filtered = $this->filtertext('{scrape url="https://example.test/page"}');

        $this->assertStringContainsString('{scrape url=', $filtered);
        $this->assertStringContainsString('https://example.test/page', $filtered);
    }

    /**
     * Test non-HTTP schemes are rejected.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_rejects_non_http_scheme(): void {
        $filtered = $this->filtertext('{scrape url="data:text/html,SECRET"}');

        $this->assertStringNotContainsString('SECRET', $filtered);
        $this->assertStringContainsString(get_string('contentmissing', 'filter_filtercodes'), $filtered);
    }

    /**
     * Test the file:// scheme is rejected (regression test for issue #361).
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_rejects_file_scheme(): void {
        global $CFG;

        // Try a few flavors of the original LFI payload to make the regression net wider.
        $payloads = [
            '{scrape url="file:///etc/hosts"}',
            '{scrape url="file:///c:/windows/system32/drivers/etc/hosts"}',
            '{scrape url="FILE:///etc/passwd"}',
            '{scrape url="php://filter/convert.base64-encode/resource=' . $CFG->dirroot . '/version.php"}',
        ];

        foreach ($payloads as $payload) {
            $filtered = $this->filtertext($payload);
            $this->assertStringNotContainsString('localhost', $filtered, "Leak detected for payload: {$payload}");
            $this->assertStringNotContainsString('root:', $filtered, "Leak detected for payload: {$payload}");
            $this->assertStringNotContainsString('<?php', $filtered, "Leak detected for payload: {$payload}");
            $this->assertStringContainsString(get_string('contentmissing', 'filter_filtercodes'), $filtered);
        }
    }

    /**
     * Test root-relative URLs are resolved against wwwroot.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_supports_root_relative_url(): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><h1>Root relative scrape</h1></body></html>');

        $filtered = $this->filtertext('{scrape url="/local/filtercodes-test.html" tag="h1"}');

        $this->assertStringContainsString('Root relative scrape', $filtered);
    }

    /**
     * Test tag, class and id extraction.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_extracts_matching_node(): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><div class="target" id="main">Matched content</div></body></html>');

        $filtered = $this->filtertext('{scrape url="https://example.test/page" tag="div" class="target" id="main"}');

        $this->assertStringContainsString('Matched content', $filtered);
    }

    /**
     * Test no matching node returns the missing content message (when enabled).
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_missing_node_returns_message(): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><h1>Available</h1></body></html>');

        $filtered = $this->filtertext('{scrape url="https://example.test/page" tag="div" id="missing"}');

        $this->assertStringContainsString(get_string('contentmissing', 'filter_filtercodes'), $filtered);
    }

    /**
     * Test scrape failure renders nothing when scrape_show_missing is disabled (default).
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_silent_failure_when_show_missing_disabled(): void {
        $this->setfiltercodesconfig('scrape_show_missing', 0);

        // Use a scheme rejection so this test does not depend on the cURL mock.
        $filtered = $this->filtertext('Before {scrape url="data:text/html,X"} After');

        $this->assertStringNotContainsString(get_string('contentmissing', 'filter_filtercodes'), $filtered);
        // The surrounding text remains so layout is preserved; the tag itself rendered nothing.
        $this->assertStringContainsString('Before', $filtered);
        $this->assertStringContainsString('After', $filtered);
    }

    /**
     * Test unsafe HTML in the scraped content is cleaned.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_cleans_external_content(): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><div><script>alert(1)</script><img src="x" onerror="alert(2)">Safe</div></body></html>');

        $filtered = $this->filtertext('{scrape url="https://example.test/page" tag="div"}');

        $this->assertStringContainsString('Safe', $filtered);
        $this->assertStringNotContainsString('<script', $filtered);
        $this->assertStringNotContainsString('onerror', $filtered);
    }

    /**
     * Test the code attribute is appended raw for backwards compatibility.
     *
     * Trust model: the code attribute is supplied by a course author (an editing role)
     * and shares the same trust level as any other HTML the author types into Moodle.
     * It is intentionally NOT cleaned, so existing courses that embed wrappers, scripts,
     * or other markup via code="..." continue to render unchanged.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_code_attribute_is_raw_for_bc(): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><div>Body</div></body></html>');

        // Safe HTML wrapper passed via code attribute should render verbatim.
        $filtered = $this->filtertext(
            '{scrape url="https://example.test/page" tag="div" code="%3Cdiv%20id%3D%22wrapper%22%3E%3C%2Fdiv%3E"}'
        );

        $this->assertStringContainsString('Body', $filtered);
        $this->assertStringContainsString('id="wrapper"', $filtered);
    }

    /**
     * Test host allowlist blocks non-matching hosts.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_allowed_hosts_blocks_other_hosts(): void {
        $this->setfiltercodesconfig('scrape_allowed_hosts', 'allowed.example');

        $filtered = $this->filtertext('{scrape url="https://blocked.example/page"}');

        $this->assertStringContainsString(get_string('contentmissing', 'filter_filtercodes'), $filtered);
    }

    /**
     * Test wildcard allowlist matches subdomains and rejects the apex.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_allowed_hosts_wildcard_semantics(): void {
        $this->requirecurlmock();

        $this->setfiltercodesconfig('scrape_allowed_hosts', '*.example.test');

        // Subdomain should match.
        \curl::mock_response('<html><body><h1>Sub matched</h1></body></html>');
        $filtered = $this->filtertext('{scrape url="https://sub.example.test/page" tag="h1"}');
        $this->assertStringContainsString('Sub matched', $filtered);

        // Apex should NOT match a leading-wildcard pattern.
        $filtered = $this->filtertext('{scrape url="https://example.test/page" tag="h1"}');
        $this->assertStringContainsString(get_string('contentmissing', 'filter_filtercodes'), $filtered);

        // Adding the apex separately should let it through.
        \cache::make('filter_filtercodes', 'scrape')->purge();
        $this->setfiltercodesconfig('scrape_allowed_hosts', "*.example.test\nexample.test");
        \curl::mock_response('<html><body><h1>Apex matched</h1></body></html>');
        $filtered = $this->filtertext('{scrape url="https://example.test/page" tag="h1"}');
        $this->assertStringContainsString('Apex matched', $filtered);
    }

    /**
     * Test class/id values cannot inject extra XPath expressions.
     *
     * Pre-fix, a class value of foo"] | //script[... would expand the XPath query and
     * leak nodes the author did not intend to extract. After escaping, the literal
     * value matches no node and the failure path is taken.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_blocks_xpath_injection_via_class(): void {
        $this->requirecurlmock();

        \curl::mock_response(
            '<html><body><div class="benign">Benign</div><script>alert(1)</script></body></html>'
        );

        $injection = 'foo"] | //script[@x="x';
        $filtered = $this->filtertext('{scrape url="https://example.test/x" tag="div" class="' . $injection . '"}');

        $this->assertStringNotContainsString('<script', $filtered);
        $this->assertStringNotContainsString('alert(1)', $filtered);
        $this->assertStringContainsString(get_string('contentmissing', 'filter_filtercodes'), $filtered);
    }

    /**
     * Test class/id values containing both quote characters are still escaped safely.
     *
     * Exercises the concat() branch of xpathliteral().
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_handles_class_with_mixed_quotes(): void {
        $this->requirecurlmock();

        // Mixed-quote class is unusual but valid HTML; it must not blow up the XPath query.
        $class = 'a\'b"c';
        \curl::mock_response('<html><body><div class="' . htmlspecialchars($class) . '">Mixed</div></body></html>');

        $scraper = new scraper();
        $filtered = $scraper->scrapehtml('https://example.test/q', 'div', $class);

        // The DOM stores the attribute decoded, so the literal value should match and the div is returned.
        $this->assertStringContainsString('Mixed', $filtered);
    }

    /**
     * Test oversized responses are rejected.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_rejects_oversized_response(): void {
        $this->requirecurlmock();

        \curl::mock_response(str_repeat('A', 1024001));

        $filtered = $this->filtertext('{scrape url="https://example.test/large"}');

        $this->assertStringContainsString(get_string('contentmissing', 'filter_filtercodes'), $filtered);
    }

    /**
     * Test the response content-type allowlist accepts HTML and rejects everything else.
     *
     * Exercises the production rejection path directly because Moodle's curl::mock_response()
     * helper does not populate the content_type field. A missing Content-Type header is
     * rejected because legitimate web servers should declare one.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_content_type_allowlist(): void {
        $scraper = new scraper();
        $method = new \ReflectionMethod(scraper::class, 'isscrapecontenttypeallowed');
        $method->setAccessible(true);

        // Allowed types.
        $this->assertTrue($method->invoke($scraper, ['content_type' => 'text/html']));
        $this->assertTrue($method->invoke($scraper, ['content_type' => 'text/html; charset=utf-8']));
        $this->assertTrue($method->invoke($scraper, ['content_type' => 'application/xhtml+xml']));
        $this->assertTrue($method->invoke($scraper, ['content_type' => 'TEXT/HTML']));

        // Rejected types.
        $this->assertFalse($method->invoke($scraper, ['content_type' => 'application/json']));
        $this->assertFalse($method->invoke($scraper, ['content_type' => 'text/plain']));
        $this->assertFalse($method->invoke($scraper, ['content_type' => 'image/png']));
        $this->assertFalse($method->invoke($scraper, ['content_type' => 'application/pdf']));
        $this->assertFalse($method->invoke($scraper, ['content_type' => 'application/octet-stream']));

        // Missing Content-Type header is rejected (servers should always declare one).
        $this->assertFalse($method->invoke($scraper, ['content_type' => '']));
        $this->assertFalse($method->invoke($scraper, []));
    }

    /**
     * Test successful scrape output is cached for the configured TTL.
     *
     * @covers \filter_filtercodes\scraper::scrapehtml
     * @return void
     */
    public function test_scrape_caches_successful_output(): void {
        $this->requirecurlmock();

        $this->setfiltercodesconfig('scrape_cachettl', 30);
        \cache::make('filter_filtercodes', 'scrape')->purge();

        \curl::mock_response('<html><body><h1>Second response</h1></body></html>');
        \curl::mock_response('<html><body><h1>First response</h1></body></html>');

        $tag = '{scrape url="https://example.test/cache" tag="h1"}';
        $first = $this->filtertext($tag);
        $second = $this->filtertext($tag);

        $this->assertStringContainsString('First response', $first);
        $this->assertStringContainsString('First response', $second);
        $this->assertStringNotContainsString('Second response', $second);

        $this->setfiltercodesconfig('scrape_cachettl', 0);
        $this->filtertext($tag);
    }
}
