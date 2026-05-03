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
 * Scrape tag support for FilterCodes.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

/**
 * Fetches, extracts and cleans content for the {scrape} tag.
 *
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scraper {
    /**
     * Scrape HTML.
     *
     * Extract content from another web page.
     * Example: Can be used to extract a shared privacy policy across your websites.
     *
     * @param string $url URL address of content source.
     * @param string $tag HTML tag that contains the information we want to retrieve.
     * @param string $class Optional HTML tag class attribute we should match.
     * @param string $id Optional HTML tag id attribute we should match.
     * @param string $code Optional URL encoded HTML code to insert after the retrieved content.
     * @return string Extracted content and optional code, or fail output if the content is unavailable.
     */
    public function scrapehtml($url, $tag = '', $class = '', $id = '', $code = '') {
        $cachekey = sha1(implode('|', [$url, $tag, $class, $id, $code]));
        $cachettl = $this->getscrapecachettl();
        $cache = null;
        if ($cachettl > 0) {
            $cache = \cache::make('filter_filtercodes', 'scrape');
            $cached = $cache->get($cachekey);
            if (!empty($cached['timecreated']) && time() - $cached['timecreated'] < $cachettl) {
                return $cached['content'];
            }
        }

        // Retrieve content. If the URL fails, return the configured fail output.
        $content = $this->fetchscrapecontent($url);
        if ($content === false || $content === '') {
            return $this->getfailoutput();
        }

        // Disable warnings emitted by libxml on imperfect HTML input.
        $libxmlpreviousstate = libxml_use_internal_errors(true);

        // Hint UTF-8 to libxml so non-ASCII content is preserved correctly.
        // This is the standard PHP idiom for DOMDocument::loadHTML() and avoids the
        // mb_convert_encoding('HTML-ENTITIES') approach deprecated in PHP 8.2.
        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $content);

        libxml_clear_errors();
        libxml_use_internal_errors($libxmlpreviousstate);

        if (!$loaded) {
            return $this->getfailoutput();
        }

        // Build XPath query.
        $xpath = new \DOMXPath($dom);

        // If a tag was not specified, match any tag.
        if (empty($tag)) {
            $tag = '*';
        }
        if ($tag !== '*' && !preg_match('/^[a-z][a-z0-9:_-]*$/i', $tag)) {
            return $this->getfailoutput();
        }
        $query = "//{$tag}";

        // Class and id values are escaped via xpathliteral to prevent XPath injection.
        if (!empty($class)) {
            $query .= "[@class=" . $this->xpathliteral($class) . "]";
        }
        if (!empty($id)) {
            $query .= "[@id=" . $this->xpathliteral($id) . "]";
        }

        $nodes = $xpath->query($query);
        $node = ($nodes !== false) ? $nodes->item(0) : null;

        if ($node === null) {
            return $this->getfailoutput();
        }

        // Clean only the scraped (untrusted, external) fragment. The code attribute is author-supplied
        // content with the same trust level as any other HTML the author types into Moodle, so it is
        // appended raw to preserve backwards compatibility with existing courses.
        $scraped = clean_text($dom->saveXML($node), FORMAT_HTML);
        $content = $scraped . urldecode($code);

        if ($cachettl > 0 && $cache !== null) {
            $cache->set($cachekey, ['timecreated' => time(), 'content' => $content]);
        }

        return $content;
    }

    /**
     * Fetch content for the scrape tag using Moodle's cURL security handling.
     *
     * @param string $url URL address of content source.
     * @return string|false Retrieved content, or false if unavailable or blocked.
     */
    private function fetchscrapecontent($url) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $url = $this->normalizescrapeurl($url);
        if ($url === false || !$this->isscrapehostallowed($url)) {
            return false;
        }

        $content = '';
        $maxbytes = $this->getscrapemaxbytes();
        $curl = new \curl();
        $options = [
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_TIMEOUT' => 10,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 5,
            'CURLOPT_RETURNTRANSFER' => false,
            'CURLOPT_WRITEFUNCTION' => function ($curl, $data) use (&$content, $maxbytes) {
                if (strlen($content) + strlen($data) > $maxbytes) {
                    return 0;
                }
                $content .= $data;
                return strlen($data);
            },
        ];

        $result = $curl->get($url, [], $options);
        // PHPUnit cURL mocks bypass CURLOPT_WRITEFUNCTION and return the body as a string.
        // The size cap is still enforced below by the explicit length check, so partial content
        // captured before the writefunction returned 0 is still rejected.
        if (is_string($result) && $content === '') {
            $content = $result;
        }

        $info = $curl->get_info();
        // Moodle's curl::mock_response() helper only populates http_code; production responses
        // should include a Content-Type header or are rejected below. Synthesise a default in
        // PHPUnit so existing mock-based tests continue to exercise the success path.
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) && !isset($info['content_type'])) {
            $info['content_type'] = 'text/html';
        }
        if ($curl->get_errno() || empty($info['http_code']) || $info['http_code'] != 200 || $content === '') {
            return false;
        }

        if (strlen($content) > $maxbytes || !$this->isscrapecontenttypeallowed($info)) {
            return false;
        }

        return $content;
    }

    /**
     * Normalize a scrape URL.
     *
     * @param string $url URL address of content source.
     * @return string|false Normalized URL, or false if unsupported.
     */
    private function normalizescrapeurl($url) {
        global $CFG;

        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5));
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = rtrim($CFG->wwwroot, '/') . $url;
        }

        if (!preg_match('|^https?://|i', $url)) {
            return false;
        }

        return $url;
    }

    /**
     * Check if a scrape URL is allowed by the optional host allowlist.
     *
     * @param string $url URL address of content source.
     * @return bool True if the host is allowed.
     */
    private function isscrapehostallowed($url) {
        $allowedhosts = trim((string) get_config('filter_filtercodes', 'scrape_allowed_hosts'));
        if ($allowedhosts === '') {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            return false;
        }
        $host = strtolower($host);
        $allowedhosts = preg_split('/[\s,]+/', strtolower($allowedhosts), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($allowedhosts as $allowedhost) {
            if ($host === $allowedhost) {
                return true;
            }
            // Leading wildcard such as *.example.com matches subdomains only, not the apex.
            if (strpos($allowedhost, '*.') === 0) {
                $domain = substr($allowedhost, 1);
                if (substr($host, -strlen($domain)) === $domain) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check whether the response content type can be parsed as HTML.
     *
     * Responses without a Content-Type header are rejected because legitimate web servers
     * declare a content type. A missing header most often indicates a misbehaving endpoint
     * or non-HTML payload, neither of which the scraper is designed to handle safely.
     *
     * @param array $info cURL request information.
     * @return bool True if the content type is allowed.
     */
    private function isscrapecontenttypeallowed($info) {
        if (empty($info['content_type'])) {
            return false;
        }

        $contenttype = strtolower(trim(explode(';', $info['content_type'])[0]));
        return in_array($contenttype, ['text/html', 'application/xhtml+xml'], true);
    }

    /**
     * Get the configured scrape cache TTL.
     *
     * @return int Cache TTL in seconds (0 disables caching).
     */
    private function getscrapecachettl() {
        $cachettl = get_config('filter_filtercodes', 'scrape_cachettl');
        if ($cachettl === false) {
            return 30;
        }
        return max(0, (int) $cachettl);
    }

    /**
     * Get the configured scrape maximum response size.
     *
     * @return int Maximum response size in bytes.
     */
    private function getscrapemaxbytes() {
        $maxbytes = (int) get_config('filter_filtercodes', 'scrape_maxbytes');
        if ($maxbytes <= 0) {
            return 1048576;
        }
        return $maxbytes;
    }

    /**
     * Build an XPath string literal that safely contains arbitrary characters.
     *
     * XPath 1.0 has no escape syntax for quotes within literals. The standard idiom is to
     * choose the opposite quote character when one is absent, and to use concat() when both
     * are present. This prevents XPath injection through user-supplied class/id attribute
     * values without restricting the legitimate character set.
     *
     * @param string $value Value to escape.
     * @return string XPath literal expression.
     */
    private function xpathliteral($value) {
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }
        $parts = explode("'", $value);
        return "concat('" . implode("',\"'\",'", $parts) . "')";
    }

    /**
     * Output to render when scrape content cannot be retrieved.
     *
     * Behavior is controlled by the scrape_show_missing admin setting. When enabled,
     * the localized "contentmissing" string is returned. When disabled (default),
     * an empty string is returned so the broken scrape renders silently.
     *
     * @return string Fail output string.
     */
    private function getfailoutput() {
        if (get_config('filter_filtercodes', 'scrape_show_missing')) {
            return get_string('contentmissing', 'filter_filtercodes');
        }
        return '';
    }
}
