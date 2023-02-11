<?php declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\TextUI\Output\Default\Issues;

use const PHP_EOL;
use function array_unique;
use function assert;
use function sort;
use function sprintf;
use function str_starts_with;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\ErrorTriggered;
use PHPUnit\Event\Test\NoticeTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpNoticeTriggered;
use PHPUnit\Event\Test\PhpWarningTriggered;
use PHPUnit\Event\Test\WarningTriggered;
use PHPUnit\Event\TestData\NoDataSetFromDataProviderException;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class FilteringIssueMapper
{
    /**
     * @psalm-var list<non-empty-string>
     */
    private readonly array $ignoredPathPrefixes;

    /**
     * @psalm-param list<non-empty-string> $ignoredPathPrefixes
     */
    public function __construct(array $ignoredPathPrefixes = [])
    {
        $this->ignoredPathPrefixes = $ignoredPathPrefixes;
    }

    /**
     * @psalm-param array<string,list<DeprecationTriggered|PhpDeprecationTriggered|ErrorTriggered|NoticeTriggered|PhpNoticeTriggered|WarningTriggered|PhpWarningTriggered>> $eventsGroupedByTest
     *
     * @psalm-return list<array{title: string, body: string}>
     */
    public function map(array $eventsGroupedByTest): array
    {
        $groupedByMessageAndLocation = [];

        foreach ($eventsGroupedByTest as $events) {
            foreach ($events as $event) {
                if ($this->shouldBeIgnored($event->file())) {
                    continue;
                }

                if (!isset($groupedByMessageAndLocation[$event->message()][$event->file()][$event->line()])) {
                    $groupedByMessageAndLocation[$event->message()][$event->file()][$event->line()] = [];
                }

                $groupedByMessageAndLocation[$event->message()][$event->file()][$event->line()][] = $event->test();
            }
        }

        $elements = [];

        foreach ($groupedByMessageAndLocation as $message => $files) {
            $locations = [];
            $tests     = [];

            foreach ($files as $file => $lines) {
                foreach ($lines as $line => $_tests) {
                    $locations[] = $file . ':' . $line;

                    foreach ($_tests as $test) {
                        $tests[] = $this->describe($test);
                    }
                }
            }

            $tests = array_unique($tests);

            sort($locations);
            sort($tests);

            $body = 'Triggered at these locations:';

            foreach ($locations as $location) {
                $body .= PHP_EOL . ' - ' . $location;
            }

            $body .= PHP_EOL . PHP_EOL . 'Triggered by these tests:';

            foreach ($tests as $test) {
                $body .= PHP_EOL . ' - ' . $test;
            }

            $elements[] = [
                'title' => $message,
                'body'  => $body,
            ];
        }

        return $elements;
    }

    /**
     * @throws NoDataSetFromDataProviderException
     */
    private function describe(Test $test): string
    {
        if (!$test->isTestMethod()) {
            return $test->name();
        }

        assert($test instanceof TestMethod);

        return sprintf(
            '%s (%s:%d)',
            $test->nameWithClass(),
            $test->file(),
            $test->line(),
        );
    }

    /**
     * @psalm-param non-empty-string $file
     */
    private function shouldBeIgnored(string $file): bool
    {
        foreach ($this->ignoredPathPrefixes as $prefix) {
            if (str_starts_with($file, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
