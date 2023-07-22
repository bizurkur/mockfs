<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test touch()
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class TouchTest extends AbstractTestCase
{
    /**
     * @dataProvider samplePrefixes
     */
    public function testTouchWhenNotExists(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        touch($url);
        $now = time();
        $stat = stat($url);
        if ($stat === false) {
            self::fail();
        }

        self::assertTrue(is_file($url));
        self::assertEqualsWithDelta($now, $stat['atime'], 1);
        self::assertEqualsWithDelta($now, $stat['mtime'], 1);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTouchWhenPathNotExistsCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_') . '/' . uniqid();
        $this->cleanup($url);

        self::expectWarning();
        self::expectWarningMessage('touch(): Unable to create file ' . $url);

        self::assertFalse(touch($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTouchWhenPathNotExistsResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_') . '/' . uniqid();
        $this->cleanup($url);

        self::assertFalse(@touch($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTouchWhenFileAlreadyExist(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        touch($url);
        $now = time();
        $stat = stat($url);
        if ($stat === false) {
            self::fail();
        }

        self::assertTrue(is_file($url));
        self::assertEqualsWithDelta($now, $stat['atime'], 1);
        self::assertEqualsWithDelta($now, $stat['mtime'], 1);
    }

    public function testTouchContextFailCreatesError(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        $message = uniqid();

        $this->setContext(['touch_fail' => true, 'touch_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        touch($path);
    }

    public function testTouchContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();

        $this->setContext(['touch_fail' => true]);

        $actual = @touch($path);

        self::assertFalse($actual);
    }
}
