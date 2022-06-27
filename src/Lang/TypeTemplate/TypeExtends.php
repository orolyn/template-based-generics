<?php

namespace Orolyn\Lang\TypeTemplate;

use Attribute;
use Orolyn\Lang\TypeTemplate\TypeArgument;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
class TypeExtends
{
    public function __construct(
        public Definition $definition
    ) {
    }
}
