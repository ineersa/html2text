#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$arguments = $argv;
array_shift($arguments);


$source = $arguments[0];
if (!is_file($source)) {
    fwrite(\STDERR, sprintf("File not found: %s\n", $source));
    exit(1);
}
$html = (string) file_get_contents($source);

$config = new Ineersa\PhpHtml2text\Config(protectLinks: true);
$html2Markdown = new Ineersa\PhpHtml2text\HTML2Markdown($config);
$markdown = $html2Markdown($html);

if (!str_ends_with($markdown, "\n")) {
    $markdown .= \PHP_EOL;
}

$testFile = __DIR__.'/../markdown_test';
file_put_contents($testFile, $markdown);

$sourceMd = str_replace('.html', '.md', $source);

$diff = shell_exec(sprintf('diff --unified --color=always -p "%s" "%s"', $sourceMd, $testFile));
if ($diff) {
    fwrite(\STDOUT, $diff);
}
