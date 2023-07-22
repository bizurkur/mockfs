<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Config\Config;
use MockFileSystem\Content\ContentInterface;
use MockFileSystem\Content\StreamContent;
use MockFileSystem\Quota\QuotaManagerInterface;
use MockFileSystem\Tests\Components\ComponentTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Abstract tests that should be applied to every regular file.
 */
abstract class RegularFileTestCase extends ComponentTestCase
{
    /**
     * @var RegularFileInterface
     */
    protected FileInterface $fixture;

    public function testGetDefaultPermissions(): void
    {
        self::assertEquals(0666, $this->fixture->getDefaultPermissions());
    }

    public function testGetSize(): void
    {
        $size = rand();
        $content = $this->createContent(['getSize' => $size]);
        $this->fixture->setContent($content);

        self::assertEquals($size, $this->fixture->getSize());
    }

    public function testOpenCallsContentOpen(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('open');

        $this->fixture->open();
    }

    public function testOpenUpdatesLastAccessTime(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $this->fixture->setLastAccessTime(rand());

        $this->fixture->open();

        self::assertEqualsWithDelta(time(), $this->fixture->getLastAccessTime(), 1);
    }

    public function testCloseCallsContentClose(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('close');

        $this->fixture->close();
    }

    public function testReadCallsContentRead(): void
    {
        $count = rand();
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('read')
            ->with($count);

        $this->fixture->read($count);
    }

    public function testReadReturnsContentRead(): void
    {
        $response = uniqid();
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->method('read')->willReturn($response);

        $actual = $this->fixture->read(rand());

        self::assertEquals($response, $actual);
    }

    public function testReadUpdatesLastAccessTime(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $this->fixture->setLastAccessTime(rand());

        $this->fixture->read(rand());

        self::assertEqualsWithDelta(time(), $this->fixture->getLastAccessTime(), 1);
    }

    public function testWriteCallsContentWrite(): void
    {
        $data = uniqid();
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $this->fixture->setConfig(new Config());

        $content->expects(self::once())
            ->method('write')
            ->with($data);

        $this->fixture->write($data);
    }

    public function testWriteReturnsContentWrite(): void
    {
        $response = rand();
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $this->fixture->setConfig(new Config());

        $content->method('write')->willReturn($response);

        $actual = $this->fixture->write(uniqid());

        self::assertEquals($response, $actual);
    }

    public function testWriteUpdatesLastModifyTime(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $this->fixture->setConfig(new Config());
        $this->fixture->setLastModifyTime(rand());

        $this->fixture->write(uniqid());

        self::assertEqualsWithDelta(time(), $this->fixture->getLastModifyTime(), 1);
    }

    public function testWriteWhenLimitedDiskSpace(): void
    {
        $remaining = rand(1, 9);
        $data = str_repeat("\0", $remaining * 2);
        $this->setUpQuotaManager($remaining);

        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('write')
            ->with(substr($data, 0, $remaining));

        $this->fixture->write($data);
    }

    public function testWriteWhenLimitedDiskSpaceAccountsForFileOffset(): void
    {
        $remaining = rand(1, 9);
        $size = rand(1000, 9999);
        $tell = $size - intval(floor($remaining / 2));
        $data = str_repeat("\0", $remaining * 2);
        $this->setUpQuotaManager($remaining);

        $content = $this->createContent(['getSize' => $size, 'tell' => $tell]);
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('write')
            ->with(substr($data, 0, $remaining + $tell - $size));

        $this->fixture->write($data);
    }

    public function testWriteWhenUnlimitedDiskSpace(): void
    {
        $data = uniqid();
        $this->setUpQuotaManager(-1);

        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('write')
            ->with($data);

        $this->fixture->write($data);
    }

    public function testTruncateCallsContentTruncate(): void
    {
        $size = rand();
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $this->fixture->setConfig(new Config());

        $content->expects(self::once())
            ->method('truncate')
            ->with($size);

        $this->fixture->truncate($size);
    }

    public function testTruncateReturnsContentTruncate(): void
    {
        $response = (bool) rand(0, 1);
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $this->fixture->setConfig(new Config());

        $content->method('truncate')->willReturn($response);

        $actual = $this->fixture->truncate(rand());

        self::assertEquals($response, $actual);
    }

    public function testTruncateUpdatesLastModifyTime(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $this->fixture->setConfig(new Config());
        $this->fixture->setLastModifyTime(rand());

        $this->fixture->truncate(rand());

        self::assertEqualsWithDelta(time(), $this->fixture->getLastModifyTime(), 1);
    }

    /**
     * @dataProvider sampleTruncateNotEnoughDiskSpace
     */
    public function testTruncateUpWhenLimitedDiskSpaceNotEnoughSpace(int $remaining): void
    {
        $size = $remaining * 2 + 1;
        $this->setUpQuotaManager($remaining);

        $content = $this->createContent(['getSize' => 0]);
        $this->fixture->setContent($content);

        $content->expects(self::never())->method('truncate');

        $actual = $this->fixture->truncate($size);

        self::assertFalse($actual);
    }

    public function sampleTruncateNotEnoughDiskSpace(): array
    {
        return [
            [0],
            [1],
            [2],
        ];
    }

