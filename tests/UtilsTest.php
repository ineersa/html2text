<?php

declare(strict_types=1);

namespace Tests;

use Ineersa\PhpHtml2text\Constants;
use Ineersa\PhpHtml2text\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UtilsTest extends TestCase
{
    public function testUnifiableNMatchesPython(): void
    {
        $map = Utils::unifiableN();

        $this->assertSame("'", $map[0x2019] ?? null);
        $this->assertArrayNotHasKey(0x00A0, $map);
    }

    public function testControlCharacterReplacementsPassthrough(): void
    {
        $this->assertSame(Constants::CONTROL_CHARACTER_REPLACEMENTS, Utils::controlCharacterReplacements());
    }

    #[DataProvider('provideHnSamples')]
    public function testHnMatchesPython(string $tag, int $expected): void
    {
        $this->assertSame($expected, Utils::hn($tag));
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
            Utils::dumbPropertyDict($style)
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
            Utils::dumbCssParser($css)
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
            Utils::elementStyle($attrs, $styleDef, $parentStyle)
        );
    }

    public function testGoogleListStyleMatchesPython(): void
    {
        $this->assertSame('ul', Utils::googleListStyle(['list-style-type' => 'disc']));
        $this->assertSame('ol', Utils::googleListStyle(['list-style-type' => 'decimal']));
    }

    public function testGoogleHasHeightMatchesPython(): void
    {
        $this->assertTrue(Utils::googleHasHeight(['height' => '10px']));
        $this->assertFalse(Utils::googleHasHeight(['width' => '10px']));
    }

    public function testGoogleTextEmphasisMatchesPython(): void
    {
        $this->assertSame(
            ['underline', 'italic', 'bold'],
            Utils::googleTextEmphasis([
                'text-decoration' => 'underline',
                'font-style' => 'italic',
                'font-weight' => 'bold',
            ])
        );
    }

    public function testGoogleFixedWidthFontMatchesPython(): void
    {
        $this->assertTrue(Utils::googleFixedWidthFont(['font-family' => 'courier new']));
        $this->assertFalse(Utils::googleFixedWidthFont(['font-family' => 'arial']));
    }

    public function testListNumberingStartMatchesPython(): void
    {
        $this->assertSame(2, Utils::listNumberingStart(['start' => '3']));
        $this->assertSame(0, Utils::listNumberingStart(['start' => 'a']));
    }

    #[DataProvider('provideSkipwrapSamples')]
    public function testSkipwrapMatchesPython(
        string $paragraph,
        bool $wrapLinks,
        bool $wrapListItems,
        bool $wrapTables,
        bool $expected,
    ): void {
        $this->assertSame($expected, Utils::skipwrap($paragraph, $wrapLinks, $wrapListItems, $wrapTables));
    }

    /**
     * @return list<array{0: string, 1: bool, 2: bool, 3: bool, 4: bool}>
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
        $this->assertSame('link \\[text\\]\\(url\\)', Utils::escapeMd('link [text](url)'));
    }

    public function testEscapeMdSectionMatchesPython(): void
    {
        $default = Utils::escapeMdSection("1. one\n+ plus\n- dash");
        $snob = Utils::escapeMdSection('Use (parentheses) and #hash!', true);

        $this->assertSame("1\\. one\n\\+ plus\n\\- dash", $default);
        $this->assertSame('Use \\(parentheses\\) and \\#hash\\!', $snob);
    }

    public function testReformatTableMatchesPython(): void
    {
        $lines = ['col1|col2', '----|-----', 'a|b'];

        $this->assertSame(
            ['| col1 |col2  |', '|------|------|', '| a    |b     |'],
            Utils::reformatTable($lines, 1)
        );
    }

    public function testPadTablesInTextMatchesPython(): void
    {
        $marker = Constants::TABLE_MARKER_FOR_PAD;
        $text = "above\n{$marker}\ncol1|col2\n----|-----\na|b\n{$marker}\nbelow";

        $this->assertSame(
            "above\n| col1 |col2  |\n|------|------|\n| a    |b     |\n\nbelow",
            Utils::padTablesInText($text, 1)
        );
    }
}
