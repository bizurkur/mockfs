<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\MockFileSystem;
use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test mkdir() and permissions
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class MakeDirTest extends AbstractTestCase
{
    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirIsDir(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        mkdir($url);

        self::assertTrue(is_dir($url), 'Failed to make directory');
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeRecursiveDir(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $child = $base . '/' . uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($child, 0777, true);

        self::assertTrue(is_dir($child), 'Failed to make directory');
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirGivesCorrectPermissions(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        mkdir($url, 0750);

        $actual = fileperms($url);

        self::assertEquals(FileInterface::TYPE_DIR | 0750, $actual);
    }

    /**
     * @dataProvider samplePrefixes
     * @runInSeparateProcess
     */
    public function testMakeDirGivesCorrectUmaskPermissions(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        $umask = 0022;

        umask($umask);
        MockFileSystem::umask($umask);

        mkdir($url, 0777);

        $actual = fileperms($url);

        self::assertEquals(FileInterface::TYPE_DIR | 0755, $actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirIsExecutable(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        mkdir($url, 0750);

        if (substr($prefix, 0, 4) === 'mfs:' && version_compare(PHP_VERSION, '7.3.0', '<')) {
            // PHP < 7.3 incorrect reports the executable bit
            self::assertFalse(is_executable($url));
        } else {
            self::assertTrue(is_executable($url));
        }
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirIsNotExecutable(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        mkdir($url, 0000);

        self::assertFalse(is_executable($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirIsReadable(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        mkdir($url, 0750);

        self::assertTrue(is_readable($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirIsNotReadable(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        mkdir($url, 0000);

        self::assertFalse(is_readable($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirIsWritable(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        mkdir($url, 0750);

        self::assertTrue(is_writable($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirIsNotWritable(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        mkdir($url, 0000);

        self::assertFalse(is_writable($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenParentDoesNotExistCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_') . '/' . uniqid();

        self::expectWarning();
        self::expectWarningMessage('mkdir(): No such file or directory');

        mkdir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenParentDoesNotExistResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_') . '/' . uniqid();

        $actual = @mkdir($url);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenNoWritePermissionCreatesError(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $child = $base . '/' . uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base, 0500);

        self::expectWarning();
        self::expectWarningMessage('mkdir(): Permission denied');

        mkdir($child);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenNoWritePermissionResponse(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $child = $base . '/' . uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base, 0500);

        $actual = @mkdir($child);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenFileExistsCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::expectWarning();
        self::expectWarningMessage('mkdir(): File exists');

        mkdir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenFileExistsResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $actual = @mkdir($url);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenPathContainsFileCreatesError(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $child = $base . '/' . uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base);
        file_put_contents($child, uniqid());

        self::expectWarning();
        // On Max, "not a directory" error.
        // On Linux, "not such file or directory" error.

        mkdir($child . '/' . uniqid());
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenPathContainsFileResponse(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $child = $base . '/' . uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base);
        file_put_contents($child, uniqid());

        $actual = @mkdir($child . '/' . uniqid());

        self::assertFalse($actual);
    }

    public function testMakeDirContextFailCreatesErrorMessage(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        $message = uniqid();

        $this->setContext(['mkdir_fail' => true, 'mkdir_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        mkdir($path);
    }

    public function testMakeDirContextFail(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();

        $this->setContext(['mkdir_fail' => true]);

        $actual = @mkdir($path);

        self::assertFalse($actual);
    }
}
