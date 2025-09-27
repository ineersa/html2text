<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text\Processors;

use Ineersa\PhpHtml2text\Config;
use Ineersa\PhpHtml2text\Constants;
use Ineersa\PhpHtml2text\DataContainer;
use Ineersa\PhpHtml2text\Elements\AnchorElement;
use Ineersa\PhpHtml2text\Elements\ListElement;
use Ineersa\PhpHtml2text\HTML2Markdown;
use Ineersa\PhpHtml2text\Utilities\ParserUtilities;
use Ineersa\PhpHtml2text\Utilities\UrlUtilities;

class TagProcessor
{
    /** @var list<array{0:string|null,1:array<string, string|null>,2:array<string, string>}> */
    public array $tagStack = [];

    public array $tagStyle = [];
    public array $parentStyle = [];

    /** @var list<array<string, string|null>|null> */
    public array $astack = [];

    public bool $inheader = false;

    public bool $splitNextTd = false;

    public ?string $abbrTitle = null;

    public bool $quote = false;

    public int $aCount = 0;

    public bool $lastWasList = false;

    public bool $tableStart = false;

    public int $tdCount = 0;

    public function __construct(
        private Config $config,
        private DataContainer $data,
        private HTML2Markdown $HTML2Markdown,
        private TrProcessor $trProcessor,
        private AnchorProcessor $anchorProcessor,
    ) {
    }

