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

#[\PHPUnit\Framework\Attributes\CoversClass(\filter_filtercodes\scraper::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\filter_filtercodes\text_filter::class)]
/**
 * Unit tests for FilterCodes scrape tag.
 *
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\scraper
 * @covers     \filter_filtercodes\text_filter
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
     * @param array $options Additional filter options, such as noclean.
     * @return string Filtered text.
     */
    private function filtertext($text, array $options = []) {
        $filter = new text_filter();
        return $filter->filter($text, $options + ['context' => \context_system::instance()]);
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
     * Get a private method of the scraper so that it can be tested directly.
     *
     * @param string $name Method name.
     * @return \ReflectionMethod
     */
    private function getscrapermethod($name) {
        $method = new \ReflectionMethod(scraper::class, $name);
        // Private methods are accessible through reflection from PHP 8.1. Calling setAccessible()
        // there does nothing and is deprecated from PHP 8.5, so only call it on older versions.
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        return $method;
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
     * Test that ordinary HTML in the code attribute, such as a wrapper with an id, is kept when cleaned.
     *
     * @return void
     */
    public function test_scrape_code_attribute_keeps_ordinary_html(): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><div>Body</div></body></html>');

        $filtered = $this->filtertext(
            '{scrape url="https://example.test/page" tag="div" code="%3Cdiv%20id%3D%22wrapper%22%3E%3C%2Fdiv%3E"}'
        );

        $this->assertStringContainsString('Body', $filtered);
        $this->assertStringContainsString('id="wrapper"', $filtered);
    }

    /**
     * Test that a script in the code attribute is removed where Moodle cleaned the surrounding text.
     *
     * The code attribute is URL encoded, so Moodle's own cleaning does not see its markup. Without
     * this, anyone who can post filtered text could add a script to the page.
     *
     * @return void
     */
    public function test_scrape_code_attribute_cleaned_with_text(): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><div>Body</div></body></html>');

        $filtered = $this->filtertext('{scrape url="https://example.test/page" tag="div" code="'
            . rawurlencode('<p onclick="alert(1)">Extra</p><script>alert(2)</script>') . '"}');

        $this->assertStringContainsString('Body', $filtered);
        $this->assertStringContainsString('Extra', $filtered);
        $this->assertStringNotContainsString('<script', $filtered);
        $this->assertStringNotContainsString('onclick', $filtered);
    }

    /**
     * Test that a script in the code attribute is kept where Moodle left the surrounding text uncleaned.
     *
     * Moodle does this for content such as Pages, activity descriptions and Text blocks, where the
     * author could add a script directly.
     *
     * @return void
     */
    public function test_scrape_code_attribute_raw_with_uncleaned_text(): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><div>Body</div></body></html>');

        $filtered = $this->filtertext('{scrape url="https://example.test/page" tag="div" code="'
            . rawurlencode('<script>alert(2)</script>') . '"}', ['noclean' => true]);

        $this->assertStringContainsString('<script>alert(2)</script>', $filtered);
    }

    /**
     * Test that the code attribute is always cleaned when the administrator forces cleaning.
     *
     * @return void
     */
    public function test_scrape_code_attribute_cleaned_with_forceclean(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->requirecurlmock();
        $CFG->forceclean = true;

        \curl::mock_response('<html><body><div>Body</div></body></html>');

        $filtered = $this->filtertext('{scrape url="https://example.test/page" tag="div" code="'
            . rawurlencode('<script>alert(2)</script>') . '"}', ['noclean' => true]);

        $this->assertStringNotContainsString('<script', $filtered);
    }

    /**
     * Test that the code attribute is kept in site policy documents shown by the Policies tool.
     *
     * Moodle cleans policy documents, but only people allowed to manage policies can edit them.
     *
     * @return void
     */
    public function test_scrape_code_attribute_raw_on_policy_pages(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->requirecurlmock();
        $code = rawurlencode('<script>alert(2)</script>');

        foreach (['index.php', 'view.php', 'viewall.php'] as $script) {
            \curl::mock_response('<html><body><div>Body</div></body></html>');
            $PAGE = new \moodle_page();
            $PAGE->set_url(new \moodle_url('/admin/tool/policy/' . $script));
            $filtered = $this->filtertext('{scrape url="https://example.test/' . $script . '" tag="div" code="' . $code . '"}');
            $this->assertStringContainsString('<script>alert(2)</script>', $filtered, $script);
        }

        // Any other page is cleaned as usual.
        \curl::mock_response('<html><body><div>Body</div></body></html>');
        $PAGE = new \moodle_page();
        $PAGE->set_url(new \moodle_url('/admin/tool/policy/acceptances.php'));
        $filtered = $this->filtertext('{scrape url="https://example.test/other" tag="div" code="' . $code . '"}');
        $this->assertStringNotContainsString('<script', $filtered);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('headinglevel_provider')]
    /**
     * Test changing the level of headings in the scraped content.
     *
     * @dataProvider headinglevel_provider
     * @param string $headinglevel Value of the headinglevel attribute.
     * @param string $expected Expected headings, in order.
     * @return void
     */
    public function test_scrape_headinglevel(string $headinglevel, string $expected): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><div id="terms"><h1 class="title">Terms</h1><p>Text</p>'
            . '<h2>Part</h2><h3>Detail</h3></div></body></html>');

        $filtered = $this->filtertext('{scrape url="https://example.test/' . md5($headinglevel)
            . '" tag="div" id="terms" headinglevel="' . $headinglevel . '"}');

        preg_match_all('/<(h[1-6])\b[^>]*>/', $filtered, $matches);
        $this->assertSame($expected, implode(',', $matches[1]), $filtered);
        // Attributes and content of a heading are kept.
        $this->assertMatchesRegularExpression('/<h[1-6] class="title">Terms<\/h[1-6]>/', $filtered);
        $this->assertStringContainsString('<p>Text</p>', $filtered);
    }

    /**
     * Data provider for test_scrape_headinglevel.
     *
     * @return array
     */
    public static function headinglevel_provider(): array {
        return [
            'not set' => ['', 'h1,h2,h3'],
            'lower by one' => ['+1', 'h2,h3,h4'],
            'lower by four, kept at h6' => ['+4', 'h5,h6,h6'],
            'raise by one, kept at h1' => ['-1', 'h1,h1,h2'],
            'highest becomes h3' => ['3', 'h3,h4,h5'],
            'highest already h1' => ['1', 'h1,h2,h3'],
            'invalid value ignored' => ['h2', 'h1,h2,h3'],
            'out of range ignored' => ['+6', 'h1,h2,h3'],
        ];
    }

    /**
     * Test that the scraped element itself is changed when it is a heading.
     *
     * @return void
     */
    public function test_scrape_headinglevel_on_heading_element(): void {
        $this->requirecurlmock();

        \curl::mock_response('<html><body><h1 class="top">Title</h1></body></html>');

        $filtered = $this->filtertext('{scrape url="https://example.test/heading" tag="h1" headinglevel="+2"}');

        $this->assertStringContainsString('<h3 class="top">Title</h3>', $filtered);
    }

    /**
     * Test host allowlist blocks non-matching hosts.
     *
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
     * @return void
     */
    public function test_scrape_content_type_allowlist(): void {
        $scraper = new scraper();
        $method = $this->getscrapermethod('isscrapecontenttypeallowed');

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
    /**
     * Test detection of whether an allowed hosts list has been configured.
     *
     * The scraper only takes over following redirects when a list is configured, so this
     * decides whether each hop of a redirect chain is checked against the list.
     *
     * @return void
     */
    public function test_scrape_allowed_hosts_detection(): void {
        $scraper = new scraper();
        $method = $this->getscrapermethod('hasscrapeallowedhosts');

        $this->setfiltercodesconfig('scrape_allowed_hosts', '');
        $this->assertFalse($method->invoke($scraper), 'An empty setting is not an allowed hosts list.');

        $this->setfiltercodesconfig('scrape_allowed_hosts', '  ' . PHP_EOL . '  ');
        $this->assertFalse($method->invoke($scraper), 'Whitespace only is not an allowed hosts list.');

        $this->setfiltercodesconfig('scrape_allowed_hosts', 'allowed.example');
        $this->assertTrue($method->invoke($scraper), 'A configured host is an allowed hosts list.');

        $this->setfiltercodesconfig('scrape_allowed_hosts', '');
    }

    /**
     * Test that a redirect target is judged against the allowed hosts list.
     *
     * Each hop of a redirect chain is passed through this check, so a host which is not on
     * the list cannot be reached by redirecting to it from a host which is.
     *
     * @return void
     */
    public function test_scrape_redirect_target_is_checked_against_allowed_hosts(): void {
        $scraper = new scraper();
        $method = $this->getscrapermethod('isscrapehostallowed');

        $this->setfiltercodesconfig('scrape_allowed_hosts', 'allowed.example');

        $this->assertTrue(
            $method->invoke($scraper, 'https://allowed.example/page.html'),
            'The allowed host should remain reachable.'
        );
        $this->assertFalse(
            $method->invoke($scraper, 'https://elsewhere.example/page.html'),
            'A redirect to another public host must be refused.'
        );
        $this->assertFalse(
            $method->invoke($scraper, 'http://127.0.0.1/page.html'),
            'A redirect to an internal address must be refused.'
        );
        $this->assertFalse(
            $method->invoke($scraper, 'http://allowed.example.evil.test/page.html'),
            'A host which merely starts with an allowed host must be refused.'
        );

        $this->setfiltercodesconfig('scrape_allowed_hosts', '');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('www_counterpart_provider')]
    /**
     * Test that a redirect between a site's www and bare address is recognised as the same site.
     *
     * A redirect is followed if its target is on the allowed hosts list, or if it only adds or
     * removes "www." from the host which redirected.
     *
     * @dataProvider www_counterpart_provider
     * @param string $from URL which redirected.
     * @param string $to URL it redirected to.
     * @param bool $expected Whether the two are www counterparts.
     * @return void
     */
    public function test_scrape_redirect_www_counterpart(string $from, string $to, bool $expected): void {
        $scraper = new scraper();
        $method = $this->getscrapermethod('iswwwcounterpart');

        $this->assertSame($expected, $method->invoke($scraper, $from, $to));
    }

    /**
     * Data provider for test_scrape_redirect_www_counterpart.
     *
     * @return array
     */
    public static function www_counterpart_provider(): array {
        return [
            'adds www' => ['https://example.com/a', 'https://www.example.com/a', true],
            'removes www' => ['https://www.example.com/a', 'https://example.com/a', true],
            'http to https with www' => ['http://example.com/a', 'https://www.example.com/a', true],
            'ignores case' => ['https://Example.com/a', 'https://WWW.example.COM/a', true],
            'same host' => ['https://example.com/a', 'https://example.com/b', false],
            'other site with www' => ['https://example.com/a', 'https://www.elsewhere.example/a', false],
            'other subdomain' => ['https://example.com/a', 'https://cdn.example.com/a', false],
            'www without dot' => ['https://example.com/a', 'https://wwwexample.com/a', false],
            'two www prefixes' => ['https://example.com/a', 'https://www.www.example.com/a', false],
            'suffix match only' => ['https://evil.test/a', 'https://www.example.evil.test/a', false],
        ];
    }
}
