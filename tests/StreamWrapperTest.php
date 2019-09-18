<?php declare(strict_types = 1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\MockFileSystem;
use MockFileSystem\StreamWrapper;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
class StreamWrapperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MockFileSystem::create();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        MockFileSystem::destroy();
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeAndRemoveDir(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);

        mkdir($url);

        self::assertTrue(is_dir($url), 'Failed to make directory');

        rmdir($url);

        self::assertFalse(file_exists($url), 'Failed to remove directory');
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeRecursiveDir(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($child, 0777, true);

        self::assertTrue(is_dir($child), 'Failed to make directory');

        rmdir($child);
        rmdir($base);
    }

    /**
     * @dataProvider samplePrefixes
     * @runInSeparateProcess
     */
    public function testMakeDirGivesCorrectPermissions(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $umask = 0022;

        umask($umask);
        MockFileSystem::umask($umask);

        mkdir($url, 0777);

        $actual = fileperms($url);
        $actual &= ~FileInterface::TYPE_DIR;

        self::assertEquals(0755, $actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenParentDoesNotExistCreatesError(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_').'/'.uniqid();

        self::expectException(Warning::class);
        self::expectExceptionMessage('mkdir(): No such file or directory');

        self::assertFalse(mkdir($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenParentDoesNotExistReturnsFalse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_').'/'.uniqid();

        self::assertFalse(@mkdir($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenNoWritePermissionCreatesError(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base, 0500);

        self::expectException(Warning::class);
        self::expectExceptionMessage('mkdir(): Permission denied');

        self::assertFalse(mkdir($child));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenNoWritePermissionReturnsFalse(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base, 0500);

        self::assertFalse(@mkdir($child));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenFileExistsCreatesError(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::expectException(Warning::class);
        self::expectExceptionMessage('mkdir(): File exists');

        self::assertFalse(mkdir($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenFileExistsReturnsFalse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::assertFalse(@mkdir($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenPathContainsFileCreatesError(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base);
        file_put_contents($child, uniqid());

        self::expectException(Warning::class);
        self::expectExceptionMessage('mkdir(): Not a directory');

        self::assertFalse(mkdir($child.'/'.uniqid()));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenPathContainsFileReturnsFalse(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base);
        file_put_contents($child, uniqid());

        self::assertFalse(@mkdir($child.'/'.uniqid()));
    }

    public function testMakeDirWhenNoPartitionCreatesError(): void
    {
        $base = StreamWrapper::PROTOCOL.'://';
        MockFileSystem::getFileSystem()->removeChild('/');

        self::expectException(Warning::class);
        self::expectExceptionMessage('mkdir(): No such file or directory');

        self::assertFalse(mkdir($base));
    }

    public function testMakeDirWhenNoPartitionReturnsFalse(): void
    {
        $base = StreamWrapper::PROTOCOL.'://';
        MockFileSystem::getFileSystem()->removeChild('/');

        self::assertFalse(@mkdir($base));
    }

    public function testMakeDirWhenPartitionNotExistsCreatesError(): void
    {
        $base = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        MockFileSystem::getFileSystem()->removeChild('/');
        MockFileSystem::createPartition('c:');

        self::expectException(Warning::class);
        self::expectExceptionMessage('mkdir(): No such file or directory');

        self::assertFalse(mkdir($base));
    }

    public function testMakeDirWhenPartitionNotExistsReturnsFalse(): void
    {
        $base = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        MockFileSystem::getFileSystem()->removeChild('/');
        MockFileSystem::createPartition('c:');

        self::assertFalse(@mkdir($base));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotExistsCreatesError(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');

        self::expectException(Warning::class);
        self::expectExceptionMessage('rmdir('.$url.'): No such file or directory');

        self::assertFalse(rmdir($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotExistsReturnsFalse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');

        self::assertFalse(@rmdir($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenPathIsNotDirCreatesError(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::expectException(Warning::class);
        self::expectExceptionMessage('rmdir('.$url.'): Not a directory');

        self::assertFalse(rmdir($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenPathIsNotDirReturnsFalse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::assertFalse(@rmdir($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenDirNotEmptyCreatesError(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base);
        file_put_contents($child, uniqid());

        self::expectException(Warning::class);
        self::expectExceptionMessage('rmdir('.$base.'): Directory not empty');

        self::assertFalse(rmdir($base));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenDirNotEmptyReturnsFalse(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base);
        file_put_contents($child, uniqid());

        self::assertFalse(@rmdir($base));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotWritableCreatesError(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base, 0700);
        mkdir($child);
        chmod($base, 0500);

        self::expectException(Warning::class);
        self::expectExceptionMessage('rmdir('.$child.'): Permission denied');

        self::assertFalse(rmdir($child));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotWritableReturnsFalse(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base, 0700);
        mkdir($child);
        chmod($base, 0500);

        self::assertFalse(@rmdir($child));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testOpenDirWhenNotExistsCreatesError(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');

        self::expectException(Warning::class);
        self::expectExceptionMessage('opendir('.$url.'): failed to open dir: No such file or directory');

        opendir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testOpenDirWhenNotExistsReturnsFalse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');

        $handle = @opendir($url);

        self::assertFalse($handle);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testOpenDirWhenNotReadableCreatesError(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        mkdir($url, 0000);

        self::expectException(Warning::class);
        self::expectExceptionMessage('opendir('.$url.'): failed to open dir: Permission denied');

        opendir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testOpenDirWhenNotReadableReturnsFalse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        mkdir($url, 0000);

        self::assertFalse(@opendir($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadDir(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $childA = uniqid('a');
        $childB = uniqid('b');
        $this->cleanup($base.'/'.$childA);
        $this->cleanup($base.'/'.$childB);
        $this->cleanup($base);
        mkdir($base);
        mkdir($base.'/'.$childA);
        file_put_contents($base.'/'.$childB, uniqid());

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
        $base = $prefix.'/'.uniqid('mfs_');
        $childA = uniqid('a');
        $childB = uniqid('b');
        $this->cleanup($base.'/'.$childA);
        $this->cleanup($base.'/'.$childB);
        $this->cleanup($base);
        mkdir($base);
        mkdir($base.'/'.$childA);
        file_put_contents($base.'/'.$childB, uniqid());

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
        $url = $prefix.'/'.uniqid('mfs_').'/'.uniqid();
        $this->cleanup($url);

        self::expectException(Warning::class);
        self::expectExceptionMessage('touch(): Unable to create file '.$url);

        self::assertFalse(touch($url));
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
                if (!@file_exists($file)) {
                    return;
                }

                if (is_file($file)) {
                    @unlink($file);
                } else {
                    @rmdir($file);
                }
            }
        );
    }
}
