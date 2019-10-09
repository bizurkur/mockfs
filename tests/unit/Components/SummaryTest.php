<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\Summary;
use PHPUnit\Framework\TestCase;

class SummaryTest extends TestCase
{
    public function testGetSize(): void
    {
        $size = rand();

        $fixture = new Summary($size, rand());

        self::assertEquals($size, $fixture->getSize());
    }

    public function testGetFileCount(): void
    {
        $count = rand();

        $fixture = new Summary(rand(), $count);

        self::assertEquals($count, $fixture->getFileCount());
    }
}
