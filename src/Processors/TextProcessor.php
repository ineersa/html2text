<?php

declare(strict_types=1);

namespace Ineersa\Html2text\Processors;

use Ineersa\Html2text\Config;
use Ineersa\Html2text\Constants;
use Ineersa\Html2text\DataContainer;
use Ineersa\Html2text\Utilities\ParserUtilities;

class TextProcessor
{
    private const PLACEHOLDER_PREFIX = '__PH2T__';
    private const PLACEHOLDER_SUFFIX = '__';

    public function __construct(
        private Config $config,
        private DataContainer $data,
    ) {
    }

    public function process(string $text): void
    {
        // Split runs of text on our synthetic entity placeholders (e.g. __PH2T__ENT_mdash__).
        $pattern = '/'.preg_quote(self::PLACEHOLDER_PREFIX, '/').'(CHAR|ENT)_([^'.preg_quote(self::PLACEHOLDER_SUFFIX, '/').']+)'.preg_quote(self::PLACEHOLDER_SUFFIX, '/').'/';
        $offset = 0;
        $length = \strlen($text);

        while (preg_match($pattern, $text, $matches, \PREG_OFFSET_CAPTURE, $offset)) {
            [$placeholder, $position] = $matches[0];
            $position = (int) $position;
            if ($position > $offset) {
                $segment = substr($text, $offset, $position - $offset);
                $this->data->push($this->normalizePlainText($segment));
            }

            $converted = $this->convertPlaceholder($matches[1][0], $matches[2][0]);
            if ('' !== $converted) {
                $this->data->push($converted, true);
            }

            $offset = $position + \strlen($placeholder);
        }

        if ($offset < $length) {
            $remaining = substr($text, $offset);
            $this->data->push($this->normalizePlainText($remaining));
        }
    }

    public function decodeAttributePlaceholders(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        // Attributes use the same placeholder encoding as text nodes; decode them deterministically.
        $pattern = '/'.preg_quote(self::PLACEHOLDER_PREFIX, '/').'(CHAR|ENT)_([^'.preg_quote(self::PLACEHOLDER_SUFFIX, '/').']+)'.preg_quote(self::PLACEHOLDER_SUFFIX, '/').'/';
        $offset = 0;
        $result = '';
        $length = \strlen($value);

        while (preg_match($pattern, $value, $matches, \PREG_OFFSET_CAPTURE, $offset)) {
            [$placeholder, $position] = $matches[0];
            $position = (int) $position;
            if ($position > $offset) {
                $result .= substr($value, $offset, $position - $offset);
            }

            $result .= $this->convertPlaceholder($matches[1][0], $matches[2][0]);
            $offset = $position + \strlen($placeholder);
        }

        if ($offset < $length) {
            $result .= substr($value, $offset);
        }

        return $this->normalizePlainText($result);
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
        $replacements = ParserUtilities::controlCharacterReplacements();
        $c = $replacements[$c] ?? $c;

        if (!$this->config->unicodeSnob) {
            $unifiable = ParserUtilities::unifiableN();
            if (\array_key_exists($c, $unifiable)) {
                return $unifiable[$c];
            }
        }

        return mb_chr($c, 'UTF-8');
    }

    public function entityref(string $c): string
    {
        if (!$this->config->unicodeSnob && \array_key_exists($c, Constants::UNIFIABLE)) {
            return Constants::UNIFIABLE[$c];
        }
        $decoded = html_entity_decode('&'.$c.';', \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        if ('nbsp' === $c) {
            return Constants::UNIFIABLE['nbsp'];
        }

        return $decoded;
    }

    private function convertPlaceholder(string $type, string $value): string
    {
        if ('CHAR' === $type) {
            return $this->charref($value);
        }

        if ('ENT' === $type) {
            return $this->entityref($value);
        }

        throw new \LogicException('Unsupported placeholder type.');
    }

    private function normalizePlainText(string $text): string
    {
        if ('' === $text) {
            return $text;
        }

        $text = str_replace(["\u{200E}", "\u{200F}"], '', $text);

        $nbsp = html_entity_decode('&nbsp;', \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        if ('' !== $nbsp) {
            $placeholder = Constants::UNIFIABLE['nbsp'];
            $text = str_replace($nbsp, $placeholder, $text);
        }

        return $text;
    }
}
