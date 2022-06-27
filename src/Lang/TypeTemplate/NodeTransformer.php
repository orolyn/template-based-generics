<?php

namespace Orolyn\Lang\TypeTemplate;

use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use ReflectionAttribute;
use ReflectionClass;

class NodeTransformer extends NodeVisitorAbstract
{
    /**
     * @param ReflectionClass $reflectionClass
     * @param Definition $definition
     */
    public function __construct(
        private ReflectionClass $reflectionClass,
        private Definition $definition
    ) {
    }

    public function enterNode(Node $node)
    {
        $this->removeAttributes($node);

        switch (true) {
            case $node instanceof Node\Stmt\ClassLike:
                $node->name = new Node\Identifier($this->definition->getTypeName());
                $this->transformExtends($node, $this->definition->typeArguments);
                $this->addDefinitionAttribute($node, $this->definition);
                break;
            case $node instanceof Node\Stmt\ClassMethod:
                $this->transformParameters($node, $this->definition->typeArguments);
                $this->transformReturnType($node, $this->definition->typeArguments);
                break;
            case $node instanceof Node\Stmt\Property:
                $this->transformProperty($node, $this->definition->typeArguments);
                break;
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Attribute) {
            switch ($node->name->toString()) {
                case TypeParameter::class:
                case TypeExtends::class:
                case ParameterType::class:
                case ReturnType::class:
                case PropertyType::class:
                    return NodeTraverser::REMOVE_NODE;
            }
        }

        if ($node instanceof Node\AttributeGroup) {
            if (empty($node->attrs)) {
                return NodeTraverser::REMOVE_NODE;
            }
        }
    }

    private function transformExtends(Node\Stmt\ClassLike $node, array $typeArguments): void
    {
        foreach ($this->reflectionClass->getAttributes(TypeExtends::class) as $attribute) {
            /** @var TypeExtends $typeExtends */
            $typeExtends = $attribute->newInstance();
            $extendsDefinition = $typeExtends->definition;

            if (($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Interface_) && !empty($node->extends)) {
                for ($i = 0; $i < count($node->extends); $i++) {
                    if ($node->extends[$i]->toString() === $extendsDefinition->type) {
                        $node->extends[$i] = $this->getType(
                            (new Definition(
                                $extendsDefinition->type,
                                ...array_map(fn ($arg) => $arg->targetDefinition, $extendsDefinition->typeArguments)))
                                    ->createTypedDefinition($typeArguments)
                        );
                    }
                }
            }

            if ($node instanceof Node\Stmt\Class_ && !empty($node->implements)) {
                for ($i = 0; $i < count($node->implements); $i++) {
                    if ($node->implements[$i]->toString() === $extendsDefinition->type) {
                        $node->implements[$i] = $this->getType(
                            (new Definition(
                                $extendsDefinition->type,
                                ...array_map(fn ($arg) => $arg->targetDefinition, $extendsDefinition->typeArguments)))
                                    ->createTypedDefinition($typeArguments)
                        );
                    }
                }
            }
        }
    }

    private function addDefinitionAttribute(Node\Stmt\ClassLike $node, Definition $definition): void
    {
        $node->attrGroups[] = new Node\AttributeGroup(
            [
                new Node\Attribute(
                    new Node\Name\FullyQualified(Definition::class),
                    $this->createNewDefinitionExpressionArguments($definition)
                )
            ]
        );
    }

    private function createNewDefinitionExpression(Definition $definition): Node\Expr\New_
    {
        return new Node\Expr\New_(
            new Node\Name\FullyQualified(Definition::class),
            $this->createNewDefinitionExpressionArguments($definition)
        );
    }

    private function createNewDefinitionExpressionArguments(Definition $definition): array
    {
        $args = [
            new Node\Arg(new Node\Scalar\String_($definition->type))
        ];

        foreach ($definition->typeArguments as $argument) {
            $args[] = new Node\Arg($this->createNewDefinitionExpression($argument->targetDefinition));
        }

        return $args;
    }

    private function transformParameters(Node\Stmt\ClassMethod $node, array $typeArguments): void
    {
        $reflectionMethod = $this->reflectionClass->getMethod($node->name);
        $parameterTypeAttributes = $reflectionMethod->getAttributes(ParameterType::class);

        if (empty($parameterTypeAttributes)) {
            return;
        }

        $parameterNodes = [];

        foreach ($node->getParams() as $parameterNode) {
            $parameterNodes[(string)$parameterNode->var->name] = $parameterNode;
        }

        foreach ($parameterTypeAttributes as $parameterTypeAttribute) {
            /** @var ParameterType $parameterType */
            $parameterType = $parameterTypeAttribute->newInstance();

            if (array_key_exists($parameterType->parameterName, $parameterNodes)) {
                $parameterNode = $parameterNodes[$parameterType->parameterName];

                $parameterNode->type = $this->getType(
                    $parameterType->definition->createTypedDefinition($typeArguments)
                );
            }
        }
    }

    private function transformReturnType(Node\Stmt\ClassMethod $node, array $typeArguments): void
    {
        $attribute = self::getSingleAttribute($this->reflectionClass->getMethod($node->name), ReturnType::class);

        if (null === $attribute) {
            return;
        }

        /** @var ReturnType $returnType */
        $returnType = $attribute->newInstance();

        $node->returnType = $this->getType($returnType->definition->createTypedDefinition($typeArguments));
    }

    private function transformProperty(Node\Stmt\Property $node, array $typeArguments): void
    {
        $attribute = self::getSingleAttribute(
            $this->reflectionClass->getProperty($node->props[0]->name),
            PropertyType::class
        );

        if (null === $attribute) {
            return;
        }

        /** @var PropertyType $propertyType */
        $propertyType = $attribute->newInstance();

        $node->type = $this->getType($propertyType->definition->createTypedDefinition($typeArguments));
    }

    private function removeAttributes(Node $node): void
    {
        $node->setAttribute('comments', null);
    }

    private function getType(Definition $definition): Node
    {
        if ($definition->isBaseType()) {
            return new Node\Identifier($definition->type);
        }

        return new Node\Name\FullyQualified(
            TypeBuilder::getType($definition)
        );
    }

    /**
     * @param object $reflection
     * @param string $name
     * @return ReflectionAttribute|null
     */
    private static function getSingleAttribute(object $reflection, string $name): ?ReflectionAttribute
    {
        if (!method_exists($reflection, 'getAttributes')) {
            throw new InvalidArgumentException('Not a reflection type object');
        }

        $attributes = $reflection->getAttributes($name);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0];
    }
}
