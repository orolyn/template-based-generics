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
 * @template TKey
 * @template TValue
 */
#[TypeParameter('TKey')]
#[TypeParameter('TValue')]
interface IDictionary
{
    /**
     * @param TKey $key
     * @param TValue $value
     * @return void
     */
    #[ParameterType('key', 'TKey')]
    #[ParameterType('value', 'TValue')]
    public function add($key, $value): void;

    /**
     * @param TKey $key
     * @return TValue
     */
    #[ParameterType('key', 'TKey')]
    #[ReturnType('TValue')]
    public function get($key);
}

/**
 * @template TKey
 * @template TValue
 * @extends IDictionary<TKey, TValue>
 */
#[TypeParameter('TKey')]
#[TypeParameter('TValue')]
#[TypeExtends(
    new Definition(
        IDictionary::class,
        new Definition('TKey'),
        new Definition('TValue')
    )
)]
class SampleDictionary// implements IDictionary
{
    /**
     * @var SplObjectStorage<TKey, TValue>
     */
    private SplObjectStorage $source;

    /**
     * @var TValue
     */
    #[PropertyType(new Definition('TValue'))]
    private $last;

    public function __construct()
    {
        $this->source = new SplObjectStorage();
    }

    /**
     * @inheritdoc
     */
    #[ParameterType('key', new Definition('TKey'))]
    #[ParameterType('value', new Definition('TValue'))]
    public function add($key, $value): void
    {
        $this->source[$key] = $value;
        $this->last = $value;
    }

    /**
     * @inheritdoc
     */
    #[ParameterType('key', new Definition('TKey'))]
    #[ReturnType(new Definition('TValue'))]
    public function get($key)
    {
        return $this->source[$key];
    }

    /**
     * @return TValue
     */
    #[ReturnType(new Definition('TValue'))]
    public function getLast()
    {
        return $this->last;
    }

    public function copy(): static
    {
        return clone $this;
    }
}

/**
 * @template T
 */
#[TypeParameter('T')]
class A
{
    /**
     * @param T $value
     */
    #[ParameterType('value', new Definition('T'))]
    public function __construct(
        private $value
    ) {
    }

    /**
     * @return T
     */
    #[ReturnType(new Definition('T'))]
    public function getValue()
    {
        return $this->value;
    }
}

/**
 * @template T
 */
#[TypeParameter('T')]
class B
{
    /**
     * @param T $value
     */
    #[ParameterType('value', new Definition('T'))]
    public function __construct(
        private $value
    ) {
    }

    /**
     * @return T
     */
    #[ReturnType(new Definition('T'))]
    public function getValue()
    {
        return $this->value;
    }
}

/*
 * Test\A<string>
 */
$a_definition = new Definition(
    A::class,
    new Definition('string')
);

/*
 * Test\B<string>
 */
$b_definition = new Definition(
    B::class,
    new Definition('string')
);

/*
 * Test\SampleDictionary<Test\A<string>, Test\B<string>>
 */
$definition = new Definition(
    SampleDictionary::class,
    new Definition(
        A::class,
        new Definition('string')
    ),
    new Definition(
        B::class,
        new Definition('string')
    )
);

/*
 * new Test\SampleDictionary<Test\A<string>, Test\B<string>>()
 */
$instance = new (TypeBuilder::getType($definition))();

/* new Test\A<string>('Class A') */
$a = new (TypeBuilder::getType($a_definition))('Class A');

/* new Test\B<string>('Class B') */
$b = new (TypeBuilder::getType($b_definition))('Class B');

$instance->add($a, $b);

var_dump($instance);
