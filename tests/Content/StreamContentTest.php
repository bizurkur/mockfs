<?php declare(strict_types = 1);

namespace MockFileSystem\Tests\Content;

use MockFileSystem\Content\AbstractContent;
use MockFileSystem\Content\StreamContent;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Tests\Content\ContentTestCase;
use PHPUnit\Framework\Error\Warning;

// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
class StreamContentTest extends ContentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }

        $this->fixture = new StreamContent($stream);
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(AbstractContent::class, $this->fixture);
    }

    public function testStringResource(): void
    {
        $content = uniqid();

        $fixture = new StreamContent($content);

        self::assertEquals($content, $fixture->read(100));
    }

    public function testInvalidResource(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Expected a resource; integer given.');

        $stream = rand();

        new StreamContent($stream);
    }

    public function testCloseReleasesLock(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('mfs_');
        $this->cleanup($path);
        $streamA = fopen($path, 'w');
        if ($streamA === false) {
            self::fail('Failed to open handle A');
        }
        flock($streamA, \LOCK_EX | \LOCK_NB);

        $this->fixture = new StreamContent($streamA);
        $this->fixture->close();

        $streamB = fopen($path, 'w');
        if ($streamB === false) {
            self::fail('Failed to open handle B');
        }
        self::assertTrue(flock($streamB, \LOCK_EX | \LOCK_NB));
    }

    public function testReadOnClosedHandleCreatesError(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        fwrite($stream, uniqid());

        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::expectException(Warning::class);
        self::expectExceptionMessage('fread(): supplied resource is not a valid stream resource');

        self::assertEquals('', $this->fixture->read(5));
    }

    public function testReadOnClosedHandleResponse(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        fwrite($stream, uniqid());

        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertEquals('', @$this->fixture->read(5));
    }

    public function testWriteOnClosedHandleCreatesError(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::expectException(Warning::class);
        self::expectExceptionMessage('fwrite(): supplied resource is not a valid stream resource');

        self::assertEquals(0, $this->fixture->write(uniqid()));
    }

    public function testWriteOnClosedHandleResponse(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertEquals(0, @$this->fixture->write(uniqid()));
    }

    public function testTruncateOnClosedHandleCreatesError(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::expectException(Warning::class);
        self::expectExceptionMessage('ftruncate(): supplied resource is not a valid stream resource');

        self::assertFalse($this->fixture->truncate(rand(1, 100)));
    }

    public function testTruncateOnClosedHandleResponse(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertFalse(@$this->fixture->truncate(rand(1, 100)));
    }

    public function testIsEofOnClosedHandleCreatesError(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::expectException(Warning::class);
        self::expectExceptionMessage('feof(): supplied resource is not a valid stream resource');

        self::assertFalse($this->fixture->isEof());
    }

    public function testIsEofOnClosedHandleResponse(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertFalse(@$this->fixture->isEof());
    }

    public function testGetSizeOnClosedHandleCreatesError(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::expectException(Warning::class);
        self::expectExceptionMessage('fstat(): supplied resource is not a valid stream resource');

        self::assertEquals(0, $this->fixture->getSize());
    }

    public function testGetSizeOnClosedHandleResponse(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertEquals(0, @$this->fixture->getSize());
    }

    public function testSeekOnClosedHandleCreatesError(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        $this->fixture->write(uniqid());
        fclose($stream);

        self::expectException(Warning::class);
        self::expectExceptionMessage('fseek(): supplied resource is not a valid stream resource');

        self::assertFalse($this->fixture->seek(3));
    }

    public function testSeekOnClosedHandleResponse(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        $this->fixture->write(uniqid());
        fclose($stream);

        self::assertFalse(@$this->fixture->seek(3));
    }

    public function testTellOnClosedHandleCreatesError(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        $this->fixture->write(uniqid());
        fclose($stream);

        self::expectException(Warning::class);
        self::expectExceptionMessage('ftell(): supplied resource is not a valid stream resource');

        self::assertEquals(0, $this->fixture->tell());
    }

    public function testTellOnClosedHandleResponse(): void
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        $this->fixture->write(uniqid());
        fclose($stream);

        self::assertEquals(0, @$this->fixture->tell());
    }

    /**
     * Cleans up temporary files.
     *
     * @param string $file
     */
    private function cleanup(string $file): void
    {
        register_shutdown_function(
            function () use ($file) {
                if (!@file_exists($file)) {
                    return;
                }

                @unlink($file);
            }
        );
    }
}
