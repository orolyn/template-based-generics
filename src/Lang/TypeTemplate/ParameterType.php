<?php

namespace Orolyn\Lang\TypeTemplate;

use Attribute;
use Orolyn\Lang\TypeTemplate\Definition;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class ParameterType
{
    public Definition $definition;

    public function __construct(
        public string $parameterName,
        Definition|string $definition
    ) {
        if (is_string($definition)) {
            $definition = new Definition($definition);
        }

        $this->definition = $definition;
    }
}
