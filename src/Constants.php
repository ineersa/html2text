<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

final class Constants
{
    /** Marker used to flag tables for padding during post-processing. */
    public const TABLE_MARKER_FOR_PAD = 'special_marker_for_table_padding';

    /** Detects space-only lines preserved during wrapping. */
    public const RE_SPACE = '/\s+/';

    /** Identifies ordered list markers like "1. ". */
    public const RE_ORDERED_LIST_MATCHER = '/\d+\.\s/';

    /** Identifies unordered list markers (dash, star, plus). */
    public const RE_UNORDERED_LIST_MATCHER = '/[-\*\+]\s/';

    /** Captures Markdown characters that require escaping. */
    public const RE_MD_CHARS_MATCHER = '/([\\\[\]\(\)])/';

    /** Captures the full set of Markdown meta characters. */
    public const RE_MD_CHARS_MATCHER_ALL = '/([`\*_{}\[\]\(\)#!])/';

    /** Finds inline and reference-style Markdown links. */
    public const RE_LINK = '/(\[.*?\] ?\(.*?\))|(\[.*?\]:.*?)/';

    /** Locates pipe-separated table rows. */
    public const RE_TABLE = '/ \| /';

    /** Matches ordered list "N." prefixes that need escaping. */
    public const RE_MD_DOT_MATCHER = '/^(\s*\d+)(\.)(?=\s)/m';

    /** Matches plus-prefixed unordered list items. */
    public const RE_MD_PLUS_MATCHER = '/^(\s*)(\+)(?=\s)/m';

    /** Matches dash-prefixed unordered lists and horizontal rules. */
    public const RE_MD_DASH_MATCHER = '/^(\s*)(-)(?=\s|-)/m';

    /** Listing of characters requiring backslash escaping. */
    public const RE_SLASH_CHARS = '\\`*_{}[]()#+-.!';

    /** Detects Markdown escaping backslashes preceding meta characters. */
    public const RE_MD_BACKSLASH_MATCHER = '/(\\\\)(?=[\\`*_{}\[\]()#+\-.!])/';

    /** HTML entity replacements to normalise plain-text output. */
    public const UNIFIABLE = [
        'rsquo' => "'",
        'lsquo' => "'",
        'rdquo' => '"',
        'ldquo' => '"',
        'copy' => '(C)',
        'mdash' => '--',
        'nbsp' => ' ',
        'rarr' => '->',
        'larr' => '<-',
        'middot' => '*',
        'ndash' => '-',
        'oelig' => 'oe',
        'aelig' => 'ae',
        'agrave' => 'a',
        'aacute' => 'a',
        'acirc' => 'a',
        'atilde' => 'a',
        'auml' => 'a',
        'aring' => 'a',
        'egrave' => 'e',
        'eacute' => 'e',
        'ecirc' => 'e',
        'euml' => 'e',
        'igrave' => 'i',
        'iacute' => 'i',
        'icirc' => 'i',
        'iuml' => 'i',
        'ograve' => 'o',
        'oacute' => 'o',
        'ocirc' => 'o',
        'otilde' => 'o',
        'ouml' => 'o',
        'ugrave' => 'u',
        'uacute' => 'u',
        'ucirc' => 'u',
        'uuml' => 'u',
        'lrm' => '',
        'rlm' => '',
    ];

    /**
     * Control character substitutions defined by the WHATWG HTML parsing spec.
     *
     * @see https://html.spec.whatwg.org/multipage/parsing.html#character-reference-code
     */
    public const CONTROL_CHARACTER_REPLACEMENTS = [
        0x80 => 0x20AC, // EURO SIGN (€)
        0x82 => 0x201A, // SINGLE LOW-9 QUOTATION MARK (‚)
        0x83 => 0x0192, // LATIN SMALL LETTER F WITH HOOK (ƒ)
        0x84 => 0x201E, // DOUBLE LOW-9 QUOTATION MARK („)
        0x85 => 0x2026, // HORIZONTAL ELLIPSIS (…)
        0x86 => 0x2020, // DAGGER (†)
        0x87 => 0x2021, // DOUBLE DAGGER (‡)
        0x88 => 0x02C6, // MODIFIER LETTER CIRCUMFLEX ACCENT (ˆ)
        0x89 => 0x2030, // PER MILLE SIGN (‰)
        0x8A => 0x0160, // LATIN CAPITAL LETTER S WITH CARON (Š)
        0x8B => 0x2039, // SINGLE LEFT-POINTING ANGLE QUOTATION MARK (‹)
        0x8C => 0x0152, // LATIN CAPITAL LIGATURE OE (Œ)
        0x8E => 0x017D, // LATIN CAPITAL LETTER Z WITH CARON (Ž)
        0x91 => 0x2018, // LEFT SINGLE QUOTATION MARK (‘)
        0x92 => 0x2019, // RIGHT SINGLE QUOTATION MARK (’)
        0x93 => 0x201C, // LEFT DOUBLE QUOTATION MARK (“)
        0x94 => 0x201D, // RIGHT DOUBLE QUOTATION MARK (”)
        0x95 => 0x2022, // BULLET (•)
        0x96 => 0x2013, // EN DASH (–)
        0x97 => 0x2014, // EM DASH (—)
        0x98 => 0x02DC, // SMALL TILDE (˜)
        0x99 => 0x2122, // TRADE MARK SIGN (™)
        0x9A => 0x0161, // LATIN SMALL LETTER S WITH CARON (š)
        0x9B => 0x203A, // SINGLE RIGHT-POINTING ANGLE QUOTATION MARK (›)
        0x9C => 0x0153, // LATIN SMALL LIGATURE OE (œ)
        0x9E => 0x017E, // LATIN SMALL LETTER Z WITH CARON (ž)
        0x9F => 0x0178, // LATIN CAPITAL LETTER Y WITH DIAERESIS (Ÿ)
    ];
}
