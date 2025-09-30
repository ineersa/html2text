<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

final readonly class Config
{
    public function __construct(
        /** Use Unicode characters instead of ASCII fallbacks. */
        public bool $unicodeSnob = false,
        /** Escape all special characters even if output is less readable. */
        public bool $escapeSnob = false,
        /** Append footnote links immediately after each paragraph. */
        public bool $linksEachParagraph = false,
        /** Wrap long lines at the configured column (0 disables wrapping). */
        public int $bodyWidth = 78,
        /** Skip internal anchors like href="#local-anchor". */
        public bool $skipInternalLinks = true,
        /** Render links using inline Markdown syntax. */
        public bool $inlineLinks = true,
        /** Surround links with angle brackets to prevent wraps. */
        public bool $protectLinks = false,
        /** Allow links to wrap across lines. */
        public bool $wrapLinks = true,
        /** Wrap list items at the configured body width. */
        public bool $wrapListItems = false,
        /** Wrap table output text. */
        public bool $wrapTables = false,
        /** Is Google Doc */
        public bool $googleDoc = false,
        /** Callback to apply at tag processing, $callback($this, $tag, $attrs, $start), should return true to break processing, false otherwise */
        public ?\Closure $tagCallback = null,
        /** Pixels Google uses to indent nested lists. */
        public int $googleListIndent = 36,
        /**
         * Values that indicate bold text in inline styles.
         *
         * @var string[]
         */
        public array $boldTextStyleValues = ['bold', '700', '800', '900'],
        /** Ignore anchor tags entirely. */
        public bool $ignoreAnchors = false,
        /** Ignore mailto links during conversion. */
        public bool $ignoreMailtoLinks = false,
        /** Drop all image tags from the output. */
        public bool $ignoreImages = false,
        /** Keep image tags rendered as raw HTML. */
        public bool $imagesAsHtml = false,
        /** Replace images with their alt text. */
        public bool $imagesToAlt = false,
        /** Include width/height attributes when preserving images. */
        public bool $imagesWithSize = false,
        /** Ignore text emphasis such as italics and bold. */
        public bool $ignoreEmphasis = false,
        /** Wrap inline code with custom markers. */
        public bool $markCode = false,
        /** Use backquotes instead of indentation for code blocks. */
        public bool $backquoteCodeStyle = false,
        /** Fallback alt text when an image omits it. */
        public string $defaultImageAlt = '',
        /** Pad tables to align cell widths. */
        public bool $padTables = false,
        /** Convert absolute links with identical href/text to <href> style. */
        public bool $useAutomaticLinks = true,
        /** Render tables as HTML instead of Markdown. */
        public bool $bypassTables = false,
        /** Ignore table tags but retain row content. */
        public bool $ignoreTables = false,
        /** Emit a single line break after block elements (requires width 0). */
        public bool $singleLineBreak = false,
        /** Use as the opening quotation mark for <q> tags. */
        public string $openQuote = '"',
        /** Use as the closing quotation mark for <q> tags. */
        public string $closeQuote = '"',
        /** Include <sup> and <sub> tags in the output. */
        public bool $includeSupSub = false,
        /** baseUrl to join with URLs if needed */
        public string $baseUrl = '',
        /** Emphasis marks */
        public string $ulItemMark = '*',
        public string $emphasisMark = '_',
        public string $strongMark = '**',
        /** hide strikethrough emphasis */
        public bool $hideStrikethrough = false,
    ) {
    }
}
