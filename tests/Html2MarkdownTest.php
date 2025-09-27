<?php

declare(strict_types=1);

namespace Tests;

use Ineersa\PhpHtml2text\Config;
use Ineersa\PhpHtml2text\HTML2Markdown;
use Ineersa\PhpHtml2text\Processors\TagProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Html2MarkdownTest extends TestCase
{
    #[DataProvider('moduleCases')]
    public function testConvert(string $filename, array $moduleArgs): void
    {
        $config = self::createConfig($moduleArgs);
        $converter = new HTML2Markdown($config);

        $expected = self::getBaseline($filename);
        $html = self::cleanupEol((string) file_get_contents($filename));
        $actual = $converter->convert($html);

        $this->assertIsString($actual);
        $this->assertSame(rtrim($expected), rtrim($actual));
    }

    #[DataProvider('functionCases')]
    public function testInvoke(string $filename, array $functionArgs): void
    {
        $config = self::createConfig($functionArgs);
        $converter = new HTML2Markdown($config);

        $html = self::cleanupEol((string) file_get_contents($filename));
        $actual = $converter($html);
        $expected = self::getBaseline($filename);

        $this->assertIsString($actual);
        $this->assertSame(rtrim($expected), rtrim($actual));
    }

    public function testTagCallback(): void
    {
        $config = self::createConfig([
            'tagCallback' => static function (TagProcessor $unusedProcessor, string $tag, array $attrs, bool $start): bool {
                if ('b' === $tag) {
                    return true;
                }

                return false;
            },
        ]);
        $converter = new HTML2Markdown($config);

        $actual = $converter->convert(
            'this is a <b>txt</b> and this is a <b class="skip">with text</b> and some <i>italics</i> too.'
        );

        $this->assertSame("this is a txt and this is a with text and some _italics_ too.\n\n", $actual);
    }

    public function testEmpty(): void
    {
        $converter = new HTML2Markdown(new Config());

        $actual = $converter->convert('');

        $this->assertSame('', $actual);
    }

    public function testStrongEmptied(): void
    {
        $config = self::createConfig([
            'emphasisMark' => '_',
            'strongMark' => '',
        ]);
        $converter = new HTML2Markdown($config);

        $this->assertSame("A B _C_.\n\n", $converter->convert('A <b>B</b> <i>C</i>.'));
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

        if (str_starts_with($base, 'url_utilities_coverage')) {
            $module['baseurl'] = 'http://user:pass@example.com:8080/dir/sub/';
            $function = [
                'baseurl' => 'http://user:pass@example.com:8080/dir/sub/',
            ];
        }

        if (\in_array($base, ['sup_tag.html', 'sub_tag.html'], true)) {
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

    private static function createConfig(array $options): Config
    {
        $normalized = [];
        $configParameters = self::configParameterMap();

        foreach ($options as $key => $value) {
            if (null === $value) {
                continue;
            }
            $normalizedKey = self::normalizeConfigKey($key);
            if (null === $normalizedKey) {
                continue;
            }
            if (!isset($configParameters[$normalizedKey])) {
                continue;
            }
            $normalized[$normalizedKey] = $value;
        }

        return new Config(...$normalized);
    }

    private static function normalizeConfigKey(string $key): ?string
    {
        return match ($key) {
            'baseurl' => 'baseUrl',
            'bodywidth' => 'bodyWidth',
            default => $key,
        };
    }

    /**
     * @return array<string, true>
     */
    private static function configParameterMap(): array
    {
        static $cache = null;
        if (null !== $cache) {
            return $cache;
        }

        $reflection = new \ReflectionClass(Config::class);
        $constructor = $reflection->getConstructor();
        $parameters = [];
        if (null !== $constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                $parameters[$parameter->getName()] = true;
            }
        }

        $cache = $parameters;

        return $cache;
    }
}
