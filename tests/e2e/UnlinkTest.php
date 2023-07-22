<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test unlink()
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class UnlinkTest extends AbstractTestCase
{
    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlinkNonExistentFileCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_src');

        self::expectWarning();
        self::expectWarningMessage('unlink(' . $url . '): No such file or directory');

        unlink($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlinkNonExistentFileResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_src');

        self::assertFalse(@unlink($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlinkDirCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_src');
        $this->cleanup($url);
        mkdir($url);

        self::expectWarning();
        // On Mac, "operation not permitted" error.
        // On Linux, "is a directory" error.

        unlink($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlinkDirResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_src');
        $this->cleanup($url);
        mkdir($url);

        self::assertFalse(@unlink($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlink(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_src');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::assertTrue(file_exists($url), 'File does not exist');
        self::assertTrue(unlink($url), 'Unlink failed');
        self::assertFalse(file_exists($url), 'File exists after unlink');
    }

    public function testUnlinkContextFailCreatesError(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        file_put_contents($path, uniqid());
        $message = uniqid();

        $this->setContext(['unlink_fail' => true, 'unlink_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        unlink($path);
    }

    public function testUnlinkContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['unlink_fail' => true]);

        $actual = unlink($path);

        self::assertFalse($actual);
        self::assertTrue(file_exists($path));
    }
}