    public function process(string $tag, array $attrs, bool $start): void
    {
        $this->data->currentTag = $tag;
        $this->parentStyle = [];
        $this->tagStyle = [];

        $isExplicitTrClosing = true;
        if ('tr' === $tag) {
            if ($start) {
                $this->trProcessor->start();
            } else {
                $isExplicitTrClosing = $this->trProcessor->end();
            }
        }

        if (null !== $this->config->tagCallback) {
            $callback = $this->config->tagCallback;
            if (true === $callback($this, $tag, $attrs, $start)) {
                return;
            }
        }

        // first thing inside the anchor tag is another tag
        // that produces some output
        if (
            $start
            && null !== $this->data->maybeAutomaticLink
            && !\in_array($tag, ['p', 'div', 'style', 'dl', 'dt'], true)
            && ('img' !== $tag || $this->config->ignoreImages)
        ) {
            $this->data->appendFormattedData('[');
            $this->data->maybeAutomaticLink = null;
            $this->data->emptyLink = false;
        }

        if ($this->config->googleDoc) {
            // the attrs parameter is empty for a closing tag. in addition, we
            // need the attributes of the parent nodes in order to get a
            // complete style description for the current element. we assume
            // that google docs export well formed html.
            if ($start) {
                if ($this->tagStack) {
                    $last = $this->tagStack[array_key_last($this->tagStack)];
                    $this->parentStyle = $last[2];
                }
                $this->tagStyle = ParserUtilities::elementStyle($attrs, $this->data->styleDef, $this->parentStyle);
                $this->tagStack[] = [$tag, $attrs, $this->tagStyle];
            } else {
                if ($this->tagStack) {
                    $stackEntry = array_pop($this->tagStack);
                    $attrs = $stackEntry[1];
                    $this->tagStyle = $stackEntry[2];
                } else {
                    $attrs = [];
                    $this->tagStyle = [];
                }
                if ($this->tagStack) {
                    $last = $this->tagStack[array_key_last($this->tagStack)];
                    $this->parentStyle = $last[2];
                }
            }
        }

        $headerLevel = ParserUtilities::hn($tag);
        if ($headerLevel > 0) {
            // check if nh is inside of an 'a' tag (incorrect but found in the wild)
            if ($this->astack) {
                if ($start) {
                    $this->inheader = true;
                    // are inside link name, so only add '#' if it can appear before '['
                    if ($this->data->outtextlist && '[' === end($this->data->outtextlist)) {
                        array_pop($this->data->outtextlist);
                        $this->data->space = false;
                        $this->data->appendFormattedData(str_repeat('#', $headerLevel).' ');
                        $this->data->appendFormattedData('[');
                    }
                } else {
                    $this->data->prettyPrint = 0;  // don't break up link name
                    $this->inheader = false;

                    return;  // prevent redundant emphasis marks on headers
                }
            } else {
                $this->data->initializePrettyPrint();
                if ($start) {
                    $this->inheader = true;
                    $this->data->appendFormattedData(str_repeat('#', $headerLevel).' ');
                } else {
                    $this->inheader = false;
                    $this->data->initializePrettyPrint();

                    return;  // prevent redundant emphasis marks on headers
                }
            }
        }
        if (\in_array($tag, ['p', 'div'], true)) {
            if ($this->config->googleDoc) {
                if ($start && ParserUtilities::googleHasHeight($this->tagStyle)) {
                    $this->data->initializePrettyPrint();
                } else {
                    $this->data->softBr();
                }
            } elseif ($this->astack || $this->splitNextTd) {
                // pass
            } else {
                $this->data->initializePrettyPrint();
            }
        }
        if ('br' === $tag && $start) {
            // Avoid carrying over pending spaces before explicit line breaks
            $this->data->space = false;
            if ($this->data->blockquote > 0) {
                $this->data->appendFormattedData("  \n> ");
            } else {
                $this->data->appendFormattedData("  \n");
            }
        }
        if ('hr' === $tag && $start) {
            $this->data->initializePrettyPrint();
            $this->data->appendFormattedData('* * *');
            $this->data->initializePrettyPrint();
        }
        if (\in_array($tag, ['head', 'style', 'script'], true)) {
            if ($start) {
                ++$this->data->quiet;
            } else {
                --$this->data->quiet;
            }
        }
        if ('style' === $tag) {
            if ($start) {
                ++$this->data->style;
            } else {
                --$this->data->style;
            }
        }
        if ('body' === $tag) {
            $this->data->quiet = 0;  // sites like 9rules.com never close <head>
        }
        if ('blockquote' === $tag) {
            if ($start) {
                $this->data->initializePrettyPrint();
                $this->data->appendFormattedData('> ', false, true);
                $this->data->start = true;
                ++$this->data->blockquote;
            } else {
                --$this->data->blockquote;
                $this->data->initializePrettyPrint();
            }
        }
        if (\in_array($tag, ['em', 'i', 'u'], true) && !$this->config->ignoreEmphasis) {
            // Separate with a space if we immediately follow an alphanumeric
            // character, since otherwise Markdown won't render the emphasis
            // marks, and we'll be left with eg 'foo_bar_' visible.
            // (Don't add a space otherwise, though, since there isn't one in the
            // original HTML.)
            if (
                $start
                && '' !== $this->data->precedingData
                && !preg_match('/\s/', substr($this->data->precedingData, -1))
                && !preg_match('/\p{P}/u', substr($this->data->precedingData, -1))
            ) {
                $emphasis = ' '.$this->config->emphasisMark;
                $this->data->precedingData .= ' ';
            } else {
                $emphasis = $this->config->emphasisMark;
            }

            $this->data->appendFormattedData($emphasis);
            if ($start) {
                $this->data->stressed = true;
            }
        }
        if (\in_array($tag, ['strong', 'b'], true) && !$this->config->ignoreEmphasis) {
            // Separate with space if we immediately follow an * character, since
            // without it, Markdown won't render the resulting *** correctly.
            // (Don't add a space otherwise, though, since there isn't one in the
            // original HTML.)
            if (
                $start
                && '' !== $this->data->precedingData
                // When `self.strong_mark` is set to empty, the next condition
                // will cause IndexError since it's trying to match the data
                // with the first character of the `self.strong_mark`.
                && '' !== $this->config->strongMark
                && substr($this->data->precedingData, -1) === $this->config->strongMark[0]
            ) {
                $strong = ' '.$this->config->strongMark;
                $this->data->precedingData .= ' ';
            } else {
                $strong = $this->config->strongMark;
            }

            $this->data->appendFormattedData($strong);
            if ($start) {
                $this->data->stressed = true;
            }
        }
        if (\in_array($tag, ['del', 'strike', 's'], true)) {
            if ($start && '' !== $this->data->precedingData && str_ends_with($this->data->precedingData, '~')) {
                $strike = ' ~~';
                $this->data->precedingData .= ' ';
            } else {
                $strike = '~~';
            }

            $this->data->appendFormattedData($strike);
            if ($start) {
                $this->data->stressed = true;
            }
        }
        if ($this->config->googleDoc) {
            if (!$this->inheader) {
                // handle some font attributes, but leave headers clean
                $this->data->addEmphasis($start, $this->tagStyle, $this->parentStyle);
            }
        }
        if (\in_array($tag, ['kbd', 'code', 'tt'], true) && !$this->data->pre) {
            $this->data->appendFormattedData('`');  // TODO: `` `this` ``
            $this->data->code = !$this->data->code;
        }
        if ('abbr' === $tag) {
            if ($start) {
                $this->abbrTitle = null;
                $this->data->abbrData = '';
                if (\array_key_exists('title', $attrs) && null !== $attrs['title']) {
                    $this->abbrTitle = $attrs['title'];
                }
            } else {
                if (null !== $this->abbrTitle && null !== $this->data->abbrData) {
                    $this->data->abbrList[$this->data->abbrData] = $this->abbrTitle;
                    $this->abbrTitle = null;
                }
                $this->data->abbrData = null;
            }
        }
        if ('q' === $tag) {
            if (!$this->quote) {
                $this->data->appendFormattedData($this->config->openQuote);
            } else {
                $this->data->appendFormattedData($this->config->closeQuote);
            }
            $this->quote = !$this->quote;
        }
        if ('a' === $tag && !$this->config->ignoreAnchors) {
            if ($start) {
                $this->handleAnchorStart($attrs);
            } else {
                $this->handleAnchorEnd();
            }
        }
        if ('img' === $tag && $start && !$this->config->ignoreImages) {
            if (\array_key_exists('src', $attrs) && null !== $attrs['src'] && '' !== $attrs['src']) {
                if (!$this->config->imagesToAlt) {
                    $attrs['href'] = $attrs['src'];
                }
                $alt = $attrs['alt'] ?? $this->config->defaultImageAlt;

                // If we have images_with_size, write raw html including width,
                // height, and alt attributes
                if (
                    $this->config->imagesAsHtml
                    || (
                        $this->config->imagesWithSize
                        && (\array_key_exists('width', $attrs) || \array_key_exists('height', $attrs))
                    )
                ) {
                    $this->data->appendFormattedData("<img src='".$attrs['src']."' ");
                    if (\array_key_exists('width', $attrs) && null !== $attrs['width'] && '' !== (string) $attrs['width']) {
                        $this->data->appendFormattedData("width='".$attrs['width']."' ");
                    }
                    if (\array_key_exists('height', $attrs) && null !== $attrs['height'] && '' !== (string) $attrs['height']) {
                        $this->data->appendFormattedData("height='".$attrs['height']."' ");
                    }
                    if ('' !== $alt) {
                        $this->data->appendFormattedData("alt='".$alt."' ");
                    }
                    $this->data->appendFormattedData('/>');

                    return;
                }

                // If we have a link to create, output the start
                if (null !== $this->data->maybeAutomaticLink) {
                    $href = $this->data->maybeAutomaticLink;
                    if (
                        $this->config->imagesToAlt
                        && ParserUtilities::escapeMd($alt) === $href
                        && 1 === preg_match(Constants::RE_ABSOLUTE_URL_MATHCER, $href)
                    ) {
                        $this->data->appendFormattedData('<'.ParserUtilities::escapeMd($alt).'>');
                        $this->data->emptyLink = false;

                        return;
                    }
                    $this->data->appendFormattedData('[');
                    $this->data->maybeAutomaticLink = null;
                    $this->data->emptyLink = false;
                }

                // If we have images_to_alt, we discard the image itself,
                // considering only the alt text.
                if ($this->config->imagesToAlt) {
                    $this->data->appendFormattedData(ParserUtilities::escapeMd($alt));
                } else {
                    $this->data->appendFormattedData('!['.ParserUtilities::escapeMd($alt).']');
                    if ($this->config->inlineLinks) {
                        $href = $attrs['href'] ?? '';
                        $this->data->appendFormattedData('('.ParserUtilities::escapeMd(UrlUtilities::urlJoin($this->config->baseUrl, $href)).')');
                    } else {
                        $index = $this->previousIndex($attrs);
                        if (null !== $index) {
                            $aProps = $this->data->a[$index];
                        } else {
                            ++$this->aCount;
                            $aProps = new AnchorElement($attrs, $this->aCount, $this->data->outcount);
                            $this->data->a[] = $aProps;
                        }
                        $this->data->appendFormattedData('['.$aProps->count.']');
                    }
                }
            }
        }
        if ('dl' === $tag && $start) {
            $this->data->initializePrettyPrint();
        }
        if ('dt' === $tag && !$start) {
            $this->data->pbr();
        }
        if ('dd' === $tag && $start) {
            $this->data->appendFormattedData('    ');
        }
        if ('dd' === $tag && !$start) {
            $this->data->pbr();
        }
        if (\in_array($tag, ['ol', 'ul'], true)) {
            // Google Docs create sub lists as top level lists
            if (!$this->HTML2Markdown->getListsStructure()->list && !$this->lastWasList) {
                $this->data->initializePrettyPrint();
            }
            if ($start) {
                if ($this->config->googleDoc) {
                    $listStyle = ParserUtilities::googleListStyle($this->tagStyle);
                } else {
                    $listStyle = $tag;
                }
                $numberingStart = ParserUtilities::listNumberingStart($attrs);
                $this->HTML2Markdown->getListsStructure()->list[] = new ListElement($listStyle, $numberingStart);
            } else {
                if ($this->HTML2Markdown->getListsStructure()->list) {
                    array_pop($this->HTML2Markdown->getListsStructure()->list);
                    if (!$this->config->googleDoc && !$this->HTML2Markdown->getListsStructure()->list) {
                        $this->data->appendFormattedData("\n");
                    }
                }
            }
            $this->lastWasList = true;
        } else {
            $this->lastWasList = false;
        }
        if ('li' === $tag) {
            if ($start) {
                $this->HTML2Markdown->getListsStructure()->ensureListStackForCurrentListItem();
            }
            $this->data->listCodeIndent = '';
            $this->data->pbr();
            if ($start) {
                if ($this->HTML2Markdown->getListsStructure()->list) {
                    $li = $this->HTML2Markdown->getListsStructure()->list[array_key_last($this->HTML2Markdown->getListsStructure()->list)];
                } else {
                    $li = new ListElement('ul', 0);
                }
                if ($this->config->googleDoc) {
                    $this->data->appendFormattedData(str_repeat('  ', ParserUtilities::googleNestCount($this->tagStyle, $this->config->googleListIndent)));
                } else {
                    $parentList = null;
                    foreach ($this->HTML2Markdown->getListsStructure()->list as $listElement) {
                        $this->data->listCodeIndent .= ('ol' === $parentList) ? '   ' : '  ';
                        $parentList = $listElement->name;
                    }
                    $this->data->appendFormattedData($this->data->listCodeIndent);
                }

                if ('ul' === $li->name) {
                    $this->data->listCodeIndent .= '  ';
                    $this->data->appendFormattedData($this->config->ulItemMark.' ');
                } elseif ('ol' === $li->name) {
                    ++$li->num;
                    $this->data->listCodeIndent .= '   ';
                    $this->data->appendFormattedData($li->num.'. ');
                }
                $this->data->start = true;
            }
        }
        if (\in_array($tag, ['table', 'tr', 'td', 'th'], true)) {
            if ($this->config->ignoreTables) {
                // TODO check it, this is some strange logic here
                if ('tr' === $tag && !$start) {
                    $this->data->softBr();
                }
            } elseif ($this->config->bypassTables) {
                if ($start) {
                    $this->data->softBr();
                }
                if (\in_array($tag, ['td', 'th'], true)) {
                    if ($start) {
                        $this->data->appendFormattedData('<'.$tag.">\n\n");
                    } else {
                        $this->data->appendFormattedData("\n</".$tag.'>');
                    }
                } else {
                    if ($start) {
                        $this->data->appendFormattedData('<'.$tag.'>');
                    } else {
                        $this->data->appendFormattedData('</'.$tag.'>');
                    }
                }
            } else {
                if ('table' === $tag) {
                    if ($start) {
                        $this->tableStart = true;
                        if ($this->config->padTables) {
                            $this->data->appendFormattedData('<'.Constants::TABLE_MARKER_FOR_PAD.'>');
                            $this->data->appendFormattedData("  \n");
                        }
                    } else {
                        if ($this->config->padTables) {
                            // add break in case the table is empty or its 1 row table
                            $this->data->softBr();
                            $this->data->appendFormattedData('</'.Constants::TABLE_MARKER_FOR_PAD.'>');
                            $this->data->appendFormattedData("  \n");
                        }
                    }
                }
                if (\in_array($tag, ['td', 'th'], true) && $start) {
                    if ($this->splitNextTd) {
                        $this->data->appendFormattedData('| ');
                    }
                    $this->splitNextTd = true;
                }

                if ('tr' === $tag && $start) {
                    $this->tdCount = 0;
                }
                if ('tr' === $tag && !$start && $isExplicitTrClosing) {
                    $this->splitNextTd = false;
                    $this->data->softBr();
                }
                if ('tr' === $tag && !$start && $this->tableStart && $isExplicitTrClosing) {
                    // Underline table header
                    if ($this->tdCount > 0) {
                        $this->data->appendFormattedData(implode('|', array_fill(0, $this->tdCount, '---')));
                    }
                    $this->data->softBr();
                    $this->tableStart = false;
                }
                if (\in_array($tag, ['td', 'th'], true) && $start) {
                    ++$this->tdCount;
                }
            }
        }
        if ('pre' === $tag) {
            if ($start) {
                $this->data->startpre = true;
                $this->data->pre = true;
                $this->data->preIndent = '';
            } else {
                $this->data->pre = false;
                if ($this->config->backquoteCodeStyle) {
                    $this->data->pushToList("\n".$this->data->preIndent.'```');
                }
                if ($this->config->markCode) {
                    $this->data->pushToList("\n[/code]");
                }
            }

            $this->data->initializePrettyPrint();
        }
        if (\in_array($tag, ['sup', 'sub'], true) && $this->config->includeSupSub) {
            if ($start) {
                $this->data->appendFormattedData('<'.$tag.'>');
            } else {
                $this->data->appendFormattedData('</'.$tag.'>');
            }
        }
    }

