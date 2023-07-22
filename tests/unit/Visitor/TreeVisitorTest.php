<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Visitor;

use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Visitor\TreeVisitor;
use MockFileSystem\Visitor\VisitorInterface;
use PHPUnit\Framework\TestCase;

class TreeVisitorTest extends TestCase
{
    public function testInstanceOf(): void
    {
        $fixture = new TreeVisitor();

        self::assertInstanceOf(VisitorInterface::class, $fixture);
    }

    /**
     * @param mixed $handle
     * @param string $expected
     *
     * @dataProvider sampleInvalidHandles
     */
    public function testThrowsExceptionWhenNotGivenResource($handle, string $expected): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage($expected);

        new TreeVisitor($handle);
    }

    public function sampleInvalidHandles(): array
    {
        return [
            'string' => [
                'handle' => uniqid(),
                'expected' => 'File handle must be of type resource; string given.',
            ],
            'int' => [
                'handle' => rand(),
                'expected' => 'File handle must be of type resource; integer given.',
            ],
            'float' => [
                'handle' => (float) rand(),
                'expected' => 'File handle must be of type resource; double given.',
            ],
            'bool' => [
                'handle' => (bool) rand(0, 1),
                'expected' => 'File handle must be of type resource; boolean given.',
            ],
            'object' => [
                'handle' => (object) [],
                'expected' => 'File handle must be of type resource; object given.',
            ],
        ];
    }
}
