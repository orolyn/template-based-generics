<?php

namespace Orolyn\Lang\TypeTemplate;

use Attribute;
use Orolyn\Lang\TypeTemplate\Definition;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PropertyType
{
    public Definition $definition;

    public function __construct(
        Definition|string $definition
    ) {
        if (is_string($definition)) {
            $definition = new Definition($definition);
        }

        $this->definition = $definition;
    }
}
