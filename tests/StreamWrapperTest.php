<?php declare(strict_types = 1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Content\ContentInterface;
use MockFileSystem\MockFileSystem;
use MockFileSystem\StreamWrapper;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

// TODO: Split this into end-to-end tests and unit tests
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

        mkdir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenParentDoesNotExistResponse(string $prefix): void
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

        mkdir($child);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenNoWritePermissionResponse(string $prefix): void
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

        mkdir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenFileExistsResponse(string $prefix): void
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
        // On Max, "not a directory" error.
        // On Linux, "not such file or directory" error.

        mkdir($child.'/'.uniqid());
    }

    public function testMakeDirWhenPathContainsFileCreatesErrorMessage(): void
    {
        $base = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $child = $base.'/'.uniqid();
        $this->cleanup($child);
        $this->cleanup($base);

        mkdir($base);
        file_put_contents($child, uniqid());

        self::expectException(Warning::class);
        self::expectExceptionMessage('mkdir(): Not a directory');

        mkdir($child.'/'.uniqid());
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testMakeDirWhenPathContainsFileResponse(string $prefix): void
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

        mkdir($base);
    }

    public function testMakeDirWhenNoPartitionResponse(): void
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

        mkdir($base);
    }

    public function testMakeDirWhenPartitionNotExistsResponse(): void
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

        rmdir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotExistsResponse(string $prefix): void
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

        rmdir($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenPathIsNotDirResponse(string $prefix): void
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

        rmdir($base);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenDirNotEmptyResponse(string $prefix): void
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

        rmdir($child);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRemoveDirWhenNotWritableResponse(string $prefix): void
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
    public function testOpenDirWhenNotExistsResponse(string $prefix): void
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
    public function testOpenDirWhenNotReadableResponse(string $prefix): void
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
     * @dataProvider sampleInvalidModes
     */
    public function testFileOpenInvalidModeCreatesError(string $mode): void
    {
        $url = uniqid('mfs_');
        $path = uniqid();

        $fixture = new StreamWrapper();

        self::expectException(Warning::class);
        self::expectExceptionMessage('Illegal mode "'.$mode.'"');

        $fixture->stream_open($url, $mode, \STREAM_REPORT_ERRORS, $path);
    }

    public function sampleInvalidModes(): array
    {
        return [
            'unknown mode' => ['q'],
            'unknown modifier' => ['rq'],
            'extra characters' => ['rbq'],
        ];
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testFileOpenInvalidModeResponse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);

        self::assertFalse(@fopen($url, 'q'));
    }

    // TODO: Test this
    // /**
    //  * @dataProvider samplePrefixes
    //  */
    // public function testFileOpenForReadOnNonExistentFileCreatesError(string $prefix): void
    // {
    //     $url = $prefix.'/'.uniqid('mfs_');
    //     $this->cleanup($url);
    //
    //     self::expectException(Warning::class);
    //     self::expectExceptionMessage('fopen('.$url.'): failed to open stream: No such file or directory');
    //
    //     fopen($url, 'r');
    // }

    public function testFileOpenForReadOnNonExistentFileCreatesError(): void
    {
        $url = uniqid('mfs_');
        $path = uniqid();

        $fixture = new StreamWrapper();

        self::expectException(Warning::class);
        self::expectExceptionMessage('Cannot open non-existent file "'.$url.'" for reading.');

        $fixture->stream_open($url, 'r', \STREAM_REPORT_ERRORS, $path);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testFileOpenForReadOnNonExistentFileResponse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);

        self::assertFalse(@fopen($url, 'r'));
    }

    /**
     * @dataProvider sampleCreateNewModes
     */
    public function testFileOpenForCreateNewWhenExistsCreatesError(string $mode): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = uniqid();
        file_put_contents($url, uniqid());

        $fixture = new StreamWrapper();

        self::expectException(Warning::class);
        self::expectExceptionMessage('File "'.$url.'" already exists; cannot open in mode '.$mode);

        $fixture->stream_open($url, $mode, \STREAM_REPORT_ERRORS, $path);
    }

    /**
     * @dataProvider sampleCreateNewModes
     */
    public function testFileOpenForCreateNewWhenExistsDoesNotCreateError(string $mode): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = uniqid();
        file_put_contents($url, uniqid());

        $fixture = new StreamWrapper();

        $actual = $fixture->stream_open($url, $mode, 0, $path);

        self::assertFalse($actual);
    }

    public function sampleCreateNewModes(): array
    {
        return [
            ['x'],
            ['x+'],
            ['xb'],
            ['xb+'],
        ];
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testFileOpenForCreateNewWhenExistsResponse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::assertFalse(@fopen($url, 'x'));
    }

    /**
     * @dataProvider sampleReadModes
     */
    public function testFileOpenForReadWhenNotReadableCreatesError(string $mode): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = uniqid();
        file_put_contents($url, uniqid());
        chmod($url, 0200);

        $fixture = new StreamWrapper();

        self::expectException(Warning::class);
        self::expectExceptionMessage('File "'.$url.'" is not readable.');

        $fixture->stream_open($url, $mode, \STREAM_REPORT_ERRORS, $path);
    }

    /**
     * @dataProvider sampleReadModes
     */
    public function testFileOpenForReadWhenNotReadableDoesNotCreateError(string $mode): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = uniqid();
        file_put_contents($url, uniqid());
        chmod($url, 0200);

        $fixture = new StreamWrapper();

        $actual = $fixture->stream_open($url, $mode, 0, $path);

        self::assertFalse($actual);
    }

    public function sampleReadModes(): array
    {
        return [
            ['r'],
            ['r+'],
            ['rb'],
            ['rb+'],
            ['w+'],
            ['wb+'],
        ];
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testFileOpenForReadWhenNotReadableResponse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());
        chmod($url, 0200);

        self::assertFalse(@fopen($url, 'r'));
    }

    /**
     * @dataProvider sampleWriteModes
     */
    public function testFileOpenForWriteWhenNotWritableCreatesError(string $mode): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = uniqid();
        file_put_contents($url, uniqid());
        chmod($url, 0500);

        $fixture = new StreamWrapper();

        self::expectException(Warning::class);
        self::expectExceptionMessage('File "'.$url.'" is not writeable.');

        $fixture->stream_open($url, $mode, \STREAM_REPORT_ERRORS, $path);
    }

    /**
     * @dataProvider sampleWriteModes
     */
    public function testFileOpenForWriteWhenNotWritableDoesNotCreateError(string $mode): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = uniqid();
        file_put_contents($url, uniqid());
        chmod($url, 0500);

        $fixture = new StreamWrapper();

        $actual = $fixture->stream_open($url, $mode, 0, $path);

        self::assertFalse($actual);
    }

    public function sampleWriteModes(): array
    {
        return [
            ['w'],
            ['w+'],
            ['wb'],
            ['wb+'],
            ['r+'],
            ['rb+'],
        ];
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testFileOpenForWriteWhenNotWritableResponse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());
        chmod($url, 0500);

        self::assertFalse(@fopen($url, 'w'));
    }

    public function testFileOpenSetsOpenPath(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = null;

        $fixture = new StreamWrapper();

        $actual = $fixture->stream_open($url, 'w', \STREAM_USE_PATH, $path);

        self::assertEquals($url, $path);
    }

    public function testFileOpenDoesNotSetOpenPath(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = null;

        $fixture = new StreamWrapper();

        $actual = $fixture->stream_open($url, 'w', 0, $path);

        self::assertNull($path);
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
    public function testAppendAlwaysWritesToEnd(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $contentA = uniqid('a');
        $contentB = uniqid('b');
        $contentC = uniqid('c');

        $handle = fopen($url, 'a+');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        fwrite($handle, $contentA);
        fseek($handle, 0);
        fwrite($handle, $contentB);
        fseek($handle, 0);
        fwrite($handle, $contentC);
        fclose($handle);

        self::assertEquals($contentA.$contentB.$contentC, file_get_contents($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadWhenNotReadMode(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = fread($handle, rand(1, 100));
        fclose($handle);

        self::assertEquals('', $actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadWhenNotReadable(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();
        file_put_contents($url, $content);

        $handle = fopen($url, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        chmod($url, 0000); // This should have no effect on an open stream
        $actual = fread($handle, rand(50, 100));
        fclose($handle);

        self::assertEquals($content, $actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testWriteWhenNotWriteMode(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $handle = fopen($url, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = fwrite($handle, uniqid());
        fclose($handle);

        self::assertEquals(0, $actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testWriteWhenNotWritable(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        chmod($url, 0000); // This should have no effect on an open stream
        $actual = fwrite($handle, $content);
        fclose($handle);

        self::assertEquals(strlen($content), $actual);
    }

    public function testWriteWithQuotaLimitedSpace(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        MockFileSystem::addQuota(4, -1);

        $actual = fwrite($handle, $content);
        fclose($handle);

        self::assertEquals(4, $actual);
        self::assertEquals(substr($content, 0, 4), file_get_contents($url));
    }

    public function testFilePutContentsWithQuotaLimitedSpaceCreatesError(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $this->cleanup($url);

        MockFileSystem::addQuota(4, -1);

        self::expectException(Warning::class);
        self::expectExceptionMessage(
            'file_put_contents(): Only 4 of 13 bytes written, possibly out of free disk space'
        );

        file_put_contents($url, uniqid());
    }

    public function testFilePutContentsWithQuotaLimitedSpaceResponse(): void
    {
        MockFileSystem::addQuota(4, -1);

        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        @file_put_contents($url, $content);

        self::assertEquals(substr($content, 0, 4), file_get_contents($url));
    }

    public function testWriteWithQuotaUnlimitedSpace(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        MockFileSystem::addQuota(-1, -1);

        $actual = fwrite($handle, $content);
        fclose($handle);

        self::assertEquals(strlen($content), $actual);
        self::assertEquals($content, file_get_contents($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTruncateWhenNotWriteMode(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $handle = fopen($url, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = ftruncate($handle, rand(1, 3));
        fclose($handle);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTruncateWhenNotWritable(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        chmod($url, 0000); // This should have no effect on an open stream
        $actual = ftruncate($handle, rand(1, 3));
        fclose($handle);

        self::assertTrue($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTruncateUp(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        $handle = fopen($url, 'w+');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        fwrite($handle, $content);
        ftruncate($handle, strlen($content) + 3);
        fclose($handle);

        self::assertEquals($content."\0\0\0", file_get_contents($url));
    }

    public function testTruncateUpWithQuotaLimitedSpace(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        MockFileSystem::addQuota(4, -1);

        $actual = ftruncate($handle, rand(50, 100));
        fclose($handle);

        self::assertFalse($actual);
    }

    public function testTruncateUpWithQuotaUnlimitedSpace(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        MockFileSystem::addQuota(-1, -1);

        $actual = ftruncate($handle, rand(50, 100));
        fclose($handle);

        self::assertTrue($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTruncateDown(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        $handle = fopen($url, 'w+');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        fwrite($handle, $content);
        ftruncate($handle, 4);
        fclose($handle);

        self::assertEquals(substr($content, 0, 4), file_get_contents($url));
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
    public function testWriteParentDirDoesNotExist(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_').'/'.uniqid();

        self::assertFalse(@fopen($url, 'w'));
    }

    public function testWriteParentDirDoesNotExistCreatesError(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_').'/'.uniqid();
        $path = null;

        $fixture = new StreamWrapper();

        self::expectException(Warning::class);
        self::expectExceptionMessage('Path "'.$url.'" does not exist.');

        $fixture->stream_open($url, 'w', \STREAM_REPORT_ERRORS, $path);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testCreateFileParentNotWritable(string $prefix): void
    {
        $base = $prefix.'/'.uniqid('mfs_');
        $url = $base.'/'.uniqid();
        mkdir($base, 0500);

        self::assertFalse(@fopen($url, 'w'));
    }

    public function testCreateFileParentNotWritableCreatesError(): void
    {
        $base = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $url = $base.'/'.uniqid();
        $path = null;
        mkdir($base, 0500);

        $fixture = new StreamWrapper();

        self::expectException(Warning::class);
        self::expectExceptionMessage('Directory "'.$base.'" is not writable.');

        $fixture->stream_open($url, 'w', \STREAM_REPORT_ERRORS, $path);
    }

    public function testCreateFileThrowsExceptionCreatesError(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = null;

        $fixture = new StreamWrapper();

        self::expectException(Warning::class);
        self::expectExceptionMessage('Not enough disk space');

        MockFileSystem::addQuota(-1, 0);

        $fixture->stream_open($url, 'w', \STREAM_REPORT_ERRORS, $path);
    }

    public function testCreateFileThrowsExceptionResponse(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $path = null;

        $fixture = new StreamWrapper();

        MockFileSystem::addQuota(-1, 0);

        $actual = $fixture->stream_open($url, 'w', 0, $path);

        self::assertFalse($actual);
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
    public function testFlush(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        $handle = fopen($url, 'w+');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        fwrite($handle, $content);
        $actual = fflush($handle);
        fclose($handle);

        self::assertTrue($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameFile(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest');
        $this->cleanup($src);
        $this->cleanup($dest);

        $content = uniqid();
        file_put_contents($src, $content);

        self::assertTrue(rename($src, $dest));
        self::assertFalse(file_exists($src), 'Source file not removed');
        self::assertTrue(is_file($dest), 'Destination file not created');
        self::assertEquals($content, file_get_contents($dest), 'Content not moved');
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDir(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest');
        $this->cleanup($src);
        $this->cleanup($dest);
        mkdir($src, 0777);

        self::assertTrue(rename($src, $dest));
        self::assertFalse(file_exists($src), 'Source not removed');
        self::assertTrue(is_dir($dest), 'Destination not created');
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameNonExistentSrcCreatesError(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest');
        $this->cleanup($src);
        $this->cleanup($dest);

        self::expectException(Warning::class);
        self::expectExceptionMessage('rename('.$src.','.$dest.'): No such file or directory');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameNonExistentSrcResponse(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest');
        $this->cleanup($src);
        $this->cleanup($dest);

        self::assertFalse(@rename($src, $dest));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameNonExistentDestCreatesError(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest').'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($dest);
        file_put_contents($src, uniqid());

        self::expectException(Warning::class);
        self::expectExceptionMessage('rename('.$src.','.$dest.'): No such file or directory');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameNonExistentDestResponse(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest').'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($dest);
        file_put_contents($src, uniqid());

        self::assertFalse(@rename($src, $dest));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDestNotDirectoryCreatesError(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $destBase = $prefix.'/'.uniqid('mfs_dest');
        $dest = $destBase.'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        file_put_contents($src, uniqid());
        file_put_contents($destBase, uniqid());

        self::expectException(Warning::class);
        self::expectExceptionMessage('rename('.$src.','.$dest.'): Not a directory');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDestNotDirectoryResponse(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $destBase = $prefix.'/'.uniqid('mfs_dest');
        $dest = $destBase.'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        file_put_contents($src, uniqid());
        file_put_contents($destBase, uniqid());

        self::assertFalse(@rename($src, $dest));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDestNotWritableCreatesError(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $destBase = $prefix.'/'.uniqid('mfs_dest');
        $dest = $destBase.'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        file_put_contents($src, uniqid());
        mkdir($destBase, 0500);

        self::expectException(Warning::class);
        self::expectExceptionMessage('rename('.$src.','.$dest.'): Permission denied');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDestNotWritableResponse(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $destBase = $prefix.'/'.uniqid('mfs_dest');
        $dest = $destBase.'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        file_put_contents($src, uniqid());
        mkdir($destBase, 0500);

        self::assertFalse(@rename($src, $dest));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDirWhenDestNotEmptyCreatesError(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest').'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($dest);
        mkdir($src, 0777);
        mkdir($dest, 0777, true);
        file_put_contents($dest.'/'.uniqid(), uniqid());

        self::expectException(Warning::class);
        self::expectExceptionMessage('rename('.$src.','.$dest.'): Directory not empty');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDirWhenDestNotEmptyResponse(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest').'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($dest);
        mkdir($src, 0777);
        mkdir($dest, 0777, true);
        file_put_contents($dest.'/'.uniqid(), uniqid());

        self::assertFalse(@rename($src, $dest));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDirWhenDestExists(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $dest = $prefix.'/'.uniqid('mfs_dest').'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($dest);
        mkdir($src, 0777);
        mkdir($dest, 0777, true);

        self::assertTrue(rename($src, $dest));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDirWhenDestExistsAsFileCreatesError(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $destBase = $prefix.'/'.uniqid('mfs_dest');
        $dest = $destBase.'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        $this->cleanup($dest);
        mkdir($src, 0777);
        mkdir($destBase, 0777);
        file_put_contents($dest, uniqid());

        self::expectException(Warning::class);
        self::expectExceptionMessage('rename('.$src.','.$dest.'): Not a directory');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDirWhenDestExistsAsFileResponse(string $prefix): void
    {
        $src = $prefix.'/'.uniqid('mfs_src');
        $destBase = $prefix.'/'.uniqid('mfs_dest');
        $dest = $destBase.'/'.uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        $this->cleanup($dest);
        mkdir($src, 0777);
        mkdir($destBase, 0777);
        file_put_contents($dest, uniqid());

        self::assertFalse(@rename($src, $dest));
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

        self::assertTrue(is_file($url));
        self::assertEqualsWithDelta($now, $stat['atime'], 1);
        self::assertEqualsWithDelta($now, $stat['mtime'], 1);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTouchWhenPathNotExistsCreatesError(string $prefix): void
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
    public function testTouchWhenPathNotExistsResponse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_').'/'.uniqid();
        $this->cleanup($url);

        self::assertFalse(@touch($url));
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

        self::assertTrue(is_file($url));
        self::assertEqualsWithDelta($now, $stat['atime'], 1);
        self::assertEqualsWithDelta($now, $stat['mtime'], 1);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testStatWhenFileNotExistsCreatesError(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');

        self::expectException(Warning::class);
        self::expectExceptionMessage('stat(): stat failed for '.$url);

        stat($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testStatWhenFileNotExistsResponse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');

        self::assertFalse(@stat($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testStatHasCorrectKeys(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_');
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
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
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
            2 => FileInterface::TYPE_FILE|$permissions,
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
            'mode' => FileInterface::TYPE_FILE|$permissions,
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
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
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
            2 => FileInterface::TYPE_DIR|$permissions,
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
            'mode' => FileInterface::TYPE_DIR|$permissions,
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

    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlinkNonExistentFileCreatesError(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_src');

        self::expectException(Warning::class);
        self::expectExceptionMessage('unlink('.$url.'): No such file or directory');

        unlink($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlinkNonExistentFileResponse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_src');

        self::assertFalse(@unlink($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlinkDirCreatesError(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_src');
        $this->cleanup($url);
        mkdir($url);

        self::expectException(Warning::class);
        // On Mac, "operation not permitted" error.
        // On Linux, "is a directory" error.

        unlink($url);
    }

    public function testUnlinkDirCreatesErrorMessage(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        $this->cleanup($url);
        mkdir($url);

        self::expectException(Warning::class);
        self::expectExceptionMessage('unlink('.$url.'): Operation not permitted');

        unlink($url);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlinkDirResponse(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_src');
        $this->cleanup($url);
        mkdir($url);

        self::assertFalse(@unlink($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testUnlink(string $prefix): void
    {
        $url = $prefix.'/'.uniqid('mfs_src');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::assertTrue(file_exists($url), 'File does not exist');
        self::assertTrue(unlink($url), 'Unlink failed');
        self::assertFalse(file_exists($url), 'File exists after unlink');
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

    public function testChownWithStringUser(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertFalse(chown($url, get_current_user()));
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

    public function testChmodWhenPathExists(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::assertTrue(chmod($url, 0440));
        self::assertEquals(FileInterface::TYPE_FILE|0440, fileperms($url));
    }

    public function testStreamCastCreatesError(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $read = [$handle];
        $write = null;
        $except = null;

        self::expectException(Warning::class);
        self::expectExceptionMessage(
            'stream_select(): cannot represent a stream of type user-space as a select()able descriptor'
        );

        stream_select($read, $write, $except, 0);
    }

    public function testStreamCastResponse(): void
    {
        $url = StreamWrapper::PROTOCOL.':///'.uniqid('mfs_');

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $read = [$handle];
        $write = null;
        $except = null;

        self::assertFalse(@stream_select($read, $write, $except, 0));
    }

    public function samplePrefixes(): array
    {
        return [
            [StreamWrapper::PROTOCOL.'://'],
            [sys_get_temp_dir()],
        ];
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFopenFail(): void
    {
        $this->setContext(['fopen_fail' => true]);

        $path = StreamWrapper::PROTOCOL.':///'.uniqid();

        $actual = @fopen($path, 'w');

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFopenFailMessage(): void
    {
        $path = uniqid('mfs_');
        $junk = uniqid();
        $message = uniqid();

        $this->setContext(
            [
                'fopen_fail' => true,
                'fopen_message' => $message,
            ]
        );

        $fixture = new StreamWrapper();

        self::expectException(Warning::class);
        self::expectExceptionMessage($message);

        $fixture->stream_open($path, 'w', \STREAM_REPORT_ERRORS, $junk);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFcloseFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        /** @var RegularFileInterface $file */
        $file = MockFileSystem::find($path);
        $content = $this->createMock(ContentInterface::class);
        $file->setContent($content);

        $this->setContext(['fclose_fail' => true]);

        $handle = fopen($path, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        $content->expects(self::never())->method('close');

        fclose($handle);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFreadFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['fread_fail' => true]);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = fread($handle, 100);
        fclose($handle);

        self::assertEquals('', $actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFwriteFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();

        $this->setContext(['fwrite_fail' => true]);

        $handle = fopen($path, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = @fwrite($handle, uniqid());
        fclose($handle);

        self::assertEquals(0, $actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFseekFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['fseek_fail' => true]);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = fseek($handle, 2);
        fclose($handle);

        self::assertEquals(-1, $actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFtellFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['ftell_fail' => true]);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        fseek($handle, 2);
        $actual = ftell($handle);
        fclose($handle);

        self::assertEquals(0, $actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFeofFailDefaultFalse(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['feof_fail' => true]);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        fread($handle, 100);
        $actual = feof($handle);
        fclose($handle);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFeofFailOverrideTrue(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['feof_fail' => true, 'feof_response' => true]);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = feof($handle);
        fclose($handle);

        self::assertTrue($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFflushFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        /** @var RegularFileInterface $file */
        $file = MockFileSystem::find($path);
        $content = $this->createMock(ContentInterface::class);
        $file->setContent($content);

        $this->setContext(['fflush_fail' => true]);

        $handle = fopen($path, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        fwrite($handle, uniqid());

        $content->expects(self::never())->method('flush');

        $actual = @fflush($handle);
        fclose($handle);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFstatFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['fstat_fail' => true]);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = @fstat($handle);
        fclose($handle);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextFtruncateFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['ftruncate_fail' => true]);

        $handle = fopen($path, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = @ftruncate($handle, 2);
        fclose($handle);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextRenameFail(): void
    {
        $pathA = StreamWrapper::PROTOCOL.':///'.uniqid();
        $pathB = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($pathA, uniqid());

        $this->setContext(['rename_fail' => true]);

        $actual = rename($pathA, $pathB);

        self::assertFalse($actual);
        self::assertTrue(file_exists($pathA));
        self::assertFalse(file_exists($pathB));
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextRenameFailCreatesErrorMessage(): void
    {
        $pathA = StreamWrapper::PROTOCOL.':///'.uniqid();
        $pathB = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($pathA, uniqid());
        $message = uniqid();

        $this->setContext(['rename_fail' => true, 'rename_message' => $message]);

        self::expectException(Warning::class);
        self::expectExceptionMessage($message);

        rename($pathA, $pathB);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextUnlinkFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['unlink_fail' => true]);

        $actual = unlink($path);

        self::assertFalse($actual);
        self::assertTrue(file_exists($path));
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextUnlinkFailCreatesErrorMessage(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());
        $message = uniqid();

        $this->setContext(['unlink_fail' => true, 'unlink_message' => $message]);

        self::expectException(Warning::class);
        self::expectExceptionMessage($message);

        unlink($path);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextStatFail(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());

        $this->setContext(['stat_fail' => true]);

        $actual = @stat($path);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextStatFailCreatesErrorMessage(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        file_put_contents($path, uniqid());
        $message = uniqid();

        $this->setContext(['stat_fail' => true, 'stat_message' => $message]);

        self::expectException(Warning::class);
        self::expectExceptionMessage($message);

        stat($path);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextTouchFails(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();

        $this->setContext(['touch_fail' => true]);

        $actual = @touch($path);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextTouchFailsCreatesErrorMessage(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        $message = uniqid();

        $this->setContext(['touch_fail' => true, 'touch_message' => $message]);

        self::expectException(Warning::class);
        self::expectExceptionMessage($message);

        touch($path);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextOpenDirFails(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        mkdir($path);

        $this->setContext(['opendir_fail' => true]);

        $actual = @opendir($path);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextOpenDirFailsCreatesErrorMessage(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        mkdir($path);
        $message = uniqid();

        $this->setContext(['opendir_fail' => true, 'opendir_message' => $message]);

        self::expectException(Warning::class);
        self::expectExceptionMessage($message);

        opendir($path);
    }

    /**
     * @runInSeparateProcess
     *
     * TODO: Not sure if it's a bug in php, but even if dir_closedir() returns
     * false the stream wrapper handle is still closed.
     */
    public function testContextCloseDirFails(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        mkdir($path);

        $this->setContext(['closedir_fail' => true]);

        $fixture = new StreamWrapper();
        $fixture->dir_opendir($path, 0);
        $actual = $fixture->dir_closedir();

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextReadDirFails(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
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
     * @runInSeparateProcess
     *
     * TODO: Not sure if it's a bug in php, but even if dir_rewinddir() returns
     * false rewinddir() itself returns null (success).
     */
    public function testContextRewindDirFails(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        mkdir($path);

        $this->setContext(['rewinddir_fail' => true]);

        $fixture = new StreamWrapper();
        $fixture->dir_opendir($path, 0);
        $actual = $fixture->dir_rewinddir();

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextMkdirFails(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();

        $this->setContext(['mkdir_fail' => true]);

        $actual = @mkdir($path);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextMkdirFailsCreatesErrorMessage(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        $message = uniqid();

        $this->setContext(['mkdir_fail' => true, 'mkdir_message' => $message]);

        self::expectException(Warning::class);
        self::expectExceptionMessage($message);

        mkdir($path);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextRmdirFails(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        mkdir($path);

        $this->setContext(['rmdir_fail' => true]);

        $actual = @rmdir($path);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testContextRmdirFailsCreatesErrorMessage(): void
    {
        $path = StreamWrapper::PROTOCOL.':///'.uniqid();
        mkdir($path);
        $message = uniqid();

        $this->setContext(['rmdir_fail' => true, 'rmdir_message' => $message]);

        self::expectException(Warning::class);
        self::expectExceptionMessage($message);

        rmdir($path);
    }

    private function setContext(array $options = []): void
    {
        stream_context_set_default([StreamWrapper::PROTOCOL => $options]);
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
