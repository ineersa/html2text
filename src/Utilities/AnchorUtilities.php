<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text\Utilities;

final class AnchorUtilities
{
    /**
     * Compute anchor nesting depth information from the original HTML source.
     * @return array{0: list<int>, 1: list<int>, 2: list<array{text: string, depth: int}>}
     */
    public static function compute(string $html): array
    {
        if ('' === $html) {
            return [[], [], []];
        }

        $startDepths = [];
        $closeDepths = [];
        $textDepths = [];
        $depth = 0;

        $tokens = preg_split('/(<[^>]+>)/', $html, -1, \PREG_SPLIT_DELIM_CAPTURE);
        foreach ($tokens as $token) {
            if ('' === $token) {
                continue;
            }

            if ('<' === $token[0]) {
                if (1 === preg_match('/^<\s*\/\s*a\b/i', $token)) {
                    if ($depth > 0) {
                        $closeDepths[] = $depth;
                        --$depth;
                    }

                    continue;
                }

                if (1 === preg_match('/^<\s*a\b/i', $token)) {
                    ++$depth;
                    $startDepths[] = $depth;

                    if (1 === preg_match('/\/\s*>$/', $token)) {
                        $closeDepths[] = $depth;
                        --$depth;
                    }
                }

                continue;
            }

            $textDepths[] = [
                'text' => $token,
                'depth' => $depth,
            ];
        }

        return [$startDepths, $closeDepths, $textDepths];
    }
}