    public function afterText(string $text): void
    {
        if ('' === $text) {
            return;
        }

        $depth = $this->anchorProcessor->consumeTextDepth($text);
        if (null === $depth) {
            return;
        }

        $nextDepth = $this->anchorProcessor->peekNextTextDepth();
        $this->anchorProcessor->flushForText($depth, $nextDepth, function (): void {
            $a = array_pop($this->astack);
            // Pop the current depth as part of closure
            // The close pointer is advanced internally by AnchorProcessor::flushForText via callback
            if (null !== $a) {
                $this->finalizeAnchorClosure($a);
            }
        });
    }

    private function handleAnchorStart(array $attrs): void
    {
        $this->anchorProcessor->pushStartDepth();

        if (
            \array_key_exists('href', $attrs)
            && null !== $attrs['href']
            && !($this->config->skipInternalLinks && str_starts_with($attrs['href'], '#'))
            && !($this->config->ignoreMailtoLinks && str_starts_with($attrs['href'], 'mailto:'))
        ) {
            if ($this->config->protectLinks) {
                $attrs['href'] = '<'.$attrs['href'].'>';
            }
            $this->astack[] = $attrs;
            $this->data->maybeAutomaticLink = $attrs['href'];
            $this->data->emptyLink = true;

            return;
        }

        $this->astack[] = null;
    }

