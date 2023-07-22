<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test directory operations:
 *
 * - opendir()
 * - readdir()
 * - rewinddir()
 * - closedir()
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class DirOperationsTest extends AbstractTestCase
{
    /**
     * @dataProvider samplePrefixes
     */
    public function testOpenDirWhenNotExistsCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');

        self::expectWarning();
        self::expectWarningMessageMatches(
            '/opendir\(' . preg_quote($url, '/') . '\): [Ff]ailed to open dir(?:ectory)?: No such file or directory/'
        );

        opendir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testOpenDirWhenNotExistsResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');

        $handle = @opendir($url);

        self::assertFalse($handle);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testOpenDirWhenNotReadableCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        mkdir($url, 0000);

        self::expectWarning();
        self::expectWarningMessageMatches(
            '/opendir\(' . preg_quote($url, '/') . '\): [Ff]ailed to open dir(?:ectory)?: Permission denied/'
        );

        opendir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testOpenDirWhenNotReadableResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        mkdir($url, 0000);

        $actual = @opendir($url);

        self::assertFalse($actual);
    }

    public function testOpenDirContextFailCreatesError(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        mkdir($path);
        $message = uniqid();

        $this->setContext(['opendir_fail' => true, 'opendir_message' => $message]);

        self::expectWarning();
        self::expectExceptionMessage($message);

        opendir($path);
    }

    public function testOpenDirContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        mkdir($path);

        $this->setContext(['opendir_fail' => true]);

        $actual = @opendir($path);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadDir(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $childA = uniqid('a');
        $childB = uniqid('b');
        $this->cleanup($base . '/' . $childA);
        $this->cleanup($base . '/' . $childB);
        $this->cleanup($base);
        mkdir($base);
        mkdir($base . '/' . $childA);
        file_put_contents($base . '/' . $childB, uniqid());

        $handle = opendir($base);
        if ($handle === false) {
            self::fail('Failed to open dir handle');
        }

        $actual = [];
        while (($entry = readdir($handle)) !== false) {
            $actual[] = $entry;
        }

        // 2nd loop should do nothing
        while (($entry = readdir($handle)) !== false) {
            $actual[] = $entry;
        }

        closedir($handle);

        sort($actual);
        self::assertEquals(['.', '..', $childA, $childB], $actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadDirWithRewind(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $childA = uniqid('a');
        $childB = uniqid('b');
        $this->cleanup($base . '/' . $childA);
        $this->cleanup($base . '/' . $childB);
        $this->cleanup($base);
        mkdir($base);
        mkdir($base . '/' . $childA);
        file_put_contents($base . '/' . $childB, uniqid());

        $handle = opendir($base);
        if ($handle === false) {
            self::fail('Failed to open dir handle');
        }

        $actual = [];
        while (($entry = readdir($handle)) !== false) {
            $actual[] = $entry;
        }

        rewinddir($handle);
        while (($entry = readdir($handle)) !== false) {
            $actual[] = $entry;
        }

        closedir($handle);

        $expected = ['.', '..', $childA, $childB, '.', '..', $childA, $childB];
        sort($actual);
        sort($expected);
        self::assertEquals($expected, $actual);
    }

    public function testReadDirContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        mkdir($path);

        $this->setContext(['readdir_fail' => true]);

        $handle = opendir($path);
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = readdir($handle);

        self::assertFalse($actual);
    }

    /**
     * TODO: Not sure if it's a bug in php, but even if dir_rewinddir() returns
     * false rewinddir() itself returns null (success).
     */
    public function testRewindDirContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        mkdir($path);

        $this->setContext(['rewinddir_fail' => true]);

        $fixture = new StreamWrapper();
        $fixture->dir_opendir($path, 0);
        $actual = $fixture->dir_rewinddir();

        self::assertFalse($actual);
    }

    /**
     * TODO: Not sure if it's a bug in php, but even if dir_closedir() returns
     * false the stream wrapper handle is still closed.
     */
    public function testCloseDirContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        mkdir($path);

        $this->setContext(['closedir_fail' => true]);

        $fixture = new StreamWrapper();
        $fixture->dir_opendir($path, 0);
        $actual = $fixture->dir_closedir();

        self::assertFalse($actual);
    }
}
