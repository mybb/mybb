<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\HelpDocuments;

use Mockery;
use MyBB\Tests\Traits\LegacyCoreAwareTest;
use MyBB\Tests\Unit\TestCase;

final class SearchTest extends TestCase
{
    use LegacyCoreAwareTest;

    public function testSearchSources(): void
    {
        global $mybb, $db, $lang;

        $cases = [
            ['name', 'answer', 'Posting an answer', '99,100'],
            ['document', 'answer', str_repeat('Content ', 500) . "A user's answer.", '99,100'],
            ['name', 'user registration or cookies', 'user profile registration', '99'],
            ['name', 'user registration or cookies', 'User registration', '99,100'],
            ['name', 'alpha or beta and gamma', 'alpha', '99,100'],
            ['name', 'alpha or beta and gamma', 'beta', '99'],
            ['name', 'gamma and "alpha beta"', 'gamma alpha beta', '99,100'],
            ['document', 'posting   an answer', 'Posting an answer', '99,100'],
            ['document', 'first*second', "first\nsecond", '99,100'],
            ['document', "user's", "A user's guide", '99,100'],
        ];
        foreach ($cases as [$field, $keywords, $languageText, $expected]) {
            self::setupMybb();
            require_once MYBB_ROOT . 'inc/functions_search.php';
            $mybb->settings['minsearchword'] = 3;
            $key = 'd100_' . $field;
            $lang->$key = $languageText;
            $document = ['hid' => 100, 'usetranslation' => 1];

            $db->type = 'mysqli';
            $db->shouldReceive('escape_string_like')->andReturnUsing(static fn ($text) => str_replace("'", "''", $text));
            $conditions = '';
            $db->shouldReceive('simple_select')->with('helpdocs', 'hid', Mockery::capture($conditions))->once()->andReturn('originals');
            $db->shouldReceive('fetch_array')->with('originals')->andReturn(['hid' => 99], false);
            $db->shouldReceive('simple_select')->with('helpdocs', 'hid,usetranslation', "enabled='1' AND usetranslation='1'")->once()->andReturn('translations');
            $db->shouldReceive('fetch_array')->with('translations')->andReturn($document, false);

            $result = helpdocument_perform_search([
                'keywords' => $keywords,
                'name' => $field === 'name' ? 1 : 0,
                'document' => $field === 'document' ? 1 : 0,
            ]);

            self::assertSame(['querycache' => $expected], $result, $keywords);
            self::assertStringContainsString($field . ' LIKE', $conditions);
            self::assertStringNotContainsString('usetranslation', $conditions);
            self::assertStringNotContainsString('CASE', $conditions);
            self::assertLessThan(512, strlen($conditions));
        }
    }
}
