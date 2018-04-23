<?php

namespace MyBB\Tests\Unit;

use MyBB\Tests\Unit\Traits\LegacyCoreAwareTest;

class ParserTest extends TestCase
{
    use LegacyCoreAwareTest;

    /**
     * @var \postParser $parser
     */
    private $parser;

    public static function setUpBeforeClass()
    {
        static::setupMybb();

        /** @var \Mockery\MockInterface $templatesMock */
        $templatesMock = $GLOBALS['templates'];

        $templatesMock->shouldReceive('get')
            ->withArgs(['mycode_size_int', 1, 0])
            ->andReturn(
                str_replace("\\'", "'", addslashes('<span style="font-size: {$size}pt;" class="mycode_size">{$text}</span>'))
            );
    }

    protected function setUp()
    {
        parent::setUp();

        $GLOBALS['mybb']->settings = array_merge($GLOBALS['mybb']->settings, [
            'allowcodemycode' => 1,
            'allowbasicmycode' => 1,
            'allowsymbolmycode' => 1,
            'allowlinkmycode' => 1,
            'allowemailmycode' => 1,
            'allowcolormycode' => 1,
            'allowsizemycode' => 1,
            'allowfontmycode' => 1,
            'allowalignmycode' => 1,
            'allowlistmycode' => 1,
        ]);

        $this->parser = new \postParser();
    }

    public function testParseBoldMyCode()
    {
        $tests = [
            '[b]test[/b]' => '<span style="font-weight: bold;" class="mycode_b">test</span>',
            '[b]
test
[/b]' => '<span style="font-weight: bold;" class="mycode_b"><br />
test<br />
</span>',
        ];

        foreach ($tests as $input => $expected) {
            $actual = $this->parser->parse_message($input, [
                'allow_mycode' => true,
            ]);

            $this->assertEquals($expected, $actual);
        }
    }

    public function testParseUnderlineMyCode()
    {
        $tests = [
            '[u]test[/u]' => '<span style="text-decoration: underline;" class="mycode_u">test</span>',
            '[u]
test
[/u]' => '<span style="text-decoration: underline;" class="mycode_u"><br />
test<br />
</span>',
        ];

        foreach ($tests as $input => $expected) {
            $actual = $this->parser->parse_message($input, [
                'allow_mycode' => true,
            ]);

            $this->assertEquals($expected, $actual);
        }
    }

    public function testSimpleParseItalicMyCode()
    {
        $tests = [
            '[i]test[/i]' => '<span style="font-style: italic;" class="mycode_i">test</span>',
            '[i]
test
[/i]' => '<span style="font-style: italic;" class="mycode_i"><br />
test<br />
</span>',
        ];

        foreach ($tests as $input => $expected) {
            $actual = $this->parser->parse_message($input, [
                'allow_mycode' => true,
            ]);

            $this->assertEquals($expected, $actual);
        }
    }

    public function testSimpleParseStrikeThroughMyCode()
    {
        $tests = [
            '[s]test[/s]' => '<span style="text-decoration: line-through;" class="mycode_s">test</span>',
            '[s]
test
[/s]' => '<span style="text-decoration: line-through;" class="mycode_s"><br />
test<br />
</span>',
        ];

        foreach ($tests as $input => $expected) {
            $actual = $this->parser->parse_message($input, [
                'allow_mycode' => true,
            ]);

            $this->assertEquals($expected, $actual);
        }
    }

    public function testSimpleHrMyCode()
    {
        $input = '[hr]';
        $expected = '<hr class="mycode_hr" />';

        $actual = $this->parser->parse_message($input, [
            'allow_mycode' => true,
        ]);

        $this->assertEquals($expected, $actual);
    }

    public function testSimpleSymbolMyCodes()
    {
        $symbols = [
            '(c)' => '&copy;',
            '(tm)' => '&#153;',
            '(r)' => '&reg;',
        ];

        foreach ($symbols as $input => $expected) {
            $this->assertEquals($expected, $this->parser->parse_message($input, [
                'allow_mycode' => true,
            ]));
        }
    }

