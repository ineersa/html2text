# PHP Html2Text

[![CI](https://github.com/ineersa/php-html2text/actions/workflows/main.yml/badge.svg?branch=main)](https://github.com/ineersa/php-html2text/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/ineersa/php-html2text/branch/main/graph/badge.svg)](https://codecov.io/gh/ineersa/php-html2text)


`html2text` converts a page of HTML into clean, easy-to-read plain ASCII text. Better yet, that ASCII also happens to be valid Markdown (a text-to-HTML format).

It is a PHP port of [Alir3z4/html2text](https://github.com/Alir3z4/html2text) with few fixes and updates.

Functionality parity is checked via the test suite, which contains all the test cases from the original and more.
Most of the code was translated with AI with a lot of refactoring and fixes.


## How to install/requirements

Project is using new DOM extension for better HTML parser and requires `ext-libxml`. 
PHP version required - 8.4+ 

To install run composer command:
```bash
composer require ineersa/php-html2text
```

## Usage

Basic usage:

```php
$html = (string) file_get_contents($source);

$config = new Ineersa\Html2text\Config();
$html2Markdown = new Ineersa\Html2text\HTML2Markdown($config);
$markdown = $html2Markdown($html);
```

Config options are compatible with Python library:

```php
final readonly class Config
{
    public function __construct(
        /** Use Unicode characters instead of ASCII fallbacks. */
        public bool $unicodeSnob = false,
        /** Escape all special characters even if output is less readable. */
        public bool $escapeSnob = false,
        /** Append footnote links immediately after each paragraph. */
        public bool $linksEachParagraph = false,
        /** Wrap long lines at the configured column (0 disables wrapping). */
        public int $bodyWidth = 78,
        /** Skip internal anchors like href="#local-anchor". */
        public bool $skipInternalLinks = true,
        /** Render links using inline Markdown syntax. */
        public bool $inlineLinks = true,
        /** Surround links with angle brackets to prevent wraps. */
        public bool $protectLinks = false,
        /** Allow links to wrap across lines. */
        public bool $wrapLinks = true,
        /** Wrap list items at the configured body width. */
        public bool $wrapListItems = false,
        /** Wrap table output text. */
        public bool $wrapTables = false,
        /** Is Google Doc */
        public bool $googleDoc = false,
        /** Callback to apply at tag processing, $callback($this, $tag, $attrs, $start), should return true to break processing, false otherwise */
        public ?\Closure $tagCallback = null,
        /** Pixels Google uses to indent nested lists. */
        public int $googleListIndent = 36,
        /**
         * Values that indicate bold text in inline styles.
         *
         * @var string[]
         */
        public array $boldTextStyleValues = ['bold', '700', '800', '900'],
        /** Ignore anchor tags entirely. */
        public bool $ignoreAnchors = false,
        /** Ignore mailto links during conversion. */
        public bool $ignoreMailtoLinks = false,
        /** Drop all image tags from the output. */
        public bool $ignoreImages = false,
        /** Keep image tags rendered as raw HTML. */
        public bool $imagesAsHtml = false,
        /** Replace images with their alt text. */
        public bool $imagesToAlt = false,
        /** Include width/height attributes when preserving images. */
        public bool $imagesWithSize = false,
        /** Ignore text emphasis such as italics and bold. */
        public bool $ignoreEmphasis = false,
        /** Wrap inline code with custom markers. */
        public bool $markCode = false,
        /** Use backquotes instead of indentation for code blocks. */
        public bool $backquoteCodeStyle = false,
        /** Fallback alt text when an image omits it. */
        public string $defaultImageAlt = '',
        /** Pad tables to align cell widths. */
        public bool $padTables = false,
        /** Convert absolute links with identical href/text to <href> style. */
        public bool $useAutomaticLinks = true,
        /** Render tables as HTML instead of Markdown. */
        public bool $bypassTables = false,
        /** Ignore table tags but retain row content. */
        public bool $ignoreTables = false,
        /** Emit a single line break after block elements (requires width 0). */
        public bool $singleLineBreak = false,
        /** Use as the opening quotation mark for <q> tags. */
        public string $openQuote = '"',
        /** Use as the closing quotation mark for <q> tags. */
        public string $closeQuote = '"',
        /** Include <sup> and <sub> tags in the output. */
        public bool $includeSupSub = false,
        /** baseUrl to join with URLs if needed */
        public string $baseUrl = '',
        /** Emphasis marks */
        public string $ulItemMark = '*',
        public string $emphasisMark = '_',
        public string $strongMark = '**',
        /** hide strikethrough emphasis */
        public bool $hideStrikethrough = false,
    ) {
    }
}
```

## Development

You can find information about the repository in [AGENTS.md](./AGENTS.md)

Composer has scripts section with commands to run all required tools:
```json
"scripts": {
    "cs-fix": "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php",
    "phpstan": "vendor/bin/phpstan analyse -c phpstan.dist.neon",
    "tests": "vendor/bin/phpunit --colors=always --testdox",
    "coverage": "XDEBUG_MODE=coverage vendor/bin/phpunit --colors=always --testdox --coverage-text --coverage-html coverage/ --coverage-clover coverage/clover.xml",
    "tests-xdebug": "php -d xdebug.mode=debug -d xdebug.client_host=127.0.0.1 -d xdebug.client_port=9003 -d xdebug.start_with_request=yes vendor/bin/phpunit --colors=always --testdox"
  }
```

## License

This project is licensed under the [GNU General Public License v3.0 or later](LICENSE).

It is a PHP port of [Alir3z4/html2text](https://github.com/Alir3z4/html2text),  
which is licensed under the GPL as well.  
All credit goes to the original authors for their work.
