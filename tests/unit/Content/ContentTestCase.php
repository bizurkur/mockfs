<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Content;

use MockFileSystem\Content\ContentInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test case for all "normal" content to extend.
 *
 * "Normal" meaning anything that reads and writes, as expected, without any
 * blocking or no-op methods.
 *
 * This ensures all classes behave the same for the basic methods.
 */
abstract class ContentTestCase extends TestCase
{
    protected ContentInterface $fixture;

    public function testInstanceOfInterface(): void
    {
        self::assertInstanceOf(ContentInterface::class, $this->fixture);
    }

    public function testRead(): void
    {
        $this->fixture->write('foo bar baz');
        $this->fixture->seek(0);

        self::assertEquals('foo', $this->fixture->read(3));

        $this->fixture->seek(5);
        self::assertEquals('ar baz', $this->fixture->read(7));
        self::assertEquals('', $this->fixture->read(rand(1, 100)));
    }

    public function testReadSetsPositionCorrectly(): void
    {
        $size = rand(10, 99);
        $this->fixture->write(str_repeat("\0", $size));
        $this->fixture->seek(0);

        $this->fixture->read(3);
        self::assertEquals(3, $this->fixture->tell());

        $this->fixture->read($size + 30);
        self::assertEquals($size, $this->fixture->tell());
    }

    public function testWrite(): void
    {
        $bytesA = $this->fixture->write('foobar   testing');
        $this->fixture->seek(2);
        $bytesB = $this->fixture->write(' ping pong ');

        $this->fixture->seek(0);
        $actual = $this->fixture->read(100);

        self::assertEquals(16, $bytesA);
        self::assertEquals(11, $bytesB);
        self::assertEquals('fo ping pong ing', $actual);
    }

    public function testWriteSetsPositionCorrectly(): void
    {
        $size = rand(10, 99);
        $this->fixture->write(str_repeat("\0", $size));
        self::assertEquals($size, $this->fixture->tell());

        $half = intval($size / 2);
        $this->fixture->seek($half);

        $this->fixture->write(str_repeat("\0", $size));
        self::assertEquals($size + $half, $this->fixture->tell());
    }

    /**
     * @dataProvider sampleTruncate
     */
    public function testTruncate(string $content, int $size, string $expected): void
    {
        $this->fixture->write($content);

        $actual = $this->fixture->truncate($size);

        $this->fixture->seek(0);
        $data = $this->fixture->read(1000);

        self::assertTrue($actual);
        self::assertEquals($expected, $data);
    }

    public function sampleTruncate(): array
    {
        return [
            'truncate up' => [
                'content' => 'foobar',
                'size' => 15,
                'expected' => "foobar\0\0\0\0\0\0\0\0\0",
            ],
            'truncate down' => [
                'content' => 'foo bar baz blur blah',
                'size' => 5,
                'expected' => 'foo b',
            ],
            'truncate none' => [
                'content' => 'foo bar',
                'size' => 7,
                'expected' => 'foo bar',
            ],
        ];
    }

    /**
     * @dataProvider sampleTruncatePosition
     */
    public function testTruncateDoesNotSetPosition(int $seek, int $size, int $expected): void
    {
        $this->fixture->write(uniqid());
        $this->fixture->seek($seek);

        $this->fixture->truncate($size);

        self::assertEquals($expected, $this->fixture->tell());
    }

    public function sampleTruncatePosition(): array
    {
        return [
            'truncate up' => [
                'seek' => 5,
                'size' => 15,
                'expected' => 5,
            ],
            'truncate down' => [
                'seek' => 5,
                'size' => 3,
                'expected' => 5,
            ],
        ];
    }

    /**
     * @dataProvider sampleIsEof
     */
    public function testIsEof(string $content, int $bytes, bool $expected): void
    {
        $this->fixture->write($content);
        $this->fixture->seek(0);
        $this->fixture->read($bytes);

        self::assertEquals($expected, $this->fixture->isEof());
    }

    public function sampleIsEof(): array
    {
        return [
            'not eof' => [
                'content' => uniqid(),
                'bytes' => rand(1, 5),
                'expected' => false,
            ],
            'is eof' => [
                'content' => uniqid(),
                'bytes' => rand(50, 100),
                'expected' => true,
            ],
        ];
    }

    /**
     * @dataProvider sampleGetSize
     */
    public function testGetSize(string $content, int $expected): void
    {
        $this->fixture->write($content);

        self::assertEquals($expected, $this->fixture->getSize());
    }

    public function sampleGetSize(): array
    {
        return [
            'no content' => [
                'content' => '',
                'expected' => 0,
            ],
            'has content' => [
                'content' => 'foo bar baz',
                'expected' => 11,
            ],
        ];
    }

    public function testSeekAndTellUsingSeekSet(): void
    {
        $this->fixture->write(str_repeat("\0", 100));
        $this->fixture->seek(rand(1, 99));
        $pos = rand(10, 30);

        self::assertTrue($this->fixture->seek($pos, \SEEK_SET));
        self::assertEquals($pos, $this->fixture->tell());
    }

    public function testSeekAndTellUsingSeekCur(): void
    {
        $this->fixture->write(str_repeat("\0", 100));
        $this->fixture->seek(50);
        $pos = rand(10, 30);

        self::assertTrue($this->fixture->seek($pos, \SEEK_CUR));
        self::assertEquals(50 + $pos, $this->fixture->tell());
    }

    public function testSeekAndTellUsingSeekEnd(): void
    {
        $this->fixture->write(str_repeat("\0", 100));
        $this->fixture->seek(rand(1, 99));
        $pos = rand(10, 30);

        self::assertTrue($this->fixture->seek(-$pos, \SEEK_END));
        self::assertEquals(100 - $pos, $this->fixture->tell());
    }

    public function testSeekPastEnd(): void
    {
        $this->fixture->write(str_repeat("\0", 100));
        $this->fixture->seek(rand(1, 99));

        self::assertFalse($this->fixture->seek(rand(101, 199), \SEEK_SET));
        self::assertEquals(0, $this->fixture->tell());
    }

    public function testSeekUnknownWhence(): void
    {
        $this->fixture->write(str_repeat("\0", 100));
        $this->fixture->seek(rand(1, 99));
        $pos = $this->fixture->tell();

        self::assertFalse($this->fixture->seek(rand(100, 199), rand(100, 1999)));
        self::assertEquals($pos, $this->fixture->tell());
    }
}
