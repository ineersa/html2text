<?php

declare(strict_types=1);

namespace Tests;

use Ineersa\PhpHtml2text\HTML2Text;
use function basename;
use function file_get_contents;
use function glob;
use function preg_replace;
use function rtrim;
use function sort;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function Ineersa\PhpHtml2text\html2text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Html2TextTest extends TestCase
{
    #[DataProvider('moduleCases')]
    public function testModule(string $filename, array $moduleArgs): void
    {
        $baseurl = $moduleArgs['baseurl'] ?? '';
        $bodyWidth = $moduleArgs['bodyWidth'] ?? null;
        $converter = new HTML2Text(null, $baseurl, $bodyWidth);

        foreach ($moduleArgs as $key => $value) {
            if ('baseurl' === $key || 'bodyWidth' === $key) {
                continue;
            }
            if (!property_exists($converter, $key)) {
                continue;
            }
            $converter->$key = $value;
        }

        $expected = self::getBaseline($filename);
        $html = self::cleanupEol((string) file_get_contents($filename));
        $actual = $converter->handle($html);

        self::assertSame(rtrim($expected), rtrim($actual));
    }

    #[DataProvider('functionCases')]
    public function testFunction(string $filename, array $functionArgs): void
    {
        $html = (string) file_get_contents($filename);
        $html = self::cleanupEol($html);
        $actual = html2text(
            $html,
            $functionArgs['baseurl'] ?? '',
            $functionArgs['bodywidth'] ?? null,
        );
        $expected = self::getBaseline($filename);

        self::assertSame(rtrim($expected), rtrim($actual));
    }

    public function testTagCallback(): void
    {
        $converter = new HTML2Text();
        $converter->tagCallback = function (HTML2Text $h2t, string $tag, array $attrs, bool $start): bool {
            if ('b' === $tag) {
                return true;
            }

            return false;
        };
        $ret = $converter->handle(
            'this is a <b>txt</b> and this is a <b class="skip">with text</b> and '
            .'some <i>italics</i> too.'
        );

        self::assertSame("this is a txt and this is a with text and some _italics_ too.\n\n", $ret);
    }

    public function testStrongEmptied(): void
    {
        /*
        """When strong is being set to empty, it should not mark it."""
        */
        $converter = new HTML2Text();
        $converter->emphasisMark = '_';
        $converter->strongMark = '';
        $string = 'A <b>B</b> <i>C</i>.';

        self::assertSame('A B _C_.\n\n', $converter->handle($string));
    }
    public static function moduleCases(): array
    {
        return self::collectTestData('module');
    }
    public static function functionCases(): array
    {
        return self::collectTestData('function');
    }
    private static function collectTestData(string $type): array
    {
        $cases = [];
        $files = glob(__DIR__.'/files/*.html');
        sort($files);
        foreach ($files as $file) {
            $base = strtolower(basename($file));
            [$moduleArgs, $functionArgs] = self::buildArgsForFile($base);
            $moduleArgs['baseurl'] = $moduleArgs['baseurl'] ?? '';
            if ('module' === $type) {
                $cases[basename($file, '.html')] = [$file, $moduleArgs];
            } elseif (null !== $functionArgs) {
                $cases[basename($file, '.html')] = [$file, $functionArgs];
            }
        }

        return $cases;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private static function buildArgsForFile(string $base): array
    {
        $module = [];
        $function = [];

        if (str_starts_with($base, 'default_image_alt')) {
            $module['defaultImageAlt'] = 'Image';
            $function = null;
        }

        if (str_starts_with($base, 'google')) {
            $module['googleDoc'] = true;
            $module['ulItemMark'] = '-';
            $module['bodyWidth'] = 0;
            $module['hideStrikethrough'] = true;
            $function = null;
        }

        if (str_contains($base, 'unicode')) {
            $module['unicodeSnob'] = true;
            $function = null;
        }

        if (str_contains($base, 'flip_emphasis')) {
            $module['emphasisMark'] = '*';
            $module['strongMark'] = '__';
            $function = null;
        }

        if (str_contains($base, 'escape_snob')) {
            $module['escapeSnob'] = true;
            $function = null;
        }

        if (str_contains($base, 'table_bypass')) {
            $module['bypassTables'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'table_ignore')) {
            $module['ignoreTables'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'bodywidth')) {
            $module['bodyWidth'] = 0;
            $function['bodywidth'] = 0;
        }

        if (str_starts_with($base, 'protect_links')) {
            $module['protectLinks'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'images_as_html')) {
            $module['imagesAsHtml'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'images_to_alt')) {
            $module['imagesToAlt'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'images_with_size')) {
            $module['imagesWithSize'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'single_line_break')) {
            $module['bodyWidth'] = 0;
            $module['singleLineBreak'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'no_inline_links')) {
            $module['inlineLinks'] = false;
            $function = null;
        }

        if (str_starts_with($base, 'no_mailto_links')) {
            $module['ignoreMailtoLinks'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'no_wrap_links')) {
            $module['wrapLinks'] = false;
            $function = null;
        }

        if (str_starts_with($base, 'mark_code')) {
            $module['markCode'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'backquote_code_style')) {
            $module['backquoteCodeStyle'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'pad_table')) {
            $module['padTables'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'wrap_list_items')) {
            $module['wrapListItems'] = true;
            $function = null;
        }

        if (str_starts_with($base, 'wrap_tables')) {
            $module['wrapTables'] = true;
            $function = null;
        }

        if ('inplace_baseurl_substitution.html' === $base) {
            $module['baseurl'] = 'http://brettterpstra.com';
            $module['bodyWidth'] = 0;
            $function = [
                'baseurl' => 'http://brettterpstra.com',
                'bodywidth' => 0,
            ];
        }

        if (in_array($base, ['sup_tag.html', 'sub_tag.html'], true)) {
            $module['includeSupSub'] = true;
            $function = null;
        }

        if (null !== $function) {
            $function['baseurl'] = $module['baseurl'] ?? '';
        }

        return [$module, $function];
    }

    private static function cleanupEol(string $input): string
    {
        $input = (string) preg_replace("/\r+/", "\r", $input);

        return str_replace("\r\n", "\n", $input);
    }

    private static function getBaseline(string $htmlFile): string
    {
        $expectedFile = preg_replace('/\.html$/', '.md', $htmlFile);
        $content = (string) file_get_contents($expectedFile);

        return rtrim(self::cleanupEol($content));
    }
}
