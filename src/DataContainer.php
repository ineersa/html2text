<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

use Ineersa\PhpHtml2text\Elements\AnchorElement;
use Ineersa\PhpHtml2text\Elements\ListElement;

class DataContainer
{
    public ?string $abbrData = null;
    /** @var array<string, string> */
    public array $abbrList = [];
    public bool $stressed = false;
    public bool $precedingStressed = false;
    public string $precedingData = '';

    public string $currentTag = '';

    public int $style = 0;
    /** @var array<string, array<string, string>> */
    public array $styleDef = [];

    public ?string $maybeAutomaticLink = null;

    public bool $emptyLink = false;

    public bool $space = false;

    public bool $pre = false;

    public bool $code = false;
    public int $quiet = 0;

    public int $dropWhiteSpace = 0;

    public bool $startpre = false;

    /** @var list<string> */
    public array $outtextlist = [];

    public bool $lastWasNL = false;

    public int $prettyPrint = 0;

    public int $blockquote = 0;

    /** @var list<ListElement> */
    public array $list = [];

    public string $listCodeIndent = '';

    public string $preIndent = '';

    public bool $start = true;

    public string $brToggle = '';

    /** @var list<AnchorElement> */
    public array $a = [];

    public int $outcount = 0;

    public int $emphasis = 0;

    public function __construct(
        private Config $config,
    ) {
    }

