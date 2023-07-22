<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components\RegularFile;

use MockFileSystem\Components\RegularFile\AbstractProxyDecorator;
use MockFileSystem\Components\RegularFile\MultiInstanceDecorator;
use MockFileSystem\Components\RegularFileInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MultiInstanceDecoratorTest extends TestCase
{
    private MultiInstanceDecorator $fixture;

    /**
     * @var RegularFileInterface&MockObject
     */
    private RegularFileInterface $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = $this->createMock(RegularFileInterface::class);

        $this->fixture = new MultiInstanceDecorator($this->base);
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(AbstractProxyDecorator::class, $this->fixture);
    }

    public function testReadCallsBaseSeek(): void
    {
        $this->base->expects(self::once())
            ->method('seek')
            ->with(0);

        $this->fixture->read(rand());
    }

    public function testReadCallsBaseRead(): void
    {
        $count = rand();

        $this->base->expects(self::once())
            ->method('read')
            ->with($count);

        $this->fixture->read($count);
    }

    public function testReadCallsBaseTell(): void
    {
        $this->base->expects(self::once())
            ->method('tell');

        $this->fixture->read(rand());
    }

    public function testReadCallsBaseSeekFromKnownPosition(): void
    {
        $position = rand();

        $this->base->method('tell')->willReturn($position);
        $this->fixture->read(rand());

        $this->base->expects(self::once())
            ->method('seek')
            ->with($position);

        $this->fixture->read(rand());
    }

    public function testReadResponse(): void
    {
        $data = uniqid();

        $this->base->method('read')->willReturn($data);

        $actual = $this->fixture->read(rand());

        self::assertEquals($data, $actual);
    }

    public function testWriteCallsBaseSeek(): void
    {
        $this->base->expects(self::once())
            ->method('seek')
            ->with(0);

        $this->fixture->write(uniqid());
    }

    public function testWriteCallsBaseWrite(): void
    {
        $data = uniqid();

        $this->base->expects(self::once())
            ->method('write')
            ->with($data);

        $this->fixture->write($data);
    }

    public function testWriteCallsBaseTell(): void
    {
        $this->base->expects(self::once())
            ->method('tell');

        $this->fixture->write(uniqid());
    }

    public function testWriteCallsBaseSeekFromKnownPosition(): void
    {
        $position = rand();

        $this->base->method('tell')->willReturn($position);
        $this->fixture->write(uniqid());

        $this->base->expects(self::once())
            ->method('seek')
            ->with($position);

        $this->fixture->write(uniqid());
    }

    public function testWriteResponse(): void
    {
        $bytes = rand();

        $this->base->method('write')->willReturn($bytes);

        $actual = $this->fixture->write(uniqid());

        self::assertEquals($bytes, $actual);
    }

    public function testTruncateCallsBaseSeek(): void
    {
        $this->base->expects(self::once())
            ->method('seek')
            ->with(0);

        $this->fixture->truncate(rand());
    }

    public function testTruncateCallsBaseTruncate(): void
    {
        $size = rand();

        $this->base->expects(self::once())
            ->method('truncate')
            ->with($size);

        $this->fixture->truncate($size);
    }

    public function testTruncateCallsBaseSeekFromKnownPosition(): void
    {
        $position = rand();

        $this->base->method('tell')->willReturn($position);
        $this->fixture->read(rand());

        $this->base->expects(self::once())
            ->method('seek')
            ->with($position);

        $this->fixture->truncate(rand());
    }

    public function testTruncateResponse(): void
    {
        $response = (bool) rand(0, 1);

        $this->base->method('truncate')->willReturn($response);

        $actual = $this->fixture->truncate(rand());

        self::assertEquals($response, $actual);
    }

    /**
     * @dataProvider sampleSeeks
     */
    public function testSeekCallsBaseSeek(int $offset, int $whence, array $expected): void
    {
        $count = count($expected);
        $this->base->expects(self::exactly($count))
            ->method('seek')
            ->withConsecutive(...$expected);

        $this->fixture->seek($offset, $whence);
    }

    public function sampleSeeks(): array
    {
        $offset = rand();

        return [
            'SEEK_CUR' => [
                'offset' => $offset,
                'whence' => \SEEK_CUR,
                'expected' => [
                    [0, \SEEK_SET],
                    [$offset, \SEEK_CUR],
                ],
            ],
            'SEEK_END' => [
                'offset' => $offset,
                'whence' => \SEEK_END,
                'expected' => [
                    [0, \SEEK_SET],
                    [$offset, \SEEK_END],
                ],
            ],
            'SEEK_SET' => [
                'offset' => $offset,
                'whence' => \SEEK_SET,
                'expected' => [
                    [$offset, \SEEK_SET],
                ],
            ],
        ];
    }

    /**
     * @dataProvider sampleDoubleSeek
     */
    public function testSeekCallsBaseSeekFromKnownPosition(int $whence): void
    {
        $offset = rand();
        $position = rand();

        $this->base->method('tell')->willReturn($position);
        $this->fixture->read(rand());

        $this->base->expects(self::exactly(2))
            ->method('seek')
            ->withConsecutive([$position, \SEEK_SET], [$offset, $whence]);

        $this->fixture->seek($offset, $whence);
    }

    public function sampleDoubleSeek(): array
    {
        return [
            [\SEEK_CUR],
            [\SEEK_END],
        ];
    }

    public function testSeekResponse(): void
    {
        $response = (bool) rand(0, 1);

        $this->base->method('seek')->willReturn($response);

        $actual = $this->fixture->seek(rand());

        self::assertEquals($response, $actual);
    }

    public function testTellCallsBaseSeek(): void
    {
        $this->base->expects(self::once())
            ->method('seek')
            ->with(0);

        $this->fixture->tell();
    }

    public function testTellCallsBaseTell(): void
    {
        $this->base->expects(self::once())
            ->method('tell');

        $this->fixture->tell();
    }

    public function testTellCallsBaseSeekFromKnownPosition(): void
    {
        $position = rand();

        $this->base->method('tell')->willReturn($position);
        $this->fixture->write(uniqid());

        $this->base->expects(self::once())
            ->method('seek')
            ->with($position);

        $this->fixture->tell();
    }

    public function testTellResponse(): void
    {
        $position = rand();

        $this->base->method('tell')->willReturn($position);

        $actual = $this->fixture->tell();

        self::assertEquals($position, $actual);
    }

    public function testIsEofCallsBaseSeek(): void
    {
        $this->base->expects(self::once())
            ->method('seek')
            ->with(0);

        $this->fixture->isEof();
    }

    public function testIsEofCallsBaseTruncate(): void
    {
        $this->base->expects(self::once())
            ->method('isEof');

        $this->fixture->isEof();
    }

    public function testIsEofCallsBaseSeekFromKnownPosition(): void
    {
        $position = rand();

        $this->base->method('tell')->willReturn($position);
        $this->fixture->read(rand());

        $this->base->expects(self::once())
            ->method('seek')
            ->with($position);

        $this->fixture->isEof();
    }

    public function testIsEofResponse(): void
    {
        $response = (bool) rand(0, 1);

        $this->base->method('isEof')->willReturn($response);

        $actual = $this->fixture->isEof();

        self::assertEquals($response, $actual);
    }
}