    public function testTruncateUpWhenLimitedDiskSpaceHasEnoughSpace(): void
    {
        $remaining = rand(1, 9);
        $size = rand(1, 999);
        $this->setUpQuotaManager($remaining);

        $content = $this->createContent(['getSize' => $size]);
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('truncate')
            ->with($size + $remaining);

        $this->fixture->truncate($size + $remaining);
    }

    public function testTruncateDownWhenLimitedDiskSpace(): void
    {
        $remaining = rand(1, 9);
        $size = $remaining * 2;
        $this->setUpQuotaManager($remaining);

        $content = $this->createContent(['getSize' => $size + rand(0, 999)]);
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('truncate')
            ->with($size);

        $this->fixture->truncate($size);
    }

    public function testTruncateWhenUnlimitedDiskSpace(): void
    {
        $size = rand();
        $this->setUpQuotaManager(-1);

        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('truncate')
            ->with($size);

        $this->fixture->truncate($size);
    }

    public function testSeekCallsContentSeek(): void
    {
        $offset = rand();
        $whence = rand();
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('seek')
            ->with($offset, $whence);

        $this->fixture->seek($offset, $whence);
    }

    public function testSeekReturnsContentSeek(): void
    {
        $response = (bool) rand(0, 1);
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->method('seek')->willReturn($response);

        $actual = $this->fixture->seek(rand(), rand());

        self::assertEquals($response, $actual);
    }

    public function testTellCallsContentTell(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('tell');

        $this->fixture->tell();
    }

    public function testTellReturnsContentTell(): void
    {
        $response = rand();
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->method('tell')->willReturn($response);

        $actual = $this->fixture->tell();

        self::assertEquals($response, $actual);
    }

    public function testIsEofCallsContentIsEof(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('isEof');

        $this->fixture->isEof();
    }

    public function testIsEofReturnsContentIsEof(): void
    {
        $response = (bool) rand(0, 1);
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->method('isEof')->willReturn($response);

        $actual = $this->fixture->isEof();

        self::assertEquals($response, $actual);
    }

    public function testFlushCallsContentFlush(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('flush');

        $this->fixture->flush();
    }

    public function testFlushReturnsContentFlush(): void
    {
        $response = (bool) rand(0, 1);
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->method('flush')->willReturn($response);

        $actual = $this->fixture->flush();

        self::assertEquals($response, $actual);
    }

    public function testUnlinkCallsContentUnlink(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->expects(self::once())
            ->method('unlink');

        $this->fixture->unlink();
    }

    public function testUnlinkExitsEarlyWhenContentUnlinkFails(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $parent = $this->createParent();
        $this->fixture->setParent($parent);

        $content->method('unlink')->willReturn(false);

        $parent->expects(self::never())->method('removeChild');

        $actual = $this->fixture->unlink();

        self::assertFalse($actual);
    }

    public function testUnlinkCallsParentRemoveChild(): void
    {
        $name = uniqid();
        $this->fixture->setName($name);
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $parent = $this->createParent();
        $this->fixture->setParent($parent);

        $content->method('unlink')->willReturn(true);

        $parent->expects(self::once())
            ->method('removeChild')
            ->with($name);

        $this->fixture->unlink();
    }

    public function testUnlinkReturnsParentRemoveChild(): void
    {
        $response = (bool) rand(0, 1);
        $content = $this->createContent();
        $this->fixture->setContent($content);
        $parent = $this->createParent();
        $this->fixture->setParent($parent);

        $content->method('unlink')->willReturn(true);
        $parent->method('removeChild')->willReturn($response);

        $actual = $this->fixture->unlink();

        self::assertEquals($response, $actual);
    }

    public function testUnlinkWhenNoParent(): void
    {
        $content = $this->createContent();
        $this->fixture->setContent($content);

        $content->method('unlink')->willReturn(true);

        $actual = $this->fixture->unlink();

        self::assertTrue($actual);
    }

    public function testSetContent(): void
    {
        $content = $this->createContent();

        $this->fixture->setContent($content);

        $actual = $this->fixture->getContent();

        self::assertSame($content, $actual);
    }

    public function testSetContentFromString(): void
    {
        $content = uniqid();

        $this->fixture->setContentFromString($content);

        $actual = $this->fixture->getContent();

        self::assertInstanceOf(StreamContent::class, $actual);
        self::assertEquals($content, $actual->read(1024));
    }

    /**
     * @param mixed[] $methods
     *
     * @return ContentInterface&MockObject
     */
    private function createContent(array $methods = []): ContentInterface
    {
        return $this->createConfiguredMock(
            ContentInterface::class,
            $methods
        );
    }

    /**
     * @param mixed[] $methods
     *
     * @return ContainerInterface&MockObject
     */
    private function createParent(array $methods = []): ContainerInterface
    {
        return $this->createConfiguredMock(
            ContainerInterface::class,
            $methods
        );
    }

    /**
     * @param int $remaining
     *
     * @return QuotaManagerInterface&MockObject
     */
    private function setUpQuotaManager(int $remaining = -1): QuotaManagerInterface
    {
        $manager = $this->createConfiguredMock(
            QuotaManagerInterface::class,
            ['getFreeDiskSpace' => $remaining]
        );
        $partition = $this->createConfiguredMock(
            PartitionInterface::class,
            ['getQuotaManager' => $manager]
        );
        $this->fixture->setParent($partition);

        $manager->expects(self::once())
            ->method('getFreeDiskSpace')
            ->with(self::isNull());

        return $manager;
    }
}
