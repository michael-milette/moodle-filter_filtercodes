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
 * Unit tests for FilterCodes HTML and language tags.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_filtercodes\text_filter
 */

namespace filter_filtercodes;

/**
 * Unit tests for FilterCodes HTML and language tags.
 *
 * Test HTML/language tags like {langx}, {nbsp}, {hr}, {details}, etc.
 *
 * @copyright  2017-2026 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class html_lang_test extends \advanced_testcase {
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
     * Test langx tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_langx_tags(): void {
        $tests = [
            [
                'before' => 'No langx tags',
                'after'  => 'No langx tags',
            ],
            [
                'before' => '{langx es}Todo el texto está en español{/langx}',
                'after'  => '<span lang="es">Todo el texto está en español</span>',
            ],
            [
                'before' => '{langx fr}Ceci est du texte en français{/langx}',
                'after'  => '<span lang="fr">Ceci est du texte en français</span>',
            ],
            [
                'before' => 'Some content in Spanish ({langx es}mejor dicho, en español{/langx})',
                'after'  => 'Some content in Spanish (<span lang="es">mejor dicho, en español</span>)',
            ],
            [
                'before' => '{langx es}Algo de español{/langx}{langx fr}Quelque chose en français{/langx}',
                'after'  => '<span lang="es">Algo de español</span><span lang="fr">Quelque chose en français</span>',
            ],
            [
                'before' => '{langx en-ca}Some content{/langx}',
                'after'  => '<span lang="en-ca">Some content</span>',
            ],
        ];

        foreach ($tests as $test) {
            $filtered = format_text($test['before'], FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertEquals($test['after'], $filtered, "Failed for: {$test['before']}");
        }
    }

    /**
     * Test invalid langx tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_langx_invalid(): void {
        $tests = [
            [
                'before' => '{langx}Bad filter syntax{langx}',
                'after'  => '{langx}Bad filter syntax{langx}',
            ],
            [
                'before' => '{langx en_ca}Some content{/langx}',
                'after'  => '{langx en_ca}Some content{/langx}', // Underscores not allowed.
            ],
        ];

        foreach ($tests as $test) {
            $filtered = format_text($test['before'], FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertEquals($test['after'], $filtered, "Failed for: {$test['before']}");
        }
    }

    /**
     * Test nbsp (non-breaking space) tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_nbsp(): void {
        $tests = [
            [
                'before' => 'Before{nbsp}: Some content After',
                'after'  => 'Before&nbsp;: Some content After',
            ],
            [
                'before' => '{nbsp}{nbsp}{nbsp}',
                'after'  => '&nbsp;&nbsp;&nbsp;',
            ],
        ];

        foreach ($tests as $test) {
            $filtered = format_text($test['before'], FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertEquals(
                $test['after'],
                $filtered,
                sprintf(
                    "Nbsp tag test failed\nInput: '%s'\nExpected: '%s'\nActual: '%s'",
                    $test['before'],
                    $test['after'],
                    $filtered
                )
            );
        }
    }

    /**
     * Test soft hyphen tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_soft_hyphen(): void {
        $tests = [
            [
                'before' => 'Before{-}: Some content After',
                'after'  => 'Before&shy;: Some content After',
            ],
            [
                'before' => 'super{-}cali{-}fragilistic{-}expiali{-}docious',
                'after'  => 'super&shy;cali&shy;fragilistic&shy;expiali&shy;docious',
            ],
        ];

        foreach ($tests as $test) {
            $filtered = format_text($test['before'], FORMAT_HTML, ['context' => \context_system::instance()]);
            $this->assertEquals(
                $test['after'],
                $filtered,
                sprintf(
                    "Soft hyphen tag test failed\nInput: '%s'\nExpected: '%s'\nActual: '%s'",
                    $test['before'],
                    $test['after'],
                    $filtered
                )
            );
        }
    }

    /**
     * Test hr (horizontal rule) tag.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_hr_tag(): void {
        $filtered = format_text('{hr}', FORMAT_HTML, ['context' => \context_system::instance()]);
        $this->assertStringContainsString(
            '<hr',
            $filtered,
            sprintf("Tag {hr} should contain '<hr'\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test details/summary tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_details_summary(): void {
        $before = '{details}{summary}Click to expand{/summary}Hidden content here{/details}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            '<details',
            $filtered,
            sprintf("Should contain '<details'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            '<summary>',
            $filtered,
            sprintf("Should contain '<summary>'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'Click to expand',
            $filtered,
            sprintf("Should contain 'Click to expand'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'Hidden content here',
            $filtered,
            sprintf("Should contain 'Hidden content here'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            '</summary>',
            $filtered,
            sprintf("Should contain '</summary>'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            '</details>',
            $filtered,
            sprintf("Should contain '</details>'\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test details with CSS class.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_details_with_class(): void {
        $before = '{details my-class}{summary}Title{/summary}Content{/details}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            'class="my-class"',
            $filtered,
            sprintf("Should contain class attribute\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            '<details',
            $filtered,
            sprintf("Should contain '<details'\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test multilang tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_multilang(): void {
        // Note: multilang tags get converted to span tags that are then processed by Multi-Language Content filter.
        $before = '{multilang en}English{/multilang}{multilang fr}Français{/multilang}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should contain multilang span tags.
        $this->assertStringContainsString(
            'English',
            $filtered,
            sprintf("Should contain 'English'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'Français',
            $filtered,
            sprintf("Should contain 'Français'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            'class="multilang"',
            $filtered,
            sprintf("Should contain multilang class\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test escaping tags with brackets.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_escape_tags(): void {
        // Tags wrapped in square brackets should not be processed.
        $before = '[{firstname}] should not be replaced';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            '{firstname}',
            $filtered,
            sprintf("Escaped tag should remain\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test escaping encoded tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_escape_encoded_tags(): void {
        // Encoded tags wrapped in square brackets should not be processed.
        $before = '[%7Buserid%7D] should not be replaced';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            '%7Buserid%7D',
            $filtered,
            sprintf("Escaped encoded tag should remain\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test mixed HTML and lang tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_mixed_html_lang_tags(): void {
        $before = 'Text{nbsp}here {langx es}español{/langx} and{-}more';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString(
            '&nbsp;',
            $filtered,
            sprintf("Should contain '&nbsp;'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            '&shy;',
            $filtered,
            sprintf("Should contain '&shy;'\nActual: '%s'", $filtered)
        );
        $this->assertStringContainsString(
            '<span lang="es">',
            $filtered,
            sprintf("Should contain '<span lang=\"es\">'\nActual: '%s'", $filtered)
        );
    }

    /**
     * Test nested langx tags (should not work).
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_nested_langx(): void {
        $before = '{langx es}Outer {langx fr}inner{/langx} outer{/langx}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString('lang="es"', $filtered);
        $this->assertStringNotContainsString('{langx es}', $filtered);
    }

    /**
     * Test multiple identical tags.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_multiple_identical_tags(): void {
        $before = '{nbsp}Word1{nbsp}Word2{nbsp}Word3{nbsp}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        // Should have 4 nbsp entities.
        $this->assertEquals(4, substr_count($filtered, '&nbsp;'));
    }

    /**
     * Test langx with non-existent language code.
     *
     * @covers \filter_filtercodes\text_filter::filter
     * @return void
     */
    public function test_langx_nonexistent_language(): void {
        // Should still create the span tag even with non-standard language code.
        $before = '{langx xyz}Some content{/langx}';
        $filtered = format_text($before, FORMAT_HTML, ['context' => \context_system::instance()]);

        $this->assertStringContainsString('lang="xyz"', $filtered);
        $this->assertStringContainsString('Some content', $filtered);
    }
}
