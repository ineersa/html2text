<?php

declare(strict_types=1);

namespace Ineersa\Html2text\Elements;

final class ListElement
{
    public function __construct(
        public string $name,
        public int $num,
    ) {
    }
}
