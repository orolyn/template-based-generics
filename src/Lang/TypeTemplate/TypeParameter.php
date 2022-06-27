<?php

namespace Orolyn\Lang\TypeTemplate;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class TypeParameter
{
    public function __construct(
        public string $name
    ) {
    }
}
