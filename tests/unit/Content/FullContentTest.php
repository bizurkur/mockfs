<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Content;

use MockFileSystem\Content\AbstractContent;
use MockFileSystem\Content\FullContent;
use PHPUnit\Framework\TestCase;

class FullContentTest extends TestCase
{
    private FullContent $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = new FullContent();
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(AbstractContent::class, $this->fixture);
    }

    public function testRead(): void
    {
        $count = rand(5, 10);
        for ($i = 1; $i < $count; $i++) {
            $data = $this->fixture->read($i);

            self::assertEquals(str_repeat("\0", $i), $data);
        }
    }

    public function testWrite(): void
    {
        $count = rand(5, 10);
        for ($i = 1; $i < $count; $i++) {
            $data = str_repeat("\0", $i);
            $bytes = $this->fixture->write($data);

            self::assertEquals(0, $bytes);
        }
    }

    public function testTruncate(): void
    {
        self::assertFalse($this->fixture->truncate(rand()));
    }

    public function testIsEof(): void
    {
        self::assertFalse($this->fixture->isEof());
    }

    public function testGetSize(): void
    {
        self::assertEquals(0, $this->fixture->getSize());
    }
}
