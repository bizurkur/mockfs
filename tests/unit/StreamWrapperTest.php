<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Content\ContentInterface;
use MockFileSystem\MockFileSystem;
use MockFileSystem\Quota\QuotaInterface;
use MockFileSystem\StreamWrapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
class StreamWrapperTest extends TestCase
{
    private StreamWrapper $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        MockFileSystem::create();

        $this->fixture = new StreamWrapper();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        stream_context_set_default(
            [
                StreamWrapper::PROTOCOL => [
                    'opendir_fail' => false,
                    'opendir_message' => null,
                    'closedir_fail' => false,
                    'readdir_fail' => false,
                    'rewinddir_fail' => false,
                    'mkdir_fail' => false,
                    'mkdir_message' => null,
                    'rmdir_fail' => false,
                    'rmdir_message' => null,
                    'fopen_fail' => false,
                    'fopen_message' => null,
                    'fclose_fail' => false,
                    'fread_fail' => false,
                    'fwrite_fail' => false,
                    'fseek_fail' => false,
                    'ftell_fail' => false,
                    'feof_fail' => false,
                    'feof_response' => false,
                    'fflush_fail' => false,
                    'fstat_fail' => false,
                    'ftruncate_fail' => false,
                    'rename_fail' => false,
                    'rename_message' => null,
                    'stat_fail' => false,
                    'stat_message' => null,
                    'touch_fail' => false,
                    'touch_message' => null,
                    'unlink_fail' => false,
                    'unlink_message' => null,
                ],
            ]
        );
    }

    public function testOpenDir(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path);

        $actual = $this->fixture->dir_opendir($path, 0);

        self::assertTrue($actual);
    }

    public function testOpenDirContextFailCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path);
        $message = uniqid();

        $this->setContext(['opendir_fail' => true, 'opendir_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        $this->fixture->dir_opendir($path, 0);
    }

    public function testOpenDirContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path);

        $this->setContext(['opendir_fail' => true]);

        $actual = $this->fixture->dir_opendir($path, 0);

        self::assertFalse($actual);
    }

    public function testOpenDirWhenNotExistsCreatesError(): void
    {
        $path = uniqid();

        self::expectWarning();
        self::expectWarningMessage('opendir(' . $path . '): failed to open dir: No such file or directory');

        $this->fixture->dir_opendir($path, 0);
    }

    public function testOpenDirWhenNotExistsResponse(): void
    {
        $path = uniqid();

        $handle = @$this->fixture->dir_opendir($path, 0);

        self::assertFalse($handle);
    }

    public function testOpenDirWhenNotReadableCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path, 0000);

        self::expectWarning();
        self::expectWarningMessage('opendir(' . $path . '): failed to open dir: Permission denied');

        $this->fixture->dir_opendir($path, 0);
    }

    public function testOpenDirWhenNotReadableResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path, 0000);

        $actual = @$this->fixture->dir_opendir($path, 0);

        self::assertFalse($actual);
    }

    /**
     * @runInSeparateProcess
     */
    public function testCloseDir(): void
    {
        $actual = $this->fixture->dir_closedir();

        self::assertTrue($actual);
    }

    public function testCloseDirContextFailResponse(): void
    {
        $this->setContext(['closedir_fail' => true]);

        $actual = $this->fixture->dir_closedir();

        self::assertFalse($actual);
    }

    public function testReadDir(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $childA = uniqid('a');
        $childB = uniqid('b');
        mkdir($path);
        mkdir($path . '/' . $childA);
        file_put_contents($path . '/' . $childB, uniqid());

        $this->fixture->dir_opendir($path, 0);

        $actual = [];
        while (($entry = $this->fixture->dir_readdir()) !== false) {
            $actual[] = $entry;
        }

        // 2nd loop should do nothing
        while (($entry = $this->fixture->dir_readdir()) !== false) {
            $actual[] = $entry;
        }

        sort($actual);
        self::assertEquals(['.', '..', $childA, $childB], $actual);
    }

    public function testReadDirContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $childA = uniqid('a');
        $childB = uniqid('b');
        mkdir($path);
        mkdir($path . '/' . $childA);
        file_put_contents($path . '/' . $childB, uniqid());

        $this->fixture->dir_opendir($path, 0);

        $this->setContext(['readdir_fail' => true]);

        $actual = $this->fixture->dir_readdir();

        self::assertFalse($actual);
    }

    public function testRewindDir(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $childA = uniqid('a');
        $childB = uniqid('b');
        mkdir($path);
        mkdir($path . '/' . $childA);
        file_put_contents($path . '/' . $childB, uniqid());

        $this->fixture->dir_opendir($path, 0);

        $actualA = [];
        while (($entry = $this->fixture->dir_readdir()) !== false) {
            $actualA[] = $entry;
        }

        self::assertTrue($this->fixture->dir_rewinddir());

        $actualB = [];
        while (($entry = $this->fixture->dir_readdir()) !== false) {
            $actualB[] = $entry;
        }

        sort($actualA);
        sort($actualB);
        self::assertEquals(['.', '..', $childA, $childB], $actualA);
        self::assertEquals(['.', '..', $childA, $childB], $actualB);
    }

    public function testRewindDirContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $childA = uniqid('a');
        $childB = uniqid('b');
        mkdir($path);
        mkdir($path . '/' . $childA);
        file_put_contents($path . '/' . $childB, uniqid());

        $this->fixture->dir_opendir($path, 0);

        $actual = [];
        while (($entry = $this->fixture->dir_readdir()) !== false) {
            $actual[] = $entry;
        }

        $this->setContext(['rewinddir_fail' => true]);
        self::assertFalse($this->fixture->dir_rewinddir());

        while (($entry = $this->fixture->dir_readdir()) !== false) {
            $actual[] = $entry;
        }

        sort($actual);
        self::assertEquals(['.', '..', $childA, $childB], $actual);
    }

    public function testMakeDir(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $actual = $this->fixture->mkdir($path, 0777, 0);

        self::assertTrue($actual);
        self::assertTrue(is_dir($path), 'Failed to make directory');
    }

    public function testMakeDirContextFailCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $message = uniqid();

        $this->setContext(['mkdir_fail' => true, 'mkdir_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        $this->fixture->mkdir($path, 0777, 0);
    }

    public function testMakeDirContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $this->setContext(['mkdir_fail' => true]);

        $actual = $this->fixture->mkdir($path, 0777, 0);

        self::assertFalse($actual);
    }

    public function testMakeRecursiveDir(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $child = $path . '/' . uniqid();

        $actual = $this->fixture->mkdir($child, 0777, \STREAM_MKDIR_RECURSIVE);

        self::assertTrue($actual);
        self::assertTrue(is_dir($child), 'Failed to make directory');
    }

    public function testMakeDirGivesCorrectPermissions(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $actual = $this->fixture->mkdir($path, 0755, 0);

        $actual = fileperms($path);

        self::assertEquals(0755 | FileInterface::TYPE_DIR, $actual);
    }

    public function testMakeDirWhenParentDoesNotExistCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/') . uniqid('/'));

        self::expectWarning();
        self::expectWarningMessage('mkdir(): No such file or directory');

        $this->fixture->mkdir($path, 0755, 0);
    }

    public function testMakeDirWhenParentDoesNotExistResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/') . uniqid('/'));

        $actual = @$this->fixture->mkdir($path, 0755, 0);

        self::assertFalse($actual);
    }

    public function testMakeDirWhenNoWritePermissionCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $child = $path . '/' . uniqid();

        $this->fixture->mkdir($path, 0500, 0);

        self::expectWarning();
        self::expectWarningMessage('mkdir(): Permission denied');

        $this->fixture->mkdir($child, 0500, 0);
    }

    public function testMakeDirWhenNoWritePermissionResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $child = $path . '/' . uniqid();

        $this->fixture->mkdir($path, 0500, 0);

        $actual = @$this->fixture->mkdir($child, 0500, 0);

        self::assertFalse($actual);
    }

    public function testMakeDirWhenFileExistsCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        self::expectWarning();
        self::expectWarningMessage('mkdir(): File exists');

        $this->fixture->mkdir($path, 0777, 0);
    }

    public function testMakeDirWhenFileExistsResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $actual = @$this->fixture->mkdir($path, 0777, 0);

        self::assertFalse($actual);
    }

    public function testMakeDirWhenPathContainsFileCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $child = $path . '/' . uniqid();

        mkdir($path);
        file_put_contents($child, uniqid());

        self::expectWarning();
        self::expectWarningMessage('mkdir(): Not a directory');

        $this->fixture->mkdir($child . uniqid('/'), 0777, 0);
    }

    public function testMakeDirWhenPathContainsFileResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $child = $path . '/' . uniqid();

        mkdir($path);
        file_put_contents($child, uniqid());

        $actual = @$this->fixture->mkdir($child . uniqid('/'), 0777, 0);

        self::assertFalse($actual);
    }

    public function testMakeDirWhenNoPartitionCreatesError(): void
    {
        $path = MockFileSystem::getUrl('/');
        MockFileSystem::getFileSystem()->removeChild('/');

        self::expectWarning();
        self::expectWarningMessage('mkdir(): No such file or directory');

        $this->fixture->mkdir($path, 0777, 0);
    }

    public function testMakeDirWhenNoPartitionResponse(): void
    {
        $path = MockFileSystem::getUrl('/');
        MockFileSystem::getFileSystem()->removeChild('/');

        $actual = @$this->fixture->mkdir($path, 0777, 0);

        self::assertFalse($actual);
    }

    public function testMakeDirWhenWrongPartitionCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('c:/'));

        self::expectWarning();
        self::expectWarningMessage('mkdir(): No such file or directory');

        $this->fixture->mkdir($path, 0777, 0);
    }

    public function testMakeDirWhenWrongPartitionResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('c:/'));

        $actual = @$this->fixture->mkdir($path, 0777, 0);

        self::assertFalse($actual);
    }

    public function testRemoveDir(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path);

        self::assertTrue(file_exists($path));

        $actual = $this->fixture->rmdir($path, 0);

        self::assertTrue($actual);
        self::assertFalse(file_exists($path));
    }

    public function testRemoveDirContextFailCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path);
        $message = uniqid();

        $this->setContext(['rmdir_fail' => true, 'rmdir_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        $this->fixture->rmdir($path, 0);
    }

    public function testRemoveDirContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path);

        $this->setContext(['rmdir_fail' => true]);

        $actual = $this->fixture->rmdir($path, 0);

        self::assertFalse($actual);
    }

    public function testRemoveDirWhenNotExistsCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        self::expectWarning();
        self::expectWarningMessage('rmdir(' . $path . '): No such file or directory');

        $this->fixture->rmdir($path, 0);
    }

    public function testRemoveDirWhenNotExistsResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $actual = @$this->fixture->rmdir($path, 0);

        self::assertFalse($actual);
    }

    public function testRemoveDirWhenPathIsNotDirCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rmdir(' . $path . '): Not a directory');

        $this->fixture->rmdir($path, 0);
    }

    public function testRemoveDirWhenPathIsNotDirResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $actual = @$this->fixture->rmdir($path, 0);

        self::assertFalse($actual);
    }

    public function testRemoveDirWhenDirNotEmptyCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $child = $path . '/' . uniqid();

        mkdir($path);
        file_put_contents($child, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rmdir(' . $path . '): Directory not empty');

        $this->fixture->rmdir($path, 0);
    }

    public function testRemoveDirWhenDirNotEmptyResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $child = $path . '/' . uniqid();

        mkdir($path);
        file_put_contents($child, uniqid());

        $actual = @$this->fixture->rmdir($path, 0);

        self::assertFalse($actual);
    }

    public function testRemoveDirWhenNotWritableCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $child = $path . '/' . uniqid();

        mkdir($path, 0700);
        mkdir($child);
        chmod($path, 0500);

        self::expectWarning();
        self::expectWarningMessage('rmdir(' . $child . '): Permission denied');

        $this->fixture->rmdir($child, 0);
    }

    public function testRemoveDirWhenNotWritableResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $child = $path . '/' . uniqid();

        mkdir($path, 0700);
        mkdir($child);
        chmod($path, 0500);

        $actual = @$this->fixture->rmdir($child, 0);

        self::assertFalse($actual);
    }

    public function testFileOpenContextFailCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        $message = uniqid();

        $this->setContext(
            [
                'fopen_fail' => true,
                'fopen_message' => $message,
            ]
        );

        self::expectWarning();
        self::expectWarningMessage($message);

        $this->fixture->stream_open($path, 'w', \STREAM_REPORT_ERRORS, $ignore);
    }

    public function testFileOpenContextFailDoesNotCreateError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        $message = uniqid();

        $this->setContext(
            [
                'fopen_fail' => true,
                'fopen_message' => $message,
            ]
        );

        $actual = $this->fixture->stream_open($path, 'w', 0, $ignore);

        self::assertFalse($actual);
    }

    public function testFileOpenContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;

        $this->setContext(['fopen_fail' => true]);

        $actual = $this->fixture->stream_open($path, 'w', 0, $ignore);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider sampleInvalidModes
     */
    public function testFileOpenInvalidModeCreatesError(string $mode): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;

        self::expectWarning();
        self::expectWarningMessage('Illegal mode "' . $mode . '"');

        $this->fixture->stream_open($path, $mode, \STREAM_REPORT_ERRORS, $ignore);
    }

    public function sampleInvalidModes(): array
    {
        return [
            'unknown mode' => ['q'],
            'unknown modifier' => ['rq'],
            'extra characters' => ['rbq'],
        ];
    }

    public function testFileOpenInvalidModeResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;

        $actual = $this->fixture->stream_open($path, 'q', 0, $ignore);

        self::assertFalse($actual);
    }

    public function testFileOpenForReadOnNonExistentFileCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;

        self::expectWarning();
        self::expectWarningMessage('Cannot open non-existent file "' . $path . '" for reading.');

        $this->fixture->stream_open($path, 'r', \STREAM_REPORT_ERRORS, $ignore);
    }

    public function testFileOpenForReadOnNonExistentFileResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;

        $actual = $this->fixture->stream_open($path, 'r', 0, $ignore);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider sampleCreateNewModes
     */
    public function testFileOpenForCreateNewWhenExistsCreatesError(string $mode): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        file_put_contents($path, uniqid());

        self::expectWarning();
        self::expectWarningMessage('File "' . $path . '" already exists; cannot open in mode ' . $mode);

        $this->fixture->stream_open($path, $mode, \STREAM_REPORT_ERRORS, $ignore);
    }

    /**
     * @dataProvider sampleCreateNewModes
     */
    public function testFileOpenForCreateNewWhenExistsDoesNotCreateError(string $mode): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        file_put_contents($path, uniqid());

        $actual = $this->fixture->stream_open($path, $mode, 0, $ignore);

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
     * @dataProvider sampleCreateNewModes
     */
    public function testFileOpenForCreateNewWhenExistsResponse(string $mode): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        file_put_contents($path, uniqid());

        $actual = $this->fixture->stream_open($path, $mode, 0, $ignore);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider sampleReadModes
     */
    public function testFileOpenForReadWhenNotReadableCreatesError(string $mode): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        file_put_contents($path, uniqid());
        chmod($path, 0200);

        self::expectWarning();
        self::expectWarningMessage('File "' . $path . '" is not readable.');

        $this->fixture->stream_open($path, $mode, \STREAM_REPORT_ERRORS, $ignore);
    }

    /**
     * @dataProvider sampleReadModes
     */
    public function testFileOpenForReadWhenNotReadableDoesNotCreateError(string $mode): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        file_put_contents($path, uniqid());
        chmod($path, 0200);

        $actual = $this->fixture->stream_open($path, $mode, 0, $ignore);

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
     * @dataProvider sampleReadModes
     */
    public function testFileOpenForReadWhenNotReadableResponse(string $mode): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        file_put_contents($path, uniqid());
        chmod($path, 0200);

        $actual = $this->fixture->stream_open($path, $mode, 0, $ignore);

        self::assertFalse($actual);
    }

    /**
     * @dataProvider sampleWriteModes
     */
    public function testFileOpenForWriteWhenNotWritableCreatesError(string $mode): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        file_put_contents($path, uniqid());
        chmod($path, 0500);

        self::expectWarning();
        self::expectWarningMessage('File "' . $path . '" is not writeable.');

        $this->fixture->stream_open($path, $mode, \STREAM_REPORT_ERRORS, $ignore);
    }

    /**
     * @dataProvider sampleWriteModes
     */
    public function testFileOpenForWriteWhenNotWritableResponse(string $mode): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        file_put_contents($path, uniqid());
        chmod($path, 0500);

        $actual = $this->fixture->stream_open($path, $mode, 0, $ignore);

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

    public function testFileOpenSetsOpenPath(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;

        $this->fixture->stream_open($path, 'w', \STREAM_USE_PATH, $openedPath);

        self::assertEquals($path, $openedPath);
    }

    public function testFileOpenDoesNotSetOpenPath(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;

        $this->fixture->stream_open($path, 'w', 0, $openedPath);

        self::assertNull($openedPath);
    }

    public function testFileCloseContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'w', 0, $openedPath);

        $content = $this->mockFileContent($path);
        $content->expects(self::never())->method('close');

        $this->setContext(['fclose_fail' => true]);

        $this->fixture->stream_close();
    }

    public function testFileClose(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;

        $this->fixture->stream_open($path, 'w', 0, $openedPath);

        $this->fixture->stream_close();

        self::assertTrue(true);
    }

    public function testWriteParentDirDoesNotExistCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/') . uniqid('/'));
        $ignore = null;

        self::expectWarning();
        self::expectWarningMessage('Path "' . $path . '" does not exist.');

        $this->fixture->stream_open($path, 'w', \STREAM_REPORT_ERRORS, $ignore);
    }

    public function testWriteParentDirDoesNotExistCreatesErrorResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/') . uniqid('/'));
        $ignore = null;

        $actual = $this->fixture->stream_open($path, 'w', 0, $ignore);

        self::assertFalse($actual);
    }

    public function testCreateFileParentNotWritableCreatesError(): void
    {
        $base = MockFileSystem::getUrl(uniqid('/'));
        $path = $base . '/' . uniqid();
        $ignore = null;
        mkdir($base, 0500);

        self::expectWarning();
        self::expectWarningMessage('Directory "' . $base . '" is not writable.');

        $this->fixture->stream_open($path, 'w', \STREAM_REPORT_ERRORS, $ignore);
    }

    public function testCreateFileParentNotWritableResponse(): void
    {
        $base = MockFileSystem::getUrl(uniqid('/'));
        $path = $base . '/' . uniqid();
        $ignore = null;
        mkdir($base, 0500);

        $actual = $this->fixture->stream_open($path, 'w', 0, $ignore);

        self::assertFalse($actual);
    }

    public function testCreateFileWhenExistsCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        mkdir($path);

        self::expectWarning();
        self::expectWarningMessage('Path "' . $path . '" already exists.');

        $this->fixture->stream_open($path, 'w', \STREAM_REPORT_ERRORS, $ignore);
    }

    public function testCreateFileWhenExistsResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;
        mkdir($path);

        $actual = @$this->fixture->stream_open($path, 'w', \STREAM_REPORT_ERRORS, $ignore);

        self::assertFalse($actual);
    }

    public function testCreateFileThrowsExceptionCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;

        self::expectWarning();
        self::expectWarningMessage('Not enough disk space');

        $quota = $this->createConfiguredMock(
            QuotaInterface::class,
            [
                'appliesTo' => true,
                'getRemainingSize' => 0,
                'getRemainingFileCount' => 0,
            ]
        );
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        $this->fixture->stream_open($path, 'w', \STREAM_REPORT_ERRORS, $ignore);
    }

    public function testCreateFileThrowsExceptionResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $ignore = null;

        $quota = $this->createConfiguredMock(
            QuotaInterface::class,
            [
                'appliesTo' => true,
                'getRemainingSize' => 0,
                'getRemainingFileCount' => 0,
            ]
        );
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        $actual = $this->fixture->stream_open($path, 'w', 0, $ignore);

        self::assertFalse($actual);
    }

    public function testAppendAlwaysWritesToEnd(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $contentA = uniqid('a');
        $contentB = uniqid('b');
        $contentC = uniqid('c');
        file_put_contents($path, $contentA);

        $this->fixture->stream_open($path, 'a+', 0, $openedPath);
        $this->fixture->stream_write($contentB);
        $this->fixture->stream_seek(0, \SEEK_SET); // seek should only effect reading
        $this->fixture->stream_write($contentC);

        self::assertEquals($contentA . $contentB . $contentC, file_get_contents($path));
    }

    public function testRead(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $content = uniqid();
        file_put_contents($path, $content);

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $actual = $this->fixture->stream_read(rand(50, 100));

        self::assertEquals($content, $actual);
    }

    public function testReadContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $this->setContext(['fread_fail' => true]);

        $actual = $this->fixture->stream_read(100);

        self::assertEquals('', $actual);
    }

    public function testReadWhenNotReadMode(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;

        $this->fixture->stream_open($path, 'w', 0, $openedPath);

        $actual = $this->fixture->stream_read(rand(1, 100));

        self::assertEquals('', $actual);
    }

    public function testReadWhenFileChangedToNotReadableAfterOpen(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $content = uniqid();
        file_put_contents($path, $content);

        $this->fixture->stream_open($path, 'r', 0, $openedPath);
        chmod($path, 0000); // This should have no effect on an open stream

        $actual = $this->fixture->stream_read(rand(50, 100));

        self::assertEquals($content, $actual);
    }

    public function testWrite(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $content = uniqid();

        $this->fixture->stream_open($path, 'w', 0, $openedPath);

        $actual = $this->fixture->stream_write($content);

        self::assertEquals(strlen($content), $actual);
    }

    public function testWriteContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;

        $this->fixture->stream_open($path, 'w', 0, $openedPath);

        $this->setContext(['fwrite_fail' => true]);

        $actual = $this->fixture->stream_write(uniqid());

        self::assertEquals(0, $actual);
    }

    public function testWriteWhenNotWriteMode(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $actual = $this->fixture->stream_write(uniqid());

        self::assertEquals(0, $actual);
    }

    public function testWriteWhenFileChangedToNotWritableAfterOpen(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $content = uniqid();
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'w', 0, $openedPath);
        chmod($path, 0000); // This should have no effect on an open stream

        $actual = $this->fixture->stream_write($content);

        self::assertEquals(strlen($content), $actual);
    }

    public function testTruncateUp(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $content = uniqid();

        $this->fixture->stream_open($path, 'w+', 0, $openedPath);
        $this->fixture->stream_write($content);
        $this->fixture->stream_truncate(strlen($content) + 3);
        $this->fixture->stream_seek(0);
        $actual = $this->fixture->stream_read(1024);

        self::assertEquals($content . "\0\0\0", $actual);
    }

    public function testTruncateDown(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $content = uniqid();

        $this->fixture->stream_open($path, 'w+', 0, $openedPath);
        $this->fixture->stream_write($content);
        $this->fixture->stream_truncate(4);
        $this->fixture->stream_seek(0);
        $actual = $this->fixture->stream_read(1024);

        self::assertEquals(substr($content, 0, 4), $actual);
    }

    public function testTruncateContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'w+', 0, $openedPath);

        $this->setContext(['ftruncate_fail' => true]);

        $actual = $this->fixture->stream_truncate(4);

        self::assertFalse($actual);
    }

    public function testTruncateWhenNotWriteMode(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $actual = $this->fixture->stream_truncate(rand(1, 3));

        self::assertFalse($actual);
    }

    public function testTruncateWhenFileChangedToNotWritableAfterOpen(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'w', 0, $openedPath);
        chmod($path, 0000); // This should have no effect on an open stream

        $actual = $this->fixture->stream_truncate(rand(1, 3));

        self::assertTrue($actual);
    }

    public function testSeekPastEnd(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'w', 0, $openedPath);
        $this->fixture->stream_seek(rand(1, 99), \SEEK_SET);

        $actual = $this->fixture->stream_tell();

        self::assertEquals(0, $actual);
    }

    public function testSeekAbsolute(): void
    {
        $offset = rand(1, 8);
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);
        $this->fixture->stream_seek($offset, \SEEK_SET);

        $actual = $this->fixture->stream_tell();

        self::assertEquals($offset, $actual);
    }

    public function testSeekFromEnd(): void
    {
        $offset = rand(1, 8);
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $content = uniqid();
        file_put_contents($path, $content);

        $this->fixture->stream_open($path, 'r', 0, $openedPath);
        $this->fixture->stream_seek(-$offset, \SEEK_END);

        $actual = $this->fixture->stream_tell();

        self::assertEquals(strlen($content) - $offset, $actual);
    }

    public function testSeekFromRelative(): void
    {
        $offsetA = rand(1, 8);
        $offsetB = rand(1, 8);
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid() . uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);
        $this->fixture->stream_seek($offsetA, \SEEK_SET);
        $this->fixture->stream_seek($offsetB, \SEEK_CUR);

        $actual = $this->fixture->stream_tell();

        self::assertEquals($offsetA + $offsetB, $actual);
    }

    public function testSeekResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $actual = $this->fixture->stream_seek(rand(1, 8));

        self::assertTrue($actual);
    }

    public function testSeekContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $this->setContext(['fseek_fail' => true]);

        $actual = $this->fixture->stream_seek(rand());

        self::assertFalse($actual);
    }

    public function testTellContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);
        $this->fixture->stream_seek(rand(1, 8));

        $this->setContext(['ftell_fail' => true]);

        $actual = $this->fixture->stream_tell();

        self::assertEquals(0, $actual);
    }

    public function testEof(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $expected = (bool) rand(0, 1);
        file_put_contents($path, uniqid());

        $content = $this->mockFileContent($path);
        $content->method('isEof')->willReturn($expected);

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $actual = $this->fixture->stream_eof();

        self::assertEquals($expected, $actual);
    }

    public function testEofNotReached(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $actual = $this->fixture->stream_eof();

        self::assertFalse($actual);
    }

    public function testEofContextFailDefaultFalse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->setContext(['feof_fail' => true]);

        $this->fixture->stream_open($path, 'r', 0, $openedPath);
        $this->fixture->stream_read(100);

        $actual = $this->fixture->stream_eof();

        self::assertFalse($actual);
    }

    public function testEofContextFailOverrideTrue(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->setContext(['feof_fail' => true, 'feof_response' => true]);

        $this->fixture->stream_open($path, 'r', 0, $openedPath);
        $this->fixture->stream_read(100);

        $actual = $this->fixture->stream_eof();

        self::assertTrue($actual);
    }

    public function testFlush(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $expected = (bool) rand(0, 1);
        file_put_contents($path, uniqid());

        $content = $this->mockFileContent($path);
        $content->method('flush')->willReturn($expected);

        $this->fixture->stream_open($path, 'w', 0, $openedPath);
        $this->fixture->stream_write(uniqid());

        $actual = $this->fixture->stream_flush();

        self::assertEquals($expected, $actual);
    }

    public function testFlushContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'w', 0, $openedPath);
        $this->fixture->stream_write(uniqid());

        $this->setContext(['fflush_fail' => true]);

        $content = $this->mockFileContent($path);
        $content->expects(self::never())->method('flush');

        $actual = $this->fixture->stream_flush();

        self::assertFalse($actual);
    }

    public function testStreamStat(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        $permissions = 0500;
        $now = time();
        file_put_contents($path, uniqid());
        chmod($path, $permissions);
        $config = MockFileSystem::getFileSystem()->getConfig();
        $file = MockFileSystem::find($path);
        if ($file === null) {
            self::fail('File not found');
        }

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $actual = $this->fixture->stream_stat();

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

    public function testStreamStatContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        $openedPath = null;
        file_put_contents($path, uniqid());

        $this->fixture->stream_open($path, 'r', 0, $openedPath);

        $this->setContext(['fstat_fail' => true]);

        $actual = $this->fixture->stream_stat();

        self::assertFalse($actual);
    }

    public function testRenameFile(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest'));

        $content = uniqid();
        file_put_contents($src, $content);

        $actual = $this->fixture->rename($src, $dest);

        self::assertTrue($actual);
        self::assertFalse(file_exists($src), 'Source file not removed');
        self::assertTrue(is_file($dest), 'Destination file not created');
        self::assertEquals($content, file_get_contents($dest), 'Content not moved');
    }

    public function testRenameDir(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        mkdir($src, 0777);

        $actual = $this->fixture->rename($src, $dest);

        self::assertTrue($actual);
        self::assertFalse(file_exists($src), 'Source not removed');
        self::assertTrue(is_dir($dest), 'Destination not created');
    }

    public function testRenameContextFailCreatesError(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        file_put_contents($src, uniqid());
        $message = uniqid();

        $this->setContext(['rename_fail' => true, 'rename_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        $this->fixture->rename($src, $dest);
    }

    public function testRenameContextFailResponse(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        file_put_contents($src, uniqid());

        $this->setContext(['rename_fail' => true]);

        $actual = $this->fixture->rename($src, $dest);

        self::assertFalse($actual);
        self::assertTrue(file_exists($src));
        self::assertFalse(file_exists($dest));
    }

    public function testRenameNonExistentSrcCreatesError(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest'));

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): No such file or directory');

        $this->fixture->rename($src, $dest);
    }

    public function testRenameNonExistentSrcResponse(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest'));

        $actual = @$this->fixture->rename($src, $dest);

        self::assertFalse($actual);
    }

    public function testRenameNonExistentDestCreatesError(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest') . uniqid('/'));
        file_put_contents($src, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): No such file or directory');

        $this->fixture->rename($src, $dest);
    }

    public function testRenameNonExistentDestResponse(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest') . uniqid('/'));
        file_put_contents($src, uniqid());

        $actual = @$this->fixture->rename($src, $dest);

        self::assertFalse($actual);
    }

    public function testRenameDestNotDirectoryCreatesError(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $destBase = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        $dest = $destBase . uniqid('/');
        file_put_contents($src, uniqid());
        file_put_contents($destBase, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): Not a directory');

        $this->fixture->rename($src, $dest);
    }

    public function testRenameDestNotDirectoryResponse(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $destBase = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        $dest = $destBase . uniqid('/');
        file_put_contents($src, uniqid());
        file_put_contents($destBase, uniqid());

        $actual = @$this->fixture->rename($src, $dest);

        self::assertFalse($actual);
    }

    public function testRenameDestNotWritableCreatesError(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $destBase = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        $dest = $destBase . uniqid('/');
        file_put_contents($src, uniqid());
        mkdir($destBase, 0500);

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): Permission denied');

        $this->fixture->rename($src, $dest);
    }

    public function testRenameDestNotWritableResponse(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $destBase = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        $dest = $destBase . uniqid('/');
        file_put_contents($src, uniqid());
        mkdir($destBase, 0500);

        $actual = @$this->fixture->rename($src, $dest);

        self::assertFalse($actual);
    }

    public function testRenameDirWhenDestNotEmptyCreatesError(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        mkdir($src, 0777);
        mkdir($dest, 0777);
        file_put_contents($dest . uniqid('/'), uniqid());

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): Directory not empty');

        $this->fixture->rename($src, $dest);
    }

    public function testRenameDirWhenDestNotEmptyResponse(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        mkdir($src, 0777);
        mkdir($dest, 0777);
        file_put_contents($dest . uniqid('/'), uniqid());

        $actual = @$this->fixture->rename($src, $dest);

        self::assertFalse($actual);
    }

    public function testRenameDirWhenDestExists(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        mkdir($src, 0777);
        mkdir($dest, 0777);

        $actual = $this->fixture->rename($src, $dest);

        self::assertTrue($actual);
    }

    public function testRenameDirWhenDestExistsAsFileCreatesError(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $destBase = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        $dest = $destBase . uniqid('/');
        mkdir($src, 0777);
        mkdir($destBase, 0777);
        file_put_contents($dest, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): Not a directory');

        $this->fixture->rename($src, $dest);
    }

    public function testRenameDirWhenDestExistsAsFileResponse(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $destBase = MockFileSystem::getUrl(uniqid('/mfs_dest'));
        $dest = $destBase . uniqid('/');
        mkdir($src, 0777);
        mkdir($destBase, 0777);
        file_put_contents($dest, uniqid());

        $actual = @$this->fixture->rename($src, $dest);

        self::assertFalse($actual);
    }

    public function testRenameWithPartialFilename(): void
    {
        $src = MockFileSystem::getUrl(uniqid('/mfs_src'));
        $dest = uniqid('mfs_dest');
        mkdir($src, 0777);

        $actual = $this->fixture->rename($src, StreamWrapper::PROTOCOL . '://' . $dest);

        self::assertTrue($actual);
        self::assertFalse(file_exists($src), 'Source not removed');
        self::assertTrue(is_dir(StreamWrapper::PROTOCOL . ':///' . $dest), 'Destination not created');
    }

    public function testTouchWhenNotExists(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $this->fixture->stream_metadata($path, \STREAM_META_TOUCH, []);

        $now = time();
        $stat = $this->fixture->url_stat($path, 0);
        if ($stat === false) {
            self::fail();
        }

        self::assertTrue(is_file($path));
        self::assertEqualsWithDelta($now, $stat['atime'], 1);
        self::assertEqualsWithDelta($now, $stat['mtime'], 1);
    }

    public function testTouchWhenPathNotExistsCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/')) . uniqid('/');

        self::expectWarning();
        self::expectWarningMessage('touch(): Unable to create file ' . $path);

        $this->fixture->stream_metadata($path, \STREAM_META_TOUCH, []);
    }

    public function testTouchContextFailCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/')) . uniqid('/');
        $message = uniqid();

        $this->setContext(['touch_fail' => true, 'touch_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        $this->fixture->stream_metadata($path, \STREAM_META_TOUCH, []);
    }

    public function testTouchContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/')) . uniqid('/');

        $this->setContext(['touch_fail' => true]);

        $actual = @$this->fixture->stream_metadata($path, \STREAM_META_TOUCH, []);

        self::assertFalse($actual);
    }

    public function testTouchWhenPathNotExistsResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/')) . uniqid('/');

        $actual = @$this->fixture->stream_metadata($path, \STREAM_META_TOUCH, []);

        self::assertFalse($actual);
    }

    public function testTouchWhenFileAlreadyExist(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $this->fixture->stream_metadata($path, \STREAM_META_TOUCH, []);

        $now = time();
        $stat = $this->fixture->url_stat($path, 0);
        if ($stat === false) {
            self::fail();
        }

        self::assertTrue(is_file($path));
        self::assertEqualsWithDelta($now, $stat['atime'], 1);
        self::assertEqualsWithDelta($now, $stat['mtime'], 1);
    }

    public function testStatWhenFileNotExistsCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        self::expectWarning();
        self::expectWarningMessage('stat(): stat failed for ' . $path);

        $this->fixture->url_stat($path, 0);
    }

    public function testStatWhenFileNotExistsResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $actual = @$this->fixture->url_stat($path, 0);

        self::assertFalse($actual);
    }

    public function testStatWhenFileNotExistsDoesNotCreateError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $actual = $this->fixture->url_stat($path, \STREAM_URL_STAT_QUIET);

        self::assertFalse($actual);
    }

    public function testStatHasCorrectKeys(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $actual = $this->fixture->url_stat($path, 0);
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
        $path = MockFileSystem::getUrl(uniqid('/'));
        $permissions = 0500;
        $now = time();
        file_put_contents($path, uniqid());
        chmod($path, $permissions);
        $config = MockFileSystem::getFileSystem()->getConfig();
        $file = MockFileSystem::find($path);
        if ($file === null) {
            self::fail('File not found');
        }

        $actual = $this->fixture->url_stat($path, 0);

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
        $path = MockFileSystem::getUrl(uniqid('/'));
        $permissions = 0750;
        $now = time();
        mkdir($path, $permissions);
        $config = MockFileSystem::getFileSystem()->getConfig();
        $file = MockFileSystem::find($path);
        if ($file === null) {
            self::fail('File not found');
        }

        $actual = $this->fixture->url_stat($path, 0);

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
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());
        $message = uniqid();

        $this->setContext(['stat_fail' => true, 'stat_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        $this->fixture->url_stat($path, 0);
    }

    public function testStatContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $this->setContext(['stat_fail' => true]);

        $actual = @$this->fixture->url_stat($path, 0);

        self::assertFalse($actual);
    }

    public function testUnlinkNonExistentFileCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        self::expectWarning();
        self::expectWarningMessage('unlink(' . $path . '): No such file or directory');

        $this->fixture->unlink($path);
    }

    public function testUnlinkNonExistentFileResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $actual = @$this->fixture->unlink($path);

        self::assertFalse($actual);
    }

    public function testUnlinkDirCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path);

        self::expectWarning();
        self::expectWarningMessage('unlink(' . $path . '): Operation not permitted');

        $this->fixture->unlink($path);
    }

    public function testUnlinkDirResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        mkdir($path);

        $actual = @$this->fixture->unlink($path);

        self::assertFalse($actual);
    }

    public function testUnlink(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $actual = @$this->fixture->unlink($path);

        self::assertTrue($actual, 'Unlink failed');
        self::assertFalse(file_exists($path), 'File exists after unlink');
    }

    public function testUnlinkContextFailCreatesError(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());
        $message = uniqid();

        $this->setContext(['unlink_fail' => true, 'unlink_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        $this->fixture->unlink($path);
    }

    public function testUnlinkContextFailResponse(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $this->setContext(['unlink_fail' => true]);

        $actual = $this->fixture->unlink($path);

        self::assertFalse($actual);
        self::assertTrue(file_exists($path));
    }

    public function testStreamCastResponse(): void
    {
        $actual = $this->fixture->stream_cast(rand());

        self::assertFalse($actual);
    }

    public function testChownWhenPathNotExists(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $actual = $this->fixture->stream_metadata($path, \STREAM_META_OWNER, rand());

        self::assertFalse($actual);
    }

    public function testChownWhenPathExists(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $actual = $this->fixture->stream_metadata($path, \STREAM_META_OWNER, 123);

        self::assertTrue($actual);
        self::assertEquals(123, fileowner($path));
    }

    public function testChownWithStringUser(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $actual = $this->fixture->stream_metadata($path, \STREAM_META_OWNER_NAME, uniqid());

        self::assertFalse($actual);
    }

    public function testChgrpWhenPathNotExists(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $actual = $this->fixture->stream_metadata($path, \STREAM_META_GROUP, rand());

        self::assertFalse($actual);
    }

    public function testChgrpWhenPathExists(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $actual = $this->fixture->stream_metadata($path, \STREAM_META_GROUP, 123);

        self::assertTrue($actual);
        self::assertEquals(123, filegroup($path));
    }

    public function testChgrpWithStringGroup(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $actual = $this->fixture->stream_metadata($path, \STREAM_META_GROUP_NAME, uniqid());

        self::assertFalse($actual);
    }

    public function testChmodWhenPathNotExists(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));

        $actual = $this->fixture->stream_metadata($path, \STREAM_META_ACCESS, 0700);

        self::assertFalse($actual);
    }

    public function testChmodWhenPathExists(): void
    {
        $path = MockFileSystem::getUrl(uniqid('/'));
        file_put_contents($path, uniqid());

        $actual = $this->fixture->stream_metadata($path, \STREAM_META_ACCESS, 0700);

        self::assertTrue($actual);
        self::assertEquals(FileInterface::TYPE_FILE | 0700, fileperms($path));
    }

    /**
     * @param mixed[] $options
     */
    private function setContext(array $options = []): void
    {
        stream_context_set_default([StreamWrapper::PROTOCOL => $options]);
    }

    /**
     * @param string $path
     *
     * @return ContentInterface&MockObject
     */
    private function mockFileContent(string $path): ContentInterface
    {
        /** @var ContentInterface&MockObject $content */
        $content = $this->createMock(ContentInterface::class);

        /** @var RegularFileInterface $file */
        $file = MockFileSystem::find($path);
        $file->setContent($content);

        return $content;
    }
}
