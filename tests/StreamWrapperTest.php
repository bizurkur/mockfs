<?php declare(strict_types = 1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\FileInterface;

use MockFileSystem\MockFileSystem;
use MockFileSystem\StreamWrapper;
use PHPUnit\Framework\TestCase;

class StreamWrapperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MockFileSystem::create();
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadAndWrite(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        file_put_contents($url, $content);

        self::assertEquals($content, file_get_contents($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testWriteSameFileFromTwoHandles(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $contentA = uniqid('a');
        $contentB = uniqid('b');

        $handle1 = fopen($url, 'w');
        if ($handle1 === false) {
            self::fail('Failed to open handle 1');
        }
        $handle2 = fopen($url, 'w');
        if ($handle2 === false) {
            self::fail('Failed to open handle 2');
        }

        fwrite($handle1, $contentA.$contentA);
        fwrite($handle2, $contentB);
        fclose($handle1);
        fclose($handle2);

        self::assertEquals($contentB.$contentA, file_get_contents($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testWriteDifferentFileFromTwoHandles(string $prefix): void
    {
        $urlA = $prefix.'/'.uniqid('mfs_a');
        $urlB = $prefix.'/'.uniqid('mfs_b');
        $this->cleanup($urlA);
        $this->cleanup($urlB);
        $contentA = uniqid('a');
        $contentB = uniqid('b');

        $handle1 = fopen($urlA, 'w');
        if ($handle1 === false) {
            self::fail('Failed to open handle 1');
        }
        $handle2 = fopen($urlB, 'w');
        if ($handle2 === false) {
            self::fail('Failed to open handle 2');
        }

        fwrite($handle1, $contentA);
        fwrite($handle2, $contentB);
        fclose($handle1);
        fclose($handle2);

        self::assertEquals($contentA, file_get_contents($urlA));
        self::assertEquals($contentB, file_get_contents($urlB));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadSameFileFromTwoHandles(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();
        file_put_contents($url, $content);

        $handle1 = fopen($url, 'r');
        $handle2 = fopen($url, 'r');
        if ($handle1 === false) {
            self::fail('Failed to open handle 1');
        }
        if ($handle2 === false) {
            self::fail('Failed to open handle 2');
        }

        $contentA = fread($handle1, 4096);
        $contentB = fread($handle2, 4096);
        fclose($handle1);
        fclose($handle2);

        self::assertEquals($content, $contentA);
        self::assertEquals($content, $contentB);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadDifferentFileFromTwoHandles(string $prefix): void
    {
        $urlA = $prefix.'/'.uniqid('mfs_');
        $urlB = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($urlA);
        $this->cleanup($urlB);
        $contentA = uniqid('a');
        $contentB = uniqid('b');
        file_put_contents($urlA, $contentA);
        file_put_contents($urlB, $contentB);

        $handle1 = fopen($urlA, 'r');
        if ($handle1 === false) {
            self::fail('Failed to open handle 1');
        }
        $handle2 = fopen($urlB, 'r');
        if ($handle2 === false) {
            self::fail('Failed to open handle 2');
        }

        $actualA = fread($handle1, 4096);
        $actualB = fread($handle2, 4096);
        fclose($handle1);
        fclose($handle2);

        self::assertEquals($contentA, $actualA);
        self::assertEquals($contentB, $actualB);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRename(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest');
        $this->cleanup($src);
        $this->cleanup($dest);

        $content = uniqid();
        file_put_contents($src, $content);

        rename($src, $dest);

        self::assertFalse(file_exists($src), 'Source file not removed');
        self::assertTrue(file_exists($dest), 'Destination file not created');
        self::assertEquals($content, file_get_contents($dest), 'Content not moved');
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTouchWhenNotExists(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);

        touch($url);
        $now = time();
        $stat = stat($url);

        self::assertEqualsWithDelta($now, $stat['atime'], 1);
        self::assertEqualsWithDelta($now, $stat['mtime'], 1);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTouchWhenPathNotExists(string $prefix): void
    {
        $level = error_reporting();
        error_reporting(0);

        $url = $prefix.'/'.uniqid('mfs_').'/'.uniqid();
        $this->cleanup($url);

        self::assertFalse(touch($url));

        error_reporting($level);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTouchWhenFileAlreadyExist(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        touch($url);
        $now = time();
        $stat = stat($url);

        self::assertEqualsWithDelta($now, $stat['atime'], 1);
        self::assertEqualsWithDelta($now, $stat['mtime'], 1);
    }

    public function testChownWhenPathNotExists(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_').'/'.uniqid();

        self::assertFalse(chown($url, 123));
    }

    public function testChownWhenPathExists(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertTrue(chown($url, 123));
        self::assertEquals(123, fileowner($url));
    }

    public function testChgrpWhenPathNotExists(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_').'/'.uniqid();

        self::assertFalse(chgrp($url, 123));
    }

    public function testChgrpWhenPathExists(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertTrue(chgrp($url, 123));
        self::assertEquals(123, filegroup($url));
    }

    public function testChmodWhenPathNotExists(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_').'/'.uniqid();

        self::assertFalse(chmod($url, 0440));
    }

    public function testChmodWhenPathExists(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertTrue(chmod($url, 0440));
        self::assertEquals(FileInterface::TYPE_FILE|0440, fileperms($url));
    }

    public function samplePrefixes(): array
    {
        return [
            [StreamWrapper::PROTOCOL.'://'],
            [sys_get_temp_dir()],
        ];
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
