<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test rename()
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class RenameTest extends AbstractTestCase
{
    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameFile(string $prefix): void
    {
        $src = $prefix . '/' . uniqid('mfs_src');
        $dest = $prefix . '/' . uniqid('mfs_dest');
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
        $src = $prefix . '/' . uniqid('mfs_src');
        $dest = $prefix . '/' . uniqid('mfs_dest');
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
        $src = $prefix . '/' . uniqid('mfs_src');
        $dest = $prefix . '/' . uniqid('mfs_dest');
        $this->cleanup($src);
        $this->cleanup($dest);

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): No such file or directory');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameNonExistentSrcResponse(string $prefix): void
    {
        $src = $prefix . '/' . uniqid('mfs_src');
        $dest = $prefix . '/' . uniqid('mfs_dest');
        $this->cleanup($src);
        $this->cleanup($dest);

        self::assertFalse(@rename($src, $dest));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameNonExistentDestCreatesError(string $prefix): void
    {
        $src = $prefix . '/' . uniqid('mfs_src');
        $dest = $prefix . '/' . uniqid('mfs_dest') . '/' . uniqid();
        $this->cleanup($src);
        $this->cleanup($dest);
        file_put_contents($src, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): No such file or directory');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameNonExistentDestResponse(string $prefix): void
    {
        $src = $prefix . '/' . uniqid('mfs_src');
        $dest = $prefix . '/' . uniqid('mfs_dest') . '/' . uniqid();
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
        $src = $prefix . '/' . uniqid('mfs_src');
        $destBase = $prefix . '/' . uniqid('mfs_dest');
        $dest = $destBase . '/' . uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        file_put_contents($src, uniqid());
        file_put_contents($destBase, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): Not a directory');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDestNotDirectoryResponse(string $prefix): void
    {
        $src = $prefix . '/' . uniqid('mfs_src');
        $destBase = $prefix . '/' . uniqid('mfs_dest');
        $dest = $destBase . '/' . uniqid();
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
        $src = $prefix . '/' . uniqid('mfs_src');
        $destBase = $prefix . '/' . uniqid('mfs_dest');
        $dest = $destBase . '/' . uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        file_put_contents($src, uniqid());
        mkdir($destBase, 0500);

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): Permission denied');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDestNotWritableResponse(string $prefix): void
    {
        $src = $prefix . '/' . uniqid('mfs_src');
        $destBase = $prefix . '/' . uniqid('mfs_dest');
        $dest = $destBase . '/' . uniqid();
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
        $src = $prefix . '/' . uniqid('mfs_src');
        $dest = $prefix . '/' . uniqid('mfs_dest') . '/' . uniqid();
        $this->cleanup($src);
        $this->cleanup($dest);
        mkdir($src, 0777);
        mkdir($dest, 0777, true);
        file_put_contents($dest . '/' . uniqid(), uniqid());

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): Directory not empty');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDirWhenDestNotEmptyResponse(string $prefix): void
    {
        $src = $prefix . '/' . uniqid('mfs_src');
        $dest = $prefix . '/' . uniqid('mfs_dest') . '/' . uniqid();
        $this->cleanup($src);
        $this->cleanup($dest);
        mkdir($src, 0777);
        mkdir($dest, 0777, true);
        file_put_contents($dest . '/' . uniqid(), uniqid());

        self::assertFalse(@rename($src, $dest));
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDirWhenDestExists(string $prefix): void
    {
        $src = $prefix . '/' . uniqid('mfs_src');
        $dest = $prefix . '/' . uniqid('mfs_dest') . '/' . uniqid();
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
        $src = $prefix . '/' . uniqid('mfs_src');
        $destBase = $prefix . '/' . uniqid('mfs_dest');
        $dest = $destBase . '/' . uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        $this->cleanup($dest);
        mkdir($src, 0777);
        mkdir($destBase, 0777);
        file_put_contents($dest, uniqid());

        self::expectWarning();
        self::expectWarningMessage('rename(' . $src . ',' . $dest . '): Not a directory');

        rename($src, $dest);
    }

    /**
     * @dataProvider samplePrefixes
     */
    public function testRenameDirWhenDestExistsAsFileResponse(string $prefix): void
    {
        $src = $prefix . '/' . uniqid('mfs_src');
        $destBase = $prefix . '/' . uniqid('mfs_dest');
        $dest = $destBase . '/' . uniqid();
        $this->cleanup($src);
        $this->cleanup($destBase);
        $this->cleanup($dest);
        mkdir($src, 0777);
        mkdir($destBase, 0777);
        file_put_contents($dest, uniqid());

        self::assertFalse(@rename($src, $dest));
    }

    public function testRenameContextFailCreatesError(): void
    {
        $pathA = StreamWrapper::PROTOCOL . ':///' . uniqid();
        $pathB = StreamWrapper::PROTOCOL . ':///' . uniqid();
        file_put_contents($pathA, uniqid());
        $message = uniqid();

        $this->setContext(['rename_fail' => true, 'rename_message' => $message]);

        self::expectWarning();
        self::expectWarningMessage($message);

        rename($pathA, $pathB);
    }

    public function testRenameContextFailResponse(): void
    {
        $pathA = StreamWrapper::PROTOCOL . ':///' . uniqid();
        $pathB = StreamWrapper::PROTOCOL . ':///' . uniqid();
        file_put_contents($pathA, uniqid());

        $this->setContext(['rename_fail' => true]);

        $actual = rename($pathA, $pathB);

        self::assertFalse($actual);
        self::assertTrue(file_exists($pathA));
        self::assertFalse(file_exists($pathB));
    }
}
