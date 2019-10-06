<?php declare(strict_types = 1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test permission operations:
 *
 * - chmod()
 * - chown()
 * - chgrp()
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class PermissionsTest extends AbstractTestCase
{
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

    public function testChownWithStringUser(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertFalse(chown($url, get_current_user()));
    }

    public function testChownMakesFileNotExecutable(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        chmod($url, 0700);
        self::assertTrue(is_executable($url), 'File did not start as executable');

        chown($url, 123);
        self::assertFalse(is_executable($url), 'File ended as executable');
    }

    public function testChownMakesFileNotReadable(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        chmod($url, 0700);
        self::assertTrue(is_readable($url), 'File did not start as readable');

        chown($url, 123);
        self::assertFalse(is_readable($url), 'File ended as readable');
    }

    public function testChownMakesFileNotWritable(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        chmod($url, 0700);
        self::assertTrue(is_writable($url), 'File did not start as writable');

        chown($url, 123);
        self::assertFalse(is_writable($url), 'File ended as writable');
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

    public function testChgrpWithStringGroup(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertFalse(chgrp($url, get_current_user()));
    }

    public function testChmodWhenPathNotExists(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_').'/'.uniqid();

        self::assertFalse(chmod($url, 0440));
    }

    public function testChmodWhenPathIsFile(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertTrue(chmod($url, 0440));
        self::assertEquals(FileInterface::TYPE_FILE|0440, fileperms($url));
    }

    public function testChmodWhenPathIsDir(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        mkdir($url);

        self::assertTrue(chmod($url, 0440));
        self::assertEquals(FileInterface::TYPE_DIR|0440, fileperms($url));
    }
}