    public function push(string $data, bool $entityChar = false): void
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
                && !\in_array($this->currentTag, ['a', 'code', 'pre'], true)
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
                && 1 === preg_match(Constants::RE_ABSOLUTE_URL_MATHCER, $href)
                && $this->config->useAutomaticLinks
            ) {
                $this->appendFormattedData('<'.$data.'>');
                $this->emptyLink = false;

                return;
            }
            $this->space = false;
            $this->appendFormattedData('[');
            $this->maybeAutomaticLink = null;
            $this->emptyLink = false;
        }

        if (!$this->code && !$this->pre && !$entityChar) {
            $data = Utils::escapeMdSection($data, $this->config->escapeSnob);
        }
        $this->precedingData = $data;
        $this->emptyLink = false;
        $this->appendFormattedData($data, true);
    }

    public function appendFormattedData(string $data, bool $puredata = false, bool|string $force = false): void
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

        if ($this->config->googleDoc) {
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
            if ('' !== $data && ' ' === $data[0]) {
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
            if ($this->config->markCode) {
                $this->pushToList("\n[code]");
                $this->prettyPrint = 0;
            }
        }

        $bq = str_repeat('>', $this->blockquote);
        if (!((true === $force || 'end' === $force) && '' !== $data && '>' === $data[0]) && $this->blockquote) {
            if ('' !== $bq) {
                $bq .= ' ';
            }
        }

        if ($this->pre) {
            if ($this->list) {
                $bq .= $this->listCodeIndent;
            }

            if (!$this->config->backquoteCodeStyle) {
                $bq .= '    ';
            }

            $data = str_replace("\n", "\n".$bq, $data);
            $this->preIndent = $bq;
        }

        if ($this->startpre) {
            $this->startpre = false;
            if ($this->config->backquoteCodeStyle) {
                $this->pushToList("\n".$this->preIndent.'```');
                $this->prettyPrint = 0;
            } elseif ($this->list) {
                // use existing initial indentation
                $data = ltrim($data, "\n".$this->preIndent);
            }
        }

        if ($this->start) {
            $this->space = false;
            $this->prettyPrint = 0;
            $this->start = false;
        }

        if ('end' === $force) {
            // It's the end.
            $this->prettyPrint = 0;
            $this->pushToList("\n");
            $this->space = false;
        }

        if ($this->prettyPrint) {
            $this->pushToList(str_repeat($this->brToggle."\n".$bq, $this->prettyPrint));
            $this->space = false;
            $this->brToggle = '';
        }

        if ($this->space) {
            if (!$this->lastWasNL) {
                $this->pushToList(' ');
            }
            $this->space = false;
        }

        if (
            $this->a
            && ((2 === $this->prettyPrint && $this->config->linksEachParagraph) || 'end' === $force)
        ) {
            if ('end' === $force) {
                $this->pushToList("\n");
            }

            $newa = [];
            foreach ($this->a as $link) {
                if ($this->outcount > $link->outcount) {
                    $this->pushToList(
                        '   ['
                        .$link->count
                        .']: '
                        .UrlBuilder::urlJoin($this->config->baseUrl, $link->attrs['href'] ?? '')
                    );
                    if (
                        \array_key_exists('title', $link->attrs)
                        && null !== $link->attrs['title']
                    ) {
                        $this->pushToList(' ('.$link->attrs['title'].')');
                    }
                    $this->pushToList("\n");
                } else {
                    $newa[] = $link;
                }
            }

            // Don't need an extra line when nothing was done.
            if ($this->a !== $newa) {
                $this->pushToList("\n");
            }

            $this->a = $newa;
        }

        if ($this->abbrList && 'end' === $force) {
            foreach ($this->abbrList as $abbr => $definition) {
                $this->pushToList('  *['.$abbr.']: '.$definition."\n");
            }
        }

        $this->prettyPrint = 0;
        $this->pushToList($data);
        ++$this->outcount;
    }

    public function initializePrettyPrint(): void
    {
        /* "Set pretty print to 1 or 2 lines" */
        $this->prettyPrint = $this->config->singleLineBreak ? 1 : 2;
    }

    public function pbr(): void
    {
        /* "Pretty print has a line break" */
        if (0 === $this->prettyPrint) {
            $this->prettyPrint = 1;
        }
    }

    public function softBr(): void
    {
        /* "Soft breaks" */
        $this->pbr();
        $this->brToggle = '  ';
    }

    public function addEmphasis(bool $start, array $tagStyle, array $parentStyle): void
    {
        /*
        Handles various text emphases
        */
        $tagEmphasis = Utils::googleTextEmphasis($tagStyle);
        $parentEmphasis = Utils::googleTextEmphasis($parentStyle);

        // handle Google's text emphasis
        $strikethrough = \in_array('line-through', $tagEmphasis, true) && $this->config->hideStrikethrough;

        // google and others may mark a font's weight as `bold` or `700`
        $bold = false;
        foreach ($this->config->boldTextStyleValues as $boldMarker) {
            $bold = \in_array($boldMarker, $tagEmphasis, true) && !\in_array($boldMarker, $parentEmphasis, true);
            if ($bold) {
                break;
            }
        }

        $italic = \in_array('italic', $tagEmphasis, true) && !\in_array('italic', $parentEmphasis, true);
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
                $this->appendFormattedData($this->config->emphasisMark);
                ++$this->dropWhiteSpace;
            }
            if ($bold) {
                $this->appendFormattedData($this->config->strongMark);
                ++$this->dropWhiteSpace;
            }
            if ($fixed) {
                $this->appendFormattedData('`');
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
                    $this->appendFormattedData('`');
                }
                $this->code = false;
            }
            if ($bold) {
                if ($this->dropWhiteSpace) {
                    // empty emphasis, drop it
                    --$this->dropWhiteSpace;
                } else {
                    $this->appendFormattedData($this->config->strongMark);
                }
            }
            if ($italic) {
                if ($this->dropWhiteSpace) {
                    // empty emphasis, drop it
                    --$this->dropWhiteSpace;
                } else {
                    $this->appendFormattedData($this->config->emphasisMark);
                }
            }
            // space is only allowed after *all* emphasis marks
            if (($bold || $italic) && !$this->emphasis) {
                $this->appendFormattedData(' ');
            }
            if ($strikethrough) {
                --$this->quiet;
            }
        }
    }

    public function pushToList(string $s): void
    {
        $this->outtextlist[] = $s;
        if ('' !== $s) {
            $this->lastWasNL = "\n" === substr($s, -1);
        }
    }
}
