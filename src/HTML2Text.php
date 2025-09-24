<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

use Ineersa\PhpHtml2text\Elements\AnchorElement;
use Ineersa\PhpHtml2text\Elements\ListElement;
use function array_fill;
use function array_key_exists;
use function array_key_last;
use function array_pop;
use function array_replace;
use function explode;
use function hexdec;
use function implode;
use function in_array;
use function strpos;
use function parse_url;
use function strrpos;
use function ltrim;
use function mb_chr;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function round;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function str_ends_with;
use function strcspn;
use function strlen;
use function substr;
use function trim;
use function wordwrap;

/**
 * html2text: Turn HTML into equivalent Markdown-structured text.
 */
final class HTML2Text
{
    public bool $splitNextTd = false;
    public int $tdCount = 0;
    public bool $tableStart = false;
    public bool $unicodeSnob;
    public bool $escapeSnob;
    public bool $linksEachParagraph;
    public int $bodyWidth;
    public bool $skipInternalLinks;
    public bool $inlineLinks;
    public bool $protectLinks;
    public int $googleListIndent;
    public array $boldTextStyleValues;
    public bool $ignoreLinks;
    public bool $ignoreMailtoLinks;
    public bool $ignoreImages;
    public bool $imagesAsHtml;
    public bool $imagesToAlt;
    public bool $imagesWithSize;
    public bool $ignoreEmphasis;
    public bool $bypassTables;
    public bool $ignoreTables;
    public bool $googleDoc = false;
    public string $ulItemMark = '*';
    public string $emphasisMark = '_';
    public string $strongMark = '**';
    public bool $singleLineBreak;
    public bool $useAutomaticLinks;
    public bool $hideStrikethrough = false;
    public bool $markCode;
    public bool $backquoteCodeStyle;
    public bool $wrapListItems;
    public bool $wrapLinks;
    public bool $wrapTables;
    public bool $padTables;
    public string $defaultImageAlt;
    /** @var callable|null */
    public $tagCallback = null;
    public string $openQuote;
    public string $closeQuote;
    public bool $includeSupSub;
    /** @var callable */
    public $out;
    /** @var list<string> */
    public array $outtextlist = [];
    public int $quiet = 0;
    public int $p_p = 0;
    public int $outcount = 0;
    public bool $start = true;
    public bool $space = false;
    /** @var list<AnchorElement> */
    public array $a = [];
    /** @var list<array<string, string|null>|null> */
    public array $astack = [];
    public ?string $maybeAutomaticLink = null;
    public bool $emptyLink = false;
    private string $absoluteUrlMatcher;
    public int $acount = 0;
    /** @var list<ListElement> */
    public array $list = [];
    public int $blockquote = 0;
    public bool $pre = false;
    public bool $startpre = false;
    public string $preIndent = '';
    public string $listCodeIndent = '';
    public bool $code = false;
    public bool $quote = false;
    public string $brToggle = '';
    public bool $lastWasNL = false;
    public bool $lastWasList = false;
    public int $style = 0;
    /** @var array<string, array<string, string>> */
    public array $styleDef = [];
    /** @var list<array{0:string|null,1:array<string, string|null>,2:array<string, string>}> */
    public array $tagStack = [];
    public int $emphasis = 0;
    public int $dropWhiteSpace = 0;
    public bool $inheader = false;
    public ?string $abbrTitle = null;
    public ?string $abbrData = null;
    /** @var array<string, string> */
    public array $abbrList = [];
    public string $baseurl;
    public bool $stressed = false;
    public bool $precedingStressed = false;
    public string $precedingData = '';
    public string $currentTag = '';
    public ?string $fn = null;

