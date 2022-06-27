<?php

namespace Orolyn\Lang\TypeTemplate;

use Attribute;
use InvalidArgumentException;
use ReflectionClass;

#[Attribute(Attribute::TARGET_CLASS)]
class Definition
{
    public array $typeArguments = [];
    private bool $isTypeParameter = false;

    public function __construct(
        public string $type,
        Definition ...$definitions
    ) {
        if (!class_exists($this->type) && !interface_exists($this->type)) {
            if (!preg_match('/[^\d]\w*/', $this->type)) {
                throw new InvalidArgumentException('Type must be a class name, a type parameter, or primitive');
            }

            $this->isTypeParameter = true;

            return;
        }

        $reflectionClass = new ReflectionClass($this->type);
        $typeParameters = $reflectionClass->getAttributes(TypeParameter::class);

        for ($i = 0; $i < count($definitions); $i++) {
            /** @var TypeParameter $typeParameter */
            $typeParameter = $typeParameters[$i]->newInstance();
            $this->typeArguments[] = new TypeArgument($typeParameter, $definitions[$i]);
        }
    }

    public function getTypeName(): string
    {
        if ($this->isBaseType()) {
            return $this->type;
        }

        $baseClassName = preg_replace('/(?:.+\\\\)?(\w+)$/', '$1', $this->type);

        if (empty($this->typeArguments)) {
            return $baseClassName;
        }

        return sprintf('%s_%s', $baseClassName, sha1($this->toString()));
    }

    public function getNamespacedTypeName(): string
    {
        if (empty($this->typeArguments) || $this->isBaseType()) {
            return $this->type;
        }

        return sprintf('%s_%s', $this->type, sha1($this->toString()));
    }

    public function toString(): string
    {
        return $this;
    }

    public function __toString(): string
    {
        if (empty($this->typeArguments) || $this->isBaseType()) {
            return $this->type;
        }

        $arguments = [];

        foreach ($this->typeArguments as $typeArgument) {
            $arguments[] = $typeArgument->targetDefinition->__toString();
        }

        return sprintf('%s<%s>', $this->type, implode(', ', $arguments));
    }

    /**
     * @param TypeArgument[] $typeArguments
     * @return Definition
     */
    public function createTypedDefinition(array $typeArguments): Definition
    {
        $definition = new Definition($this->type);

        foreach ($typeArguments as $newArgument) {
            if ($definition->type === $newArgument->typeParameter->name) {
                $definition->type = TypeBuilder::getType($newArgument->targetDefinition);

                return $definition;
            }
        }

        foreach ($this->typeArguments as $currentArgument) {
            $definition->typeArguments[] = new TypeArgument(
                $currentArgument->typeParameter,
                $currentArgument->targetDefinition->createTypedDefinition($typeArguments)
            );
        }

        return $definition;
    }

    public function isTypeParameter(): bool
    {
        return $this->isTypeParameter;
    }

    public function isBaseType(): bool
    {


        return in_array(strtolower($this->type), self::getBaseTypes());
    }

    public static function getBaseTypes(): array
    {
        static $types;

        if (null === $types) {
            $types = [
                'self',
                'static',
                'parent',
                'array',
                'callable',
                'bool',
                'float',
                'int',
                'string',
                'iterable',
                'object',
                'mixed',
                'resource',
                'void',
                'null'
            ];
        }

        return $types;
    }
}
