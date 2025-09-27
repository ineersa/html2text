<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text\Utilities;

use function Symfony\Component\String\u;

final class StringUtilities
{
    private const int KIND_DEFAULT = 0;
    private const int KIND_SPACE = 1;
    private const int KIND_HYPHEN_BREAK = 2;
    private const int KIND_BREAK_MARKER = 3;

    /**
     * This method ripped from https://github.com/symfony/string/blob/f96476035142921000338bad71e5247fbc138872/AbstractString.php#L663
     * Added hyphen aware break.
     */
    public static function wordwrap(string $string, int $width = 75, string $break = "\n", bool $cut = false): string
    {
        if ($width <= 0) {
            return $string;
        }

        $lines = '' !== $break ? u($string)->split($break) : [u($string)];

        if (1 === \count($lines) && '' === (string) $lines[0]) {
            return '';
        }

        $chars = [];
        $mask = '';
        $charKinds = [];

        foreach ($lines as $lineIndex => $line) {
            if ($lineIndex > 0) {
                $chars[] = $break;
                $mask .= '#';
                $charKinds[] = self::KIND_BREAK_MARKER;
            }

            $lineString = (string) $line;
            if ('' === $lineString) {
                continue;
            }

            $characters = self::chunkToArray($lineString);
            $charCount = \count($characters);

            for ($i = 0; $i < $charCount; ++$i) {
                $char = $characters[$i];
                $chars[] = $char;

                if (' ' === $char) {
                    $mask .= ' ';
                    $charKinds[] = self::KIND_SPACE;
                    continue;
                }

                if (!$cut && self::isHyphenBreakpoint($char, $characters[$i - 1] ?? null, $characters[$i + 1] ?? null)) {
                    $mask .= ' ';
                    $charKinds[] = self::KIND_HYPHEN_BREAK;
                    continue;
                }

                $mask .= '?';
                $charKinds[] = self::KIND_DEFAULT;
            }
        }

        $wrapped = '';
        $mask = \wordwrap($mask, $width, '#', $cut);
        $maskCursor = -1;
        $maskIndex = -1;
        $charIndex = 0;

        while (false !== $maskCursor = \strpos($mask, '#', $maskCursor + 1)) {
            for (++$maskIndex; $maskIndex < $maskCursor; ++$maskIndex) {
                $wrapped .= $chars[$charIndex];
                unset($chars[$charIndex], $charKinds[$charIndex]);
                ++$charIndex;
            }

            if (isset($chars[$charIndex])) {
                $kind = $charKinds[$charIndex] ?? self::KIND_DEFAULT;

                if (self::KIND_BREAK_MARKER === $kind || self::KIND_SPACE === $kind) {
                    unset($chars[$charIndex], $charKinds[$charIndex]);
                    ++$charIndex;
                } elseif (self::KIND_HYPHEN_BREAK === $kind) {
                    $wrapped .= $chars[$charIndex];
                    unset($chars[$charIndex], $charKinds[$charIndex]);
                    ++$charIndex;
                }
            }

            $wrapped .= $break;
        }

        return $wrapped.implode('', $chars);
    }

    /**
     * @return list<string>
     */
    private static function chunkToArray(string $line): array
    {
        $chunks = \iterator_to_array(u($line)->chunk(), false);
        $characters = [];

        foreach ($chunks as $chunk) {
            $characters[] = (string) $chunk;
        }

        return $characters;
    }

    private static function isHyphenBreakpoint(string $char, ?string $previous, ?string $next): bool
    {
        if ('-' !== $char) {
            return false;
        }

        if (null === $previous || null === $next) {
            return false;
        }

        if ('-' === $previous || '-' === $next) {
            return false;
        }

        if (1 !== \preg_match('/[\p{L}\p{N}]/u', $previous)) {
            return false;
        }

        if (1 !== \preg_match('/[\p{L}\p{N}]/u', $next)) {
            return false;
        }

        return true;
    }
}
