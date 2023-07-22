<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\MockFileSystem;
use MockFileSystem\Quota\Quota;
use MockFileSystem\StreamWrapper;
use MockFileSystem\Tests\AbstractTestCase;

/**
 * Test quotas
 *
 * phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
 */
class QuotaTest extends AbstractTestCase
{
    public function testWriteWithQuotaLimitedSpace(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        $quota = new Quota(4, -1);
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        $actual = fwrite($handle, $content);
        fclose($handle);

        self::assertEquals(4, $actual);
        self::assertEquals(substr($content, 0, 4), file_get_contents($url));
    }

    public function testFilePutContentsWithQuotaLimitedSpaceCreatesError(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        $this->cleanup($url);

        $quota = new Quota(4, -1);
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        self::expectWarning();
        self::expectWarningMessage(
            'file_put_contents(): Only 4 of 13 bytes written, possibly out of free disk space'
        );

        file_put_contents($url, uniqid());
    }

    public function testFilePutContentsWithQuotaLimitedSpaceResponse(): void
    {
        $quota = new Quota(4, -1);
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        @file_put_contents($url, $content);

        self::assertEquals(substr($content, 0, 4), file_get_contents($url));
    }

    public function testWriteWithQuotaUnlimitedSpace(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        $this->cleanup($url);
        $content = uniqid();

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        $quota = new Quota(-1, -1);
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        $actual = fwrite($handle, $content);
        fclose($handle);

        self::assertEquals(strlen($content), $actual);
        self::assertEquals($content, file_get_contents($url));
    }

    public function testTruncateUpWithQuotaLimitedSpace(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        $quota = new Quota(4, -1);
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        $actual = ftruncate($handle, rand(50, 100));
        fclose($handle);

        self::assertFalse($actual);
    }

    public function testTruncateUpWithQuotaUnlimitedSpace(): void
    {
        $url = StreamWrapper::PROTOCOL . ':///' . uniqid('mfs_');
        $this->cleanup($url);
        file_put_contents($url, uniqid());

        $handle = fopen($url, 'w');
        if ($handle === false) {
            self::fail('Failed to open handle');
        }

        $quota = new Quota(-1, -1);
        $partition = MockFileSystem::getFileSystem()->getChild('/');
        if ($partition instanceof PartitionInterface) {
            $partition->setQuota($quota);
        }

        $actual = ftruncate($handle, rand(50, 100));
        fclose($handle);

        self::assertTrue($actual);
    }
}
