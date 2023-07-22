<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\MockFileSystem;
use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test stat()
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class StatTest extends AbstractTestCase
{
    /**
     * @dataProvider samplePrefixes
     */
    public function testStatWhenFileNotExistsCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');

        self::expectWarning();
        self::expectWarningMessage('stat(): stat failed for ' . $url);

        // @phpstan-ignore-next-line
        stat($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testStatWhenFileNotExistsResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');

        self::assertFalse(@stat($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testStatHasCorrectKeys(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $actual = stat($url);
        if ($actual === false) {
            self::fail('Stat returned false');
        }

        $expected = [
            'dev',
            'ino',
            'mode',
            'nlink',
            'uid',
            'gid',
            'rdev',
            'size',
            'atime',
            'mtime',
            'ctime',
            'blksize',
            'blocks',
        ];
        self::assertEquals(array_merge(array_keys($expected), $expected), array_keys($actual));
        foreach ($expected as $index => $name) {
            self::assertEquals($actual[$index], $actual[$name]);
        }
    }

    public function testStatOnFile(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        $this->cleanup($url);
        $permissions = 0500;
        $now = time();
        file_put_contents($url, uniqid());
        chmod($url, $permissions);
        $config = MockFileSystem::getFileSystem()->getConfig();
        $file = MockFileSystem::find($url);
        if ($file === null) {
            self::fail('File not found');
        }

        $actual = stat($url);

        $expected = [
            0 => 0,
            1 => spl_object_id($file),
            2 => FileInterface::TYPE_FILE | $permissions,
            3 => 1,
            4 => $config->getUser(),
            5 => $config->getGroup(),
            6 => 0,
            7 => 13,
            8 => $now,
            9 => $now,
            10 => $now,
            11 => -1,
            12 => -1,
            'dev' => 0,
            'ino' => spl_object_id($file),
            'mode' => FileInterface::TYPE_FILE | $permissions,
            'nlink' => 1,
            'uid' => $config->getUser(),
            'gid' => $config->getGroup(),
            'rdev' => 0,
            'size' => 13,
            'atime' => $now,
            'mtime' => $now,
            'ctime' => $now,
            'blksize' => -1,
            'blocks' => -1,
        ];
        self::assertEquals($expected, $actual);
    }

    public function testStatOnDir(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        $this->cleanup($url);
        $permissions = 0750;
        $now = time();
        mkdir($url, $permissions);
        $config = MockFileSystem::getFileSystem()->getConfig();
        $file = MockFileSystem::find($url);
        if ($file === null) {
            self::fail('File not found');
        }

        $actual = stat($url);

        $expected = [
            0 => 0,
            1 => spl_object_id($file),
            2 => FileInterface::TYPE_DIR | $permissions,
            3 => 1,
            4 => $config->getUser(),
            5 => $config->getGroup(),
            6 => 0,
            7 => 0,
            8 => $now,
            9 => $now,
            10 => $now,
            11 => -1,
            12 => -1,
            'dev' => 0,
            'ino' => spl_object_id($file),
            'mode' => FileInterface::TYPE_DIR | $permissions,
            'nlink' => 1,
            'uid' => $config->getUser(),
            'gid' => $config->getGroup(),
            'rdev' => 0,
            'size' => 0,
            'atime' => $now,
            'mtime' => $now,
            'ctime' => $now,
            'blksize' => -1,
            'blocks' => -1,
        ];
        self::assertEquals($expected, $actual);
    }

    public function testStatContextFailCreatesError(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        file_put_contents($path, uniqid());
        $message = uniqid();

        $this->setContext(['stat_fail' => true, 'stat_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        // @phpstan-ignore-next-line
        stat($path);
    }

    public function testStatContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['stat_fail' => true]);

        $actual = @stat($path);

        self::assertFalse($actual);
    }
}
