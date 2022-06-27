<?php

namespace Orolyn\Lang\ExtendedLanguage;

use http\Exception\RuntimeException;
use InvalidArgumentException;
use Orolyn\Lang\TypeTemplate\Definition;
use Orolyn\Lang\TypeTemplate\ParameterType;
use Orolyn\Lang\TypeTemplate\PropertyType;
use Orolyn\Lang\TypeTemplate\ReturnType;
use Orolyn\Lang\TypeTemplate\TypeBuilder;
use Orolyn\Lang\TypeTemplate\TypeExtends;
use Orolyn\Lang\TypeTemplate\TypeParameter;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use ReflectionAttribute;
use ReflectionClass;

class Reconstructor extends NodeVisitorAbstract
{
    /**
     * @var string[]
     */
    private array $currentTypeParameters = [];

    private bool $processingClass = false;

    private bool $processingAttribute = false;

    public function enterNode(Node $node)
    {
        switch (true) {
            case $node instanceof Node\AttributeGroup:
                $this->processingAttribute = true;
                break;
            case $node instanceof Node\Stmt\ClassLike:
                $this->processingClass = true;
                if ($typeParams = $node->getAttribute('typeParams')) {
                    $this->addTypeParameters($node, $typeParams);
                }
                $this->addExtends($node);
                break;
            case $node instanceof Node\Stmt\Property:
                $this->replacePropertyType($node);
                break;
            case $node instanceof Node\Stmt\ClassMethod:
                $this->replaceMethodTypeHints($node);
                break;
        }
    }

    public function leaveNode(Node $node)
    {
        switch (true) {
            case $node instanceof Node\AttributeGroup:
                $this->processingAttribute = false;
                break;
            case $node instanceof Node\Stmt\ClassLike:
                $this->processingClass = false;
                $this->currentTypeParameters = [];
                break;
            case $node instanceof Node\Expr\New_ && !$this->processingAttribute:
                return $this->rewriteNewStatement($node);
        }
    }

    /**
     * @param Node\Stmt\ClassLike $node
     * @param Node\Identifier[] $typeParams
     * @return void
     */
    private function addTypeParameters(Node\Stmt\ClassLike $node, array $typeParams): void
    {
        $attributes = [];

        foreach ($typeParams as $identifier) {
            $this->currentTypeParameters[] = $identifier->toString();

            $attributes[] = new Node\Attribute(
                new Node\Name\FullyQualified(TypeParameter::class),
                [
                    new Node\Arg(new Node\Scalar\String_($identifier->toString()))
                ]
            );
        }

        $node->attrGroups[] = new Node\AttributeGroup($attributes);
    }

    private function addExtends(Node\Stmt\ClassLike $node): void
    {
        $inherited = [];

        if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Interface_) {
            if (!empty($node->extends)) {
                $inherited = $node->extends;
            }
        }

        if ($node instanceof Node\Stmt\Class_) {
            if (!empty($node->implements)) {
                $inherited = array_merge($node->implements);
            }
        }

        foreach ($inherited as $name) {
            if (!empty($name->getAttribute('typeArgs'))) {
                $node->attrGroups[] = new Node\AttributeGroup(
                    [
                        new Node\Attribute(
                            new Node\Name\FullyQualified(TypeExtends::class),
                            [
                                new Node\Arg($this->createDefinition($name))
                            ]
                        )
                    ]
                );
            }
        }
    }

    private function replaceMethodTypeHints(Node\Stmt\ClassMethod $node): void
    {
        foreach ($node->params as $param) {
            if (null === $definition = $this->createDefinition($param->type)) {
                continue;
            }

            $param->type = null;

            $node->attrGroups[] = new Node\AttributeGroup(
                [
                    new Node\Attribute(
                        new Node\Name\FullyQualified(ParameterType::class),
                        [
                            new Node\Arg(new Node\Scalar\String_($param->var->name)),
                            new Node\Arg($definition)
                        ]
                    )
                ]
            );
        }

        if ($definition = $this->createDefinition($node->returnType)) {
            $node->returnType = null;

            $node->attrGroups[] = new Node\AttributeGroup(
                [
                    new Node\Attribute(
                        new Node\Name\FullyQualified(ReturnType::class),
                        [
                            new Node\Arg($definition)
                        ]
                    )
                ]
            );
        }
    }

    private function replacePropertyType(Node\Stmt\Property $node): void
    {
        if (null === $definition = $this->createDefinition($node->type)) {
            return;
        }

        $node->type = null;

        $node->attrGroups[] = new Node\AttributeGroup(
            [
                new Node\Attribute(
                    new Node\Name\FullyQualified(PropertyType::class),
                    [
                        new Node\Arg($definition)
                    ]
                )
            ]
        );
    }

    private function rewriteNewStatement(Node\Expr\New_ $node): ?Node
    {
        if (!$node->class instanceof Node\Name) {
            return null;
        }

        if (in_array($node->class->toString(), $this->currentTypeParameters)) {
            throw new RuntimeException('Cannot instantiate a type parameter');
        }

        if (!$node->class->getAttribute('typeArgs')) {
            return null;
        }

        if (null === $definition = $this->createDefinition($node->class)) {
            return null;
        }

        return new Node\Expr\StaticCall(
            new Node\Name\FullyQualified(TypeBuilder::class),
            'instantiateType',
            [
                new Node\Arg($definition),
                $this->processingClass
                    ? new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('__CLASS__')))
                    : new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('null'))),
                ...$node->getArgs()
            ]
        );
    }

    private function createGetTypeExpression(Node\Expr\New_ $definition): Node\Expr\StaticCall
    {
        return new Node\Expr\StaticCall(
            new Node\Name\FullyQualified(TypeBuilder::class),
            'getType',
            [
                new Node\Arg($definition),
                $this->processingClass
                    ? new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('__CLASS__')))
                    : new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('null')))
            ]
        );
    }

    private function createDefinition(?Node $node): ?Node\Expr\New_
    {
        if (null === $node) {
            return null;
        }

        if (
            ($node instanceof Node\Identifier || $node instanceof Node\Name) &&
            in_array($node->toString(), $this->currentTypeParameters)
        ) {
            return new Node\Expr\New_(
                new Node\Name\FullyQualified(Definition::class),
                [
                    new Node\Arg(new Node\Scalar\String_($node->toString()))
                ]
            );
        }


        $args = [];

        if ($typeArgs = $node->getAttribute('typeArgs')) {
            foreach ($typeArgs as $arg) {
                $args[] = $this->createDefinition($arg);
            }
        }

        if (in_array($node->toString(), Definition::getBaseTypes())) {
            $type = new Node\Scalar\String_($node->toString());
        } else {
            $type = new Node\Expr\ClassConstFetch(new Node\Name($node->toString()), 'class');
        }

        $definition = new Node\Expr\New_(
            new Node\Name\FullyQualified(Definition::class),
            [
                new Node\Arg($type),
                ...$args
            ]
        );

        return $definition;
    }
}
