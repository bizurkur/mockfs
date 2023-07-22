<?php

declare(strict_types=1);

namespace MockFileSystem\Tests\Components;

use MockFileSystem\Components\DirectoryInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\Finder;
use MockFileSystem\Components\FinderInterface;
use MockFileSystem\Components\PartitionInterface;
use MockFileSystem\Config\ConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
class FinderTest extends TestCase
{
    private Finder $fixture;

    /**
     * @var FileSystemInterface&MockObject
     */
    private FileSystemInterface $fileSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = $this->createMock(FileSystemInterface::class);

        $this->fixture = new Finder($this->fileSystem);
    }

    public function testInstanceOf(): void
    {
        self::assertInstanceOf(FinderInterface::class, $this->fixture);
    }

    public function testCallsGetPath(): void
    {
        $path = uniqid();

        $this->setUpDependencies();

        $this->fileSystem->expects(self::once())
            ->method('getPath')
            ->with($path);

        $this->fixture->find($path);
    }

    /**
     * @dataProvider samplePartitions
     */
    public function testFileSystemCallsHasChild(string $path, string $separator, string $expected): void
    {
        if (
            empty($separator)
            && version_compare(PHP_VERSION, '7.9.9', '>=')
        ) {
            self::markTestSkipped('This test only applies to PHP < 8.0');
        }
        $config = $this->createConfig($separator);
        $this->setUpDependencies($config, $path);

        $this->fileSystem->expects(self::once())
            ->method('hasChild')
            ->with($expected);

        @$this->fixture->find(uniqid());
    }

    /**
     * @dataProvider samplePartitions
     */
    public function testFileSystemCallsGetChild(string $path, string $separator, string $expected): void
    {
        if (
            empty($separator)
            && version_compare(PHP_VERSION, '7.9.9', '>=')
        ) {
            self::markTestSkipped('This test only applies to PHP < 8.0');
        }
        $config = $this->createConfig($separator);
        $this->setUpDependencies($config, $path);

        $this->fileSystem->method('hasChild')->willReturn(true);

        $this->fileSystem->expects(self::once())
            ->method('getChild')
            ->with($expected);

        @$this->fixture->find(uniqid());
    }

    public function samplePartitions(): array
    {
        return [
            'normal slash' => [
                'path' => '/foo/bar',
                'separator' => '/',
                'expected' => '',
            ],
            'windows slash' => [
                'path' => 'C:\\foo\\bar',
                'separator' => '\\',
                'expected' => 'C:',
            ],
            'invalid separator' => [
                'path' => '/foo/bar',
                'separator' => '',
                'expected' => '/foo/bar',
            ],
        ];
    }

    public function testPartitionNotFound(): void
    {
        $this->setUpDependencies();

        $this->fileSystem->method('hasChild')->willReturn(false);

        $actual = $this->fixture->find(uniqid());

        self::assertNull($actual);
    }

    public function testPartitionFound(): void
    {
        $file = $this->createMock(PartitionInterface::class);
        $this->setUpDependencies(null, 'c:');

        $this->fileSystem->method('hasChild')->willReturn(true);
        $this->fileSystem->method('getChild')->willReturn($file);

        $actual = $this->fixture->find(uniqid());

        self::assertSame($file, $actual);
    }

    public function testCorrectFileFoundLevel1(): void
    {
        $file = $this->createFile();
        $dirA = $this->createDir($file);
        $dirB = $this->createDir($dirA);
        $dirC = $this->createDir($dirB);
        $this->setUpDependencies();

        $this->fileSystem->method('hasChild')->willReturn(true);
        $this->fileSystem->method('getChild')->willReturn($dirC);

        $actual = $this->fixture->find(uniqid());

        self::assertSame($dirC, $actual);
    }

    public function testCorrectFileFoundLevel2(): void
    {
        $file = $this->createFile();
        $dirA = $this->createDir($file);
        $dirB = $this->createDir($dirA);
        $dirC = $this->createDir($dirB);
        $this->setUpDependencies(null, uniqid('/'));

        $this->fileSystem->method('hasChild')->willReturn(true);
        $this->fileSystem->method('getChild')->willReturn($dirC);

        $actual = $this->fixture->find(uniqid());

        self::assertSame($dirB, $actual);
    }

    public function testCorrectFileFoundLevel3(): void
    {
        $file = $this->createFile();
        $dirA = $this->createDir($file);
        $dirB = $this->createDir($dirA);
        $dirC = $this->createDir($dirB);
        $this->setUpDependencies(null, uniqid('/') . uniqid('/'));

        $this->fileSystem->method('hasChild')->willReturn(true);
        $this->fileSystem->method('getChild')->willReturn($dirC);

        $actual = $this->fixture->find(uniqid());

        self::assertSame($dirA, $actual);
    }

    public function testCorrectFileFoundLevel4(): void
    {
        $file = $this->createFile();
        $dirA = $this->createDir($file);
        $dirB = $this->createDir($dirA);
        $dirC = $this->createDir($dirB);
        $this->setUpDependencies(null, uniqid('/') . uniqid('/') . uniqid('/'));

        $this->fileSystem->method('hasChild')->willReturn(true);
        $this->fileSystem->method('getChild')->willReturn($dirC);

        $actual = $this->fixture->find(uniqid());

        self::assertSame($file, $actual);
    }

    public function testFileNotContainer(): void
    {
        $file = $this->createFile();
        $dir = $this->createDir($file);
        $this->setUpDependencies(null, uniqid('/') . uniqid('/') . uniqid('/'));

        $this->fileSystem->method('hasChild')->willReturn(true);
        $this->fileSystem->method('getChild')->willReturn($dir);

        $actual = $this->fixture->find(uniqid());

        self::assertNull($actual);
    }

    private function createConfig(string $separator = '/'): ConfigInterface
    {
        return $this->createConfiguredMock(
            ConfigInterface::class,
            ['getFileSeparator' => $separator]
        );
    }

    private function createDir(?FileInterface $file = null): DirectoryInterface
    {
        return $this->createConfiguredMock(
            DirectoryInterface::class,
            [
                'hasChild' => $file !== null,
                'getChild' => $file,
            ]
        );
    }

    private function createFile(): FileInterface
    {
        return $this->createMock(FileInterface::class);
    }

    private function setUpDependencies(?ConfigInterface $config = null, string $path = ''): void
    {
        $config = $config ?? $this->createConfig();
        $this->fileSystem->method('getConfig')->willReturn($config);

        $this->fileSystem->method('getPath')->willReturn($path);
    }
}