    private function handleAnchorEnd(): void
    {
        if (!$this->astack) {
            return;
        }

        $currentDepth = $this->anchorProcessor->currentDepth();
        $expectedDepth = $this->anchorProcessor->expectedCloseDepth();
        $isPremature = null !== $expectedDepth && $currentDepth !== $expectedDepth;

        $attrs = end($this->astack);

        if (null !== $this->data->maybeAutomaticLink && !$this->data->emptyLink) {
            $this->data->maybeAutomaticLink = null;

            if ($isPremature) {
                $this->anchorProcessor->addPendingClosureForCurrentDepth();

                return;
            }

            array_pop($this->astack);
            $this->anchorProcessor->popOnExplicitClose();
            $this->flushPendingAnchorClosures($currentDepth);

            return;
        }

        if (null !== $attrs && $this->data->emptyLink) {
            $this->data->appendFormattedData('[');
            $this->data->emptyLink = false;
            $this->data->maybeAutomaticLink = null;
        }

        if ($isPremature) {
            $this->anchorProcessor->addPendingClosureForCurrentDepth();

            return;
        }

        $closedDepth = $currentDepth;
        $a = array_pop($this->astack);
        $this->anchorProcessor->popOnExplicitClose();

        if (null !== $a) {
            $this->finalizeAnchorClosure($a);
        }

        $this->flushPendingAnchorClosures($closedDepth);
    }

