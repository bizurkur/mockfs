<?php

declare(strict_types=1);

namespace MockFileSystem\Tests;

use MockFileSystem\Components\Block;
use MockFileSystem\Components\Directory;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystem;
use MockFileSystem\Components\Partition;
use MockFileSystem\Components\RegularFile;
use MockFileSystem\Components\RegularFileInterface;
use MockFileSystem\Config\Config;
use MockFileSystem\Config\ConfigInterface;
use MockFileSystem\Content\FullContent;
use MockFileSystem\Content\NullContent;
use MockFileSystem\Content\RandomContent;
use MockFileSystem\Content\StreamContent;
use MockFileSystem\Content\ZeroContent;
use MockFileSystem\Exception\InvalidArgumentException;
use MockFileSystem\Exception\RuntimeException;
use MockFileSystem\MockFileSystem;
use MockFileSystem\StreamWrapper;
use MockFileSystem\Visitor\VisitorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
class MockFileSystemTest extends TestCase
{
    protected function setUp(): void
    {
        $this->tearDown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $wrappers = stream_get_wrappers();
        if (in_array(StreamWrapper::PROTOCOL, $wrappers, true)) {
            stream_wrapper_unregister(StreamWrapper::PROTOCOL);
        }

        MockFileSystem::destroy();
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

        self::expectWarning();
        self::expectWarningMessage(
            'stream_wrapper_register(): Protocol ' . StreamWrapper::PROTOCOL . ':// is already defined.'
        );

        MockFileSystem::create();
    }

