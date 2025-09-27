<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

use Ineersa\PhpHtml2text\Elements\ListElement;
use Ineersa\PhpHtml2text\Utilities\ParserUtilities;

class ListsStructure
{
    public int $liCursor;

    /** @var list<ListElement> */
    public array $list = [];
    private array $listStructure;

    public function __construct(
        string $html,
        private readonly bool $googleDoc,
    ) {
        $this->liCursor = 0;
        $this->listStructure = $this->getListStructure($html);
    }

    public function ensureListStackForCurrentListItem(): void
    {
        ++$this->liCursor;

        if (!\array_key_exists($this->liCursor, $this->listStructure)) {
            return;
        }

        $targetStack = $this->listStructure[$this->liCursor];
        $this->alignListStack($targetStack);
    }

    /**
     * @param list<array{name:string,num:int}> $targetStack
     */
    public function alignListStack(array $targetStack): void
    {
        $targetDepth = \count($targetStack);

        while (\count($this->list) > $targetDepth) {
            array_pop($this->list);
        }

        for ($i = 0; $i < $targetDepth; ++$i) {
            $target = $targetStack[$i];
            if (!\array_key_exists($i, $this->list) || $this->list[$i]->name !== $target['name']) {
                while (\count($this->list) > $i) {
                    array_pop($this->list);
                }
                $this->list[] = new ListElement($target['name'], $target['num']);
            }
        }
    }

    /**
     * @return array<int, list<array{name:string,num:int}>>
     */
    private function getListStructure(string $html): array
    {
        $pattern = '/<'
            .'(\/)?'
            .'\s*([a-zA-Z0-9]+)'
            .'([^>]*)'
            .'>'
            .'/';

        // Collect CSS style definitions to emulate Google Docs list style resolution
        $styleDef = [];
        if ($this->googleDoc) {
            if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $styleBlocks)) {
                foreach ($styleBlocks[1] as $css) {
                    $styleDef = array_replace($styleDef, ParserUtilities::dumbCssParser($css));
                }
            }
        }

        $stack = [];
        $structure = [];
        $liIndex = 0;

        if (!preg_match_all($pattern, $html, $matches, \PREG_OFFSET_CAPTURE)) {
            return $structure;
        }

        $total = \count($matches[0]);
        for ($i = 0; $i < $total; ++$i) {
            $rawTag = $matches[0][$i][0];
            $isEnd = '' !== $matches[1][$i][0];
            $tagName = strtolower($matches[2][$i][0]);
            $attrString = $matches[3][$i][0] ?? '';
            $selfClosing = !$isEnd && str_ends_with(trim($rawTag), '/>');

            if ($isEnd) {
                if (\in_array($tagName, ['ol', 'ul'], true)) {
                    for ($j = \count($stack) - 1; $j >= 0; --$j) {
                        if ($stack[$j]['name'] === $tagName) {
                            array_splice($stack, $j, 1);
                            break;
                        }
                    }
                }

                continue;
            }

            if ('ol' === $tagName || 'ul' === $tagName) {
                // For Google Docs, the actual list type can be encoded in inline styles or CSS classes
                if ($this->googleDoc) {
                    $style = [];
                    // Merge class-based styles from <style> blocks
                    if (preg_match('/class\s*=\s*(["\'])(.*?)\1/i', $attrString, $classMatch)) {
                        $classes = preg_split('/\s+/', trim($classMatch[2])) ?: [];
                        foreach ($classes as $cssClass) {
                            if ('' === $cssClass) {
                                continue;
                            }
                            $css = $styleDef['.'.strtolower($cssClass)] ?? [];
                            $style = array_merge($style, $css);
                        }
                    }
                    // Merge inline style if present
                    if (preg_match('/style\s*=\s*(["\'])(.*?)\1/i', $attrString, $styleMatch)) {
                        $style = array_merge($style, ParserUtilities::dumbPropertyDict($styleMatch[2]));
                    }
                    $effective = ParserUtilities::googleListStyle($style);
                    if ('ul' === $effective || 'ol' === $effective) {
                        $tagName = $effective;
                    }
                }

                $numberingStart = 0;
                if ('ol' === $tagName) {
                    if (preg_match('/start\s*=\s*(?:"|\'|)(\d+)/i', $attrString, $startMatch)) {
                        $numberingStart = ((int) $startMatch[1]) - 1;
                    }
                }

                $stack[] = ['name' => $tagName, 'num' => $numberingStart];

                if ($selfClosing) {
                    array_pop($stack);
                }

                continue;
            }

            if ('li' === $tagName) {
                $currentStack = $stack;
                if (!$currentStack) {
                    $currentStack = [['name' => 'ul', 'num' => 0]];
                }
                $structure[++$liIndex] = array_map(
                    static fn (array $level): array => ['name' => $level['name'], 'num' => $level['num']],
                    $currentStack
                );
            }
        }

        return $structure;
    }
}
