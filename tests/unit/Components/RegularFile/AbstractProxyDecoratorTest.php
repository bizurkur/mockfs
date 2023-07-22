<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components\RegularFile;

use MockFileSystem\Components\ChildInterface;
use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\RegularFile\AbstractProxyDecorator;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Content\ContentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbstractProxyDecoratorTest extends TestCase
{
    /**
     * @var AbstractProxyDecorator
     */
    private AbstractProxyDecorator $fixture;

    /**
     * @var RegularFileInterface&MockObject
     */
    private RegularFileInterface $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = $this->createMock(RegularFileInterface::class);

        $this->fixture = $this->getMockForAbstractClass(
            AbstractProxyDecorator::class,
            [$this->base]
        );
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(RegularFileInterface::class, $this->fixture);
    }

    public function testSetConfigCallsBase(): void
    {
        $config = $this->createMock(ConfigInterface::class);

        $this->base->expects(self::once())
            ->method('setConfig')
            ->with($config);

        $this->fixture->setConfig($config);
    }

    public function testSetConfigResponse(): void
    {
        $config = $this->createMock(ConfigInterface::class);

        $this->base->method('setConfig')->willReturn($this->base);

        $actual = $this->fixture->setConfig($config);

        self::assertSame($this->base, $actual);
    }

    public function testGetConfigCallsBase(): void
    {
        $this->base->expects(self::once())->method('getConfig');

        $this->fixture->getConfig();
    }

    public function testGetConfigResponse(): void
    {
        $config = $this->createMock(ConfigInterface::class);

        $this->base->method('getConfig')->willReturn($config);

        $actual = $this->fixture->getConfig();

        self::assertSame($config, $actual);
    }

    public function testGetParentResponseWhenNotNull(): void
    {
        $parent = $this->createMock(ContainerInterface::class);

        $this->base->method('getParent')->willReturn($parent);

        $actual = $this->fixture->getParent();

        self::assertSame($parent, $actual);
    }

    public function testSetParentCallsBase(): void
    {
        $parent = $this->createMock(ContainerInterface::class);

        $this->base->expects(self::once())
            ->method('setParent')
            ->with($parent);

        $this->fixture->setParent($parent);
    }

    public function testSetParentCallsBaseWhenNull(): void
    {
        $this->base->expects(self::once())
            ->method('setParent')
            ->with(self::isNull());

        $this->fixture->setParent(null);
    }

    public function testSetParentResponse(): void
    {
        $child = $this->createMock(ChildInterface::class);

        $this->base->method('setParent')->willReturn($child);

        $actual = $this->fixture->setParent(null);

        self::assertSame($child, $actual);
    }

    public function testAddToCallsBase(): void
    {
        $parent = $this->createMock(ContainerInterface::class);

        $this->base->expects(self::once())
            ->method('addTo')
            ->with($parent);

        $this->fixture->addTo($parent);
    }

    public function testAddToResponse(): void
    {
        $parent = $this->createMock(ContainerInterface::class);
        $file = $this->createMock(FileInterface::class);

        $this->base->method('addTo')->willReturn($file);

        $actual = $this->fixture->addTo($parent);

        self::assertSame($file, $actual);
    }

    /**
     * @dataProvider sampleBasicGetters
     */
    public function testBasicGettersCallBase(string $method, array $params): void
    {
        $this->base->expects(self::once())
            ->method($method)
            ->with(...$params);

        $this->fixture->$method(...$params);
    }

    /**
     * @param string $method
     * @param array $params
     * @param mixed $expected
     *
     * @dataProvider sampleBasicGetters
     */
    public function testBasicGettersResponse(string $method, array $params, $expected): void
    {
        $this->base->method($method)->willReturn($expected);

        $actual = $this->fixture->$method(...$params);

        self::assertEquals($expected, $actual);
    }

    public function sampleBasicGetters(): array
    {
        return [
            ['getDefaultPermissions', [], rand()],
            ['getPermissions', [], rand()],
            ['getParent', [], null],
            ['getUser', [], rand()],
            ['getGroup', [], rand()],
            ['isReadable', [rand(), rand()], (bool) rand(0, 1)],
            ['isWritable', [rand(), rand()], (bool) rand(0, 1)],
            ['isExecutable', [rand(), rand()], (bool) rand(0, 1)],
            ['getType', [], rand()],
            ['getName', [], uniqid()],
            ['getPath', [], uniqid()],
            ['getUrl', [], uniqid()],
            ['getSize', [], rand()],
            ['stat', [], [uniqid()]],
            ['getLastAccessTime', [], rand()],
            ['getLastModifyTime', [], rand()],
            ['getLastChangeTime', [], rand()],
            ['open', [], (bool) rand(0, 1)],
            ['close', [], (bool) rand(0, 1)],
            ['read', [rand()], uniqid()],
            ['write', [uniqid()], rand()],
            ['truncate', [rand()], (bool) rand(0, 1)],
            ['seek', [rand(), rand()], (bool) rand(0, 1)],
            ['tell', [], rand()],
            ['isEof', [], (bool) rand(0, 1)],
            ['flush', [], (bool) rand(0, 1)],
            ['unlink', [], (bool) rand(0, 1)],
        ];
    }

    /**
     * @dataProvider sampleBasicSetters
     */
    public function testBasicSettersCallBase(string $method, array $params): void
    {
        $this->base->expects(self::once())
            ->method($method)
            ->with(...$params);

        $this->fixture->$method(...$params);
    }

    /**
     * @dataProvider sampleBasicSetters
     */
    public function testBasicSettersResponse(string $method, array $params): void
    {
        $this->base->method($method)->willReturn($this->base);

        $actual = $this->fixture->$method(...$params);

        self::assertSame($this->base, $actual);
    }

    public function sampleBasicSetters(): array
    {
        return [
            ['setPermissions', [rand()]],
            ['setUser', [rand()]],
            ['setGroup', [rand()]],
            ['setName', [uniqid()]],
            ['setLastAccessTime', [rand()]],
            ['setLastModifyTime', [rand()]],
            ['setLastChangeTime', [rand()]],
        ];
    }

    public function testSetContentCallsBase(): void
    {
        $content = $this->createMock(ContentInterface::class);

        $this->base->expects(self::once())
            ->method('setContent')
            ->with($content);

        $this->fixture->setContent($content);
    }

    public function testSetContentResponse(): void
    {
        $content = $this->createMock(ContentInterface::class);
        $file = $this->createMock(RegularFileInterface::class);

        $this->base->method('setContent')->willReturn($file);

        $actual = $this->fixture->setContent($content);

        self::assertSame($file, $actual);
    }

    public function testSetContentFromStringCallsBase(): void
    {
        $content = uniqid();

        $this->base->expects(self::once())
            ->method('setContentFromString')
            ->with($content);

        $this->fixture->setContentFromString($content);
    }

    public function testSetContentFromStringResponse(): void
    {
        $content = uniqid();
        $file = $this->createMock(RegularFileInterface::class);

        $this->base->method('setContentFromString')->willReturn($file);

        $actual = $this->fixture->setContentFromString($content);

        self::assertSame($file, $actual);
    }

    public function testGetContentCallsBase(): void
    {
        $this->base->expects(self::once())->method('getContent');

        $this->fixture->getContent();
    }

    public function testGetContentResponse(): void
    {
        $content = $this->createMock(ContentInterface::class);

        $this->base->method('getContent')->willReturn($content);

        $actual = $this->fixture->getContent();

        self::assertSame($content, $actual);
    }
}
