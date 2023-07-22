<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Content\ContentInterface;
use MockFileSystem\MockFileSystem;
use MockFileSystem\Quota\Quota;
use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test file operations:
 *
 * - fopen()
 * - fclose()
 * - fread()
 * - fwrite()
 * - fseek()
 * - ftell()
 * - ftruncate()
 * - fflush()
 * - fstat()
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class FileOperationsTest extends AbstractTestCase
{
    /**
     * @dataProvider samplePrefixes
     */
    public function testFileOpenInvalidModeResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        self::assertFalse(@fopen($url, 'q'));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testFileOpenForReadOnNonExistentFileCreatesError(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        self::expectWarning();
        if (version_compare(PHP_VERSION, '7.9.9', '>=')) {
            self::expectWarningMessage('fopen(' . $url . '): Failed to open stream');
        } else {
            self::expectWarningMessage('fopen(' . $url . '): failed to open stream');
        }

        fopen($url, 'r');
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testFileOpenForReadOnNonExistentFileResponse(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        self::assertFalse(@fopen($url, 'r'));
    }

    /**
     * @dataProvider sampleCreateNewModes
     */
    public function testFileOpenForCreateNewWhenExistsCreatesError(string $mode): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());

        self::expectWarning();
        if (version_compare(PHP_VERSION, '7.9.9', '>=')) {
            self::expectWarningMessage('fopen(' . $url . '): Failed to open stream');
        } else {
            self::expectWarningMessage('fopen(' . $url . '): failed to open stream');
        }

        fopen($url, $mode);
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
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        self::assertFalse(@fopen($url, 'x'));
    }

    /**
     * @dataProvider sampleReadModes
     */
    public function testFileOpenForReadWhenNotReadableCreatesError(string $mode): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());
        chmod($url, 0200);

        self::expectWarning();
        if (version_compare(PHP_VERSION, '7.9.9', '>=')) {
            self::expectWarningMessage('fopen(' . $url . '): Failed to open stream');
        } else {
            self::expectWarningMessage('fopen(' . $url . '): failed to open stream');
        }

        fopen($url, $mode);
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
        $url = $prefix . '/' . uniqid('mfs_');
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
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        file_put_contents($url, uniqid());
        chmod($url, 0500);

        self::expectWarning();
        if (version_compare(PHP_VERSION, '7.9.9', '>=')) {
            self::expectWarningMessage('fopen(' . $url . '): Failed to open stream');
        } else {
            self::expectWarningMessage('fopen(' . $url . '): failed to open stream');
        }

        fopen($url, $mode);
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
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());
        chmod($url, 0500);

        self::assertFalse(@fopen($url, 'w'));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadAndWrite(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
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
        $url = $prefix . '/' . uniqid('mfs_');
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

        self::assertEquals($contentA . $contentB . $contentC, file_get_contents($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadWhenNotReadMode(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = @fread($handle, rand(1, 100));
        fclose($handle);

        self::assertEquals('', $actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadWhenNotReadable(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
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
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $handle = fopen($url, 'r');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = @fwrite($handle, uniqid());
        fclose($handle);

        self::assertEquals(0, $actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testWriteWhenNotWritable(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
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

    /**
     * @dataProvider samplePrefixes
     */
    public function testTruncateWhenNotWriteMode(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
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
        $url = $prefix . '/' . uniqid('mfs_');
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
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        $handle = fopen($url, 'w+');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        fwrite($handle, $content);
        ftruncate($handle, strlen($content) + 3);
        fclose($handle);

        self::assertEquals($content . "\0\0\0", file_get_contents($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testTruncateDown(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
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
        $url = $prefix . '/' . uniqid('mfs_');
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

        fwrite($handle1, $contentA . $contentA);
        fwrite($handle2, $contentB);
        fclose($handle1);
        fclose($handle2);

        self::assertEquals($contentB . $contentA, file_get_contents($url));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testWriteDifferentFileFromTwoHandles(string $prefix): void
    {
        $urlA = $prefix . '/' . uniqid('mfs_a');
        $urlB = $prefix . '/' . uniqid('mfs_b');
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
        $url = $prefix . '/' . uniqid('mfs_') . '/' . uniqid();

        self::assertFalse(@fopen($url, 'w'));
    }

    public function testWriteParentDirDoesNotExistCreatesError(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_') . '/' . uniqid();

        self::expectWarning();
        if (version_compare(PHP_VERSION, '7.9.9', '>=')) {
            self::expectWarningMessage('fopen(' . $url . '): Failed to open stream');
        } else {
            self::expectWarningMessage('fopen(' . $url . '): failed to open stream');
        }

        fopen($url, 'w');
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testCreateFileParentNotWritable(string $prefix): void
    {
        $base = $prefix . '/' . uniqid('mfs_');
        $url = $base . '/' . uniqid();
        mkdir($base, 0500);

        self::assertFalse(@fopen($url, 'w'));
    }

    public function testCreateFileParentNotWritableCreatesError(): void
    {
        $base = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        $url = $base . '/' . uniqid();
        mkdir($base, 0500);

        self::expectWarning();
        if (version_compare(PHP_VERSION, '7.9.9', '>=')) {
            self::expectWarningMessage('fopen(' . $url . '): Failed to open stream');
        } else {
            self::expectWarningMessage('fopen(' . $url . '): failed to open stream');
        }

        fopen($url, 'w');
    }

    public function testCreateFileThrowsExceptionCreatesError(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');

        self::expectWarning();
        if (version_compare(PHP_VERSION, '7.9.9', '>=')) {
            self::expectWarningMessage('fopen(' . $url . '): Failed to open stream');
        } else {
            self::expectWarningMessage('fopen(' . $url . '): failed to open stream');
        }

        $quota = new Quota(-1, 0);
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        fopen($url, 'w');
    }

    public function testCreateFileThrowsExceptionResponse(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');

        $quota = new Quota(-1, 0);
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        $actual = @fopen($url, 'w');

        self::assertFalse($actual);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testReadSameFileFromTwoHandles(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
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
        $urlA = $prefix . '/' . uniqid('mfs_');
        $urlB = $prefix . '/' . uniqid('mfs_');
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
        $url = $prefix . '/' . uniqid('mfs_');
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

    public function testFileOpenContextFailResponse(): void
    {
        $this->setContext(['fopen_fail' => true]);

        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();

        $actual = @fopen($path, 'w');

        self::assertFalse($actual);
    }

    public function testFileCloseContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        file_put_contents($path, uniqid());

        /** @var RegularFileInterface $file */
        $file = MockFileSystem::find($path);
        $content = $this->createMock(ContentInterface::class);
        $content->method('open')->willReturn(true);
        $content->method('close')->willReturn(true);
        $file->setContent($content);

        $this->setContext(['fclose_fail' => true]);

        $handle = fopen($path, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        $content->expects(self::never())->method('close');

        fclose($handle);
    }

    public function testFileReadContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
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

    public function testFileWriteContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();

        $this->setContext(['fwrite_fail' => true]);

        $handle = fopen($path, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = @fwrite($handle, uniqid());
        fclose($handle);

        self::assertEquals(0, $actual);
    }

    public function testFileSeekContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
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

    public function testFileTellContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
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

    public function testFileEofContextFailDefaultFalse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
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

    public function testFileEofContextFailOverrideTrue(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
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

    public function testFileFlushContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
        file_put_contents($path, uniqid());

        /** @var RegularFileInterface $file */
        $file = MockFileSystem::find($path);
        $content = $this->createMock(ContentInterface::class);
        $content->method('open')->willReturn(true);
        $content->method('close')->willReturn(true);
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

    public function testFileStatContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
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

    public function testFileTruncateContextFailResponse(): void
    {
        $path = StreamWrapper::PROTOCOL . ':///' . uniqid();
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
     * @dataProvider samplePrefixes
     */
    public function testFileStat(string $prefix): void
    {
        $url = $prefix . '/' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $handle = fopen($url, 'w+');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = fstat($handle);
        fclose($handle);

        $expected = stat($url);

        self::assertEquals($expected, $actual);
    }

    public function testFileStatFailResponse(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid();
        file_put_contents($url, uniqid());

        $this->setContext(['fstat_fail' => true]);

        $handle = fopen($url, 'w+');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $actual = fstat($handle);
        fclose($handle);

        self::assertFalse($actual);
    }

    // TODO: The below tests don't belong here

    public function testStreamCastCreatesError(): void
    {
        if (version_compare(PHP_VERSION, '7.9.9', '>=')) {
            self::markTestSkipped('This test only applies to PHP < 8.0');
        }
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $read = [$handle];
        $write = null;
        $except = null;

        self::expectWarning();
        self::expectWarningMessage(
            'stream_select(): cannot represent a stream of type user-space as a select()able descriptor'
        );

        stream_select($read, $write, $except, 0);
    }

    public function testStreamCastResponse(): void
    {
        if (version_compare(PHP_VERSION, '7.9.9', '>=')) {
            self::markTestSkipped('This test only applies to PHP < 8.0');
        }
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }
        $read = [$handle];
        $write = null;
        $except = null;

        self::assertFalse(@stream_select($read, $write, $except, 0));
    }
}
