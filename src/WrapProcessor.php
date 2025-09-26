<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

use function Symfony\Component\String\u;

class WrapProcessor
{
    public function __construct(
        private Config $config,
    ) {
    }

    /**
     * Wrap all paragraphs in the provided text.
     */
    public function process(string $text): string
    {
        if (!$this->config->bodyWidth) {
            return $text;
        }

        $result = '';
        $newlines = 0;
        $inlineLinks = $this->config->inlineLinks;
        // I cannot think of a better solution for now.
        // To avoid the non-wrap behaviour for entire paras
        // because of the presence of a link in it
        if (!$this->config->wrapLinks) {
            $inlineLinks = false;
        }
        $startCode = false;
        foreach (explode("\n", $text) as $para) {
            // If the text is between tri-backquote pairs, it's a code block;
            // don't wrap
            if ($this->config->backquoteCodeStyle && str_starts_with(ltrim($para), '```')) {
                $startCode = !$startCode;
            }
            if ($startCode) {
                $result .= $para."\n";
                $newlines = 1;
            } elseif ('' !== $para) {
                if (!Utils::skipwrap($para, $this->config->wrapLinks, $this->config->wrapListItems, $this->config->wrapTables)) {
                    $indent = '';
                    if (str_starts_with($para, '  '.$this->config->ulItemMark)) {
                        // list item continuation: add a double indent to the
                        // new lines
                        $indent = '    ';
                    } elseif (str_starts_with($para, '> ')) {
                        // blockquote continuation: add the greater than symbol
                        // to the new lines
                        $indent = '> ';
                    }
                    $wrapped = $this->wrapParagraph($para, $this->config->bodyWidth, $indent);
                    $result .= implode("\n", $wrapped);
                    if (str_ends_with($para, '  ')) {
                        // Preserve explicit two-space line breaks without duplicating spaces
                        // The trailing two spaces are already part of $para
                        $result .= "\n";
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

        if ($this->config->padTables) {
            return Utils::padTablesInText($result);
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

        $hasExplicitLineBreak = str_ends_with($text, '  ');
        $wrapped = u($text)->wordwrap($width, "\n", false)->toString();
        $lines = explode("\n", $wrapped);
        foreach ($lines as $index => $line) {
            $lines[$index] = rtrim($line, " \t");
        }
        if ($hasExplicitLineBreak && $lines) {
            $last = array_key_last($lines);
            $lines[$last] .= '  ';
        }
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
}
