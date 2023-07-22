<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\MockFileSystem;
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
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_') . '/' . uniqid();

        self::assertFalse(chown($url, 123));
    }

    public function testChownWhenPathExists(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertTrue(chown($url, 123));
        self::assertEquals(123, fileowner($url));
    }

    public function testChownWithStringUser(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertFalse(chown($url, get_current_user()));
    }

    public function testChownMakesFileNotExecutable(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        chmod($url, 0700);
        self::assertTrue(is_executable($url), 'File did not start as executable');

        chown($url, 123);
        self::assertFalse(is_executable($url), 'File ended as executable');
    }

    public function testChownMakesFileNotReadable(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        chmod($url, 0700);
        self::assertTrue(is_readable($url), 'File did not start as readable');

        chown($url, 123);
        self::assertFalse(is_readable($url), 'File ended as readable');
    }

    public function testChownMakesFileNotWritable(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        chmod($url, 0700);
        self::assertTrue(is_writable($url), 'File did not start as writable');

        chown($url, 123);
        self::assertFalse(is_writable($url), 'File ended as writable');
    }

    public function testChownUpdatesLastChangeTime(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());
        /** @var FileInterface $file */
        $file = MockFileSystem::find($url);
        $file->setLastChangeTime(rand());

        $now = time();
        chown($url, 123);

        $actual = stat($url);
        if ($actual === false) {
            self::fail();
        }

        self::assertEquals($now, $actual['ctime']);
    }

    public function testChgrpWhenPathNotExists(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_') . '/' . uniqid();

        self::assertFalse(chgrp($url, 123));
    }

    public function testChgrpWhenPathExists(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertTrue(chgrp($url, 123));
        self::assertEquals(123, filegroup($url));
    }

    public function testChgrpWithStringGroup(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertFalse(chgrp($url, get_current_user()));
    }

    public function testChgrpUpdatesLastChangeTime(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());
        /** @var FileInterface $file */
        $file = MockFileSystem::find($url);
        $file->setLastChangeTime(rand());

        $now = time();
        chgrp($url, 123);

        $actual = stat($url);
        if ($actual === false) {
            self::fail();
        }

        self::assertEquals($now, $actual['ctime']);
    }

    public function testChmodWhenPathNotExists(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_') . '/' . uniqid();

        self::assertFalse(chmod($url, 0440));
    }

    public function testChmodWhenPathIsFile(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertTrue(chmod($url, 0440));
        self::assertEquals(FileInterface::TYPE_FILE | 0440, fileperms($url));
    }

    public function testChmodWhenPathIsDir(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        mkdir($url);

        self::assertTrue(chmod($url, 0440));
        self::assertEquals(FileInterface::TYPE_DIR | 0440, fileperms($url));
    }

    public function testChmodUpdatesLastChangeTime(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());
        /** @var FileInterface $file */
        $file = MockFileSystem::find($url);
        $file->setLastChangeTime(rand());

        $now = time();
        chmod($url, 0440);

        $actual = stat($url);
        if ($actual === false) {
            self::fail();
        }

        self::assertEquals($now, $actual['ctime']);
    }

    /**
     * @dataProvider sampleIsReadable
     */
    public function testIsReadable(?int $permissions, ?int $user, ?int $group, bool $expected): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        if ($permissions !== null) {
            self::assertTrue(chmod($url, $permissions), 'chmod failed');
        }
        if ($group !== null) {
            self::assertTrue(chgrp($url, $group), 'chgrp failed');
        }
        if ($user !== null) {
            self::assertTrue(chown($url, $user), 'chown failed');
        }

        /** @var FileInterface $file */
        $file = MockFileSystem::find($url);
        $config = $file->getConfig();
        $actual = $file->isReadable($config->getUser(), $config->getGroup());

        self::assertEquals($expected, $actual);
    }

    public function sampleIsReadable(): array
    {
        return [
            'readable to user' => [
                'permissions' => 0400,
                'user' => null,
                'group' => null,
                'expected' => true,
            ],
            'not readable to user' => [
                'permissions' => 0200,
                'user' => null,
                'group' => null,
                'expected' => false,
            ],
            'readable to group' => [
                'permissions' => 0040,
                'user' => 123,
                'group' => null,
                'expected' => true,
            ],
            'not readable to group' => [
                'permissions' => 0020,
                'user' => 123,
                'group' => null,
                'expected' => false,
            ],
            'readable to other' => [
                'permissions' => 0004,
                'user' => 123,
                'group' => 123,
                'expected' => true,
            ],
            'not readable to other' => [
                'permissions' => 0002,
                'user' => 123,
                'group' => 123,
                'expected' => false,
            ],
            'readable to nobody' => [
                'permissions' => 0000,
                'user' => 123,
                'group' => 123,
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider sampleIsWritable
     */
    public function testIsWritable(?int $permissions, ?int $user, ?int $group, bool $expected): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        if ($permissions !== null) {
            self::assertTrue(chmod($url, $permissions), 'chmod failed');
        }
        if ($group !== null) {
            self::assertTrue(chgrp($url, $group), 'chgrp failed');
        }
        if ($user !== null) {
            self::assertTrue(chown($url, $user), 'chown failed');
        }

        /** @var FileInterface $file */
        $file = MockFileSystem::find($url);
        $config = $file->getConfig();
        $actual = $file->isWritable($config->getUser(), $config->getGroup());

        self::assertEquals($expected, $actual);
    }

    public function sampleIsWritable(): array
    {
        return [
            'writable to user' => [
                'permissions' => 0200,
                'user' => null,
                'group' => null,
                'expected' => true,
            ],
            'not writable to user' => [
                'permissions' => 0400,
                'user' => null,
                'group' => null,
                'expected' => false,
            ],
            'writable to group' => [
                'permissions' => 0020,
                'user' => 123,
                'group' => null,
                'expected' => true,
            ],
            'not writable to group' => [
                'permissions' => 0040,
                'user' => 123,
                'group' => null,
                'expected' => false,
            ],
            'writable to other' => [
                'permissions' => 0002,
                'user' => 123,
                'group' => 123,
                'expected' => true,
            ],
            'not writable to other' => [
                'permissions' => 0004,
                'user' => 123,
                'group' => 123,
                'expected' => false,
            ],
            'writable to nobody' => [
                'permissions' => 0000,
                'user' => 123,
                'group' => 123,
                'expected' => false,
            ],
        ];
    }

    /**
     * @dataProvider sampleIsExecutable
     */
    public function testIsExecutable(?int $permissions, ?int $user, ?int $group, bool $expected): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        if ($permissions !== null) {
            self::assertTrue(chmod($url, $permissions), 'chmod failed');
        }
        if ($group !== null) {
            self::assertTrue(chgrp($url, $group), 'chgrp failed');
        }
        if ($user !== null) {
            self::assertTrue(chown($url, $user), 'chown failed');
        }

        /** @var FileInterface $file */
        $file = MockFileSystem::find($url);
        $config = $file->getConfig();
        $actual = $file->isExecutable($config->getUser(), $config->getGroup());

        self::assertEquals($expected, $actual);
    }

    public function sampleIsExecutable(): array
    {
        return [
            'executable to user' => [
                'permissions' => 0100,
                'user' => null,
                'group' => null,
                'expected' => true,
            ],
            'not executable to user' => [
                'permissions' => 0200,
                'user' => null,
                'group' => null,
                'expected' => false,
            ],
            'executable to group' => [
                'permissions' => 0010,
                'user' => 123,
                'group' => null,
                'expected' => true,
            ],
            'not executable to group' => [
                'permissions' => 0020,
                'user' => 123,
                'group' => null,
                'expected' => false,
            ],
            'executable to other' => [
                'permissions' => 0001,
                'user' => 123,
                'group' => 123,
                'expected' => true,
            ],
            'not executable to other' => [
                'permissions' => 0002,
                'user' => 123,
                'group' => 123,
                'expected' => false,
            ],
            'executable to nobody' => [
                'permissions' => 0000,
                'user' => 123,
                'group' => 123,
                'expected' => false,
            ],
        ];
    }
}
