<?php declare(strict_types = 1);

namespace MockFileSystem\Tests\Content;

use MockFileSystem\Content\AbstractContent;
use MockFileSystem\Content\StreamContent;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Tests\Content\ContentTestCase;

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

    public function testInvalidResource(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Expected a resource; string given.');

        /** @var resource $stream */
        $stream = uniqid();

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

    public function testReadOnClosedHandle(): void
    {
        $level = error_reporting();
        error_reporting(0);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        fwrite($stream, uniqid());

        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertEquals('', $this->fixture->read(5));

        error_reporting($level);
    }

    public function testWriteOnClosedHandle(): void
    {
        $level = error_reporting();
        error_reporting(0);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertEquals(0, $this->fixture->write(uniqid()));

        error_reporting($level);
    }

    public function testTruncateOnClosedHandle(): void
    {
        $level = error_reporting();
        error_reporting(0);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertFalse($this->fixture->truncate(rand(1, 100)));

        error_reporting($level);
    }

    public function testIsEofOnClosedHandle(): void
    {
        $level = error_reporting();
        error_reporting(0);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertFalse($this->fixture->isEof());

        error_reporting($level);
    }

    public function testGetSizeOnClosedHandle(): void
    {
        $level = error_reporting();
        error_reporting(0);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        fclose($stream);

        self::assertEquals(0, $this->fixture->getSize());

        error_reporting($level);
    }

    public function testSeekOnClosedHandle(): void
    {
        $level = error_reporting();
        error_reporting(0);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        $this->fixture->write(uniqid());
        fclose($stream);

        self::assertFalse($this->fixture->seek(3));

        error_reporting($level);
    }

    public function testTellOnClosedHandle(): void
    {
        $level = error_reporting();
        error_reporting(0);

        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            self::fail('Failed to open handle');
        }
        $this->fixture = new StreamContent($stream);
        $this->fixture->write(uniqid());
        fclose($stream);

        self::assertEquals(0, $this->fixture->tell());

        error_reporting($level);
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
                error_reporting(0);

                if (!file_exists($file)) {
                    return;
                }

                unlink($file);
            }
        );
    }
}
