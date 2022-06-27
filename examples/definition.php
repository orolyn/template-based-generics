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

#[TypeParameter('T')]
interface ISample
{
    #[ReturnType('T')]
    public function getValue();
}

#[TypeParameter('T')]
#[TypeExtends(
    new Definition(
        ISample::class,
        new Definition('T')
    )
)]
class Sample implements ISample
{
    #[ReturnType('T')]
    public function getValue()
    {

    }

    #[ReturnType(
        new Definition(
            Sample::class,
            new Definition('T')
        )
    )]
    public function copy()
    {
        $class = TypeBuilder::getType(
            new Definition(
                Sample::class,
                new Definition('T')
            ),
            __CLASS__
        );

        return new $class();
    }
}

$sample = new Definition(
    Sample::class,
    new Definition('string')
);

$s1 = new (TypeBuilder::getType($sample))();
$s2 = $s1->copy();

var_dump(get_class($s1));
var_dump(get_class($s2));
