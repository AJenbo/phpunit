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

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class TestFindingVisitor extends NodeVisitorAbstract
{
    /**
     * @psalm-var list<Test>
     */
    private array $tests = [];

    public function tests(): TestCollection
    {
        return TestCollection::fromArray($this->tests);
    }

    public function enterNode(Node $node): void
    {
    }
}
