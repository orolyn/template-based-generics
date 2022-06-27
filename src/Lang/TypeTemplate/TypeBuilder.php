<?php

namespace Orolyn\Lang\TypeTemplate;

use Exception;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FindingVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;

class TypeBuilder
{
    private static ?Filesystem $filesystem = null;
    private static bool $isDev;
    private static string $storage;
    private static array $currentBuilds = [];

    /**
     * @param string $storage
     * @param bool $isDev
     * @return void
     */
    public static function configure(string $storage, bool $isDev): void
    {
        self::$filesystem = new Filesystem();
        self::$storage = $storage;
        self::$isDev = $isDev;
    }

    /**
     * @return string
     */
    public static function getStoragePath(): string
    {
        return self::$storage;
    }

    public static function instantiateType(Definition $definition, ?string $callingClass = null, ...$args)
    {
        return new (self::getType($definition, $callingClass))(...$args);

    }

    /**
     * @param Definition $definition
     * @return string
     * @throws \ReflectionException
     */
    public static function getType(Definition $definition, ?string $callingClass = null): ?string
    {
        if ($definition->isBaseType()) {
            return $definition->type;
        }

        if (null !== $callingClass) {
            $reflectionClass = new ReflectionClass($callingClass);
            $definitionAttributes = $reflectionClass->getAttributes(Definition::class);

            if (!empty($definitionAttributes)) {
                /** @var Definition $callingDefinition */
                $callingDefinition = $definitionAttributes[0]->newInstance();
                $definition = $definition->createTypedDefinition($callingDefinition->typeArguments);
            }
        }

        $className = $definition->getNamespacedTypeName();

        if (!array_key_exists($className, self::$currentBuilds)) {
            self::$currentBuilds[$className] = true;

            if (!class_exists($className) && !interface_exists($className)) {
                $classPath = self::getStoragePath() . '/classes/' . self::getClassPath($definition);

                if (!self::$filesystem->exists($classPath) || self::$isDev) {
                    self::build($definition);
                }

                if (!self::$filesystem->exists($classPath)) {
                    return null;
                }

                require_once $classPath;
            }

            unset(self::$currentBuilds[$className]);
        }

        return $className;
    }

    /**
     * @param Definition $definition
     * @return bool
     * @throws \ReflectionException
     */
    private static function build(Definition $definition): bool
    {
        $reflectionClass = new ReflectionClass($definition->type);
        $ast = self::getClassAst($reflectionClass);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeTransformer($reflectionClass, $definition));
        $traverser->traverse($ast);

        $content = (new Standard())->prettyPrintFile($ast);
        $path = self::getStoragePath() . '/classes/' . self::getClassPath($definition);

        self::$filesystem->dumpFile($path, $content);

        //var_dump($content);

        return true;
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @return Node\Stmt\Namespace_[]
     */
    private static function getClassAst(ReflectionClass $reflectionClass): array
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $traverser = new NodeTraverser();

        $finder = new FindingVisitor(
            function (Node $node) use ($reflectionClass) {
                if ($node instanceof Node\Stmt\ClassLike) {
                    if ($node->namespacedName->toString() === $reflectionClass->getName()) {
                        return true;
                    }
                }
            }
        );

        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($finder);

        $traverser->traverse($parser->parse(file_get_contents($reflectionClass->getFileName())));
        $classNode = $finder->getFoundNodes()[0];

        if ($reflectionClass->getNamespaceName()) {
            $classNode = new Node\Stmt\Namespace_(new Node\Name($reflectionClass->getNamespaceName()), [$classNode]);
        }

        return [
            $classNode
        ];
    }

    /**
     * @param Definition $definition
     * @return string
     */
    private static function getClassPath(Definition $definition): string
    {
        return sprintf('%s/%s.php', str_replace('\\', '/', $definition->type), sha1($definition->toString()));
    }
}
