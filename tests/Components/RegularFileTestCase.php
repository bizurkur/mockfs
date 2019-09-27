<?php declare(strict_types = 1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Config\Config;
use MockFileSystem\Content\ContentInterface;
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
    protected $fixture = null;

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

        $this->fixture->write(uniqid());

        self::assertEqualsWithDelta(time(), $this->fixture->getLastModifyTime(), 1);
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

        $this->fixture->truncate(rand());

        self::assertEqualsWithDelta(time(), $this->fixture->getLastModifyTime(), 1);
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
}
