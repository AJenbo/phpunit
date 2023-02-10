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

use function assert;
use function file_get_contents;
use function sprintf;
use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class ParsingTestFinder
{
    /**
     * @throws ParserException
     */
    public function findTestsIn(string $filename): TestCollection
    {
        $source = file_get_contents($filename);

        $parser = (new ParserFactory)->create(
            ParserFactory::PREFER_PHP7,
            new Lexer
        );

        try {
            $nodes = $parser->parse($source);

            assert($nodes !== null);

            $traverser = new NodeTraverser;

            $traverser->addVisitor(new NameResolver);
            $traverser->addVisitor(new ParentConnectingVisitor);

            $visitor = new TestFindingVisitor;

            $traverser->addVisitor($visitor);

            /* @noinspection UnusedFunctionResultInspection */
            $traverser->traverse($nodes);
            // @codeCoverageIgnoreStart

            return $visitor->tests();
        } catch (Error $error) {
            throw new ParserException(
                sprintf(
                    'Cannot parse %s: %s',
                    $filename,
                    $error->getMessage()
                ),
                $error->getCode(),
                $error
            );
        }
    }
}