    private Config $config;
    /** @var array<string, string> */
    private array $configUnifiable;
    private string $buffer = '';
    /**
     * Input parameters:
     *     out: possible custom replacement for self.outtextf (which
     *          appends lines of text).
     *     baseurl: base URL of the document we process
     */
    public function __construct(
        ?callable $out = null,
        string $baseurl = '',
        ?int $bodywidth = null,
        ?Config $config = null,
    ) {
        $this->config = $config ?? new Config();

        $this->unicodeSnob = $this->config->unicodeSnob;
        $this->escapeSnob = $this->config->escapeSnob;
        $this->linksEachParagraph = $this->config->linksEachParagraph;
        $this->bodyWidth = $bodywidth ?? $this->config->bodyWidth;
        $this->skipInternalLinks = $this->config->skipInternalLinks;
        $this->inlineLinks = $this->config->inlineLinks;
        $this->protectLinks = $this->config->protectLinks;
        $this->wrapLinks = $this->config->wrapLinks;
        $this->wrapListItems = $this->config->wrapListItems;
        $this->wrapTables = $this->config->wrapTables;
        $this->googleListIndent = $this->config->googleListIndent;
        $this->boldTextStyleValues = $this->config->boldTextStyleValues;
        $this->ignoreLinks = $this->config->ignoreAnchors;
        $this->ignoreMailtoLinks = $this->config->ignoreMailtoLinks;
        $this->ignoreImages = $this->config->ignoreImages;
        $this->imagesAsHtml = $this->config->imagesAsHtml;
        $this->imagesToAlt = $this->config->imagesToAlt;
        $this->imagesWithSize = $this->config->imagesWithSize;
        $this->ignoreEmphasis = $this->config->ignoreEmphasis;
        $this->markCode = $this->config->markCode;
        $this->backquoteCodeStyle = $this->config->backquoteCodeStyle;
        $this->defaultImageAlt = $this->config->defaultImageAlt;
        $this->padTables = $this->config->padTables;
        $this->useAutomaticLinks = $this->config->useAutomaticLinks;
        $this->bypassTables = $this->config->bypassTables;
        $this->ignoreTables = $this->config->ignoreTables;
        $this->singleLineBreak = $this->config->singleLineBreak;
        $this->openQuote = $this->config->openQuote;
        $this->closeQuote = $this->config->closeQuote;
        $this->includeSupSub = $this->config->includeSupSub;

        if (null === $out) {
            $this->out = [$this, 'outtextf'];
        } else {
            $this->out = $out;
        }

        $this->absoluteUrlMatcher = '/^[a-zA-Z+]+:\/\//';

        $this->baseurl = $baseurl;

        $this->configUnifiable = Constants::UNIFIABLE;
        $this->configUnifiable['nbsp'] = '&nbsp_place_holder;';
    }
    public function feed(string $data): void
    {
        $data = str_replace("</' + 'script>", '</ignore>', $data);
        $this->buffer .= $data;
        if ('' === $data) {
            $this->parseBuffer();
        }
    }
    public function handle(string $data): string
    {
        $this->start = true;
        $this->feed($data);
        $this->feed('');
        $markdown = $this->optwrap($this->finish());
        if ($this->padTables) {
            return Utils::padTablesInText($markdown);
        }

        return $markdown;
    }
    public function outtextf(string $s): void
    {
        $this->outtextlist[] = $s;
        if ('' !== $s) {
            $this->lastWasNL = substr($s, -1) === "\n";
        }
    }
    public function finish(): string
    {
        $this->pbr();
        $this->o('', false, 'end');

        $outtext = implode('', $this->outtextlist);

        if ($this->unicodeSnob) {
            $nbsp = html_entity_decode('&nbsp;', \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        } else {
            $nbsp = ' ';
        }
        $outtext = str_replace('&nbsp_place_holder;', $nbsp, $outtext);

        $this->outtextlist = [];

        return $outtext;
    }
    private function parseBuffer(): void
    {
        $html = $this->buffer;
        $this->buffer = '';
        if ('' === $html) {
            return;
        }

        $length = strlen($html);
        $pos = 0;
        while ($pos < $length) {
            $ltPos = strpos($html, '<', $pos);
            if (false === $ltPos) {
                $this->emitText(substr($html, $pos));
                break;
            }

            if ($ltPos > $pos) {
                $this->emitText(substr($html, $pos, $ltPos - $pos));
            }

            $pos = $ltPos;
            [$tagText, $newPos] = $this->readTag($html, $pos);
            if (null === $tagText) {
                $this->emitText(substr($html, $pos));
                break;
            }

            $pos = $newPos;
            if (str_starts_with($tagText, '<!--')) {
                continue;
            }
            if (str_starts_with($tagText, '<![CDATA[')) {
                $cdata = substr($tagText, 9, -3);
                if ('' !== $cdata) {
                    $this->handle_data($cdata);
                }
                continue;
            }

            $inner = substr($tagText, 1, -1);
            if ('' === $inner) {
                continue;
            }
            $innerTrimmed = trim($inner);
            if ('' === $innerTrimmed) {
                continue;
            }

            $firstChar = $innerTrimmed[0];
            if ('!' === $firstChar || '?' === $firstChar) {
                continue;
            }

            if ('/' === $firstChar) {
                $tagName = strtolower(trim(substr($innerTrimmed, 1)));
                if ('' !== $tagName) {
                    $this->handle_endtag($tagName);
                }
                continue;
            }

            $selfClosing = false;
            if (str_ends_with($innerTrimmed, '/')) {
                $selfClosing = true;
                $innerTrimmed = rtrim(substr($innerTrimmed, 0, -1));
            }

            $spacePos = strcspn($innerTrimmed, "\t\n\r\f \v");
            if ($spacePos >= strlen($innerTrimmed)) {
                $tagName = strtolower($innerTrimmed);
                $attrText = '';
            } else {
                $tagName = strtolower(substr($innerTrimmed, 0, $spacePos));
                $attrText = substr($innerTrimmed, $spacePos);
            }

            if ('' === $tagName) {
                continue;
            }

            $attrs = $this->parseAttributes($attrText);
            $this->handle_starttag($tagName, $attrs);
            if ($selfClosing || $this->isVoidElement($tagName)) {
                $this->handle_endtag($tagName);
            }
        }
    }

    /**
     * @return array{0:?string,1:int}
     */
    private function readTag(string $html, int $start): array
    {
        $length = strlen($html);
        $i = $start + 1;
        $quote = null;
        while ($i < $length) {
            $char = $html[$i];
            if (null !== $quote) {
                if ($char === $quote) {
                    $quote = null;
                } elseif ('\\' === $char && '"' === $quote && $i + 1 < $length) {
                    ++$i;
                }
            } else {
                if ('"' === $char || "'" === $char) {
                    $quote = $char;
                } elseif ('>' === $char) {
                    return [substr($html, $start, $i - $start + 1), $i + 1];
                }
            }
            ++$i;
        }

        return [null, $length];
    }

    /**
     * @return list<array{0:string,1:string|null}>
     */
    private function parseAttributes(string $attrText): array
    {
        $attrs = [];
        $length = strlen($attrText);
        $i = 0;
        while ($i < $length) {
            while ($i < $length && ctype_space($attrText[$i])) {
                ++$i;
            }
            if ($i >= $length) {
                break;
            }

            $nameStart = $i;
            while ($i < $length && preg_match('/[A-Za-z0-9_:-]/', $attrText[$i])) {
                ++$i;
            }

            if ($nameStart === $i) {
                ++$i;
                continue;
            }

            $name = strtolower(substr($attrText, $nameStart, $i - $nameStart));
            while ($i < $length && ctype_space($attrText[$i])) {
                ++$i;
            }

            $value = null;
            if ($i < $length && '=' === $attrText[$i]) {
                ++$i;
                while ($i < $length && ctype_space($attrText[$i])) {
                    ++$i;
                }
                if ($i < $length && ('"' === $attrText[$i] || "'" === $attrText[$i])) {
                    $quote = $attrText[$i];
                    ++$i;
                    $valueStart = $i;
                    while ($i < $length && $attrText[$i] !== $quote) {
                        if ('\\' === $attrText[$i] && $i + 1 < $length) {
                            $i += 2;
                            continue;
                        }
                        ++$i;
                    }
                    $value = substr($attrText, $valueStart, $i - $valueStart);
                    if ($i < $length && $attrText[$i] === $quote) {
                        ++$i;
                    }
                } else {
                    $valueStart = $i;
                    while ($i < $length && !ctype_space($attrText[$i]) && '>' !== $attrText[$i]) {
                        ++$i;
                    }
                    $value = substr($attrText, $valueStart, $i - $valueStart);
                }
            }

            $attrs[] = [$name, $value];
        }

        return $attrs;
    }

    private function isVoidElement(string $tag): bool
    {
        return in_array($tag, [
            'area',
            'base',
            'br',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'link',
            'meta',
            'param',
            'source',
            'track',
            'wbr',
        ], true);
    }

    private function emitText(string $text): void
    {
        if ('' === $text) {
            return;
        }

        $length = strlen($text);
        $pos = 0;
        while ($pos < $length) {
            $amp = strpos($text, '&', $pos);
            if (false === $amp) {
                $chunk = substr($text, $pos);
                if ('' !== $chunk) {
                    $this->handle_data($chunk);
                }
                break;
            }

            if ($amp > $pos) {
                $chunk = substr($text, $pos, $amp - $pos);
                if ('' !== $chunk) {
                    $this->handle_data($chunk);
                }
            }

            $semi = strpos($text, ';', $amp);
            if (false === $semi) {
                $this->handle_data('&');
                $pos = $amp + 1;
                continue;
            }

            $entity = substr($text, $amp + 1, $semi - $amp - 1);
            if ('' === $entity) {
                $this->handle_data('&');
                $pos = $amp + 1;
                continue;
            }

            if ($entity[0] === '#') {
                $this->handle_charref(substr($entity, 1));
            } else {
                $this->handle_entityref($entity);
            }

            $pos = $semi + 1;
        }
    }
    public function handle_charref(string $c): void
    {
        $this->handle_data($this->charref($c), true);
    }

    public function handle_entityref(string $c): void
    {
        $ref = $this->entityref($c);

        // ref may be an empty string (e.g. for &lrm;/&rlm; markers that should
        // not contribute to the final output).
        // self.handle_data cannot handle a zero-length string right after a
        // stressed tag or mid-text within a stressed tag (text get split and
        // self.stressed/self.preceding_stressed gets switched after the first
        // part of that text).
        if ('' !== $ref) {
            $this->handle_data($ref, true);
        }
    }

    /**
     * @param list<array{0:string,1:string|null}> $attrs
     */
    public function handle_starttag(string $tag, array $attrs): void
    {
        $assoc = [];
        foreach ($attrs as $attr) {
            [$name, $value] = $attr;
            $assoc[$name] = $value;
        }

        $this->handle_tag($tag, $assoc, true);
    }

    public function handle_endtag(string $tag): void
    {
        $this->handle_tag($tag, [], false);
    }
    public function previousIndex(array $attrs): ?int
    {
        /*
        :type attrs: dict

        :returns: The index of certain set of attributes (of a link) in the
        self.a list. If the set of attributes is not found, returns None
        :rtype: int
        */
        if (!array_key_exists('href', $attrs) || null === $attrs['href']) {
            return null;
        }

        foreach ($this->a as $i => $a) {
            if (array_key_exists('href', $a->attrs) && $a->attrs['href'] === $attrs['href']) {
                if (array_key_exists('title', $a->attrs) || array_key_exists('title', $attrs)) {
                    if (
                        array_key_exists('title', $a->attrs)
                        && array_key_exists('title', $attrs)
                        && $a->attrs['title'] === $attrs['title']
                    ) {
                        return $i;
                    }
                } else {
                    return $i;
                }
            }
        }

        return null;
    }
    public function handle_emphasis(bool $start, array $tagStyle, array $parentStyle): void
    {
        /*
        Handles various text emphases
        */
        $tagEmphasis = Utils::googleTextEmphasis($tagStyle);
        $parentEmphasis = Utils::googleTextEmphasis($parentStyle);

        // handle Google's text emphasis
        $strikethrough = in_array('line-through', $tagEmphasis, true) && $this->hideStrikethrough;

        // google and others may mark a font's weight as `bold` or `700`
        $bold = false;
        foreach ($this->boldTextStyleValues as $boldMarker) {
            $bold = in_array($boldMarker, $tagEmphasis, true) && !in_array($boldMarker, $parentEmphasis, true);
            if ($bold) {
                break;
            }
        }

        $italic = in_array('italic', $tagEmphasis, true) && !in_array('italic', $parentEmphasis, true);
        $fixed = (
            Utils::googleFixedWidthFont($tagStyle)
            && !Utils::googleFixedWidthFont($parentStyle)
            && !$this->pre
        );

        if ($start) {
            // crossed-out text must be handled before other attributes
            // in order not to output qualifiers unnecessarily
            if ($bold || $italic || $fixed) {
                ++$this->emphasis;
            }
            if ($strikethrough) {
                ++$this->quiet;
            }
            if ($italic) {
                $this->o($this->emphasisMark);
                ++$this->dropWhiteSpace;
            }
            if ($bold) {
                $this->o($this->strongMark);
                ++$this->dropWhiteSpace;
            }
            if ($fixed) {
                $this->o('`');
                ++$this->dropWhiteSpace;
                $this->code = true;
            }
        } else {
            if ($bold || $italic || $fixed) {
                // there must not be whitespace before closing emphasis mark
                --$this->emphasis;
                $this->space = false;
            }
            if ($fixed) {
                if ($this->dropWhiteSpace) {
                    // empty emphasis, drop it
                    --$this->dropWhiteSpace;
                } else {
                    $this->o('`');
                }
                $this->code = false;
            }
            if ($bold) {
                if ($this->dropWhiteSpace) {
                    // empty emphasis, drop it
                    --$this->dropWhiteSpace;
                } else {
                    $this->o($this->strongMark);
                }
            }
            if ($italic) {
                if ($this->dropWhiteSpace) {
                    // empty emphasis, drop it
                    --$this->dropWhiteSpace;
                } else {
                    $this->o($this->emphasisMark);
                }
            }
            // space is only allowed after *all* emphasis marks
            if (($bold || $italic) && !$this->emphasis) {
                $this->o(' ');
            }
            if ($strikethrough) {
                --$this->quiet;
            }
        }
    }
    public function handle_tag(string $tag, array $attrs, bool $start): void
    {
        $this->currentTag = $tag;

        if (null !== $this->tagCallback) {
            $callback = $this->tagCallback;
            if (true === $callback($this, $tag, $attrs, $start)) {
                return;
            }
        }

        // first thing inside the anchor tag is another tag
        // that produces some output
        if (
            $start
            && null !== $this->maybeAutomaticLink
            && !in_array($tag, ['p', 'div', 'style', 'dl', 'dt'], true)
            && ('img' !== $tag || $this->ignoreImages)
        ) {
            $this->o('[');
            $this->maybeAutomaticLink = null;
            $this->emptyLink = false;
        }

        $tagStyle = [];
        $parentStyle = [];
        if ($this->googleDoc) {
            // the attrs parameter is empty for a closing tag. in addition, we
            // need the attributes of the parent nodes in order to get a
            // complete style description for the current element. we assume
            // that google docs export well formed html.
            if ($start) {
                if ($this->tagStack) {
                    $last = $this->tagStack[array_key_last($this->tagStack)];
                    $parentStyle = $last[2];
                }
                $tagStyle = Utils::elementStyle($attrs, $this->styleDef, $parentStyle);
                $this->tagStack[] = [$tag, $attrs, $tagStyle];
            } else {
                if ($this->tagStack) {
                    $stackEntry = array_pop($this->tagStack);
                    $attrs = $stackEntry[1];
                    $tagStyle = $stackEntry[2];
                } else {
                    $attrs = [];
                    $tagStyle = [];
                }
                if ($this->tagStack) {
                    $last = $this->tagStack[array_key_last($this->tagStack)];
                    $parentStyle = $last[2];
                }
            }
        }
        $headerLevel = Utils::hn($tag);
        if ($headerLevel > 0) {
            // check if nh is inside of an 'a' tag (incorrect but found in the wild)
            if ($this->astack) {
                if ($start) {
                    $this->inheader = true;
                    // are inside link name, so only add '#' if it can appear before '['
                    if ($this->outtextlist && end($this->outtextlist) === '[') {
                        array_pop($this->outtextlist);
                        $this->space = false;
                        $this->o(str_repeat('#', $headerLevel).' ');
                        $this->o('[');
                    }
                } else {
                    $this->p_p = 0;  // don't break up link name
                    $this->inheader = false;
                    return;  // prevent redundant emphasis marks on headers
                }
            } else {
                $this->p();
                if ($start) {
                    $this->inheader = true;
                    $this->o(str_repeat('#', $headerLevel).' ');
                } else {
                    $this->inheader = false;
                    return;  // prevent redundant emphasis marks on headers
                }
            }
        }
        if (in_array($tag, ['p', 'div'], true)) {
            if ($this->googleDoc) {
                if ($start && Utils::googleHasHeight($tagStyle)) {
                    $this->p();
                } else {
                    $this->soft_br();
                }
            } elseif ($this->astack) {
                // pass
            } elseif ($this->splitNextTd) {
                // pass
            } else {
                $this->p();
            }
        }
        if ('br' === $tag && $start) {
            if ($this->blockquote > 0) {
                $this->o("  \n> ");
            } else {
                $this->o("  \n");
            }
        }
        if ('hr' === $tag && $start) {
            $this->p();
            $this->o('* * *');
            $this->p();
        }
        if (in_array($tag, ['head', 'style', 'script'], true)) {
            if ($start) {
                ++$this->quiet;
            } else {
                --$this->quiet;
            }
        }
        if ('style' === $tag) {
            if ($start) {
                ++$this->style;
            } else {
                --$this->style;
            }
        }
        if ('body' === $tag) {
            $this->quiet = 0;  // sites like 9rules.com never close <head>
        }
        if ('blockquote' === $tag) {
            if ($start) {
                $this->p();
                $this->o('> ', false, true);
                $this->start = true;
                ++$this->blockquote;
            } else {
                --$this->blockquote;
                $this->p();
            }
        }
        if (in_array($tag, ['em', 'i', 'u'], true) && !$this->ignoreEmphasis) {
            // Separate with a space if we immediately follow an alphanumeric
            // character, since otherwise Markdown won't render the emphasis
            // marks, and we'll be left with eg 'foo_bar_' visible.
            // (Don't add a space otherwise, though, since there isn't one in the
            // original HTML.)
            if (
                $start
                && '' !== $this->precedingData
                && !preg_match('/\s/', substr($this->precedingData, -1))
                && !preg_match('/[\p{P}]/u', substr($this->precedingData, -1))
            ) {
                $emphasis = ' '.$this->emphasisMark;
                $this->precedingData .= ' ';
            } else {
                $emphasis = $this->emphasisMark;
            }

            $this->o($emphasis);
            if ($start) {
                $this->stressed = true;
            }
        }
        if (in_array($tag, ['strong', 'b'], true) && !$this->ignoreEmphasis) {
            // Separate with space if we immediately follow an * character, since
            // without it, Markdown won't render the resulting *** correctly.
            // (Don't add a space otherwise, though, since there isn't one in the
            // original HTML.)
            if (
                $start
                && '' !== $this->precedingData
                // When `self.strong_mark` is set to empty, the next condition
                // will cause IndexError since it's trying to match the data
                // with the first character of the `self.strong_mark`.
                && strlen($this->strongMark) > 0
                && substr($this->precedingData, -1) === $this->strongMark[0]
            ) {
                $strong = ' '.$this->strongMark;
                $this->precedingData .= ' ';
            } else {
                $strong = $this->strongMark;
            }

            $this->o($strong);
            if ($start) {
                $this->stressed = true;
            }
        }
        if (in_array($tag, ['del', 'strike', 's'], true)) {
            if ($start && '' !== $this->precedingData && substr($this->precedingData, -1) === '~') {
                $strike = ' ~~';
                $this->precedingData .= ' ';
            } else {
                $strike = '~~';
            }

            $this->o($strike);
            if ($start) {
                $this->stressed = true;
            }
        }
        if ($this->googleDoc) {
            if (!$this->inheader) {
                // handle some font attributes, but leave headers clean
                $this->handle_emphasis($start, $tagStyle, $parentStyle);
            }
        }
        if (in_array($tag, ['kbd', 'code', 'tt'], true) && !$this->pre) {
            $this->o('`');  // TODO: `` `this` ``
            $this->code = !$this->code;
        }
        if ('abbr' === $tag) {
            if ($start) {
                $this->abbrTitle = null;
                $this->abbrData = '';
                if (array_key_exists('title', $attrs) && null !== $attrs['title']) {
                    $this->abbrTitle = $attrs['title'];
                }
            } else {
                if (null !== $this->abbrTitle && null !== $this->abbrData) {
                    $this->abbrList[$this->abbrData] = $this->abbrTitle;
                    $this->abbrTitle = null;
                }
                $this->abbrData = null;
            }
        }
        if ('q' === $tag) {
            if (!$this->quote) {
                $this->o($this->openQuote);
            } else {
                $this->o($this->closeQuote);
            }
            $this->quote = !$this->quote;
        }
        $linkUrl = function (string $link, string $title = ''): void {
            $url = $this->urlJoin($this->baseurl, $link);
            $titlePart = '' !== trim($title) ? ' "'.$title.'"' : '';
            $this->o(']('.Utils::escapeMd($url).$titlePart.')');
        };
        if ('a' === $tag && !$this->ignoreLinks) {
            if ($start) {
                if (
                    array_key_exists('href', $attrs)
                    && null !== $attrs['href']
                    && !($this->skipInternalLinks && str_starts_with($attrs['href'], '#'))
                    && !($this->ignoreMailtoLinks && str_starts_with($attrs['href'], 'mailto:'))
                ) {
                    if ($this->protectLinks) {
                        $attrs['href'] = '<'.$attrs['href'].'>';
                    }
                    $this->astack[] = $attrs;
                    $this->maybeAutomaticLink = $attrs['href'];
                    $this->emptyLink = true;
                } else {
                    $this->astack[] = null;
                }
            } else {
                if ($this->astack) {
                    $a = array_pop($this->astack);
                    if (null !== $this->maybeAutomaticLink && !$this->emptyLink) {
                        $this->maybeAutomaticLink = null;
                    } elseif (null !== $a) {
                        if ($this->emptyLink) {
                            $this->o('[');
                            $this->emptyLink = false;
                            $this->maybeAutomaticLink = null;
                        }
                        if ($this->inlineLinks) {
                            $this->p_p = 0;
                            $title = $a['title'] ?? '';
                            $title = Utils::escapeMd($title ?? '');
                            $href = $a['href'] ?? '';
                            $linkUrl($href, $title);
                        } else {
                            $index = $this->previousIndex($a);
                            if (null !== $index) {
                                $aProps = $this->a[$index];
                            } else {
                                ++$this->acount;
                                $aProps = new AnchorElement($a, $this->acount, $this->outcount);
                                $this->a[] = $aProps;
                            }
                            $this->o('['.$aProps->count.']');
                        }
                    }
                }
            }
        }
        if ('img' === $tag && $start && !$this->ignoreImages) {
            if (array_key_exists('src', $attrs) && null !== $attrs['src']) {
                if (!$this->imagesToAlt) {
                    $attrs['href'] = $attrs['src'];
                }
                $alt = $attrs['alt'] ?? $this->defaultImageAlt;

                // If we have images_with_size, write raw html including width,
                // height, and alt attributes
                if (
                    $this->imagesAsHtml
                    || (
                        $this->imagesWithSize
                        && (array_key_exists('width', $attrs) || array_key_exists('height', $attrs))
                    )
                ) {
                    $this->o("<img src='".$attrs['src']."' ");
                    if (array_key_exists('width', $attrs) && null !== $attrs['width']) {
                        $this->o("width='".$attrs['width']."' ");
                    }
                    if (array_key_exists('height', $attrs) && null !== $attrs['height']) {
                        $this->o("height='".$attrs['height']."' ");
                    }
                    if ('' !== $alt) {
                        $this->o("alt='".$alt."' ");
                    }
                    $this->o('/>');
                    return;
                }

                // If we have a link to create, output the start
                if (null !== $this->maybeAutomaticLink) {
                    $href = $this->maybeAutomaticLink;
                    if (
                        $this->imagesToAlt
                        && Utils::escapeMd($alt) === $href
                        && 1 === preg_match($this->absoluteUrlMatcher, $href)
                    ) {
                        $this->o('<'.Utils::escapeMd($alt).'>');
                        $this->emptyLink = false;
                        return;
                    }
                    $this->o('[');
                    $this->maybeAutomaticLink = null;
                    $this->emptyLink = false;
                }

                // If we have images_to_alt, we discard the image itself,
                // considering only the alt text.
                if ($this->imagesToAlt) {
                    $this->o(Utils::escapeMd($alt));
                } else {
                    $this->o('!['.Utils::escapeMd($alt).']');
                    if ($this->inlineLinks) {
                        $href = $attrs['href'] ?? '';
                        $this->o('('.Utils::escapeMd($this->urlJoin($this->baseurl, $href)).')');
                    } else {
                        $index = $this->previousIndex($attrs);
                        if (null !== $index) {
                            $aProps = $this->a[$index];
                        } else {
                            ++$this->acount;
                            $aProps = new AnchorElement($attrs, $this->acount, $this->outcount);
                            $this->a[] = $aProps;
                        }
                        $this->o('['.$aProps->count.']');
                    }
                }
            }
        }
        if ('dl' === $tag && $start) {
            $this->p();
        }
        if ('dt' === $tag && !$start) {
            $this->pbr();
        }
        if ('dd' === $tag && $start) {
            $this->o('    ');
        }
        if ('dd' === $tag && !$start) {
            $this->pbr();
        }
        if (in_array($tag, ['ol', 'ul'], true)) {
            // Google Docs create sub lists as top level lists
            if (!$this->list && !$this->lastWasList) {
                $this->p();
            }
            if ($start) {
                if ($this->googleDoc) {
                    $listStyle = Utils::googleListStyle($tagStyle);
                } else {
                    $listStyle = $tag;
                }
                $numberingStart = Utils::listNumberingStart($attrs);
                $this->list[] = new ListElement($listStyle, $numberingStart);
            } else {
                if ($this->list) {
                    array_pop($this->list);
                    if (!$this->googleDoc && !$this->list) {
                        $this->o("\n");
                    }
                }
            }
            $this->lastWasList = true;
        } else {
            $this->lastWasList = false;
        }
        if ('li' === $tag) {
            $this->listCodeIndent = '';
            $this->pbr();
            if ($start) {
                if ($this->list) {
                    $li = $this->list[array_key_last($this->list)];
                } else {
                    $li = new ListElement('ul', 0);
                }
                if ($this->googleDoc) {
                    $this->o(str_repeat('  ', $this->google_nest_count($tagStyle)));
                } else {
                    $parentList = null;
                    foreach ($this->list as $listElement) {
                        $this->listCodeIndent .= ('ol' === $parentList) ? '   ' : '  ';
                        $parentList = $listElement->name;
                    }
                    $this->o($this->listCodeIndent);
                }

                if ('ul' === $li->name) {
                    $this->listCodeIndent .= '  ';
                    $this->o($this->ulItemMark.' ');
                } elseif ('ol' === $li->name) {
                    ++$li->num;
                    $this->listCodeIndent .= '   ';
                    $this->o((string) $li->num.'. ');
                }
                $this->start = true;
            }
        }
        if (in_array($tag, ['table', 'tr', 'td', 'th'], true)) {
            if ($this->ignoreTables) {
                if ('tr' === $tag) {
                    if ($start) {
                        // pass
                    } else {
                        $this->soft_br();
                    }
                } else {
                    // pass
                }
            } elseif ($this->bypassTables) {
                if ($start) {
                    $this->soft_br();
                }
                if (in_array($tag, ['td', 'th'], true)) {
                    if ($start) {
                        $this->o('<'.$tag.">\n\n");
                    } else {
                        $this->o("\n</".$tag.'>');
                    }
                } else {
                    if ($start) {
                        $this->o('<'.$tag.'>');
                    } else {
                        $this->o('</'.$tag.'>');
                    }
                }
            } else {
                if ('table' === $tag) {
                    if ($start) {
                        $this->tableStart = true;
                        if ($this->padTables) {
                            $this->o('<'.Constants::TABLE_MARKER_FOR_PAD.'>');
                            $this->o("  \n");
                        }
                    } else {
                        if ($this->padTables) {
                            // add break in case the table is empty or its 1 row table
                            $this->soft_br();
                            $this->o('</'.Constants::TABLE_MARKER_FOR_PAD.'>');
                            $this->o("  \n");
                        }
                    }
                }
                if (in_array($tag, ['td', 'th'], true) && $start) {
                    if ($this->splitNextTd) {
                        $this->o('| ');
                    }
                    $this->splitNextTd = true;
                }

                if ('tr' === $tag && $start) {
                    $this->tdCount = 0;
                }
                if ('tr' === $tag && !$start) {
                    $this->splitNextTd = false;
                    $this->soft_br();
                }
                if ('tr' === $tag && !$start && $this->tableStart) {
                    // Underline table header
                    if ($this->tdCount > 0) {
                        $this->o(implode('|', array_fill(0, $this->tdCount, '---')));
                    }
                    $this->soft_br();
                    $this->tableStart = false;
                }
                if (in_array($tag, ['td', 'th'], true) && $start) {
                    ++$this->tdCount;
                }
            }
        }
        if ('pre' === $tag) {
            if ($start) {
                $this->startpre = true;
                $this->pre = true;
                $this->preIndent = '';
            } else {
                $this->pre = false;
                if ($this->backquoteCodeStyle) {
                    ($this->out)("\n".$this->preIndent.'```');
                }
                if ($this->markCode) {
                    ($this->out)("\n[/code]");
                }
            }
            $this->p();
        }
        if (in_array($tag, ['sup', 'sub'], true) && $this->includeSupSub) {
            if ($start) {
                $this->o('<'.$tag.'>');
            } else {
                $this->o('</'.$tag.'>');
            }
        }
    }
    public function pbr(): void
    {
        /* "Pretty print has a line break" */
        if (0 === $this->p_p) {
            $this->p_p = 1;
        }
    }

    public function p(): void
    {
        /* "Set pretty print to 1 or 2 lines" */
        $this->p_p = $this->singleLineBreak ? 1 : 2;
    }

    public function soft_br(): void
    {
        /* "Soft breaks" */
        $this->pbr();
        $this->brToggle = '  ';
    }
    public function o(string $data, bool $puredata = false, bool|string $force = false): void
    {
        /*
        Deal with indentation and whitespace
        */
        if (null !== $this->abbrData) {
            $this->abbrData .= $data;
        }

        if ($this->quiet) {
            return;
        }

        if ($this->googleDoc) {
            // prevent white space immediately after 'begin emphasis'
            // marks ('**' and '_')
            $lstrippedData = ltrim($data);
            if ($this->dropWhiteSpace && !($this->pre || $this->code)) {
                $data = $lstrippedData;
            }
            if ('' !== $lstrippedData) {
                $this->dropWhiteSpace = 0;
            }
        }

        if ($puredata && !$this->pre) {
            // This is a very dangerous call ... it could mess up
            // all handling of &nbsp; when not handled properly
            // (see entityref)
            $data = (string) preg_replace('/\s+/', ' ', $data);
            if ('' !== $data && $data[0] === ' ') {
                $this->space = true;
                $data = substr($data, 1);
            }
        }
        if ('' === $data && false === $force) {
            return;
        }

        if ($this->startpre) {
            // self.out(" :") #TODO: not output when already one there
            if (!str_starts_with($data, "\n") && !str_starts_with($data, "\r\n")) {
                // <pre>stuff...
                $data = "\n".$data;
            }
            if ($this->markCode) {
                ($this->out)("\n[code]");
                $this->p_p = 0;
            }
        }

        $bq = str_repeat('>', $this->blockquote);
        if (!((true === $force || 'end' === $force) && '' !== $data && $data[0] === '>') && $this->blockquote) {
            if ('' !== $bq) {
                $bq .= ' ';
            }
        }

        if ($this->pre) {
            if ($this->list) {
                $bq .= $this->listCodeIndent;
            }

            if (!$this->backquoteCodeStyle) {
                $bq .= '    ';
            }

            $data = str_replace("\n", "\n".$bq, $data);
            $this->preIndent = $bq;
        }

        if ($this->startpre) {
            $this->startpre = false;
            if ($this->backquoteCodeStyle) {
                ($this->out)("\n".$this->preIndent.'```');
                $this->p_p = 0;
            } elseif ($this->list) {
                // use existing initial indentation
                $data = ltrim($data, "\n".$this->preIndent);
            }
        }

        if ($this->start) {
            $this->space = false;
            $this->p_p = 0;
            $this->start = false;
        }

        if ('end' === $force) {
            // It's the end.
            $this->p_p = 0;
            ($this->out)("\n");
            $this->space = false;
        }

        if ($this->p_p) {
            ($this->out)(str_repeat($this->brToggle."\n".$bq, $this->p_p));
            $this->space = false;
            $this->brToggle = '';
        }

        if ($this->space) {
            if (!$this->lastWasNL) {
                ($this->out)(' ');
            }
            $this->space = false;
        }

        if (
            $this->a
            && (($this->p_p === 2 && $this->linksEachParagraph) || 'end' === $force)
        ) {
            if ('end' === $force) {
                ($this->out)("\n");
            }

            $newa = [];
            foreach ($this->a as $link) {
                if ($this->outcount > $link->outcount) {
                    ($this->out)(
                        '   ['
                        .$link->count
                        .']: '
                        .$this->urlJoin($this->baseurl, $link->attrs['href'] ?? '')
                    );
                    if (
                        array_key_exists('title', $link->attrs)
                        && null !== $link->attrs['title']
                    ) {
                        ($this->out)(' ('.$link->attrs['title'].')');
                    }
                    ($this->out)("\n");
                } else {
                    $newa[] = $link;
                }
            }

            // Don't need an extra line when nothing was done.
            if ($this->a !== $newa) {
                ($this->out)("\n");
            }

            $this->a = $newa;
        }

        if ($this->abbrList && 'end' === $force) {
            foreach ($this->abbrList as $abbr => $definition) {
                ($this->out)('  *['.$abbr.']: '.$definition."\n");
            }
        }

        $this->p_p = 0;
        ($this->out)($data);
        ++$this->outcount;
    }
    public function handle_data(string $data, bool $entityChar = false): void
    {
        if ('' === $data) {
            // Data may be empty for some HTML entities. For example,
            // LEFT-TO-RIGHT MARK.
            return;
        }

        if ($this->stressed) {
            $data = trim($data);
            $this->stressed = false;
            $this->precedingStressed = true;
        } elseif ($this->precedingStressed) {
            $firstChar = mb_substr($data, 0, 1);
            if (
                1 === preg_match('/[^\[\](){}\s.!?]/u', $firstChar)
                && 0 === Utils::hn($this->currentTag)
                && !in_array($this->currentTag, ['a', 'code', 'pre'], true)
            ) {
                // should match a letter or common punctuation
                $data = ' '.$data;
            }
            $this->precedingStressed = false;
        }

        if ($this->style) {
            $this->styleDef = array_replace($this->styleDef, Utils::dumbCssParser($data));
        }

        if (null !== $this->maybeAutomaticLink) {
            $href = $this->maybeAutomaticLink;
            if (
                $href === $data
                && 1 === preg_match($this->absoluteUrlMatcher, $href)
                && $this->useAutomaticLinks
            ) {
                $this->o('<'.$data.'>');
                $this->emptyLink = false;
                return;
            }
            $this->o('[');
            $this->maybeAutomaticLink = null;
            $this->emptyLink = false;
        }

        if (!$this->code && !$this->pre && !$entityChar) {
            $data = Utils::escapeMdSection($data, $this->escapeSnob);
        }
        $this->precedingData = $data;
        $this->emptyLink = false;
        $this->o($data, true);
    }
    public function charref(string $name): string
    {
        if ('' === $name) {
            return '';
        }

        if ('x' === $name[0] || 'X' === $name[0]) {
            $c = (int) hexdec(substr($name, 1));
        } else {
            $c = (int) $name;
        }

        if ($c <= 0 || $c >= 0x110000 || ($c >= 0xD800 && $c < 0xE000)) {
            $c = 0xFFFD;  // REPLACEMENT CHARACTER
        }
        $replacements = Utils::controlCharacterReplacements();
        $c = $replacements[$c] ?? $c;

        if (!$this->unicodeSnob) {
            $unifiable = Utils::unifiableN();
            if (array_key_exists($c, $unifiable)) {
                return $unifiable[$c];
            }
        }

        return mb_chr($c, 'UTF-8');
    }
    public function entityref(string $c): string
    {
        if (!$this->unicodeSnob && array_key_exists($c, $this->configUnifiable)) {
            return $this->configUnifiable[$c];
        }
        $decoded = html_entity_decode('&'.$c.';', \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        if ('&'.$c.';' === $decoded) {
            return '&'.$c.';';
        }

        if ('nbsp' === $c) {
            return $this->configUnifiable['nbsp'] ?? $decoded;
        }

        return $decoded;
    }
    public function google_nest_count(array $style): int
    {
        /*
        Calculate the nesting count of google doc lists

        :type style: dict

        :rtype: int
        */
        $nestCount = 0;
        if (array_key_exists('margin-left', $style)) {
            $value = $style['margin-left'];
            if (null !== $value && str_ends_with($value, 'px')) {
                $margin = (int) round((float) substr($value, 0, -2));
                if ($this->googleListIndent > 0) {
                    $nestCount = (int) floor($margin / $this->googleListIndent);
                }
            }
        }

        return $nestCount;
    }
    public function optwrap(string $text): string
    {
        /*
        Wrap all paragraphs in the provided text.

        :type text: str

        :rtype: str
        */
        if (!$this->bodyWidth) {
            return $text;
        }

        $result = '';
        $newlines = 0;
        // I cannot think of a better solution for now.
        // To avoid the non-wrap behaviour for entire paras
        // because of the presence of a link in it
        if (!$this->wrapLinks) {
            $this->inlineLinks = false;
        }
        $startCode = false;
        foreach (explode("\n", $text) as $para) {
            // If the text is between tri-backquote pairs, it's a code block;
            // don't wrap
            if ($this->backquoteCodeStyle && str_starts_with(ltrim($para), '```')) {
                $startCode = !$startCode;
            }
            if ($startCode) {
                $result .= $para."\n";
                $newlines = 1;
            } elseif ('' !== $para) {
                if (!Utils::skipwrap($para, $this->wrapLinks, $this->wrapListItems, $this->wrapTables)) {
                    $indent = '';
                    if (str_starts_with($para, '  '.$this->ulItemMark)) {
                        // list item continuation: add a double indent to the
                        // new lines
                        $indent = '    ';
                    } elseif (str_starts_with($para, '> ')) {
                        // blockquote continuation: add the greater than symbol
                        // to the new lines
                        $indent = '> ';
                    }
                    $wrapped = $this->wrapParagraph($para, $this->bodyWidth, $indent);
                    $result .= implode("\n", $wrapped);
                    if (str_ends_with($para, '  ')) {
                        $result .= "  \n";
                        $newlines = 1;
                    } elseif ('' !== $indent) {
                        $result .= "\n";
                        $newlines = 1;
                    } else {
                        $result .= "\n\n";
                        $newlines = 2;
                    }
                } else {
                    // Warning for the tempted!!!
                    // Be aware that obvious replacement of this with
                    // line.isspace()
                    // DOES NOT work! Explanations are welcome.
                    if (1 !== preg_match(Constants::RE_SPACE, $para)) {
                        $result .= $para."\n";
                        $newlines = 1;
                    }
                }
            } else {
                if ($newlines < 2) {
                    $result .= "\n";
                    ++$newlines;
                }
            }
        }

        return $result;
    }
    /**
     * @return list<string>
     */
    private function wrapParagraph(string $text, int $width, string $subIndent): array
    {
        if ($width <= 0) {
            return [$text];
        }

        $wrapped = wordwrap($text, $width, "\n", false);
        $lines = explode("\n", $wrapped);
        if ('' !== $subIndent) {
            foreach ($lines as $index => $line) {
                if (0 === $index) {
                    continue;
                }
                $lines[$index] = $subIndent.$line;
            }
        }

        return $lines;
    }

    private function urlJoin(string $base, string $link): string
    {
        if ('' === $link) {
            return $base;
        }
        if ('' === $base) {
            return $link;
        }
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $link)) {
            return $link;
        }

        $baseParts = parse_url($base);
        if (false === $baseParts) {
            return $link;
        }

        if ($link[0] === '#') {
            $baseNoFragment = $base;
            $hashPos = strpos($baseNoFragment, '#');
            if (false !== $hashPos) {
                $baseNoFragment = substr($baseNoFragment, 0, $hashPos);
            }

            return $baseNoFragment.$link;
        }

        if ($link[0] === '?' ) {
            $path = $baseParts['path'] ?? '/';
            return $this->buildUrl($baseParts, $path.$link);
        }

        if (str_starts_with($link, '//')) {
            $scheme = $baseParts['scheme'] ?? '';
            return ($scheme ? $scheme.':' : '').$link;
        }

        $fragment = '';
        if (false !== ($hashPos = strpos($link, '#'))) {
            $fragment = substr($link, $hashPos);
            $link = substr($link, 0, $hashPos);
        }

        $query = '';
        if (false !== ($queryPos = strpos($link, '?'))) {
            $query = substr($link, $queryPos);
            $link = substr($link, 0, $queryPos);
        }

        if (str_starts_with($link, '/')) {
            $path = $this->normalizePath($link);
        } else {
            $basePath = $baseParts['path'] ?? '';
            if ('' === $basePath) {
                $basePath = '/';
            }
            $dir = $basePath;
            if ('/' !== substr($dir, -1)) {
                $lastSlash = strrpos($dir, '/');
                if (false !== $lastSlash) {
                    $dir = substr($dir, 0, $lastSlash + 1);
                } else {
                    $dir = '/';
                }
            }
            $path = $this->normalizePath($dir.$link);
        }

        if ('' === $query && isset($baseParts['query']) && '' !== $baseParts['query']) {
            $query = '?'.$baseParts['query'];
        }

        return $this->buildUrl($baseParts, $path.$query.$fragment);
    }

