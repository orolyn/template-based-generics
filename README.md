Userland Generics Prototype
===========================

This is an experimental project for implementing userland generic classes. The task of fully supporting
every part of the language is quite monumental, so please forgive any errors that occur that aren't 
directly referenced in the examples.

Also please forgive bad code inside, it was done very quickly, so its very rough.

Example
-------

```php
namespace Test;

use DateTime;

class Item<T>
{
    public function __construct(public T $value)
    {
    }
}

interface IDictionary<TKey, TValue>
{
    public function add(TKey $key, TValue $value): void;
    public function get(TKey $key): Item<TValue>;
}

interface ISimpleDictionary<TKey, TValue> extends IDictionary<TKey, TValue>
{
    public function getLast(): TValue;
}

class MySimpleDictionary<TKey, TValue> implements ISimpleDictionary<TKey, TValue>
{
    public TValue $last;

    public function add(TKey $key, TValue $value): void
    {
        $this->last = $value;
    }

    public function get(TKey $key): Item<TValue>
    {
        return new Item<TValue>($this->last);
    }

    public function copy(): MySimpleDictionary<TKey, TValue>
    {
        return new MySimpleDictionary<TKey, TValue>();
    }

    public function getLast(): TValue
    {
        return $this->last;
    }
}

$sample = new MySimpleDictionary<string, DateTime>();
$sample->add('a', new DateTime());

var_dump($sample->get('a'));
```

Example of execution:

```php
use \Orolyn\Lang\TypeTemplate\TypeBuilder;
use \Orolyn\Lang\ExtendedLanguage\ScriptLoader;

require_once __DIR__ . '/../vendor/autoload.php';

TypeBuilder::configure(__DIR__ . '/../var/php_plus', true);
ScriptLoader::configure(__DIR__ . '/../var/php_plus', true);

ScriptLoader::load('path/to/sourcecode.php+');
```

Output:

```text
object(Test\Item_c31e2f4bf29c58d23105befd8aac2670e48d1365)#2196 (1) {
  ["value"]=>
  object(DateTime)#1025 (3) {
    ["date"]=>
    string(26) "2022-06-27 21:44:52.310968"
    ["timezone_type"]=>
    int(3)
    ["timezone"]=>
    string(13) "Europe/London"
  }
}
```

In this example MySimpleDictionary implements ISimpleDictionary, which extends IDictionary.
The type arguments are passed down each one, and the type parameter uses within each class/interface
are replaced with the values of the original type creation.

Compilation Process
-------------------

These are essentially templates, for instance, the following class `A` has 3 different
type creations. The process is two-phase, first is translation to PHP with attributes.
The second is rendering of the classes from attributes.

Take the following example;

```php
class Z<T>
{
    public function __construct(T $value) {}
}

new Z<string>('Hello, World');
new Z<int>(123);
new Z<float>(123.123);
```

This is translated to:

```php
#[\Orolyn\Lang\TypeTemplate\TypeParameter('T')]
class Z
{
    #[\Orolyn\Lang\TypeTemplate\ParameterType(
        'value',
        new \Orolyn\Lang\TypeTemplate\Definition('T')
    )]
    public function __construct($value)
    {
    }
}

\Orolyn\Lang\TypeTemplate\TypeBuilder::instantiateType(
    new \Orolyn\Lang\TypeTemplate\Definition(
        Z::class,
        new \Orolyn\Lang\TypeTemplate\Definition('string')
    ), 
    null, 
    'Hello, World!'
);
\Orolyn\Lang\TypeTemplate\TypeBuilder::instantiateType(
    new \Orolyn\Lang\TypeTemplate\Definition(
        Z::class,
        new \Orolyn\Lang\TypeTemplate\Definition('int')
    ), 
    null, 
    123
);
\Orolyn\Lang\TypeTemplate\TypeBuilder::instantiateType(
    new \Orolyn\Lang\TypeTemplate\Definition(
        Z::class,
        new \Orolyn\Lang\TypeTemplate\Definition('float')
    ), 
    null, 
    123.123
);
```

And the classes are rendered as:

```php
#[\Orolyn\Lang\TypeTemplate\Definition(
    'Z',
    new \Orolyn\Lang\TypeTemplate\Definition('string')
)]
class Z_dc9a207af33fb2fdceda986d35b203e1eec02aad
{
    public function __construct(string $value)
    {
    }
}

#[\Orolyn\Lang\TypeTemplate\Definition(
    'Z',
    new \Orolyn\Lang\TypeTemplate\Definition('int')
)]
class Z_d03d9e7d4eaca0c0a021a9b3d536a0371f2225f3
{
    public function __construct(int $value)
    {
    }
}

#[\Orolyn\Lang\TypeTemplate\Definition(
    'Z',
    new \Orolyn\Lang\TypeTemplate\Definition('float')
)]
class Z_4dbe0c0a0ed10eb71d6a3dccec04693af1d88060
{
    public function __construct(float $value)
    {
    }
}
```

Templates Not Inheritance
-------------------------

An important aspect of this process is that these are templates. You may use the original base class like so:

```php
class A<T>
{

}

new A();
```

And this will not result in a new rendering of the class. It will be treated as though the type parameters
were not used at all.

However, two different renderings of the same class are not alike, a rendered class with type arguments
is a completely different class to the one it was created from. This I believe is in line with other languages
in the practical sense, since a rendering that accepts Datetime should not be interchangable with one that
accepts anything else.
