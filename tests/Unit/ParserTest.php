<?php

namespace MyBB\Tests\Unit;

use Generator;
use MyBB\Tests\Traits\LegacyCoreAwareTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

class ParserTest extends TestCase
{
    use LegacyCoreAwareTest;

    /**
     * @var \postParser $parser
     */
    private $parser;

    public static function setUpBeforeClass(): void
    {
        static::setupMybb();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['mybb']->settings = array_merge($GLOBALS['mybb']->settings, [
            'allowautourl' => '1',
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
                "[align={$alignment}]test[/align]" =>
                    "<div style=\"text-align: {$alignment};\" class=\"mycode_align\">test</div>",
                "[align={$alignment}]
test
[/align]" => "<div style=\"text-align: {$alignment};\" class=\"mycode_align\">
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
                "[size={$size}]test[/size]" =>
                    "<span style=\"font-size: {$expected}pt;\" class=\"mycode_size\">test</span>",
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
                "[color={$colour}]test[/color]" =>
                    "<span style=\"color: {$colour};\" class=\"mycode_color\">test</span>",
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
                "[font={$font}]test[/font]" =>
                    "<span style=\"font-family: {$font};\" class=\"mycode_font\">test</span>",
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

    public function testSimpleImgMyCodesWithImagesAllowed()
    {
        $images = [
            'https://example.com/some_image.jpg' => 'some_image.jpg',
            'http://example.com/some_image.png' => 'some_image.png',
        ];

        foreach ($images as $imageUrl => $imageName) {
            $tests = [
                "[img]{$imageUrl}[/img]" =>
                    "<img src=\"{$imageUrl}\" loading=\"lazy\" alt=\"[Image: {$imageName}]\" class=\"mycode_img\" />\n",
                "[img=50x50]{$imageUrl}[/img]" =>
                    "<img src=\"{$imageUrl}\" loading=\"lazy\" width=\"50\" height=\"50\"" .
                    " alt=\"[Image: {$imageName}]\" class=\"mycode_img\" />\n",
                "[img align=left]{$imageUrl}[/img]" =>
                    "<img src=\"{$imageUrl}\" loading=\"lazy\" alt=\"[Image: {$imageName}]\" style=\"float: left;\" " .
                    "class=\"mycode_img\" />\n<br class=\"clear\" />",
                "[img align=right]{$imageUrl}[/img]" =>
                    "<img src=\"{$imageUrl}\" loading=\"lazy\" alt=\"[Image: {$imageName}]\" style=\"float: right;\" " .
                    "class=\"mycode_img\" />\n<br class=\"clear\" />",
                "[img=50x50 align=left]{$imageUrl}[/img]" =>
                    "<img src=\"{$imageUrl}\" loading=\"lazy\" width=\"50\" height=\"50\" alt=\"[Image: {$imageName}]\" " .
                    "style=\"float: left;\" class=\"mycode_img\" />\n<br class=\"clear\" />",
            ];

            foreach ($tests as $input => $expected) {
                $actual = $this->parser->parse_message($input, [
                    'allow_mycode' => true,
                    'allow_imgcode' => true,
                    'nl2br' => false,
                ]);

                $this->assertEquals($expected, $actual);
            }
        }
    }

    public function testSimpleImgMyCodesWithImagesNotAllowed()
    {
        $images = [
            'https://example.com/some_image.jpg' => 'some_image.jpg',
            'http://example.com/some_image.png' => 'some_image.png',
        ];

        foreach ($images as $imageUrl => $imageName) {
            $tests = [
                "[img]{$imageUrl}[/img]" =>
                    "[Image: <a href=\"{$imageUrl}\" target=\"_blank\" rel=\"noopener\" class=\"mycode_url\">{$imageUrl}</a>]",
                "[img=50x50]{$imageUrl}[/img]" =>
                    "[Image: <a href=\"{$imageUrl}\" target=\"_blank\" rel=\"noopener\" class=\"mycode_url\">{$imageUrl}</a>]",
                "[img align=left]{$imageUrl}[/img]" =>
                    "[Image: <a href=\"{$imageUrl}\" target=\"_blank\" rel=\"noopener\" class=\"mycode_url\">{$imageUrl}</a>]",
                "[img align=right]{$imageUrl}[/img]" =>
                    "[Image: <a href=\"{$imageUrl}\" target=\"_blank\" rel=\"noopener\" class=\"mycode_url\">{$imageUrl}</a>]",
                "[img=50x50 align=left]{$imageUrl}[/img]" =>
                    "[Image: <a href=\"{$imageUrl}\" target=\"_blank\" rel=\"noopener\" class=\"mycode_url\">{$imageUrl}</a>]",
            ];

            foreach ($tests as $input => $expected) {
                $actual = $this->parser->parse_message($input, [
                    'allow_mycode' => true,
                    'allow_imgcode' => false,
                ]);

                $this->assertEquals($expected, $actual);
            }
        }
    }


    /**
     * Generic combinations of URL components.
     *
     * @return Generator<string[]>
     *
     * @note Test concrete cases in specialized methods for performance.
     */
    public static function validUrlCases(): Generator
    {
        $urlComponents = [
            'prefix' => [
                // scheme
                'http://',

                // common subdomain
                'www.',
            ],
            'host' => [
                // IPv6
                '[::]',
                '[::1]',
                '[2001:db8::ff%eth0]',

                // IPv4
                '192.0.2.0',

                // domain name
                'example.co.uk',
                'localhost',

                // punycode domain name
                'xn--kgbechtv',
                'xn--d1acpjx3f.xn--p1ai',
            ],
            'port' => [
                '',
                ':8080',
            ],
            'path' => [
                '',
                '/',
                '/.well-known/index.php',
            ],
            'query' => [
                '',
                '?key&',
                '?array[]=1&array[]=2',
            ],
            'fragment' => [
                '',
                '#fragment',
                '#:~:text=a-,b,c,-d',
            ],
        ];

        foreach ($urlComponents['prefix'] as $prefix) {
            foreach ($urlComponents['host'] as $host) {
                if (
                    $prefix === 'www.' &&
                    ($host[0] === '[' || is_numeric($host[0]))
                ) {
                    continue;
                }

                foreach ($urlComponents['port'] as $port) {
                    foreach ($urlComponents['path'] as $path) {
                        foreach ($urlComponents['query'] as $query) {
                            foreach ($urlComponents['fragment'] as $fragment) {
                                yield [$prefix . $host . $port . $path . $query . $fragment];
                            }
                        }
                    }
                }
            }
        }
    }

    #[DataProvider('validUrlCases')]
    public function testAutoUrlConsumesAllCharacters(string $message): void
    {
        $actual = $this->parser->parse_message($message, [
            'allow_mycode' => true,
            'allowautourl' => true,
        ]);

        $this->assertMatchesRegularExpression(
            '/^<a href="[^"]+"[ \w="]*>[^"]+<\/a>$/',
            $actual,
            'No expected HTML found for link: ' . $message,
        );
    }


    #[TestWith([ 'visit ', 'http://example.com', ' for more' ])]
    #[TestWith([ "\t", 'http://example.com', "\t" ])]
    #[TestWith([ "\n", 'http://example.com', "\n" ])]

    #[TestWith([ '', 'http://example.com', '.' ])]
    #[TestWith([ '', 'http://example.com', ',' ])]
    #[TestWith([ '', 'http://example.com', ':' ])]
    #[TestWith([ '', 'http://example.com', '?' ])]
    #[TestWith([ '', 'http://example.com', '!' ])]
    #[TestWith([ '', 'http://example.com', ['&' => '&amp;'] ])]
    #[TestWith([ '', 'http://example.com', ['<' => '&lt;'] ])]
    #[TestWith([ '', 'http://example.com', '&', true ])]

    #[TestWith([ "(", 'http://example.com', ')' ])]
    #[TestWith([ '[', 'http://example.com', ']' ])]

    #[TestWith([ '', 'http://en.wikipedia.org/wiki/PHP_(disambiguation)', '' ])]
    #[TestWith([ '(', 'http://en.wikipedia.org/wiki/PHP_(disambiguation)', ')' ])]
    #[TestWith([ '(e.g. ', 'http://en.wikipedia.org/wiki/PHP_(disambiguation)', '?)' ])]


    #[TestWith([ ['[list][*]' => '<ul class="mycode_list"><li>'], 'http://example.com', ['[/list]' => "</li>\n</ul>\n"] ])]
    public function testAutoUrlWithSurroundingCharacters(
        string|array $prefix,
        string $url,
        string|array $suffix,
        bool $allowHtml = false,
    ): void
    {
        $inputPrefix = is_array($prefix) ? array_key_first($prefix) : $prefix;
        $inputSuffix = is_array($suffix) ? array_key_first($suffix) : $suffix;

        $message = $inputPrefix . $url . $inputSuffix;

        $actual = $this->parser->parse_message($message, [
            'allow_mycode' => true,
            'allow_html' => $allowHtml,
            'allowautourl' => true,
            'nl2br' => false,
        ]);


        $outputPrefix = is_array($prefix) ? current($prefix) : $prefix;
        $outputSuffix = is_array($suffix) ? current($suffix) : $suffix;

        $this->assertMatchesRegularExpression(
            '/^' .
            preg_quote($outputPrefix, '/') .
            '<a href="[^"]+"[ \w="]*>[^"]+<\/a>' .
            preg_quote($outputSuffix, '/') .
            '$/',
            $actual,
            'No expected HTML found for link: ' . $message,
        );
    }

    #[TestWith([ '"http://example.com"' ])]
    #[TestWith([ ' "http://example.com" ' ])]
    #[TestWith([ '"www.example.com"' ])]
    #[TestWith([ 'contact@example.com' ])]

    #[TestWith([ 'http://example.com >', true ])]
    #[TestWith([ '"http://example.com"', true ])]
    #[TestWith([ ' "http://example.com" ', true ])]
    public function testAutoUrlIgnoresString(string $message, bool $allowHtml = false): void
    {
        $actual = $this->parser->parse_message($message, [
            'allow_mycode' => true,
            'allow_html' => $allowHtml,
            'allowautourl' => true,
        ]);

        $this->assertSame($message, $actual);
    }
}
