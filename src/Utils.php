<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text;

final class Utils
{
    /**
     * Equivalent of the Python ``unifiable_n`` map derived from HTML entities.
     *
     * @return array<int, string>
     */
    public static function unifiableN(): array
    {
        static $unifiableN = null;
        if (null === $unifiableN) {
            $unifiableN = self::buildUnifiableN();
        }

        return $unifiableN;
    }

    /**
     * https://html.spec.whatwg.org/multipage/parsing.html#character-reference-code.
     *
     * @return array<int, int>
     */
    public static function controlCharacterReplacements(): array
    {
        return Constants::CONTROL_CHARACTER_REPLACEMENTS;
    }

    public static function hn(string $tag): int
    {
        if ('' !== $tag && 'h' === $tag[0] && 2 === \strlen($tag)) {
            $n = $tag[1];
            if ($n > '0' && $n <= '9') {
                return (int) $n;
            }
        }

        return 0;
    }

    /**
     * :returns: A hash of css attributes.
     *
     * @return array<string, string>
     */
    public static function dumbPropertyDict(string $style): array
    {
        $result = [];
        foreach (explode(';', $style) as $component) {
            if (!str_contains($component, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $component, 2);
            $key = strtolower(trim($key));
            $value = strtolower(trim($value));
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * :type data: str.
     *
     * :returns: A hash of css selectors, each of which contains a hash of
     * css attributes.
     * :rtype: dict
     *
     * @return array<string, array<string, string>>
     */
    public static function dumbCssParser(string $data): array
    {
        // remove @import sentences
        $data .= ';';
        $importIndex = strpos($data, '@import');
        while (false !== $importIndex) {
            $semicolonIndex = strpos($data, ';', $importIndex);
            if (false === $semicolonIndex) {
                $data = substr($data, 0, $importIndex);
                break;
            }

            $data = substr($data, 0, $importIndex).substr($data, $semicolonIndex + 1);
            $importIndex = strpos($data, '@import');
        }

        // parse the css. reverted from dictionary comprehension in order to
        // support older pythons
        $pairs = [];
        foreach (explode('}', $data) as $chunk) {
            $trimmed = trim($chunk);
            if ('' === $trimmed || !str_contains($chunk, '{')) {
                continue;
            }

            $segments = explode('{', $chunk, 2);
            if (2 !== \count($segments)) {
                continue;
            }

            [$selector, $declarations] = $segments;
            $pairs[] = [trim($selector), $declarations];
        }

        $elements = [];
        foreach ($pairs as [$selector, $declarations]) {
            $elements[$selector] = self::dumbPropertyDict($declarations);
        }

        return $elements;
    }

    /**
     * :type attrs: dict
     * :type style_def: dict
     * :type style_def: dict.
     *
     * :returns: A hash of the 'final' style attributes of the element
     * :rtype: dict
     *
     * @param array<string, string|null>           $attrs
     * @param array<string, array<string, string>> $styleDef
     * @param array<string, string>                $parentStyle
     *
     * @return array<string, string>
     */
    public static function elementStyle(
        array $attrs,
        array $styleDef,
        array $parentStyle,
    ): array {
        $style = $parentStyle;
        if (\array_key_exists('class', $attrs) && null !== $attrs['class']) {
            $classes = preg_split('/\s+/', trim($attrs['class']));
            if (false !== $classes) {
                foreach ($classes as $cssClass) {
                    if ('' === $cssClass) {
                        continue;
                    }

                    $cssStyle = $styleDef['.'.$cssClass] ?? [];
                    $style = array_merge($style, $cssStyle);
                }
            }
        }

        if (\array_key_exists('style', $attrs) && null !== $attrs['style']) {
            $immediateStyle = self::dumbPropertyDict($attrs['style']);
            $style = array_merge($style, $immediateStyle);
        }

        return $style;
    }

    /**
     * Finds out whether this is an ordered or unordered list.
     *
     * :type style: dict
     *
     * :rtype: str
     *
     * @param array<string, string> $style
     */
    public static function googleListStyle(array $style): string
    {
        if (\array_key_exists('list-style-type', $style)) {
            $listStyle = $style['list-style-type'];
            if (\in_array($listStyle, ['disc', 'circle', 'square', 'none'], true)) {
                return 'ul';
            }
        }

        return 'ol';
    }

    /**
     * Check if the style of the element has the 'height' attribute
     * explicitly defined.
     *
     * :type style: dict
     *
     * :rtype: bool
     *
     * @param array<string, string> $style
     */
    public static function googleHasHeight(array $style): bool
    {
        return \array_key_exists('height', $style);
    }

    /**
     * :type style: dict.
     *
     * :returns: A list of all emphasis modifiers of the element
     * :rtype: list
     *
     * @param array<string, string> $style
     *
     * @return list<string>
     */
    public static function googleTextEmphasis(array $style): array
    {
        $emphasis = [];
        if (\array_key_exists('text-decoration', $style)) {
            $emphasis[] = $style['text-decoration'];
        }
        if (\array_key_exists('font-style', $style)) {
            $emphasis[] = $style['font-style'];
        }
        if (\array_key_exists('font-weight', $style)) {
            $emphasis[] = $style['font-weight'];
        }

        return $emphasis;
    }

    /**
     * Check if the css of the current element defines a fixed width font.
     *
     * :type style: dict
     *
     * :rtype: bool
     *
     * @param array<string, string> $style
     */
    public static function googleFixedWidthFont(array $style): bool
    {
        $fontFamily = '';
        if (\array_key_exists('font-family', $style)) {
            $fontFamily = $style['font-family'];
        }

        return 'courier new' === $fontFamily || 'consolas' === $fontFamily;
    }

    /**
     * Extract numbering from list element attributes.
     *
     * :type attrs: dict
     *
     * :rtype: int or None
     *
     * @param array<string, string|null> $attrs
     */
    public static function listNumberingStart(array $attrs): int
    {
        if (\array_key_exists('start', $attrs) && null !== $attrs['start']) {
            $value = trim($attrs['start']);
            if ('' !== $value && 1 === preg_match('/^-?\d+$/', $value)) {
                return (int) $value - 1;
            }
        }

        return 0;
    }

    public static function skipwrap(
        string $para,
        bool $wrapLinks,
        bool $wrapListItems,
        bool $wrapTables,
    ): bool {
        // If it appears to contain a link
        // don't wrap
        if (!$wrapLinks && 1 === preg_match(Constants::RE_LINK, $para)) {
            return true;
        }
        // If the text begins with four spaces or one tab, it's a code block;
        // don't wrap
        if (str_starts_with($para, '    ') || str_starts_with($para, "\t")) {
            return true;
        }

        // If the text begins with only two "--", possibly preceded by
        // whitespace, that's an emdash; so wrap.
        $stripped = ltrim($para);
        if (
            str_starts_with($stripped, '--')
            && mb_strlen($stripped) > 2
            && '-' !== mb_substr($stripped, 2, 1)
        ) {
            return false;
        }

        // I'm not sure what this is for; I thought it was to detect lists,
        // but there's a <br>-inside-<span> case in one of the tests that
        // also depends upon it.
        if (str_starts_with($stripped, '-') || str_starts_with($stripped, '*')) {
            if (!str_starts_with($stripped, '**')) {
                return !$wrapListItems;
            }
        }

        // If text contains a pipe character it is likely a table
        if (!$wrapTables && 1 === preg_match(Constants::RE_TABLE, $para)) {
            return true;
        }

        // If the text begins with a single -, *, or +, followed by a space,
        // or an integer, followed by a ., followed by a space (in either
        // case optionally proceeded by whitespace), it's a list; don't wrap.
        if (self::regexMatchesAtStart(Constants::RE_ORDERED_LIST_MATCHER, $stripped)) {
            return true;
        }
        if (self::regexMatchesAtStart(Constants::RE_UNORDERED_LIST_MATCHER, $stripped)) {
            return true;
        }

        return false;
    }

    /**
     * Escapes markdown-sensitive characters within other markdown
     * constructs.
     */
    public static function escapeMd(string $text): string
    {
        return (string) preg_replace(Constants::RE_MD_CHARS_MATCHER, '\\\\$1', $text);
    }

    /**
     * Escapes markdown-sensitive characters across whole document sections.
     */
    public static function escapeMdSection(string $text, bool $snob = false): string
    {
        $text = (string) preg_replace(Constants::RE_MD_BACKSLASH_MATCHER, '\\\\$1', $text);

        if ($snob) {
            $text = (string) preg_replace(Constants::RE_MD_CHARS_MATCHER_ALL, '\\\\$1', $text);
        }

        $text = (string) preg_replace_callback(
            Constants::RE_MD_DOT_MATCHER,
            static fn (array $match): string => $match[1].'\\'.$match[2],
            $text
        );
        $text = (string) preg_replace_callback(
            Constants::RE_MD_PLUS_MATCHER,
            static fn (array $match): string => $match[1].'\\'.$match[2],
            $text
        );
        $text = (string) preg_replace_callback(
            Constants::RE_MD_DASH_MATCHER,
            static fn (array $match): string => $match[1].'\\'.$match[2],
            $text
        );

        return $text;
    }

    /**
     * Given the lines of a table
     * padds the cells and returns the new lines.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    public static function reformatTable(array $lines, int $rightMargin): array
    {
        // Safeguard: handle empty or invalid input gracefully (e.g., empty table)
        if (0 === \count($lines)) {
            return [];
        }

        // find the maximum width of the columns
        $firstLineColumns = explode('|', $lines[0]);
        $maxWidth = [];
        foreach ($firstLineColumns as $column) {
            $maxWidth[] = mb_strlen(rtrim($column)) + $rightMargin;
        }

        $maxCols = \count($maxWidth);
        foreach ($lines as $line) {
            $cols = [];
            foreach (explode('|', $line) as $column) {
                $cols[] = rtrim($column);
            }

            $numCols = \count($cols);

            // don't drop any data if colspan attributes result in unequal lengths
            if ($numCols < $maxCols) {
                $cols = array_merge($cols, array_fill(0, $maxCols - $numCols, ''));
            } elseif ($maxCols < $numCols) {
                for ($i = $maxCols; $i < $numCols; ++$i) {
                    $maxWidth[] = mb_strlen($cols[$i]) + $rightMargin;
                }
                $maxCols = $numCols;
            }

            foreach ($cols as $index => $value) {
                $width = mb_strlen($value) + $rightMargin;
                if (!\array_key_exists($index, $maxWidth) || $width > $maxWidth[$index]) {
                    $maxWidth[$index] = $width;
                }
            }
        }

        // reformat
        $newLines = [];
        foreach ($lines as $line) {
            $cols = [];
            foreach (explode('|', $line) as $column) {
                $cols[] = rtrim($column);
            }

            $trimmed = trim($line);
            if ('' !== $trimmed && strspn($trimmed, '-|') === \strlen($trimmed)) {
                $filler = '-';
                $newCols = [];
                foreach ($cols as $index => $value) {
                    $target = $maxWidth[$index] ?? (mb_strlen($value) + $rightMargin);
                    $newCols[] = $value.str_repeat($filler, $target - mb_strlen($value));
                }
                $newLines[] = '|-'.implode('|', $newCols).'|';
            } else {
                $filler = ' ';
                $newCols = [];
                foreach ($cols as $index => $value) {
                    $target = $maxWidth[$index] ?? (mb_strlen($value) + $rightMargin);
                    $newCols[] = $value.str_repeat($filler, $target - mb_strlen($value));
                }
                $newLines[] = '| '.implode('|', $newCols).'|';
            }
        }

        return $newLines;
    }

    /**
     * Provide padding for tables in the text.
     */
    public static function padTablesInText(string $text, int $rightMargin = 1): string
    {
        $lines = explode("\n", $text);
        /** @var list<string> $tableBuffer */
        $tableBuffer = [];
        $tableStarted = false;
        $newLines = [];
        foreach ($lines as $line) {
            // Toggle table started
            if (str_contains($line, Constants::TABLE_MARKER_FOR_PAD)) {
                $tableStarted = !$tableStarted;
                if (!$tableStarted) {
                    $table = self::reformatTable($tableBuffer, $rightMargin);
                    $newLines = array_merge($newLines, $table);
                    $tableBuffer = [];
                    $newLines[] = '';
                }
                continue;
            }
            // Process lines
            if ($tableStarted) {
                $tableBuffer[] = $line;
            } else {
                $newLines[] = $line;
            }
        }

        return implode("\n", $newLines);
    }

    public static function googleNestCount(array $style, int $googleListIndent): int
    {
        /*
        Calculate the nesting count of google doc lists

        :type style: dict

        :rtype: int
        */
        $nestCount = 0;
        if (\array_key_exists('margin-left', $style)) {
            $value = $style['margin-left'];
            if (null !== $value) {
                $trimmed = trim($value);
                if (preg_match('/^(-?\d+(?:\.\d+)?)(px|pt)?$/i', $trimmed, $matches)) {
                    $margin = (float) $matches[1];
                    if ($googleListIndent > 0) {
                        $nestCount = (int) floor(round($margin) / $googleListIndent);
                    }
                }
            }
        }

        return $nestCount;
    }

    /**
     * @return array<int, string>
     */
    private static function buildUnifiableN(): array
    {
        $result = [];
        foreach (Constants::UNIFIABLE as $entity => $replacement) {
            if ('nbsp' === $entity) {
                continue;
            }

            $decoded = html_entity_decode('&'.$entity.';', \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
            if ($decoded === '&'.$entity.';') {
                continue;
            }

            $encoded = mb_convert_encoding($decoded, 'UCS-4LE', 'UTF-8');
            $codepoint = unpack('V', $encoded);
            if (false === $codepoint) {
                continue;
            }

            $result[$codepoint[1]] = $replacement;
        }

        return $result;
    }

    private static function regexMatchesAtStart(string $pattern, string $subject): bool
    {
        if (1 !== preg_match($pattern, $subject, $matches, \PREG_OFFSET_CAPTURE)) {
            return false;
        }

        return 0 === $matches[0][1];
    }
}