    private function buildUrl(array $baseParts, string $path): string
    {
        $scheme = $baseParts['scheme'] ?? '';
        $host = $baseParts['host'] ?? '';
        $port = isset($baseParts['port']) ? ':'.$baseParts['port'] : '';
        $user = $baseParts['user'] ?? null;
        $pass = $baseParts['pass'] ?? null;
        $auth = '';
        if (null !== $user) {
            $auth = $user;
            if (null !== $pass) {
                $auth .= ':'.$pass;
            }
            $auth .= '@';
        }
        $authority = $auth.$host.$port;

        return ($scheme ? $scheme.'://' : '').$authority.$path;
    }

    private function normalizePath(string $path): string
    {
        $leadingSlash = str_starts_with($path, '/');
        $trailingSlash = str_ends_with($path, '/');
        $segments = explode('/', $path);
        $output = [];
        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                array_pop($output);
                continue;
            }
            $output[] = $segment;
        }
        $normalized = implode('/', $output);
        if ($leadingSlash) {
            $normalized = '/'.$normalized;
        }
        if ('' === $normalized) {
            $normalized = $leadingSlash ? '/' : '';
        } elseif ($trailingSlash) {
            $normalized .= '/';
        }

        return $normalized;
    }
}

function html2text(string $html, string $baseurl = '', ?int $bodywidth = null): string
{
    if (null === $bodywidth) {
        $bodywidth = (new Config())->bodyWidth;
    }
    $converter = new HTML2Text(null, $baseurl, $bodywidth);

    return $converter->handle($html);
}
