<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Content;

use MockFileSystem\Content\AbstractContent;
use MockFileSystem\Content\ContentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbstractContentTest extends TestCase
{
    /**
     * @var AbstractContent&MockObject
     */
    private $fixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = $this->getMockForAbstractClass(AbstractContent::class);
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(ContentInterface::class, $this->fixture);
    }

    public function testOpen(): void
    {
        $actual = $this->fixture->open();

        self::assertTrue($actual);
    }

    public function testClose(): void
    {
        $actual = $this->fixture->close();

        self::assertTrue($actual);
    }

    /**
     * @dataProvider sampleIsEof
     */
    public function testIsEof(int $size, int $bytes, bool $expected): void
    {
        $this->fixture->method('getSize')->willReturn($size);
        $this->fixture->seek($bytes);

        self::assertEquals($expected, $this->fixture->isEof());
    }

    public function sampleIsEof(): array
    {
        $size = rand(50, 100);

        return [
            'not eof' => [
                'size' => $size,
                'bytes' => intval($size / 2),
                'expected' => false,
            ],
            'is eof' => [
                'size' => $size,
                'bytes' => $size,
                'expected' => true,
            ],
        ];
    }

    public function testSeekAndTellUsingSeekSet(): void
    {
        $this->fixture->method('getSize')->willReturn(rand(100, 199));
        $this->fixture->seek(rand(1, 99));
        $pos = rand(10, 30);

        self::assertTrue($this->fixture->seek($pos, \SEEK_SET));
        self::assertEquals($pos, $this->fixture->tell());
    }

    public function testSeekAndTellUsingSeekCur(): void
    {
        $this->fixture->method('getSize')->willReturn(rand(100, 199));
        $this->fixture->seek(50);
        $pos = rand(10, 30);

        self::assertTrue($this->fixture->seek($pos, \SEEK_CUR));
        self::assertEquals(50 + $pos, $this->fixture->tell());
    }

    public function testSeekAndTellUsingSeekEnd(): void
    {
        $size = rand(100, 199);
        $this->fixture->method('getSize')->willReturn($size);
        $this->fixture->seek(rand(1, 99));
        $pos = rand(10, 30);

        self::assertTrue($this->fixture->seek(-$pos, \SEEK_END));
        self::assertEquals($size - $pos, $this->fixture->tell());
    }

    public function testSeekPastEnd(): void
    {
        $this->fixture->method('getSize')->willReturn(99);
        $this->fixture->seek(rand(1, 99));

        self::assertFalse($this->fixture->seek(rand(100, 199), \SEEK_SET));
        self::assertEquals(0, $this->fixture->tell());
    }

    public function testSeekUnknownWhence(): void
    {
        $this->fixture->method('getSize')->willReturn(99);
        $this->fixture->seek(rand(1, 99));
        $pos = $this->fixture->tell();

        self::assertFalse($this->fixture->seek(rand(100, 199), rand(100, 1999)));
        self::assertEquals($pos, $this->fixture->tell());
    }

    public function testFlush(): void
    {
        self::assertTrue($this->fixture->flush());
    }

    public function testUnlink(): void
    {
        self::assertTrue($this->fixture->unlink());
    }
}
