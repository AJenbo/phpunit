<?php declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\TestRunner\TestFinder;

use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_file;
use function md5;
use function serialize;
use function unserialize;
use PHPUnit\Util\Filesystem;
use SebastianBergmann\FileIterator\Facade as FileIteratorFacade;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class CachingTestFinder
{
    private static ?string $cacheVersion = null;

    /**
     * @psalm-var array<string, TestCollection>
     */
    private array $cache = [];
    private readonly TestFinder $testFinder;
    private readonly string $directory;

    public function __construct(string $directory, TestFinder $testFinder)
    {
        Filesystem::createDirectory($directory);

        $this->directory  = $directory;
        $this->testFinder = $testFinder;
    }

    public function findTestsIn(string $filename): TestCollection
    {
        if (!isset($this->cache[$filename])) {
            $this->process($filename);
        }

        return $this->cache[$filename];
    }

    private function process(string $filename): void
    {
        $cache = $this->read($filename);

        if ($cache !== false) {
            $this->cache[$filename] = $cache;

            return;
        }

        $this->cache[$filename] = $this->testFinder->findTestsIn($filename);

        $this->write($filename, $this->cache[$filename]);
    }

    private function read(string $filename): TestCollection|false
    {
        $cacheFile = $this->cacheFile($filename);

        if (!is_file($cacheFile)) {
            return false;
        }

        return unserialize(
            file_get_contents($cacheFile),
            [
                'allowed_classes' => [
                    TestCollection::class,
                    Test::class,
                ],
            ]
        );
    }

    private function write(string $filename, TestCollection $tests): void
    {
        file_put_contents(
            $this->cacheFile($filename),
            serialize($tests)
        );
    }

    private function cacheFile(string $filename): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . md5($filename . "\0" . file_get_contents($filename) . "\0" . self::cacheVersion());
    }

    private static function cacheVersion(): string
    {
        if (self::$cacheVersion !== null) {
            return self::$cacheVersion;
        }

        $buffer = [];

        foreach ((new FileIteratorFacade)->getFilesAsArray(__DIR__, '.php') as $file) {
            $buffer[] = $file;
            $buffer[] = file_get_contents($file);
        }

        self::$cacheVersion = md5(implode("\0", $buffer));

        return self::$cacheVersion;
    }
}
