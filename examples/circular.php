<?php

namespace Test;

use Orolyn\Lang\TypeTemplate\ParameterType;
use Orolyn\Lang\TypeTemplate\PropertyType;
use Orolyn\Lang\TypeTemplate\ReturnType;
use Orolyn\Lang\TypeTemplate\TypeExtends;
use Orolyn\Lang\TypeTemplate\TypeParameter;
use Orolyn\Lang\TypeTemplate\Definition;
use Orolyn\Lang\TypeTemplate\TypeArgument;
use Orolyn\Lang\TypeTemplate\TypeBuilder;
use SplObjectStorage;

require_once __DIR__ . '/../vendor/autoload.php';

TypeBuilder::configure(__DIR__ . '/../var/php_plus', true);

/**
 * interface CircularInterfaceA<T>
 */
#[TypeParameter('T')]
interface CircularInterfaceA
{
    /**
     * public function getValue(): Test\CircularInterfaceB<T>
     */
    #[ReturnType(new Definition(CircularInterfaceB::class, new TypeArgument('T', 'T')))]
    public function getValue();
}

/**
 * interface CircularInterfaceB<T>
 */
#[TypeParameter('T')]
interface CircularInterfaceB
{
    /**
     * public function getValue(): Test\CircularInterfaceA<T>
     */
    #[ReturnType(new Definition(CircularInterfaceA::class, new TypeArgument('T', 'T')))]
    public function getValue();
}

/*
 * Test\CircularInterfaceA<string>
 */
$a_definition = new Definition(
    CircularInterfaceA::class,
    new TypeArgument('T', 'string')
);

/*
 * Test\CircularInterfaceB<string>
 */
$b_definition = new Definition(
    CircularInterfaceB::class,
    new TypeArgument('T', 'string')
);

var_dump(TypeBuilder::getType($a_definition));


