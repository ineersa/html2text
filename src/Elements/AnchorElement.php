<?php

declare(strict_types=1);

namespace Ineersa\Html2text\Elements;

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
