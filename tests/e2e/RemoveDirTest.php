<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test rmdir()
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class RemoveDirTest extends AbstractTestCase
{
    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotExistsCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');

        self::expectWarning();
        self::expectWarningMessage('rmdir(' . $url . '): No such file or directory');

        rmdir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotExistsResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');

        $actual = @rmdir($url);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenPathIsNotDirCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rmdir(' . $url . '): Not a directory');

        rmdir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenPathIsNotDirResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $actual = @rmdir($url);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenDirNotEmptyCreatesError(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $child = $base . '/' . uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base);
        file_put_contents($child, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rmdir(' . $base . '): Directory not empty');

        rmdir($base);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenDirNotEmptyResponse(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $child = $base . '/' . uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base);
        file_put_contents($child, uniqid());

        $actual = @rmdir($base);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotWritableCreatesError(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $child = $base . '/' . uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base, 0700);
        mkdir($child);
        chmod($base, 0500);

        self::expectWarning();
        self::expectWarningMessage('rmdir(' . $child . '): Permission denied');

        rmdir($child);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotWritableResponse(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $child = $base . '/' . uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base, 0700);
        mkdir($child);
        chmod($base, 0500);

        $actual = @rmdir($child);

        self::assertFalse($actual);
    }

    public function testRemoveDirContextFailCreatesError(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        mkdir($path);
        $message = uniqid();

        $this->setContext(['rmdir_fail' => true, 'rmdir_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        rmdir($path);
    }

    public function testRemoveDirContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        mkdir($path);

        $this->setContext(['rmdir_fail' => true]);

        $actual = @rmdir($path);

        self::assertFalse($actual);
    }
}