    public function testCreateFailsToRegisterStreamWrapperThrowsException(): void
    {
        stream_wrapper_register(StreamWrapper::PROTOCOL, StreamWrapper::class);

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unable to register ' . StreamWrapper::PROTOCOL . ':// protocol.');

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
            'Options must be an array or instance of ' . ConfigInterface::class . '; string given'
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

    public function testCreateMakesStructure(): void
    {
        $structure = [
            'a' => 'a',
            'b' => [
                'c' => 'c',
                'd' => [
                    'e' => 'e',
                    '[f]' => 'f',
                ],
            ],
        ];

        MockFileSystem::create('', null, $structure);

        $actual = $this->getStructure('mfs://');

        self::assertEquals($structure, $actual);
        self::assertInstanceOf(Block::class, MockFileSystem::find('mfs:///b/d/f'));
    }

    /**
     * @dataProvider sampleSpecialBlocks
     *
     * @phpstan-param class-string<object> $expected
     */
    public function testCreateMakesStructureSpecialBlock(
        array $structure,
        string $file,
        string $expected
    ): void {
        MockFileSystem::create('', null, $structure);

        $actual = MockFileSystem::find($file);

        self::assertInstanceOf(Block::class, $actual);
        self::assertInstanceOf($expected, $actual->getContent());
    }

    public function sampleSpecialBlocks(): array
    {
        return [
            'null' => [
                'structure' => [
                    'dev' => [
                        '[null]' => null,
                    ],
                ],
                'file' => 'mfs:///dev/null',
                'expected' => NullContent::class,
            ],
            'null, with content' => [
                'structure' => [
                    'dev' => [
                        '[null]' => uniqid(),
                    ],
                ],
                'file' => 'mfs:///dev/null',
                'expected' => StreamContent::class,
            ],
            'full' => [
                'structure' => [
                    'dev' => [
                        '[full]' => null,
                    ],
                ],
                'file' => 'mfs:///dev/full',
                'expected' => FullContent::class,
            ],
            'full, with content' => [
                'structure' => [
                    'dev' => [
                        '[full]' => uniqid(),
                    ],
                ],
                'file' => 'mfs:///dev/full',
                'expected' => StreamContent::class,
            ],
            'random' => [
                'structure' => [
                    'dev' => [
                        '[random]' => null,
                    ],
                ],
                'file' => 'mfs:///dev/random',
                'expected' => RandomContent::class,
            ],
            'random, with content' => [
                'structure' => [
                    'dev' => [
                        '[random]' => uniqid(),
                    ],
                ],
                'file' => 'mfs:///dev/random',
                'expected' => StreamContent::class,
            ],
            'zero' => [
                'structure' => [
                    'dev' => [
                        '[zero]' => null,
                    ],
                ],
                'file' => 'mfs:///dev/zero',
                'expected' => ZeroContent::class,
            ],
            'zero, with content' => [
                'structure' => [
                    'dev' => [
                        '[zero]' => uniqid(),
                    ],
                ],
                'file' => 'mfs:///dev/zero',
                'expected' => StreamContent::class,
            ],
        ];
    }

    public function testCreateThrowsExceptionForInvalidStructureKey(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('File name must be a string; received integer');

        MockFileSystem::create('', null, [0 => uniqid()]);
    }

    public function testCreateThrowsExceptionForInvalidStructureData(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Data must be a string (file) or array (directory); received NULL');

        MockFileSystem::create('', null, [uniqid() => null]);
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

    public function testCreatePartitionMakesStructure(): void
    {
        $structure = [
            'a' => 'a',
            'b' => [
                'c' => 'c',
                'd' => [
                    'e' => 'e',
                    '[f]' => 'f',
                ],
            ],
        ];

        MockFileSystem::create();
        MockFileSystem::createPartition('', null, $structure)->addTo(MockFileSystem::getFileSystem());

        $actual = $this->getStructure('mfs://');

        self::assertEquals($structure, $actual);
        self::assertInstanceOf(Block::class, MockFileSystem::find('mfs:///b/d/f'));
    }

    /**
     * @dataProvider sampleSpecialBlocks
     *
     * @phpstan-param class-string<object> $expected
     */
    public function testCreatePartitionMakesStructureSpecialBlock(
        array $structure,
        string $file,
        string $expected
    ): void {
        MockFileSystem::create();
        MockFileSystem::createPartition('', null, $structure)->addTo(MockFileSystem::getFileSystem());

        $actual = MockFileSystem::find($file);

        self::assertInstanceOf(Block::class, $actual);
        self::assertInstanceOf($expected, $actual->getContent());
    }

    public function testCreatePartitionThrowsExceptionForInvalidStructureKey(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('File name must be a string; received integer');

        MockFileSystem::create();
        MockFileSystem::createPartition('', null, [0 => uniqid()]);
    }

    public function testCreatePartitionThrowsExceptionForInvalidStructureData(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Data must be a string (file) or array (directory); received NULL');

        MockFileSystem::create();
        MockFileSystem::createPartition('', null, [uniqid() => null]);
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

    public function testCreateDirectoryMakesStructure(): void
    {
        $structure = [
            'a' => 'a',
            'b' => [
                'c' => 'c',
                'd' => [
                    'e' => 'e',
                    '[f]' => 'f',
                ],
            ],
        ];

        $root = MockFileSystem::create();
        MockFileSystem::createDirectory('foo', null, $structure)->addTo($root);

        $actual = $this->getStructure('mfs:///foo');

        self::assertEquals($structure, $actual);
        self::assertInstanceOf(Block::class, MockFileSystem::find('mfs:///foo/b/d/f'));
    }

    /**
     * @dataProvider sampleSpecialBlocks
     *
     * @phpstan-param class-string<object> $expected
     */
    public function testCreateDirectoryMakesStructureSpecialBlock(
        array $structure,
        string $file,
        string $expected
    ): void {
        $root = MockFileSystem::create();
        MockFileSystem::createDirectory('foo', null, $structure)->addTo($root);

        $actual = MockFileSystem::find(str_replace('mfs://', 'mfs:///foo', $file));

        self::assertInstanceOf(Block::class, $actual);
        self::assertInstanceOf($expected, $actual->getContent());
    }

    public function testCreateDirectoryThrowsExceptionForInvalidStructureKey(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('File name must be a string; received integer');

        MockFileSystem::create();
        MockFileSystem::createDirectory(uniqid(), null, [0 => uniqid()]);
    }

    public function testCreateDirectoryThrowsExceptionForInvalidStructureData(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Data must be a string (file) or array (directory); received NULL');

        MockFileSystem::create();
        MockFileSystem::createDirectory(uniqid(), null, [uniqid() => null]);
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
        self::assertEquals('/' . $nameA . '/' . $nameB . '/' . $nameC, $actual->getPath());
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

    /**
     * @dataProvider sampleInvalidNames
     */
    public function testCreateFileInvalidName(array $config, string $name, string $expected): void
    {
        MockFileSystem::create('', null, [], $config);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage($expected);

        MockFileSystem::createFile($name);
    }

    public function sampleInvalidNames(): array
    {
        $config = [
            'fileSeparator' => '/',
            'partitionSeparator' => ':',
            'blacklist' => [
                '>',
                'tab' => "\t",
            ],
        ];

        return [
            'dot' => [
                'config' => $config,
                'name' => '.',
                'expected' => 'Name cannot be "." or ".."',
            ],
            'dotdot' => [
                'config' => $config,
                'name' => '..',
                'expected' => 'Name cannot be "." or ".."',
            ],
            'no name' => [
                'config' => $config,
                'name' => '',
                'expected' => 'Name cannot be empty.',
            ],
            'has fileSeparator' => [
                'config' => $config,
                'name' => 'test/ing',
                'expected' => 'Name cannot contain a "/" character.',
            ],
            'has partitionSeparator' => [
                'config' => $config,
                'name' => 'test:ing',
                'expected' => 'Name cannot contain a ":" character.',
            ],
            'has null character' => [
                'config' => $config,
                'name' => ".\0.",
                'expected' => 'Name cannot contain a "null" character.',
            ],
            'has custom blacklist character, no index' => [
                'config' => $config,
                'name' => 'some>ting',
                'expected' => 'Name cannot contain a ">" character.',
            ],
            'has custom blacklist character, named index' => [
                'config' => $config,
                'name' => "some\tting",
                'expected' => 'Name cannot contain a "tab" character.',
            ],
        ];
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
        self::assertEquals('/' . $nameA . '/' . $nameB . '/' . $nameC, $actual->getPath());
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

    /**
     * @dataProvider sampleInvalidNames
     */
    public function testCreateBlockInvalidName(array $config, string $name, string $expected): void
    {
        MockFileSystem::create('', null, [], $config);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage($expected);

        MockFileSystem::createBlock($name);
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
        self::assertEquals('/' . $nameA . '/' . $nameB . '/' . $nameC, $actual->getPath());
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

        self::assertEquals(StreamWrapper::PROTOCOL . '://' . $expected, $actual);
    }

    public function samplePaths(): array
    {
        $multibyte = utf8_encode('Déjà_vu');

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
                'path' => StreamWrapper::PROTOCOL . ':///foo/../bar',
                'expected' => '/bar',
            ],
            'multibyte support' => [
                'options' => [],
                'path' => StreamWrapper::PROTOCOL . ':///' . $multibyte . '/υπέρ/../νωθρού',
                'expected' => '/' . $multibyte . '/νωθρού',
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

    public function testAddStructureMakesStructure(): void
    {
        $structure = [
            'a' => 'a',
            'b' => [
                'c' => 'c',
                'd' => [
                    'e' => 'e',
                    '[f]' => 'f',
                    '[g' => 'g',
                    'h]' => 'h',
                ],
            ],
        ];

        $root = MockFileSystem::create();
        MockFileSystem::addStructure($structure, $root);

        $actual = $this->getStructure('mfs://');

        self::assertEquals($structure, $actual);
        self::assertInstanceOf(Block::class, MockFileSystem::find('mfs:///b/d/f'));
        self::assertInstanceOf(RegularFile::class, MockFileSystem::find('mfs:///b/d/[g'));
        self::assertInstanceOf(RegularFile::class, MockFileSystem::find('mfs:///b/d/h]'));
    }

    /**
     * @dataProvider sampleSpecialBlocks
     *
     * @phpstan-param class-string<object> $expected
     */
    public function testAddStructureMakesStructureSpecialBlock(
        array $structure,
        string $file,
        string $expected
    ): void {
        $root = MockFileSystem::create();
        MockFileSystem::addStructure($structure, $root);

        $actual = MockFileSystem::find($file);

        self::assertInstanceOf(Block::class, $actual);
        self::assertInstanceOf($expected, $actual->getContent());
    }

    public function testAddStructureThrowsExceptionForInvalidStructureKey(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('File name must be a string; received integer');

        $root = MockFileSystem::create();
        MockFileSystem::addStructure([0 => uniqid()], $root);
    }

    public function testAddStructureThrowsExceptionForInvalidStructureData(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Data must be a string (file) or array (directory); received NULL');

        $root = MockFileSystem::create();
        MockFileSystem::addStructure([uniqid() => null], $root);
    }

    public function testVisitCallsVisitorVisitWithFile(): void
    {
        /** @var VisitorInterface&MockObject $visitor */
        $visitor = $this->createMock(VisitorInterface::class);
        $file = $this->createMock(FileInterface::class);

        $visitor->expects(self::once())
            ->method('visit')
            ->with($file);

        MockFileSystem::visit($file, $visitor);
    }

    public function testVisitCallsVisitorVisitWithFileSystem(): void
    {
        MockFileSystem::create();

        /** @var VisitorInterface&MockObject $visitor */
        $visitor = $this->createMock(VisitorInterface::class);
        $expected = MockFileSystem::getFileSystem();

        $visitor->expects(self::once())
            ->method('visitFileSystem')
            ->with($expected);

        MockFileSystem::visit(null, $visitor);
    }

    /**
     * Gets the file structure of a given directory.
     *
     * @param string $dir
     *
     * @return array<int|string,array|string>
     */
    private function getStructure(string $dir): array
    {
        $actual = [];

        $files = array_filter(
            scandir($dir) ?: [],
            function ($file) {
                return $file !== '.' && $file !== '..';
            }
        );

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $actual[$file] = $this->getStructure($path);

                continue;
            }

            /** @var RegularFileInterface $object **/
            $object = MockFileSystem::find($path);
            if ($object instanceof Block) {
                $file = '[' . $file . ']';
            }

            $content = $object->getContent();
            if ($content instanceof StreamContent) {
                $actual[$file] = $content->read(1024);
            } else {
                $actual[$file] = get_class($content);
            }
        }

        return $actual;
    }
}