    public function testSimpleAlignMyCodes()
    {
        $alignments = [
            'left',
            'center',
            'right',
            'justify',
        ];

        foreach ($alignments as $alignment) {
            $tests = [
                "[align={$alignment}]test[/align]" => "<div style=\"text-align: {$alignment};\" class=\"mycode_align\">test</div>",
                "[align={$alignment}]
test
[/align]" => "<div style=\"text-align: {$alignment};\" class=\"mycode_align\"><br />
test<br />
</div>",
            ];

            foreach ($tests as $input => $expected) {
                $actual = $this->parser->parse_message($input, [
                    'allow_mycode' => true,
                ]);

                $this->assertEquals($expected, $actual);
            }
        }
    }

    public function testSimpleSizeTextMyCodes()
    {
        $sizes = [
            'xx-small',
            'x-small',
            'small',
            'medium',
            'large',
            'x-large',
            'xx-large',
        ];

        foreach ($sizes as $size) {
            $tests = [
                "[size={$size}]test[/size]" => "<span style=\"font-size: {$size};\" class=\"mycode_size\">test</span>",
                "[size={$size}]
test
[/size]" => "<span style=\"font-size: {$size};\" class=\"mycode_size\"><br />
test<br />
</span>",
            ];

            foreach ($tests as $input => $expected) {
                $actual = $this->parser->parse_message($input, [
                    'allow_mycode' => true,
                ]);

                $this->assertEquals($expected, $actual);
            }
        }
    }

    public function testSimpleSizeIntegerMyCodes()
    {
        $sizes = [
            '-10' => '1',
            '-1' => '1',
            '0' => '1',
            '1' => '1',
            '10' => '10',
            '50' => '50',
            '100' => '50',
            '+1' => '1',
            '+10' => '10',
            '+50' => '50',
            '+100' => '50',
        ];

        foreach ($sizes as $size => $expected) {
            $tests = [
                "[size={$size}]test[/size]" => "<span style=\"font-size: {$expected}pt;\" class=\"mycode_size\">test</span>",
                "[size={$size}]
test
[/size]" => "<span style=\"font-size: {$expected}pt;\" class=\"mycode_size\"><br />
test<br />
</span>",
            ];

            foreach ($tests as $input => $expectedResult) {
                $actual = $this->parser->parse_message($input, [
                    'allow_mycode' => true,
                ]);

                $this->assertEquals($expectedResult, $actual);
            }
        }
    }

    public function testSimpleColourMyCodes()
    {
        $colours = [
            '#000000',
            '#fff',
            'red',
        ];

        foreach ($colours as $colour) {
            $tests = [
                "[color={$colour}]test[/color]" => "<span style=\"color: {$colour};\" class=\"mycode_color\">test</span>",
                "[color={$colour}]
test
[/color]" => "<span style=\"color: {$colour};\" class=\"mycode_color\"><br />
test<br />
</span>",
            ];

            foreach ($tests as $input => $expected) {
                $actual = $this->parser->parse_message($input, [
                    'allow_mycode' => true,
                ]);

                $this->assertEquals($expected, $actual);
            }
        }
    }

    public function testSimpleFontMyCodes()
    {
        $fonts = [
            'arial',
            'Segoe UI',
            'sans 10',
        ];

        foreach ($fonts as $font) {
            $tests = [
                "[font={$font}]test[/font]" => "<span style=\"font-family: {$font};\" class=\"mycode_font\">test</span>",
                "[font={$font}]
test
[/font]" => "<span style=\"font-family: {$font};\" class=\"mycode_font\"><br />
test<br />
</span>",
            ];

            foreach ($tests as $input => $expected) {
                $actual = $this->parser->parse_message($input, [
                    'allow_mycode' => true,
                ]);

                $this->assertEquals($expected, $actual);
            }
        }
    }
}