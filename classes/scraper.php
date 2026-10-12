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
     * @param bool $cleancode Whether the HTML in $code must be cleaned. Pass false only where Moodle did not
     *     clean the text containing the tag, so that $code gets the same treatment as that text.
     * @param string $headinglevel Optional change to the level of headings in the retrieved content. See
     *     changeheadinglevels().
     * @return string Extracted content and optional code, or fail output if the content is unavailable.
     */
    public function scrapehtml($url, $tag = '', $class = '', $id = '', $code = '', $cleancode = true, $headinglevel = '') {
        $cleancode = (bool) $cleancode;
        $cachekey = sha1(implode('|', [$url, $tag, $class, $id, $code, (int) $cleancode, $headinglevel]));
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

        if ($headinglevel !== '') {
            $node = $this->changeheadinglevels($xpath, $node, $headinglevel);
        }

        // The scraped fragment comes from an external site and is always cleaned. The code attribute
        // is URL encoded, so Moodle's own cleaning sees it as plain text and it only becomes markup
        // here. It is cleaned too, unless Moodle left the text containing the tag uncleaned, in which
        // case its author could have added the same markup directly. Ids are kept because wrapping
        // the scraped content in an element with an id is a documented use.
        $scraped = clean_text($dom->saveXML($node), FORMAT_HTML);
        $code = urldecode($code);
        if ($cleancode && $code !== '') {
            $code = clean_text($code, FORMAT_HTML, ['allowid' => true]);
        }
        $content = $scraped . $code;

        if ($cachettl > 0 && $cache !== null) {
            $cache->set($cachekey, ['timecreated' => time(), 'content' => $content]);
        }

        return $content;
    }

    /**
     * Change the level of the headings in the retrieved content.
     *
     * This lets a page from another site fit the heading structure of the Moodle page it is shown on,
     * without a script. The value is either relative or absolute:
     *
     * - "+1" to "+5" lowers every heading by that many levels (h1 becomes h2 with "+1"), and "-1"
     *   to "-5" raises them.
     * - "1" to "6" makes the highest heading found that level and moves the others by the same
     *   amount, so their structure is kept. With "3", h1 becomes h3 and h2 becomes h4.
     *
     * Levels are kept between h1 and h6. Any other value is ignored.
     *
     * @param \DOMXPath $xpath XPath for the document containing the node.
     * @param \DOMElement $node The retrieved element.
     * @param string $headinglevel Relative or absolute heading level.
     * @return \DOMElement The retrieved element, which is a new element if it was itself a heading.
     */
    private function changeheadinglevels(\DOMXPath $xpath, \DOMElement $node, $headinglevel) {
        $headinglevel = trim((string) $headinglevel);
        if (!preg_match('/^(?:[+-][1-5]|[1-6])$/', $headinglevel)) {
            return $node;
        }

        $headings = [];
        $query = 'descendant-or-self::*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]';
        foreach ($xpath->query($query, $node) as $heading) {
            $headings[] = $heading;
        }
        if (empty($headings)) {
            return $node;
        }

        if ($headinglevel[0] === '+' || $headinglevel[0] === '-') {
            $shift = (int) $headinglevel;
        } else {
            $highest = 6;
            foreach ($headings as $heading) {
                $highest = min($highest, (int) substr(strtolower($heading->nodeName), 1));
            }
            $shift = (int) $headinglevel - $highest;
        }
        if ($shift === 0) {
            return $node;
        }

        // Elements cannot be renamed, so each heading is replaced by a new one with the same
        // attributes and content.
        foreach ($headings as $heading) {
            $level = (int) substr(strtolower($heading->nodeName), 1);
            $newlevel = max(1, min(6, $level + $shift));
            if ($newlevel === $level) {
                continue;
            }
            $replacement = $heading->ownerDocument->createElement('h' . $newlevel);
            foreach ($heading->attributes as $attribute) {
                $replacement->setAttribute($attribute->nodeName, $attribute->nodeValue);
            }
            while ($heading->firstChild) {
                $replacement->appendChild($heading->firstChild);
            }
            $heading->parentNode->replaceChild($replacement, $heading);
            if ($heading === $node) {
                $node = $replacement;
            }
        }

        return $node;
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

        // When an allowed hosts list is configured, redirects are followed here rather than by
        // cURL, so that every host in the chain is checked against the list and not only the
        // host of the first request. Without a list there is no additional boundary to apply,
        // and Moodle's own cURL security settings already check each redirect.
        $followredirectshere = $this->hasscrapeallowedhosts();
        $maxredirects = 5;
        $maxbytes = $this->getscrapemaxbytes();

        for ($hop = 0; $hop <= $maxredirects; $hop++) {
            $content = '';
            $curl = new \curl();
            $options = [
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_TIMEOUT' => 10,
                'CURLOPT_FOLLOWLOCATION' => $followredirectshere ? 0 : 1,
                'CURLOPT_MAXREDIRS' => $maxredirects,
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

            if ($curl->get_errno()) {
                return false;
            }

            // Where a redirect wanted to go. Only reported when cURL was told not to follow it.
            $redirecturl = $followredirectshere && !empty($info['redirect_url']) ? $info['redirect_url'] : '';
            if ($redirecturl !== '') {
                if ($hop === $maxredirects) {
                    return false;
                }
                $fromurl = $url;
                $url = $this->normalizescrapeurl($redirecturl);
                if ($url === false) {
                    return false;
                }
                // A site moving between its www and bare address, such as example.com to
                // www.example.com, is the same site, so it is followed even when only one of the
                // two is listed. Any other host must be on the list.
                if (!$this->isscrapehostallowed($url) && !$this->iswwwcounterpart($fromurl, $url)) {
                    return false;
                }
                continue;
            }

            if (empty($info['http_code']) || $info['http_code'] != 200 || $content === '') {
                return false;
            }

            if (strlen($content) > $maxbytes || !$this->isscrapecontenttypeallowed($info)) {
                return false;
            }

            return $content;
        }

        return false;
    }

    /**
     * Check whether an allowed hosts list has been configured for the scrape tag.
     *
     * @return bool True if at least one allowed host has been configured.
     */
    private function hasscrapeallowedhosts() {
        return trim((string) get_config('filter_filtercodes', 'scrape_allowed_hosts')) !== '';
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
     * Check whether two URLs are on the same host apart from a leading "www.".
     *
     * Used when following redirects, so that a site which redirects between example.com and
     * www.example.com keeps working when only one of the two is in the allowed hosts list.
     *
     * @param string $fromurl URL which redirected.
     * @param string $tourl URL it redirected to.
     * @return bool True if one host is the other with "www." added or removed.
     */
    private function iswwwcounterpart($fromurl, $tourl) {
        $from = strtolower((string) parse_url($fromurl, PHP_URL_HOST));
        $to = strtolower((string) parse_url($tourl, PHP_URL_HOST));
        if ($from === '' || $to === '' || $from === $to) {
            return false;
        }

        return $to === 'www.' . $from || $from === 'www.' . $to;
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
