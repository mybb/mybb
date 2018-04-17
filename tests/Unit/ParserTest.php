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
        $input = '[b]test[/b]';
        $expected = '<span style="font-weight: bold;" class="mycode_b">test</span>';

        $actual = $this->parser->parse_message($input, [
            'allow_mycode' => true,
        ]);

        $this->assertEquals($expected, $actual);
    }

    public function testParseUnderlineMyCode()
    {
        $input = '[u]test[/u]';
        $expected = '<span style="text-decoration: underline;" class="mycode_u">test</span>';

        $actual = $this->parser->parse_message($input, [
            'allow_mycode' => true,
        ]);

        $this->assertEquals($expected, $actual);
    }

    public function testSimpleParseItalicMyCode()
    {
        $input = '[i]test[/i]';
        $expected = '<span style="font-style: italic;" class="mycode_i">test</span>';

        $actual = $this->parser->parse_message($input, [
            'allow_mycode' => true,
        ]);

        $this->assertEquals($expected, $actual);
    }

    public function testSimpleParseStrikeThroughMyCode()
    {
        $input = '[s]test[/s]';
        $expected = '<span style="text-decoration: line-through;" class="mycode_s">test</span>';

        $actual = $this->parser->parse_message($input, [
            'allow_mycode' => true,
        ]);

        $this->assertEquals($expected, $actual);
    }
}