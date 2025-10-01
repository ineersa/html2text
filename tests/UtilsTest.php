<?php

declare(strict_types=1);

namespace Tests;

use Ineersa\Html2text\Constants;
use Ineersa\Html2text\Utilities\ParserUtilities;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
    public function testUnifiableNMatchesPython(): void
    {
        $map = ParserUtilities::unifiableN();

        $this->assertSame("'", $map[0x2019] ?? null);
        $this->assertArrayNotHasKey(0x00A0, $map);
    }

    public function testControlCharacterReplacementsPassthrough(): void
    {
        $this->assertSame(Constants::CONTROL_CHARACTER_REPLACEMENTS, ParserUtilities::controlCharacterReplacements());
    }

    #[DataProvider('provideHnSamples')]
    public function testHnMatchesPython(string $tag, int $expected): void
    {
        $this->assertSame($expected, ParserUtilities::hn($tag));
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    public static function provideHnSamples(): array
    {
        return [
            ['h1', 1],
            ['h9', 9],
            ['h0', 0],
            ['h10', 0],
            ['div', 0],
        ];
    }

    public function testDumbPropertyDictMatchesPython(): void
    {
        $style = 'color: Red ; font-weight : Bold; missing; line-height: 1.5;';

        $this->assertSame(
            [
                'color' => 'red',
                'font-weight' => 'bold',
                'line-height' => '1.5',
            ],
            ParserUtilities::dumbPropertyDict($style)
        );
    }

    public function testDumbCssParserMatchesPython(): void
    {
        $css = 'p { color: blue; } @import url("foo"); .highlight { font-weight: bold; }';

        $this->assertSame(
            [
                'p' => ['color' => 'blue'],
                '.highlight' => ['font-weight' => 'bold'],
            ],
            ParserUtilities::dumbCssParser($css)
        );
    }

    public function testElementStyleMatchesPython(): void
    {
        $attrs = ['class' => 'highlight special', 'style' => 'line-height: 1.5;'];
        $styleDef = [
            '.highlight' => ['font-weight' => 'bold'],
            '.special' => ['color' => 'red'],
        ];
        $parentStyle = ['font-size' => '12px'];

        $this->assertSame(
            [
                'font-size' => '12px',
                'font-weight' => 'bold',
                'color' => 'red',
                'line-height' => '1.5',
            ],
            ParserUtilities::elementStyle($attrs, $styleDef, $parentStyle)
        );
    }

    public function testGoogleListStyleMatchesPython(): void
    {
        $this->assertSame('ul', ParserUtilities::googleListStyle(['list-style-type' => 'disc']));
        $this->assertSame('ol', ParserUtilities::googleListStyle(['list-style-type' => 'decimal']));
    }

    public function testGoogleHasHeightMatchesPython(): void
    {
        $this->assertTrue(ParserUtilities::googleHasHeight(['height' => '10px']));
        $this->assertFalse(ParserUtilities::googleHasHeight(['width' => '10px']));
    }

    public function testGoogleTextEmphasisMatchesPython(): void
    {
        $this->assertSame(
            ['underline', 'italic', 'bold'],
            ParserUtilities::googleTextEmphasis([
                'text-decoration' => 'underline',
                'font-style' => 'italic',
                'font-weight' => 'bold',
            ])
        );
    }

    public function testGoogleFixedWidthFontMatchesPython(): void
    {
        $this->assertTrue(ParserUtilities::googleFixedWidthFont(['font-family' => 'courier new']));
        $this->assertFalse(ParserUtilities::googleFixedWidthFont(['font-family' => 'arial']));
    }

    public function testListNumberingStartMatchesPython(): void
    {
        $this->assertSame(2, ParserUtilities::listNumberingStart(['start' => '3']));
        $this->assertSame(0, ParserUtilities::listNumberingStart(['start' => 'a']));
    }

    #[DataProvider('provideSkipwrapSamples')]
    public function testSkipwrapMatchesPython(
        string $paragraph,
        bool $wrapLinks,
        bool $wrapListItems,
        bool $wrapTables,
        bool $expected,
    ): void {
        $this->assertSame($expected, ParserUtilities::skipwrap($paragraph, $wrapLinks, $wrapListItems, $wrapTables));
    }

    /**
     * @return array<string, array{string, bool, bool, bool, bool}>
     */
    public static function provideSkipwrapSamples(): array
    {
        return [
            'link detection' => ['Check this [link](http://example.com)', false, false, false, true],
            'code block' => ['    code block', true, true, true, true],
            'emdash wrap' => [' --dash', true, true, true, false],
            'list marker suppression' => ['- list item', true, false, true, true],
            'table detection' => ['A | B', true, true, false, true],
            'ordered list' => ['1. list', true, true, true, true],
            'plain paragraph' => ['Regular paragraph', true, true, true, false],
        ];
    }

    public function testEscapeMdMatchesPython(): void
    {
        $this->assertSame('link \\[text\\]\\(url\\)', ParserUtilities::escapeMd('link [text](url)'));
    }

    public function testEscapeMdSectionMatchesPython(): void
    {
        $default = ParserUtilities::escapeMdSection("1. one\n+ plus\n- dash");
        $snob = ParserUtilities::escapeMdSection('Use (parentheses) and #hash!', true);

        $this->assertSame("1\\. one\n\\+ plus\n\\- dash", $default);
        $this->assertSame('Use \\(parentheses\\) and \\#hash\\!', $snob);
    }

    public function testReformatTableMatchesPython(): void
    {
        $lines = ['col1|col2', '----|-----', 'a|b'];

        $this->assertSame(
            ['| col1 |col2  |', '|------|------|', '| a    |b     |'],
            ParserUtilities::reformatTable($lines, 1)
        );
    }

    public function testPadTablesInTextMatchesPython(): void
    {
        $marker = Constants::TABLE_MARKER_FOR_PAD;
        $text = "above\n{$marker}\ncol1|col2\n----|-----\na|b\n{$marker}\nbelow";

        $this->assertSame(
            "above\n| col1 |col2  |\n|------|------|\n| a    |b     |\n\nbelow",
            ParserUtilities::padTablesInText($text, 1)
        );
    }
}
