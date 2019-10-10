<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\Block;
use MockFileSystem\Components\Directory;
use MockFileSystem\Components\FileSystem;
use MockFileSystem\Components\Partition;
use MockFileSystem\Components\RegularFile;
use MockFileSystem\Config\Config;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Content\StreamContent;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Exception\RuntimeException;
use MockFileSystem\MockFileSystem;
use MockFileSystem\StreamWrapper;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
class MockFileSystemTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        $wrappers = stream_get_wrappers();
        if (in_array(StreamWrapper::PROTOCOL, $wrappers, true)) {
            stream_wrapper_unregister(StreamWrapper::PROTOCOL);
            MockFileSystem::destroy();
        }
    }

    public function testCreateRegistersStreamWrapper(): void
    {
        MockFileSystem::create();

        $actual = stream_get_wrappers();

        self::assertContains(StreamWrapper::PROTOCOL, $actual);
    }

    public function testCreateCalledTwiceOnlyRegistersOnce(): void
    {
        MockFileSystem::create();
        MockFileSystem::create();

        $actual = stream_get_wrappers();

        self::assertContains(StreamWrapper::PROTOCOL, $actual);
    }

    public function testCreateFailsToRegisterStreamWrapperCreatesError(): void
    {
        stream_wrapper_register(StreamWrapper::PROTOCOL, StreamWrapper::class);

        self::expectException(Warning::class);
        self::expectExceptionMessage(
            'stream_wrapper_register(): Protocol '.StreamWrapper::PROTOCOL.':// is already defined.'
        );

        MockFileSystem::create();
    }

    public function testCreateFailsToRegisterStreamWrapperThrowsException(): void
    {
        stream_wrapper_register(StreamWrapper::PROTOCOL, StreamWrapper::class);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unable to register '.StreamWrapper::PROTOCOL.':// protocol.');

        @MockFileSystem::create();
    }

    public function testCreateReturnsPartition(): void
    {
        $actual = MockFileSystem::create();

        self::assertInstanceOf(Partition::class, $actual);
    }

    /**
     * @dataProvider sampleOptions
     */
    public function testCreateUsingCorrectOptions(array $options, array $expected): void
    {
        $partition = MockFileSystem::create('', null, [], $options);
        $actual = $partition->getConfig()->toArray();

        self::assertEquals($expected, $actual);
    }

    public function sampleOptions(): array
    {
        $default = [
            'umask' => 0000,
            'fileSeparator' => '/',
            'partitionSeparator' => '',
            'ignoreCase' => false,
            'includeDotFiles' => true,
            'normalizeSlashes' => false,
            'blacklist' => [],
            'user' => function_exists('posix_getuid') ? posix_getuid() : Config::ROOT_UID,
            'group' => function_exists('posix_getgid') ? posix_getgid() : Config::ROOT_GID,
        ];

        return [
            'no options' => [
                'options' => [],
                'expected' => $default,
            ],
            'umask' => [
                'options' => ['umask' => 0444],
                'expected' => array_replace($default, ['umask' => 0444]),
            ],
            'fileSeparator' => [
                'options' => ['fileSeparator' => '\\'],
                'expected' => array_replace($default, ['fileSeparator' => '\\']),
            ],
            'partitionSeparator' => [
                'options' => ['partitionSeparator' => ':'],
                'expected' => array_replace($default, ['partitionSeparator' => ':']),
            ],
            'ignoreCase' => [
                'options' => ['ignoreCase' => true],
                'expected' => array_replace($default, ['ignoreCase' => true]),
            ],
            'includeDotFiles' => [
                'options' => ['includeDotFiles' => false],
                'expected' => array_replace($default, ['includeDotFiles' => false]),
            ],
            'normalizeSlashes' => [
                'options' => ['normalizeSlashes' => true],
                'expected' => array_replace($default, ['normalizeSlashes' => true]),
            ],
            'blacklist' => [
                'options' => ['blacklist' => ['\\', '>']],
                'expected' => array_replace($default, ['blacklist' => ['\\', '>']]),
            ],
            'user' => [
                'options' => ['user' => 123],
                'expected' => array_replace($default, ['user' => 123]),
            ],
            'group' => [
                'options' => ['group' => 123],
                'expected' => array_replace($default, ['group' => 123]),
            ],
        ];
    }

    public function testCreateUsingConfig(): void
    {
        $config = $this->createConfiguredMock(
            ConfigInterface::class,
            ['getFileSeparator' => '/']
        );
        $partition = MockFileSystem::create('', null, [], $config);

        $actual = $partition->getConfig();

        self::assertSame($config, $actual);
    }

    public function testCreateThrowsExceptionForInvalidConfig(): void
    {
        $config = uniqid();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(
            'Options must be an array or instance of '.ConfigInterface::class.'; string given'
        );

        MockFileSystem::create('', null, [], $config);
    }

    public function testCreateUsesPartitionName(): void
    {
        $name = uniqid();

        $actual = MockFileSystem::create($name);

        self::assertEquals($name, $actual->getName());
    }

    public function testCreateUsesPartitionPermissions(): void
    {
        $permissions = 0750;

        $actual = MockFileSystem::create('', $permissions);

        self::assertEquals($permissions, $actual->getPermissions());
    }

    public function testCreateMakesEmptyPartition(): void
    {
        $actual = MockFileSystem::create();

        $summary = $actual->getSummary();
        self::assertEquals(0, $summary->getSize());
        self::assertEquals(0, $summary->getFileCount());
    }

    public function testDestroyUnregistersStreamWrapper(): void
    {
        MockFileSystem::create();
        MockFileSystem::destroy();

        $actual = stream_get_wrappers();

        self::assertNotContains(StreamWrapper::PROTOCOL, $actual);
    }

    public function testDestroyDeletesFileSystem(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::getFileSystem();
        self::assertInstanceOf(FileSystem::class, $actual);

        MockFileSystem::destroy();

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::getFileSystem();
    }

    public function testGetUmask(): void
    {
        $umask = 0200;

        MockFileSystem::create('', null, [], ['umask' => $umask]);

        $actual = MockFileSystem::umask();

        self::assertEquals($umask, $actual);
    }

    public function testSetUmaskReturnsOldUmask(): void
    {
        $umask = 0222;

        MockFileSystem::create('', null, [], ['umask' => $umask]);

        $actual = MockFileSystem::umask(0777);

        self::assertEquals($umask, $actual);
    }

    public function testSetUmask(): void
    {
        $umask = 0222;

        MockFileSystem::create();
        MockFileSystem::umask($umask);

        $actualB = MockFileSystem::umask();

        self::assertEquals($umask, $actualB);
    }

    public function testUmaskThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::umask();
    }

    public function testCreatePartition(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::createPartition(uniqid());

        self::assertInstanceOf(Partition::class, $actual);
    }

    /**
     * @dataProvider samplePartitionNames
     */
    public function testCreatePartitionSetsName(
        array $options,
        string $name,
        string $expectedName,
        string $expectedPath
    ): void {
        MockFileSystem::create('', null, [], $options);
        $root = MockFileSystem::getFileSystem();

        $actual = MockFileSystem::createPartition($name)->addTo($root);

        self::assertEquals($expectedName, $actual->getName(), 'wrong name');
        self::assertEquals($expectedPath, $actual->getPath(), 'wrong path');
    }

    public function samplePartitionNames(): array
    {
        return [
            'no slash' => [
                'options' => ['fileSeparator' => '\\'],
                'name' => 'D:',
                'expectedName' => 'D:',
                'expectedPath' => 'D:\\',
            ],
            'trailing slash' => [
                'options' => ['fileSeparator' => '\\'],
                'name' => 'D:\\',
                'expectedName' => 'D:',
                'expectedPath' => 'D:\\',
            ],
            'includes partition separator' => [
                'options' => ['fileSeparator' => '\\', 'partitionSeparator' => ':'],
                'name' => 'D:',
                'expectedName' => 'D',
                'expectedPath' => 'D:\\',
            ],
        ];
    }

    public function testCreatePartitionSetsPermissions(): void
    {
        $permissions = 0777;

        MockFileSystem::create();

        $actual = MockFileSystem::createPartition(uniqid(), $permissions);

        self::assertEquals($permissions, $actual->getPermissions());
    }

    public function testCreatePartitionThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::createPartition(uniqid());
    }

    public function testCreateDirectory(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::createDirectory(uniqid());

        self::assertInstanceOf(Directory::class, $actual);
    }

    public function testCreateDirectorySetsName(): void
    {
        $name = uniqid();

        MockFileSystem::create();

        $actual = MockFileSystem::createDirectory($name);

        self::assertEquals($name, $actual->getName());
    }

    public function testCreateNestedDirectory(): void
    {
        $nameA = uniqid('a');
        $nameB = uniqid('b');
        $nameC = uniqid('c');

        $root = MockFileSystem::create();

        $dirA = MockFileSystem::createDirectory($nameA);
        $dirA->addTo($root);
        $dirB = MockFileSystem::createDirectory($nameB);
        $dirB->addTo($dirA);
        $actual = MockFileSystem::createDirectory($nameC);
        $actual->addTo($dirB);

        self::assertEquals($nameC, $actual->getName());
        self::assertEquals('/'.$nameA.'/'.$nameB.'/'.$nameC, $actual->getPath());
    }

    public function testCreateDirectorySetsPermissions(): void
    {
        $permissions = 0777;

        MockFileSystem::create();

        $actual = MockFileSystem::createDirectory(uniqid(), $permissions);

        self::assertEquals($permissions, $actual->getPermissions());
    }

    public function testCreateDirectoryThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::createDirectory(uniqid());
    }

    public function testCreateFile(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::createFile(uniqid());

        self::assertInstanceOf(RegularFile::class, $actual);
    }

    public function testCreateFileSetsName(): void
    {
        $name = uniqid();

        MockFileSystem::create();

        $actual = MockFileSystem::createFile($name);

        self::assertEquals($name, $actual->getName());
    }

    public function testCreateNestedFile(): void
    {
        $nameA = uniqid('a');
        $nameB = uniqid('b');
        $nameC = uniqid('c');

        $root = MockFileSystem::create();

        $dirA = MockFileSystem::createDirectory($nameA);
        $dirA->addTo($root);
        $dirB = MockFileSystem::createDirectory($nameB);
        $dirB->addTo($dirA);
        $actual = MockFileSystem::createFile($nameC);
        $actual->addTo($dirB);

        self::assertEquals($nameC, $actual->getName());
        self::assertEquals('/'.$nameA.'/'.$nameB.'/'.$nameC, $actual->getPath());
    }

    public function testCreateFileSetsPermissions(): void
    {
        $permissions = 0777;

        MockFileSystem::create();

        $actual = MockFileSystem::createFile(uniqid(), $permissions);

        self::assertEquals($permissions, $actual->getPermissions());
    }

    public function testCreateFileSetsContent(): void
    {
        $content = new StreamContent(uniqid());

        MockFileSystem::create();

        $actual = MockFileSystem::createFile(uniqid(), null, $content);

        self::assertSame($content, $actual->getContent());
    }

    public function testCreateFileThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::createFile(uniqid());
    }

    public function testCreateBlock(): void
    {
        MockFileSystem::create();

        $actual = MockFileSystem::createBlock(uniqid());

        self::assertInstanceOf(Block::class, $actual);
    }

    public function testCreateBlockSetsName(): void
    {
        $name = uniqid();

        MockFileSystem::create();

        $actual = MockFileSystem::createBlock($name);

        self::assertEquals($name, $actual->getName());
    }

    public function testCreateNestedBlock(): void
    {
        $nameA = uniqid('a');
        $nameB = uniqid('b');
        $nameC = uniqid('c');

        $root = MockFileSystem::create();

        $dirA = MockFileSystem::createDirectory($nameA);
        $dirA->addTo($root);
        $dirB = MockFileSystem::createDirectory($nameB);
        $dirB->addTo($dirA);
        $actual = MockFileSystem::createBlock($nameC);
        $actual->addTo($dirB);

        self::assertEquals($nameC, $actual->getName());
        self::assertEquals('/'.$nameA.'/'.$nameB.'/'.$nameC, $actual->getPath());
    }

    public function testCreateBlockSetsPermissions(): void
    {
        $permissions = 0777;

        MockFileSystem::create();

        $actual = MockFileSystem::createBlock(uniqid(), $permissions);

        self::assertEquals($permissions, $actual->getPermissions());
    }

    public function testCreateBlockThrowsException(): void
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('File system has not been created.');

        MockFileSystem::createBlock(uniqid());
    }

    /**
     * @dataProvider samplePaths
     */
    public function testGetPath(array $options, string $path, string $expected): void
    {
        MockFileSystem::create('/', null, [], $options);

        $actual = MockFileSystem::getPath($path);

        self::assertEquals($expected, $actual);
    }

    /**
     * @dataProvider samplePaths
     */
    public function testGetUrl(array $options, string $path, string $expected): void
    {
        MockFileSystem::create('/', null, [], $options);

        $actual = MockFileSystem::getUrl($path);

        self::assertEquals(StreamWrapper::PROTOCOL.'://'.$expected, $actual);
    }

    public function samplePaths(): array
    {
        $multibyte = utf8_encode("Déjà_vu");

        return [
            'absolute, single' => [
                'options' => [],
                'path' => '/foo',
                'expected' => '/foo',
            ],
            'absolute, nested' => [
                'options' => [],
                'path' => '/foo/bar/../bing bong/./baz/../',
                'expected' => '/foo/bing bong',
            ],
            'relative, single' => [
                'options' => [],
                'path' => 'foo',
                'expected' => 'foo',
            ],
            'relative, nested' => [
                'options' => [],
                'path' => 'foo/bar/../bing bong/./baz/../',
                'expected' => 'foo/bing bong',
            ],
            'excessive relativeness' => [
                'options' => [],
                'path' => '/../../../../../../../.././././.././.././../bar',
                'expected' => '/bar',
            ],
            'leading protocol' => [
                'options' => [],
                'path' => StreamWrapper::PROTOCOL.':///foo/../bar',
                'expected' => '/bar',
            ],
            'multibyte support' => [
                'options' => [],
                'path' => StreamWrapper::PROTOCOL.':///'.$multibyte.'/υπέρ/../νωθρού',
                'expected' => '/'.$multibyte.'/νωθρού',
            ],
            'dir slash not normalized' => [
                'options' => [],
                'path' => '/foo\\..\\bar/baz/../bur',
                'expected' => '/foo\\..\\bar/bur',
            ],
            'dir slash normalized' => [
                'options' => ['normalizeSlashes' => true],
                'path' => '/foo\\..\\bar/baz/../bur',
                'expected' => '/bar/bur',
            ],
            'custom separator' => [
                'options' => ['fileSeparator' => '>'],
                'path' => '/foo/bar>..>baz>hot/../cakes',
                'expected' => '/foo/bar>baz>hot/../cakes',
            ],
        ];
    }
}
