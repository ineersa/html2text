<?php

declare(strict_types=1);

namespace Ineersa\PhpHtml2text\Elements;

final class AnchorElement
{
    /** @param array<string, string|null> $attrs */
    public function __construct(
        public array $attrs,
        public int $count,
        public int $outcount,
    ) {
    }
}
