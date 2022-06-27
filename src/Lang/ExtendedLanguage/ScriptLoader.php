<?php

namespace Orolyn\Lang\ExtendedLanguage;

use Orolyn\Lang\ExtendedLanguage\Parser\Emulative;
use Orolyn\Lang\ExtendedLanguage\Parser\PhpPlus;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Filesystem\Filesystem;

class ScriptLoader
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

    public static function load(string $path)
    {
        $storagePath = self::getStoragePath() . '/scripts/' . sha1($path) . '.php';

        $parser = new PhpPlus(new Emulative());
        $ast = $parser->parse(file_get_contents($path));

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new Reconstructor());
        $traverser->traverse($ast);

        $content = (new Standard())->prettyPrintFile($ast);

        self::$filesystem->dumpFile($storagePath, $content);

        require_once $storagePath;
    }
}
