<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

/**
 * Tracks whether <tr> elements had explicit closing tags in the original markup.
 */
final class TrProcessor
{
    /** @var list<bool> */
    private array $explicitClosings = [];

    /** @var list<int> */
    private array $startStack = [];

    private int $startCounter = 0;

    public function __construct(string $html)
    {
        $this->prepareMetadata($html);
    }

    public function start(): void
    {
        $this->startStack[] = $this->startCounter;
        ++$this->startCounter;
    }

    public function end(): bool
    {
        $index = array_pop($this->startStack);
        if (null === $index) {
            return true;
        }

        return $this->explicitClosings[$index] ?? true;
    }

    private function prepareMetadata(string $html): void
    {
        $pattern = '/<(\/)?(table|tr)\b[^>]*>/i';
        $matches = [];
        if (0 === preg_match_all($pattern, $html, $matches, \PREG_SET_ORDER)) {
            return;
        }

        /** @var list<list<int>> $tableStack */
        $tableStack = [];
        $currentTrIndex = 0;
        foreach ($matches as $match) {
            $isClosing = '/' === ($match[1] ?? '');
            $tagName = strtolower($match[2] ?? '');

            if ('table' === $tagName) {
                if ($isClosing) {
                    array_pop($tableStack);
                } else {
                    $tableStack[] = [];
                }

                continue;
            }

            if ('tr' !== $tagName) {
                continue;
            }

            $tableKey = array_key_last($tableStack);

            if ($isClosing) {
                if (null === $tableKey) {
                    continue;
                }
                $openTrs = &$tableStack[$tableKey];
                $trIndex = array_pop($openTrs);
                unset($openTrs);
                if (null === $trIndex) {
                    continue;
                }
                $this->explicitClosings[$trIndex] = true;

                continue;
            }

            $this->explicitClosings[$currentTrIndex] = false;
            if (null !== $tableKey) {
                $tableStack[$tableKey][] = $currentTrIndex;
            }
            ++$currentTrIndex;
        }
    }
}

