<?php

namespace Orolyn\Lang\TypeTemplate;

class TypeArgument
{
    public Definition $targetDefinition;

    public function __construct(
        public TypeParameter $typeParameter,
        Definition|string $targetDefinition
    ) {
        if (is_string($targetDefinition)) {
            $targetDefinition = new Definition($targetDefinition);
        }

        $this->targetDefinition = $targetDefinition;
    }

    public function toString(): string
    {
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('[%s => %s]', $this->typeParameter->name, $this->targetDefinition->toString());
    }
}