    private function finalizeAnchorClosure(array $attrs): void
    {
        if ($this->config->inlineLinks) {
            $this->data->prettyPrint = 0;
            $title = ParserUtilities::escapeMd($attrs['title'] ?? '');
            $href = $attrs['href'] ?? '';
            $url = UrlUtilities::urlJoin($this->config->baseUrl, $href);
            $titlePart = '' !== trim($title) ? ' "'.$title.'"' : '';
            $this->data->appendFormattedData(']('.ParserUtilities::escapeMd($url).$titlePart.')');

            return;
        }

        $index = $this->previousIndex($attrs);
        if (null !== $index) {
            $aProps = $this->data->a[$index];
        } else {
            ++$this->aCount;
            $aProps = new AnchorElement($attrs, $this->aCount, $this->data->outcount);
            $this->data->a[] = $aProps;
        }
        $this->data->appendFormattedData(']['.$aProps->count.']');
    }

    private function flushPendingAnchorClosures(?int $triggerDepth = null): void
    {
        $this->anchorProcessor->flushPending($triggerDepth, function (): void {
            $a = array_pop($this->astack);
            if (null !== $a) {
                $this->finalizeAnchorClosure($a);
            }
        });
    }

    private function previousIndex(array $attrs): ?int
    {
        /*
        :type attrs: dict

        :returns: The index of certain set of attributes (of a link) in the
        self.a list. If the set of attributes is not found, returns None
        :rtype: int
        */
        if (!\array_key_exists('href', $attrs) || null === $attrs['href']) {
            return null;
        }

        foreach ($this->data->a as $i => $a) {
            if (\array_key_exists('href', $a->attrs) && $a->attrs['href'] === $attrs['href']) {
                if (\array_key_exists('title', $a->attrs) || \array_key_exists('title', $attrs)) {
                    if (
                        \array_key_exists('title', $a->attrs)
                        && \array_key_exists('title', $attrs)
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
}
